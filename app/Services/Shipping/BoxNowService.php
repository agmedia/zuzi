<?php

namespace App\Services\Shipping;

use App\Models\Back\Orders\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BoxNowService
{
    public const CARRIER = 'boxnow';

    public function createDeliveryRequest(Order $order): array
    {
        $response = Http::withToken($this->accessToken())
            ->asJson()
            ->post($this->url('/delivery-requests'), $this->deliveryPayload($order));

        if (! $response->successful()) {
            throw new RuntimeException($this->errorMessage($response->json(), 'Box Now pošiljka nije kreirana.'));
        }

        $payload = $response->json() ?: [];
        $parcelId = data_get($payload, 'parcels.0.id');

        if (! $parcelId) {
            throw new RuntimeException('Box Now nije vratio parcel ID.');
        }

        return [
            'carrier' => self::CARRIER,
            'parcel_id' => (string) $parcelId,
            'tracking_code' => (string) $parcelId,
            'tracking_url' => $this->trackingUrl((string) $parcelId),
            'status_code' => 'new',
            'status' => $this->statusLabel('new'),
            'tracked_at' => now(),
            'payload' => $payload,
        ];
    }

    public function track(Order $order): array
    {
        $query = [
            'limit' => 1,
        ];

        if (filled($order->shipping_parcel_id ?: $order->tracking_code)) {
            $query['parcelId'] = $order->shipping_parcel_id ?: $order->tracking_code;
        } else {
            $query['orderNumber'] = (string) $order->id;
        }

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->get($this->url('/parcels'), $query);

        if (! $response->successful()) {
            throw new RuntimeException($this->errorMessage($response->json(), 'Box Now status nije dohvaćen.'));
        }

        $payload = $response->json() ?: [];
        $parcel = $this->firstParcel($payload);
        $parcelId = (string) (data_get($parcel, 'id') ?: data_get($parcel, 'parcelId') ?: $order->shipping_parcel_id ?: $order->tracking_code);
        $state = (string) (data_get($parcel, 'event') ?: data_get($parcel, 'state') ?: data_get($parcel, 'parcelState'));

        if ($parcelId === '') {
            throw new RuntimeException('Box Now nije pronašao parcelu za ovu narudžbu.');
        }

        return [
            'carrier' => self::CARRIER,
            'parcel_id' => $parcelId,
            'tracking_code' => $parcelId,
            'tracking_url' => $this->trackingUrl($parcelId),
            'status_code' => $state ?: null,
            'status' => $state !== '' ? $this->statusLabel($state) : 'Box Now status nije dostupan.',
            'tracked_at' => now(),
            'payload' => $payload,
            'is_delivered' => $state === 'delivered',
        ];
    }

    public function normalizeWebhookPayload(array $payload): array
    {
        $data = data_get($payload, 'data', []);
        $event = (string) data_get($data, 'event', data_get($data, 'parcelState', ''));
        $parcelId = (string) data_get($data, 'parcelId', data_get($payload, 'subject', ''));
        $eventTime = data_get($data, 'time', data_get($payload, 'time'));

        return [
            'carrier' => self::CARRIER,
            'order_number' => (string) data_get($data, 'orderNumber', ''),
            'parcel_id' => $parcelId,
            'tracking_code' => $parcelId,
            'tracking_url' => $parcelId !== '' ? $this->trackingUrl($parcelId) : null,
            'status_code' => $event ?: null,
            'status' => $event !== '' ? $this->statusLabel($event) : 'Box Now status nije dostupan.',
            'tracked_at' => $this->parseTime($eventTime),
            'payload' => $payload,
            'is_delivered' => $event === 'delivered',
        ];
    }

    public function verifyWebhookSignature(string $body, ?string $signature): bool
    {
        $secret = (string) config('services.boxnow.webhook_secret');

        if ($secret === '') {
            return true;
        }

        if (! filled($signature)) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $body, $secret), (string) $signature);
    }

    public function statusLabel(?string $state): string
    {
        $state = trim((string) $state);

        return [
            'new' => 'Čeka se preuzimanje iz e-trgovine',
            'in-depot' => 'Dostavlja se',
            'in-transit' => 'Dostavlja se',
            'final-destination' => 'Paket se nalazi u pretincu',
            'delivered' => 'Paket je dostavljen',
            'returned' => 'Paket je vraćen',
            'expired' => 'Isteklo je vrijeme preuzimanja i paket je vraćen pošiljatelju',
            'expired-return' => 'Isteklo je vrijeme preuzimanja i paket je vraćen pošiljatelju',
            'canceled' => 'Paket je poništen',
            'cancelled' => 'Paket je poništen',
            'lost' => 'Paket se pronalazi',
            'missing' => 'Paket se pronalazi',
            'accepted-to-locker' => 'U procesu isporuke',
            'accepted-for-return' => 'U procesu isporuke',
            'wait-for-load' => 'Paket čeka preuzimanje iz pretinca',
        ][$state] ?? 'Box Now status: ' . $state;
    }

    private function accessToken(): string
    {
        $response = Http::asJson()->post($this->url('/auth-sessions'), [
            'grant_type' => 'client_credentials',
            'client_id' => config('services.boxnow.client_id'),
            'client_secret' => config('services.boxnow.client_secret'),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->errorMessage($response->json(), 'Box Now autorizacija nije uspjela.'));
        }

        $token = (string) data_get($response->json(), 'access_token', '');

        if ($token === '') {
            throw new RuntimeException('Box Now nije vratio access token.');
        }

        return $token;
    }

    private function deliveryPayload(Order $order): array
    {
        $paymentMode = $order->payment_code === 'cod' ? 'cod' : 'prepaid';
        $lockerId = $this->lockerId($order);

        if ($lockerId === '') {
            throw new RuntimeException('Box Now paketomat nije upisan na narudžbi.');
        }

        return [
            'orderNumber' => (string) $order->id,
            'invoiceValue' => number_format((float) $order->total, 2, '.', ''),
            'paymentMode' => $paymentMode,
            'amountToBeCollected' => $paymentMode === 'cod' ? number_format((float) $order->total, 2, '.', '') : '0.00',
            'allowReturn' => true,
            'origin' => [
                'contactNumber' => '+385 91 258 1981',
                'contactEmail' => 'info@zuzi.hr',
                'contactName' => 'Mirjana Vulić',
                'locationId' => '7168',
            ],
            'destination' => [
                'contactNumber' => $this->normalizePhone($order->payment_phone ?: $order->shipping_phone),
                'contactEmail' => $order->payment_email ?: $order->shipping_email,
                'contactName' => trim($order->payment_fname . ' ' . $order->payment_lname),
                'locationId' => $lockerId,
            ],
            'items' => [$this->itemPayload($order)],
        ];
    }

    private function itemPayload(Order $order): array
    {
        $total = 0;
        $id = (string) $order->id;
        $name = 'Narudžba #' . $order->id;

        foreach ($order->products as $product) {
            $total += (float) $product->total;
            $id = (string) ($product->product_id ?: $order->id);
            $name = (string) ($product->name ?: $name);
        }

        return [
            'id' => $id,
            'name' => $name,
            'value' => number_format($total ?: (float) $order->total, 2, '.', ''),
            'weight' => 0,
            'compartmentSize' => 1,
        ];
    }

    private function lockerId(Order $order): string
    {
        $comment = (string) $order->comment;
        $position = strrpos($comment, '_');

        return trim($position === false ? $comment : substr($comment, $position + 1));
    }

    private function normalizePhone(?string $phone): string
    {
        $phone = preg_replace('/\s+/', '', (string) $phone);

        if (str_starts_with($phone, '0')) {
            return '+385' . substr($phone, 1);
        }

        return $phone;
    }

    private function firstParcel(array $payload): array
    {
        $data = data_get($payload, 'data', data_get($payload, 'parcels', $payload));

        if (isset($data[0]) && is_array($data[0])) {
            return $data[0];
        }

        return is_array($data) ? $data : [];
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.boxnow.base_url'), '/') . '/' . ltrim($path, '/');
    }

    public function trackingUrl(string $parcelId): ?string
    {
        $parcelId = trim($parcelId);

        if ($parcelId === '') {
            return null;
        }

        $baseUrl = trim((string) config('services.boxnow.tracking_url'));

        if ($baseUrl === '') {
            return null;
        }

        if (str_contains($baseUrl, '{parcel}')) {
            return str_replace('{parcel}', urlencode($parcelId), $baseUrl);
        }

        if (str_contains($baseUrl, 'track.boxnow.hr')) {
            $baseUrl = preg_replace('#/track/?$#', '', rtrim($baseUrl, '/')) ?: $baseUrl;

            if (preg_match('/([?&]track=)([^&]*)/', $baseUrl)) {
                return preg_replace('/([?&]track=)([^&]*)/', '$1' . urlencode($parcelId), $baseUrl);
            }

            return $baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') . 'track=' . urlencode($parcelId);
        }

        return rtrim($baseUrl, '/') . '/' . urlencode($parcelId);
    }

    private function errorMessage($payload, string $fallback): string
    {
        return (string) (data_get($payload, 'message') ?: data_get($payload, 'error') ?: $fallback);
    }

    private function parseTime($value): Carbon
    {
        try {
            return $value ? Carbon::parse($value) : now();
        } catch (\Throwable $e) {
            return now();
        }
    }
}
