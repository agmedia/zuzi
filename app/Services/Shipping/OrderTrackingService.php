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
    public const TRACKING_EMAIL_HISTORY_COMMENT = 'Kupcu poslan email s podacima za praćenje pošiljke.';

    public function __construct(
        private GlsTrackingService $gls,
        private BoxNowService $boxNow
    ) {
    }

    public function refresh(Order $order): array
    {
        $carrier = $this->resolveCarrier($order);

        if ($carrier === GlsTrackingService::CARRIER) {
            return $this->apply($order, $this->gls->trackOrder($order));
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
        $hadCustomerTrackingIdentifier = $this->hasCustomerTrackingIdentifier($order);

        if ($currentTrackedAt && $trackedAt->lt($currentTrackedAt)) {
            return [
                'updated' => false,
                'message' => 'Preskočen je stariji tracking update.',
                'tracking' => $tracking,
            ];
        }

        $previousStatusCode = (string) ($order->shipping_tracking_status_code ?? '');
        $newStatusCode = (string) ($tracking['status_code'] ?? '');
        $previousCustomerTrackingCode = $this->customerTrackingIdentifier($order);

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

        $customerTrackingCodeFirstAppeared = $previousCustomerTrackingCode === ''
            && $this->customerTrackingIdentifier($order) !== '';

        if ($writeHistory && (
            ($newStatusCode !== '' && $newStatusCode !== $previousStatusCode)
            || $customerTrackingCodeFirstAppeared
        )) {
            $this->storeHistory($order, $tracking);
        }

        $this->sendTrackingAvailableMail($order, $hadCustomerTrackingIdentifier);

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

    public function trackingUrlForOrder(Order $order): ?string
    {
        $carrier = $this->resolveCarrier($order);
        $trackingCode = trim((string) ($order->tracking_code ?: $order->shipping_parcel_id));

        if ($carrier === BoxNowService::CARRIER && $trackingCode !== '') {
            return $this->boxNow->trackingUrl($trackingCode);
        }

        return $order->shipping_tracking_url;
    }

    public function trackingEmailSentAt(Order $order): ?Carbon
    {
        if (Schema::hasColumn('orders', 'shipping_tracking_email_sent_at') && $order->shipping_tracking_email_sent_at) {
            return Carbon::make($order->shipping_tracking_email_sent_at);
        }

        $historyCreatedAt = OrderHistory::query()
            ->where('order_id', $order->id)
            ->where('comment', 'like', self::TRACKING_EMAIL_HISTORY_COMMENT . '%')
            ->latest('created_at')
            ->value('created_at');

        return $historyCreatedAt ? Carbon::make($historyCreatedAt) : null;
    }

    public function sendTrackingAvailableMailManually(Order $order): array
    {
        if (! $this->hasCustomerTrackingIdentifier($order)) {
            return [
                'sent' => false,
                'error' => 'Tracking broj nije upisan.',
            ];
        }

        if (! filled($order->payment_email)) {
            return [
                'sent' => false,
                'error' => 'Narudžba nema e-mail adresu kupca.',
            ];
        }

        $sentAt = $this->trackingEmailSentAt($order);

        if ($sentAt) {
            return [
                'sent' => false,
                'message' => 'Tracking email je već poslan kupcu ' . $sentAt->format('d.m.Y H:i') . '.',
            ];
        }

        $this->sendTrackingAvailableMail($order, false, true);

        return [
            'sent' => true,
            'message' => 'Tracking email je poslan kupcu.',
        ];
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
        $trackingCode = trim((string) ($tracking['tracking_code'] ?? ''));
        $trackingInfo = $trackingCode !== '' ? ' Broj pošiljke: ' . $trackingCode . '.' : '';

        OrderHistory::store($order->id, new Request([
            'status' => 0,
            'comment' => 'Tracking update (' . $carrier . '): ' . $status . $trackingInfo,
        ]));
    }

    private function sendTrackingAvailableMail(Order $order, bool $hadCustomerTrackingIdentifier, bool $throwOnFailure = false): bool
    {
        if ($hadCustomerTrackingIdentifier || ! $this->hasCustomerTrackingIdentifier($order)) {
            return false;
        }

        if (! filled($order->payment_email)) {
            return false;
        }

        if ($this->trackingEmailSentAt($order)) {
            return false;
        }

        try {
            Mail::to($order->payment_email)->send(new ShippingTrackingAvailable($order->fresh(['products', 'totals']) ?: $order));

            if (Schema::hasColumn('orders', 'shipping_tracking_email_sent_at')) {
                $order->forceFill([
                    'shipping_tracking_email_sent_at' => now(),
                ])->save();
            }

            OrderHistory::store($order->id, new Request([
                'status' => 0,
                'comment' => self::TRACKING_EMAIL_HISTORY_COMMENT . ' Broj pošiljke: ' . $this->customerTrackingIdentifier($order) . '.',
            ]));

            return true;
        } catch (\Throwable $e) {
            Log::warning('Shipment tracking email failed.', [
                'order_id' => $order->id,
                'email' => $order->payment_email,
                'error' => $e->getMessage(),
            ]);

            if ($throwOnFailure) {
                throw $e;
            }
        }

        return false;
    }

    private function hasCustomerTrackingIdentifier(Order $order): bool
    {
        return $this->customerTrackingIdentifier($order) !== '';
    }

    private function customerTrackingIdentifier(Order $order): string
    {
        return trim((string) $order->tracking_code);
    }
}
