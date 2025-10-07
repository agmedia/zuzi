<?php

namespace App\Services\WoltDrive;

use App\Models\Back\Orders\Order;
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
        $this->merchantId = $merchantId ?? Arr::get($cfg, 'merchant_id'); // nije nužno
        $this->venueId    = $venueId ?? Arr::get($cfg, 'venue_id');
    }

    /**
     * Glavni poziv: 1) shipment promise, 2) delivery.
     */
    public function sendOrderToWolt(
        Order $order,
        ?string $merchantId = null, // ne koristi se u venueful, ali ostavljeno radi API kompatibilnosti
        ?string $venueId = null,
        ?string $apiKey = null
    ): array {
        $venueId = $venueId ?: $this->venueId;
        $apiKey  = $apiKey  ?: $this->apiKey;

        if (!$venueId || !$apiKey) {
            throw new \RuntimeException('Wolt konfiguracija nije potpuna (venue_id/api_key).');
        }

        // (Opcionalno) idempotentna zaštita — ako već imaš kolonu wolt_delivery_id
        if (isset($order->wolt_delivery_id) && $order->wolt_delivery_id) {
            return [
                'ok'          => true,
                'id'          => $order->wolt_delivery_id,
                'status'      => $order->wolt_status ?? null,
                'tracking'    => $order->wolt_tracking_url ?? null,
                'already'     => true,
            ];
        }

        // 1) Shipment Promise: pripremi dropoff podatke iz narudžbe
        $dropoff = $this->buildDropoff($order);

        // (po želji) dimenzije/masa/price po paketu — minimalni primjer
        $parcels = $this->buildParcels($order);

        $promise = $this->createShipmentPromise($apiKey, $venueId, $dropoff, $parcels, 30 /* min prep time */);
        $shipmentPromiseId = Arr::get($promise, 'id');
        $price             = Arr::get($promise, 'price'); // ['amount'=>..., 'currency'=>...]

        if (!$shipmentPromiseId || !$price) {
            Log::warning('WoltDrive: shipment promise bez potrebnih polja', ['order_id' => $order->id, 'promise' => $promise]);
            throw new \RuntimeException('Wolt shipment promise je vratio nepotpune podatke.');
        }

        // 2) Delivery
        $recipient       = $this->buildRecipient($order);
        $customerSupport = $this->buildCustomerSupport();

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
            ],
            'ORD-'.$order->id,
            (string) $order->id,
        );

        // Izvuci ključne podatke
        $deliveryId   = Arr::get($delivery, 'id');
        $status       = Arr::get($delivery, 'status');
        $trackingId   = Arr::get($delivery, 'tracking.id');
        $trackingUrl  = Arr::get($delivery, 'tracking.url');

        // Spremi u narudžbu (prilagodi svojim kolonama)
        $update = [
            'carrier'           => 'wolt_drive',
            'printed'           => true, // makni ako želiš "printed" tek kad preuzmeš etiketu
            'tracking'          => $trackingId ?? $deliveryId,
            'wolt_delivery_id'  => $deliveryId ?? null,     // ako imaš kolonu
            'wolt_status'       => $status ?? null,          // ako imaš kolonu
            'wolt_tracking_url' => $trackingUrl ?? null,     // ako imaš kolonu
        ];
        // Filtriraj null ključeve da ne ruši save ako kolona ne postoji
        $order->update(array_filter($update, fn($v) => !is_null($v)));

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
     * 1) Shipment Promise (venueful)
     */
    protected function createShipmentPromise(string $apiKey, string $venueId, array $dropoff, array $parcels, int $minPrepMinutes = 30): array
    {
        $endpoint = "{$this->baseUrl}/v1/venues/{$venueId}/shipment-promises";

        $payload = array_filter([
            // Lokacija dostave — preporučeno street/city/post_code i/ili lat/lon
            'street'    => Arr::get($dropoff, 'street'),
            'city'      => Arr::get($dropoff, 'city'),
            'post_code' => Arr::get($dropoff, 'post_code'),
            'lat'       => Arr::get($dropoff, 'lat'),
            'lon'       => Arr::get($dropoff, 'lon'),
            'min_preparation_time_minutes' => max(0, min(60, $minPrepMinutes)),
            'parcels'   => $parcels ?: null,
            // 'language' => 'hr', // po potrebi
            // 'cash'     => [...], // ako imate ugovorenu opciju pouzeća
        ]);

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
     * 2) Delivery (venueful)
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
        ?string $orderNumber = null
    ): array {
        $endpoint = "{$this->baseUrl}/v1/venues/{$venueId}/deliveries";

        $payload = [
            'pickup'  => [
                'options' => [
                    'min_preparation_time_minutes' => Arr::get($dropoffOpts, 'min_prep', 30),
                ],
            ],
            'dropoff' => array_filter([
                'comment' => Arr::get($dropoffOpts, 'comment'),
                'options' => array_filter([
                    'is_no_contact'  => Arr::get($dropoffOpts, 'is_no_contact', false),
                    'scheduled_time' => Arr::get($dropoffOpts, 'scheduled_time'), // ISO8601
                ]),
            ]),
            'price'      => $price,       // npr. ['amount'=> 590, 'currency'=>'EUR']
            'recipient'  => $recipient,   // ['name','phone_number','email']
            'parcels'    => $parcels,     // lista ParcelV1
            'shipment_promise_id'         => $shipmentPromiseId,
            'customer_support'            => $this->buildCustomerSupport(),
            'merchant_order_reference_id' => $orderRef,
            'order_number'                => $orderNumber,
            // po potrebi: 'sms_notifications' / 'tips' / 'cash' / 'handshake_delivery' ...
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
     * Headeri za DaaS (nema X-Merchant-Id / X-Venue-Id)
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
     * Dropoff adresa (iz narudžbe) — doda lat/lon ako imaš kolone.
     */
    protected function buildDropoff(Order $order): array
    {
        $drop = [
            'street'    => $order->shipping_address ?? '',
            'city'      => $order->shipping_city ?? '',
            'post_code' => $order->shipping_zip ?? '',
        ];

        // Ako u bazi imaš koordinate (npr. shipping_lat/shipping_lon)
        if (!empty($order->shipping_lat) && !empty($order->shipping_lon)) {
            $drop['lat'] = (float) $order->shipping_lat;
            $drop['lon'] = (float) $order->shipping_lon;
        }

        return $drop;
    }

    /**
     * Primatelj (recipient) iz narudžbe.
     */
    protected function buildRecipient(Order $order): array
    {
        return [
            'name'         => trim(($order->shipping_fname ?? '').' '.($order->shipping_lname ?? '')),
            'phone_number' => $order->shipping_phone ?? $order->phone ?? '',
            'email'        => $order->email ?? '',
        ];
    }

    /**
     * Parcels — minimalistički: 1 paket s težinom i ukupnom vrijednošću narudžbe.
     */
    protected function buildParcels(Order $order): array
    {
        $amountCents = (int) round(((float) $order->total) * 100);

        return [[
            'count'      => 1,
            'dimensions' => [
                'weight_gram' => $this->guessTotalWeightGrams($order), // ili vrati null ako nemaš težine
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
     * Ako imaš težine po artiklu — zbroji; u suprotnom vrati razumnu default vrijednost.
     */
    protected function guessTotalWeightGrams(Order $order): ?int
    {
        try {
            $grams = 0;
            foreach ($order->products ?? [] as $p) {
                $qty   = (int) ($p->pivot->qty ?? $p->quantity ?? 1);
                $wgr   = null;

                // prilagodi naziv kolone za težinu (npr. $p->weight_grams ili $p->weight_kg)
                if (!empty($p->weight_grams)) {
                    $wgr = (int) $p->weight_grams;
                } elseif (!empty($p->weight)) {
                    // ako je u kg
                    $wgr = (int) round(((float) $p->weight) * 1000);
                }

                $grams += max(0, (int) $wgr) * max(1, $qty);
            }

            if ($grams > 0) {
                return $grams;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // fallback — npr. 500g ako nemaš podatke
        return 500;
    }

    /**
     * Customer support blok (prikazuje se korisniku u DaaS UI/obavijestima).
     */
    protected function buildCustomerSupport(): array
    {
        $url   = rtrim(config('app.url') ?: env('APP_PUBLIC_URL', ''), '/');
        $email = config('mail.from.address', 'support@'.$this->domainFromAppUrl());
        $phone = config('company.support_phone', '');

        return array_filter([
            'url'          => $url ?: null,
            'email'        => $email ?: null,
            'phone_number' => $phone ?: null,
        ]);
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
