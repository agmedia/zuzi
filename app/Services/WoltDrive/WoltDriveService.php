<?php

namespace App\Services\WoltDrive;

use App\Models\Back\Orders\Order;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WoltDriveService
{
    private string $baseUrl;
    private ?string $apiKey;
    private ?string $merchantId;
    private ?string $venueId;

    public function __construct(
        ?string $baseUrl = null,
        ?string $apiKey = null,
        ?string $merchantId = null,
        ?string $venueId = null,
    ) {
        $cfg = config('services.wolt');

        $this->baseUrl    = rtrim($baseUrl ?? Arr::get($cfg, 'url', ''), '/');            // npr. https://daas-public-api.wolt.com
        $this->apiKey     = $apiKey ?? Arr::get($cfg, 'api_key');                          // WOLT_API_KEY (merchant key)
        $this->merchantId = $merchantId ?? Arr::get($cfg, 'merchant_id');                  // ne treba za venueful flow, ali ostavljeno
        $this->venueId    = $venueId ?? Arr::get($cfg, 'venue_id');                        // WOLT_VENUE_ID
    }

    /**
     * Glavni tijek: 1) shipment-promise -> 2) delivery
     */
    public function sendOrderToWolt(
        Order $order,
        ?string $merchantId = null, // ne koristi se u venueful
        ?string $venueId = null,
        ?string $apiKey = null
    ): array {
        $venueId = $venueId ?: $this->venueId;
        $apiKey  = $apiKey  ?: $this->apiKey;

        if (!$venueId || !$apiKey) {
            throw new \RuntimeException('Wolt konfiguracija nije potpuna (venue_id/api_key).');
        }

        // 1) priprema za promise
        $dropoff      = $this->buildDropoff($order);
        $parcels      = $this->buildParcels($order);
        $cashPromise  = $this->buildCashForPromise($order); // int (cents) ili null
        $cashDelivery = $this->buildCashOption($order);     // objekt ili null

        $promise = $this->createShipmentPromise($apiKey, $venueId, $dropoff, $parcels, 30, $cashPromise);
        $shipmentPromiseId = Arr::get($promise, 'id');
        $price             = Arr::get($promise, 'price'); // ['amount'=>..,'currency'=>'EUR']
        $promiseCoords     = Arr::get($promise, 'dropoff.location.coordinates'); // ['lat'=>..,'lon'=>..]

        if (!$shipmentPromiseId || !$price) {
            Log::warning('WoltDrive: neispravan shipment promise', ['order_id' => $order->id, 'promise' => $promise]);
            throw new \RuntimeException('Wolt shipment promise je vratio nepotpune podatke.');
        }

        if (!$promiseCoords || !isset($promiseCoords['lat'], $promiseCoords['lon'])) {
            Log::warning('WoltDrive: shipment promise bez koord.', ['order_id' => $order->id, 'promise' => $promise]);
            throw new \RuntimeException('Wolt shipment promise nema valjane koordinate dropoff lokacije.');
        }

        // 2) delivery — dropoff.location.coordinates iz promise-a je OBAVEZAN
        $recipient = $this->buildRecipient($order);

        $delivery = $this->createDelivery(
            $apiKey,
            $venueId,
            $shipmentPromiseId,
            $price,
            $recipient,
            $parcels,
            [
                'min_prep' => 30,
                'comment'  => 'Online narudžba #'.$order->id,
                'coords'   => $promiseCoords,
            ],
            'ORD-'.$order->id,
            (string) $order->id,
            $cashDelivery
        );

        $deliveryId   = Arr::get($delivery, 'id');
        $status       = Arr::get($delivery, 'status');
        $trackingId   = Arr::get($delivery, 'tracking.id');
        $trackingUrl  = Arr::get($delivery, 'tracking.url');

        $order->update(array_filter([
            'carrier'           => 'wolt_drive',
            'printed'           => true,              // makni ako želiš printati kasnije
            'tracking'          => $trackingId ?? $deliveryId,
            // ispod koristi ako imaš te kolone; ako nemaš — ukloni
            'wolt_delivery_id'  => $deliveryId ?? null,
            'wolt_status'       => $status ?? null,
            'wolt_tracking_url' => $trackingUrl ?? null,
        ], fn($v) => !is_null($v)));

        return [
            'ok'        => true,
            'id'        => $deliveryId,
            'status'    => $status,
            'tracking'  => $trackingUrl ?: $trackingId,
            'raw'       => $delivery,
            'promise'   => $promise,
        ];
    }

    /**
     * Shipment Promise (venueful)
     * - cash: integer u centima ili null
     */
    protected function createShipmentPromise(
        string $apiKey,
        string $venueId,
        array $dropoff,
        array $parcels,
        int $minPrepMinutes = 30,
        ?int $cashCents = null
    ): array {
        $endpoint = "{$this->baseUrl}/v1/venues/{$venueId}/shipment-promises";

        // Ako imaš koordinate — pošalji ih; inače street/city/zip.
        $payload = array_filter([
            'street'    => Arr::get($dropoff, 'street'),
            'city'      => Arr::get($dropoff, 'city'),
            'post_code' => Arr::get($dropoff, 'post_code'),
            'lat'       => Arr::get($dropoff, 'lat'),
            'lon'       => Arr::get($dropoff, 'lon'),
            'min_preparation_time_minutes' => max(0, min(60, $minPrepMinutes)),
            'parcels'   => $parcels ?: null,
            'cash'      => $cashCents, // integer u centima
            // 'language' => 'hr',
        ], fn($v) => !is_null($v)); // važno: ne odbaci 0

        try {
            $resp = Http::withHeaders($this->buildHeaders($apiKey))
                ->timeout(20)
                ->acceptJson()
                ->asJson()
                ->post($endpoint, $payload);

            if (!$resp->successful()) {
                Log::warning('WoltDrive shipment-promise error', ['status' => $resp->status(), 'body' => $resp->json() ?? $resp->body()]);
                $resp->throw();
            }

            return $resp->json();
        } catch (RequestException $e) {
            throw new \RuntimeException($this->formatHttpError($e), previous: $e);
        }
    }

    /**
     * Delivery (venueful)
     * - dropoff.location.coordinates je obavezan i mora se poklapati s promise-om.
     * - cash: objekt s amount_to_collect ili null
     */
    protected function createDelivery(
        string $apiKey,
        string $venueId,
        string $shipmentPromiseId,
        array $price,
        array $recipient,
        array $parcels,
        array $dropoffOpts = [],
        ?string $orderRef = null,
        ?string $orderNumber = null,
        ?array $cash = null
    ): array {
        $endpoint = "{$this->baseUrl}/v1/venues/{$venueId}/deliveries";

        $coords = Arr::get($dropoffOpts, 'coords'); // ['lat'=>.., 'lon'=>..]

        $payload = [
            'pickup'  => [
                'options' => [
                    'min_preparation_time_minutes' => Arr::get($dropoffOpts, 'min_prep', 30),
                ],
            ],
            'dropoff' => array_filter([
                'location' => $coords ? ['coordinates' => ['lat' => $coords['lat'], 'lon' => $coords['lon']]] : null,
                'comment'  => Arr::get($dropoffOpts, 'comment'),
                'options'  => array_filter([
                    'is_no_contact'  => Arr::get($dropoffOpts, 'is_no_contact', false),
                    'scheduled_time' => Arr::get($dropoffOpts, 'scheduled_time'), // ISO8601
                ], fn($v) => !is_null($v)),
            ], fn($v) => !is_null($v)),
            'price'      => $price,
            'recipient'  => $recipient,
            'parcels'    => $parcels,
            'shipment_promise_id'         => $shipmentPromiseId,
            'customer_support'            => $this->buildCustomerSupport(),
            'merchant_order_reference_id' => $orderRef,
            'order_number'                => $orderNumber,
            'cash'                        => $cash, // objekt s amount_to_collect ili null
        ];

        try {
            $resp = Http::withHeaders($this->buildHeaders($apiKey))
                ->timeout(20)
                ->acceptJson()
                ->asJson()
                ->post($endpoint, $payload);

            if (!$resp->successful()) {
                Log::warning('WoltDrive delivery error', ['status' => $resp->status(), 'body' => $resp->json() ?? $resp->body()]);
                $resp->throw();
            }

            return $resp->json();
        } catch (RequestException $e) {
            throw new \RuntimeException($this->formatHttpError($e), previous: $e);
        }
    }

    /**
     * Headeri za DaaS (nema X-Merchant-Id / X-Venue-Id).
     */
    protected function buildHeaders(string $apiKey): array
    {
        return [
            'Authorization' => 'Bearer '.$apiKey,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];
    }

    /**
     * Dropoff adresa (iz narudžbe). Ako imaš koordinate, pošalji i njih.
     */
    protected function buildDropoff(Order $order): array
    {
        $drop = [
            'street'    => $order->shipping_address ?? '',
            'city'      => $order->shipping_city ?? '',
            'post_code' => $order->shipping_zip ?? '',
        ];

        if (!empty($order->shipping_lat) && !empty($order->shipping_lon)) {
            $drop['lat'] = (float) $order->shipping_lat;
            $drop['lon'] = (float) $order->shipping_lon;
        }

        return $drop;
    }

    /**
     * Primatelj – minimalno ime, telefon (+385...), email.
     */
    protected function buildRecipient(Order $order): array
    {
        $phone = trim($order->shipping_phone ?? $order->phone ?? '');
        $phone = preg_replace('/\s+/', '', $phone ?? '');
        if ($phone && str_starts_with($phone, '0')) {
            $phone = '+385'.ltrim($phone, '0');
        }

        return [
            'name'         => trim(($order->shipping_fname ?? '').' '.($order->shipping_lname ?? '')),
            'phone_number' => $phone,
            'email'        => $order->email ?? '',
        ];
    }

    /**
     * Parcels – 1 paket, težina (ako postoji) i ukupna vrijednost.
     */
    protected function buildParcels(Order $order): array
    {
        $amountCents = (int) round(((float) $order->total) * 100);

        return [[
            'count'      => 1,
            'dimensions' => [
                'weight_gram' => $this->guessTotalWeightGrams($order),
            ],
            'price'      => [
                'amount'   => $amountCents,
                'currency' => 'EUR',
            ],
            'description' => 'Narudžba #'.$order->id,
            'identifier'  => (string) $order->id,
        ]];
    }

    /**
     * COD – za shipment-promise: integer u centima ili null.
     */
    protected function buildCashForPromise(Order $order): ?int
    {
        $code = strtolower((string) ($order->payment_code ?? ''));
        if ($code === 'cod') {
            return (int) round(((float) $order->total) * 100);
        }
        return null;
    }

    /**
     * COD – za delivery: objekt s amount_to_collect ili null.
     */
    protected function buildCashOption(Order $order): ?array
    {
        $code = strtolower((string) ($order->payment_code ?? ''));
        if ($code === 'cod') {
            return [
                'amount_to_collect' => [
                    'amount'   => (int) round(((float) $order->total) * 100),
                    'currency' => 'EUR',
                ],
            ];
        }
        return null;
    }

    /**
     * Procjena ukupne težine u gramima (zbroj artikala); fallback 500g.
     */
    protected function guessTotalWeightGrams(Order $order): ?int
    {
        try {
            $grams = 0;
            foreach ($order->products ?? [] as $p) {
                $qty = (int) ($p->pivot->qty ?? $p->quantity ?? 1);
                $wgr = null;

                if (!empty($p->weight_grams)) {
                    $wgr = (int) $p->weight_grams;
                } elseif (!empty($p->weight)) {
                    // pretpostavka: kg
                    $wgr = (int) round(((float) $p->weight) * 1000);
                }

                $grams += max(0, (int) $wgr) * max(1, $qty);
            }
            if ($grams > 0) return $grams;
        } catch (\Throwable $e) {}

        return 500; // fallback
    }

    protected function buildCustomerSupport(): array
    {
        $url   = rtrim(config('app.url') ?: env('APP_PUBLIC_URL', ''), '/');
        $email = config('mail.from.address', 'support@'.$this->domainFromAppUrl());
        $phone = config('company.support_phone', '');

        return array_filter([
            'url'          => $url ?: null,
            'email'        => $email ?: null,
            'phone_number' => $phone ?: null,
        ], fn($v) => !is_null($v));
    }

    protected function domainFromAppUrl(): string
    {
        $url = rtrim(config('app.url') ?: env('APP_PUBLIC_URL', ''), '/');
        $host = parse_url($url, PHP_URL_HOST) ?: 'example.com';
        return ltrim($host, 'www.');
    }

    protected function formatHttpError(RequestException $e): string
    {
        $resp   = $e->response;
        $status = $resp?->status();
        $body   = $resp?->json() ?? $resp?->body();
        return 'Wolt API greška (HTTP '.$status.'): '.(is_string($body) ? $body : json_encode($body));
    }
}
