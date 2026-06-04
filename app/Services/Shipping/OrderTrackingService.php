<?php

namespace App\Services\Shipping;

use App\Mail\ShippingTrackingAvailable;
use App\Models\Back\Orders\Order;
use App\Models\Back\Orders\OrderHistory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class OrderTrackingService
{
    public function __construct(
        private GlsTrackingService $gls,
        private BoxNowService $boxNow
    ) {
    }

    public function refresh(Order $order): array
    {
        $carrier = $this->resolveCarrier($order);

        if ($carrier === GlsTrackingService::CARRIER) {
            $trackingCode = trim((string) $order->tracking_code);

            if ($trackingCode === '') {
                throw new RuntimeException('GLS tracking broj nije upisan za ovu narudžbu.');
            }

            return $this->apply($order, $this->gls->track($trackingCode));
        }

        if ($carrier === BoxNowService::CARRIER) {
            return $this->apply($order, $this->boxNow->track($order));
        }

        throw new RuntimeException('Praćenje nije podržano za ovaj način dostave.');
    }

    public function apply(Order $order, array $tracking, bool $writeHistory = true): array
    {
        $trackedAt = $this->trackedAt($tracking['tracked_at'] ?? null);
        $currentTrackedAt = $order->shipping_tracking_updated_at ? Carbon::make($order->shipping_tracking_updated_at) : null;
        $hadTrackingIdentifier = $this->hasTrackingIdentifier($order);

        if ($currentTrackedAt && $trackedAt->lt($currentTrackedAt)) {
            return [
                'updated' => false,
                'message' => 'Preskočen je stariji tracking update.',
                'tracking' => $tracking,
            ];
        }

        $previousStatusCode = (string) ($order->shipping_tracking_status_code ?? '');
        $newStatusCode = (string) ($tracking['status_code'] ?? '');

        $order->forceFill([
            'shipping_carrier' => $tracking['carrier'] ?? $this->resolveCarrier($order),
            'shipping_parcel_id' => $tracking['parcel_id'] ?? $order->shipping_parcel_id,
            'tracking_code' => $tracking['tracking_code'] ?? $order->tracking_code,
            'shipping_tracking_url' => $tracking['tracking_url'] ?? $order->shipping_tracking_url,
            'shipping_tracking_status_code' => $newStatusCode ?: null,
            'shipping_tracking_status' => $tracking['status'] ?? null,
            'shipping_tracking_updated_at' => $trackedAt,
            'shipping_tracking_payload' => $tracking['payload'] ?? [],
        ])->save();

        if (! empty($tracking['is_delivered'])) {
            $order->forceFill(['shipped' => true])->save();
        }

        if ($writeHistory && $newStatusCode !== '' && $newStatusCode !== $previousStatusCode) {
            $this->storeHistory($order, $tracking);
        }

        $this->sendTrackingAvailableMail($order, $hadTrackingIdentifier);

        return [
            'updated' => true,
            'message' => 'Tracking je osvježen: ' . ($tracking['status'] ?? 'status nije dostupan'),
            'tracking' => $tracking,
        ];
    }

    public function applyBoxNowWebhook(array $payload): array
    {
        $tracking = $this->boxNow->normalizeWebhookPayload($payload);
        $order = $this->findBoxNowOrder($tracking);

        if (! $order) {
            throw new RuntimeException('Narudžba za Box Now webhook nije pronađena.');
        }

        return $this->apply($order, $tracking);
    }

    public function resolveCarrier(Order $order): ?string
    {
        if (filled($order->shipping_carrier)) {
            return (string) $order->shipping_carrier;
        }

        $shippingMethod = strtolower((string) $order->shipping_method);
        $shippingCode = strtolower((string) $order->shipping_code);

        if (str_contains($shippingMethod, 'boxnow') || str_contains($shippingMethod, 'box now') || str_contains($shippingCode, 'boxnow')) {
            return BoxNowService::CARRIER;
        }

        if (str_contains($shippingMethod, 'gls') || str_contains($shippingCode, 'gls')) {
            return GlsTrackingService::CARRIER;
        }

        return null;
    }

    public function carrierLabel(?string $carrier): string
    {
        return [
            BoxNowService::CARRIER => 'Box Now',
            GlsTrackingService::CARRIER => 'GLS',
        ][$carrier] ?? 'Dostava';
    }

    private function findBoxNowOrder(array $tracking): ?Order
    {
        $orderNumber = (string) ($tracking['order_number'] ?? '');
        $parcelId = (string) ($tracking['parcel_id'] ?? '');

        if ($orderNumber === '' && $parcelId === '') {
            return null;
        }

        return Order::query()
            ->where(function ($query) use ($orderNumber, $parcelId) {
                if (ctype_digit($orderNumber)) {
                    $query->orWhere('id', (int) $orderNumber);
                }

                if ($parcelId !== '') {
                    $query->orWhere('shipping_parcel_id', $parcelId)
                        ->orWhere('tracking_code', $parcelId);
                }
            })
            ->first();
    }

    private function trackedAt($value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        try {
            return $value ? Carbon::parse($value) : now();
        } catch (\Throwable $e) {
            return now();
        }
    }

    private function storeHistory(Order $order, array $tracking): void
    {
        $carrier = $this->carrierLabel($tracking['carrier'] ?? null);
        $status = $tracking['status'] ?? 'status nije dostupan';

        OrderHistory::store($order->id, new Request([
            'status' => 0,
            'comment' => 'Tracking update (' . $carrier . '): ' . $status,
        ]));
    }

    private function sendTrackingAvailableMail(Order $order, bool $hadTrackingIdentifier): void
    {
        if ($hadTrackingIdentifier || ! $this->hasTrackingIdentifier($order)) {
            return;
        }

        if (! filled($order->payment_email) || ! Schema::hasColumn('orders', 'shipping_tracking_email_sent_at')) {
            return;
        }

        if ($order->shipping_tracking_email_sent_at) {
            return;
        }

        try {
            Mail::to($order->payment_email)->send(new ShippingTrackingAvailable($order->fresh(['products', 'totals']) ?: $order));

            $order->forceFill([
                'shipping_tracking_email_sent_at' => now(),
            ])->save();

            OrderHistory::store($order->id, new Request([
                'status' => 0,
                'comment' => 'Kupcu poslan email s podacima za praćenje pošiljke.',
            ]));
        } catch (\Throwable $e) {
            Log::warning('Shipment tracking email failed.', [
                'order_id' => $order->id,
                'email' => $order->payment_email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function hasTrackingIdentifier(Order $order): bool
    {
        return filled($order->tracking_code) || filled($order->shipping_parcel_id);
    }
}
