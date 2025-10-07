<?php

namespace App\Services\WoltDrive;

use App\Models\Order;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WoltDriveService
{
    public function __construct(
        private readonly string $baseUrl = '',
        private readonly ?string $apiKey = null,
        private readonly ?string $merchantId = null,
        private readonly ?string $venueId = null,
    ) {
        $cfg = config('services.wolt');
        $this->baseUrl   = $this->baseUrl   ?: rtrim(Arr::get($cfg, 'url', ''), '/');
        $this->apiKey    = $this->apiKey    ?: Arr::get($cfg, 'api_key');
        $this->merchantId= $this->merchantId?: Arr::get($cfg, 'merchant_id');
        $this->venueId   = $this->venueId   ?: Arr::get($cfg, 'venue_id');
    }

    /**
     * Pošalji narudžbu na Wolt (create delivery).
     *
     * @throws \RuntimeException
     */
    public function sendOrderToWolt(
        Order $order,
        ?string $merchantId = null,
        ?string $venueId = null,
        ?string $apiKey = null
    ): array {
        $merchantId = $merchantId ?: $this->merchantId;
        $venueId    = $venueId    ?: $this->venueId;
        $apiKey     = $apiKey     ?: $this->apiKey;

        if (! $merchantId || ! $venueId || ! $apiKey) {
            throw new \RuntimeException('Wolt konfiguracija nije potpuna (merchantId/venueId/apiKey).');
        }

        $endpoint = $this->baseUrl.'/v1/deliveries'; // ostavi fleksibilno; ako koristite drugi path, zamijeni ovdje

        $payload = $this->buildPayload($order, $merchantId, $venueId);

        try {
            $response = Http::withHeaders($this->buildHeaders($apiKey, $merchantId, $venueId))
                ->timeout(15)
                ->acceptJson()
                ->asJson()
                ->post($endpoint, $payload);

            // Ako API vraća ne-2xx -> iznimka (prvo logiraj tijelo radi debug-a)
            if (! $response->successful()) {
                Log::warning('WoltDrive API error', [
                    'order_id' => $order->id,
                    'status'   => $response->status(),
                    'body'     => $response->json() ?? $response->body(),
                ]);
                $response->throw(); // baca RequestException
            }

            $data = $response->json();

            // ===> Ovdje validiraj ključne stvari iz responsa <===
            // Primjeri tipičnih polja (prilagodi stvarnom odgovoru Wolt API-ja):
            $deliveryId   = Arr::get($data, 'id') ?? Arr::get($data, 'delivery_id');
            $trackingUrl  = Arr::get($data, 'tracking_url');
            $labelUrl     = Arr::get($data, 'label_url'); // ako postoji
            $status       = Arr::get($data, 'status');

            // (Opcionalno) update narudžbe:
            $order->update([
                'printed' => true,           // ako ti "printed" znači „etiketa/generirano”
                'carrier' => 'wolt_drive',   // ako imaš kolonu
                'tracking' => $deliveryId,   // ili tracking code/url ako takav postoji
            ]);

            return [
                'ok'          => true,
                'delivery_id' => $deliveryId,
                'status'      => $status,
                'tracking'    => $trackingUrl,
                'label_url'   => $labelUrl,
                'raw'         => $data,
            ];
        } catch (RequestException $e) {
            // server vratio 4xx/5xx
            throw new \RuntimeException($this->formatHttpError($e), previous: $e);
        } catch (\Throwable $e) {
            Log::error('WoltDrive fatal error', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
            throw new \RuntimeException('Neuspjelo slanje na Wolt Drive: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Headeri — zadrži generički oblik, lako se prilagodi pravom API-u:
     */
    protected function buildHeaders(string $apiKey, string $merchantId, string $venueId): array
    {
        return [
            'Authorization'  => 'Bearer '.$apiKey,   // ili 'Merchant-Secret' ako API traži drugačije
            'X-Merchant-Id'  => $merchantId,        // ili header koji Wolt očekuje
            'X-Venue-Id'     => $venueId,           // ili header koji Wolt očekuje
            'Content-Type'   => 'application/json',
        ];
    }

    /**
     * Izgradi payload za Wolt — PRILAGODI polja točnom API ugovoru.
     */
    protected function buildPayload(Order $order, string $merchantId, string $venueId): array
    {
        $orderNumber = 'ORD-'.$order->id.'-'.Str::upper(Str::random(4));

        // Adresa kupca
        $recipient = [
            'name'        => trim(($order->shipping_fname ?? '').' '.($order->shipping_lname ?? '')),
            'phone'       => $order->shipping_phone ?? $order->phone ?? '',
            'email'       => $order->email ?? '',
            'address'     => [
                'street'      => $order->shipping_address ?? '',
                'city'        => $order->shipping_city ?? '',
                'postal_code' => $order->shipping_zip ?? '',
                'country'     => $order->shipping_country ?? 'HR',
            ],
            // Ako API traži precise lat/lng, dodaj ovdje (geokodiranje ili iz baze):
            // 'coordinates' => ['lat' => ..., 'lng' => ...],
            'notes'       => $order->note ?? '',
        ];

        // Artikli
        $items = $order->products->map(function ($p) {
            return [
                'name'        => $p->title ?? $p->name ?? 'Artikl',
                'quantity'    => (int)($p->pivot->qty ?? $p->quantity ?? 1),
                'price'       => round((float)($p->pivot->price ?? $p->price ?? 0), 2),
                'sku'         => $p->sku ?? null,
                'description' => Str::limit($p->description ?? '', 120),
            ];
        })->values()->all();

        // Vrijednosti i reference
        $amountCents = (int) round(((float) $order->total) * 100);

        // Hookovi / callbackovi (ako API podržava)
        $webhookBase = rtrim(config('app.url') ?: env('APP_PUBLIC_URL', ''), '/');
        $webhooks = $webhookBase ? [
            'webhook_url' => $webhookBase.'/api/webhooks/wolt', // napravi rutu po potrebi
        ] : [];

        // Vrijeme (ako API podržava zakazivanje)
        $pickupAt   = null; // npr. now()->addMinutes(15)->toIso8601String()
        $deliverBy  = null; // npr. now()->addMinutes(60)->toIso8601String()

        // Konačni payload — zadrži generički oblik, lako ga mapiraj kad fiksiraš točan API
        return array_filter([
            'merchant_id'   => $merchantId,
            'venue_id'      => $venueId,
            'external_id'   => $orderNumber,           // tvoja interna referenca
            'amount'        => $amountCents,           // u centima
            'currency'      => 'EUR',
            'recipient'     => $recipient,
            'items'         => $items,
            'comment'       => 'Online narudžba #'.$order->id,
            'pickup'        => array_filter([
                // Ako šalješ iz jedne lokacije (venue) obično nije potrebno ništa posebno osim venue_id
                'notes' => 'Pickup iz venue-a: '.$venueId,
                'pickup_at' => $pickupAt,
            ]),
            'delivery'      => array_filter([
                'deliver_by' => $deliverBy,
            ]),
        ]);
    }

    protected function formatHttpError(RequestException $e): string
    {
        $resp = $e->response;
        $status = $resp?->status();
        $body   = $resp?->json() ?? $resp?->body();
        return 'Wolt API greška (HTTP '.$status.'): '.(is_string($body) ? $body : json_encode($body));
    }
}
