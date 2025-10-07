<?php

namespace App\Services\WoltDrive;

use App\Models\Back\Orders\Order; // prilagodi ako ti je namespace drugačiji
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

        $this->baseUrl    = rtrim($baseUrl ?? Arr::get($cfg, 'url', ''), '/');
        $this->apiKey     = $apiKey ?? Arr::get($cfg, 'api_key');
        $this->merchantId = $merchantId ?? Arr::get($cfg, 'merchant_id');
        $this->venueId    = $venueId ?? Arr::get($cfg, 'venue_id');
    }

    public function sendOrderToWolt(
        Order $order,
        ?string $merchantId = null,
        ?string $venueId = null,
        ?string $apiKey = null
    ): array {
        $merchantId = $merchantId ?: $this->merchantId;
        $venueId    = $venueId    ?: $this->venueId;
        $apiKey     = $apiKey     ?: $this->apiKey;

        if (!$merchantId || !$venueId || !$apiKey) {
            throw new \RuntimeException('Wolt konfiguracija nije potpuna (merchantId/venueId/apiKey).');
        }

        $endpoint = $this->baseUrl.'/v1/deliveries';
        $payload  = $this->buildPayload($order, $merchantId, $venueId);

        try {
            $response = Http::withHeaders($this->buildHeaders($apiKey, $merchantId, $venueId))
                ->timeout(15)
                ->acceptJson()
                ->asJson()
                ->post($endpoint, $payload);

            if (!$response->successful()) {
                Log::warning('WoltDrive API error', [
                    'order_id' => $order->id,
                    'status'   => $response->status(),
                    'body'     => $response->json() ?? $response->body(),
                ]);
                $response->throw();
            }

            $data        = $response->json();
            $deliveryId  = data_get($data, 'id', data_get($data, 'delivery_id'));
            $trackingUrl = data_get($data, 'tracking_url');
            $labelUrl    = data_get($data, 'label_url');
            $status      = data_get($data, 'status');

            // prilagodi svojim kolonama po potrebi
            $order->update([
                'printed'  => true,
                'carrier'  => 'wolt_drive',
                'tracking' => $deliveryId,
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
            throw new \RuntimeException($this->formatHttpError($e), previous: $e);
        } catch (\Throwable $e) {
            Log::error('WoltDrive fatal error', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
            throw new \RuntimeException('Neuspjelo slanje na Wolt Drive: '.$e->getMessage(), 0, $e);
        }
    }

    protected function buildHeaders(string $apiKey, string $merchantId, string $venueId): array
    {
        return [
            'Authorization' => 'Bearer '.$apiKey,
            'X-Merchant-Id' => $merchantId,
            'X-Venue-Id'    => $venueId,
            'Content-Type'  => 'application/json',
        ];
    }

    protected function buildPayload(Order $order, string $merchantId, string $venueId): array
    {
        $orderNumber = 'ORD-'.$order->id.'-'.Str::upper(Str::random(4));

        $recipient = [
            'name'    => trim(($order->shipping_fname ?? '').' '.($order->shipping_lname ?? '')),
            'phone'   => $order->shipping_phone ?? $order->phone ?? '',
            'email'   => $order->email ?? '',
            'address' => [
                'street'      => $order->shipping_address ?? '',
                'city'        => $order->shipping_city ?? '',
                'postal_code' => $order->shipping_zip ?? '',
                'country'     => $order->shipping_country ?? 'HR',
            ],
            'notes'   => $order->note ?? '',
        ];

        $items = $order->products->map(function ($p) {
            return [
                'name'        => $p->title ?? $p->name ?? 'Artikl',
                'quantity'    => (int)($p->pivot->qty ?? $p->quantity ?? 1),
                'price'       => round((float)($p->pivot->price ?? $p->price ?? 0), 2),
                'sku'         => $p->sku ?? null,
                'description' => \Illuminate\Support\Str::limit($p->description ?? '', 120),
            ];
        })->values()->all();

        $amountCents = (int) round(((float) $order->total) * 100);

        $webhookBase = rtrim(config('app.url') ?: env('APP_PUBLIC_URL', ''), '/');
        $webhooks    = $webhookBase ? ['webhook_url' => $webhookBase.'/api/webhooks/wolt'] : [];

        return array_filter([
                'merchant_id' => $merchantId,
                'venue_id'    => $venueId,
                'external_id' => $orderNumber,
                'amount'      => $amountCents,
                'currency'    => 'EUR',
                'recipient'   => $recipient,
                'items'       => $items,
                'comment'     => 'Online narudžba #'.$order->id,
            ] + $webhooks);
    }

    protected function formatHttpError(RequestException $e): string
    {
        $resp   = $e->response;
        $status = $resp?->status();
        $body   = $resp?->json() ?? $resp?->body();
        return 'Wolt API greška (HTTP '.$status.'): '.(is_string($body) ? $body : json_encode($body));
    }
}
