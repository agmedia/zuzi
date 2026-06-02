<?php

namespace App\Services;

use App\Models\Back\Marketing\Action;
use App\Models\Back\Orders\Order;
use App\Models\GiftVoucher;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UnfinishedOrderPromoService
{
    public const ALLOWED_DISCOUNTS = [5, 10, 15, 20];
    public const VALID_FOR_DAYS = 7;
    public const TITLE_PREFIX = 'Promo za nedovrsenu narudzbu #';
    public const REMINDER_HISTORY_COMMENT = 'Poslan podsjetnik za nedovrsenu narudzbu bez promo koda.';
    private const COUPON_SUFFIX_LENGTH = 7;

    public function issueForOrder(Order $order, int $discount): Action
    {
        $now = Carbon::now();
        $expiresAt = (clone $now)->addDays(self::VALID_FOR_DAYS);

        $payload = [
            'title' => $this->titleForOrder($order),
            'type' => 'P',
            'discount' => $discount,
            'group' => 'total',
            'links' => json_encode(['total']),
            'date_start' => $now,
            'date_end' => $expiresAt,
            'data' => json_encode([
                'source' => 'unfinished_order_promo',
                'order_id' => (int) $order->id,
                'source_order_status_id' => (int) $order->order_status_id,
                'source_order_status_title' => (string) optional($order->status)->title,
                'discount' => $discount,
            ]),
            'coupon' => $this->generateUniqueCode($discount),
            'quantity' => 1,
            'lock' => 0,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        return Action::query()->create($payload);
    }

    public function titleForOrder(Order $order): string
    {
        return self::TITLE_PREFIX . $order->id;
    }

    public function findForOrder(Order $order): ?Action
    {
        return Action::query()
            ->where('title', $this->titleForOrder($order))
            ->where('group', 'total')
            ->first();
    }

    public function shouldSuppressSendButtonForOrder(Order $order): bool
    {
        return $this->orderIdsWithAppliedCoupons(collect([(int) $order->id]))->contains((int) $order->id);
    }

    public function orderIdsWithAppliedCoupons(Collection $orderIds): Collection
    {
        $orderIds = $orderIds
            ->map(fn ($orderId) => (int) $orderId)
            ->filter(fn ($orderId) => $orderId > 0)
            ->unique()
            ->values();

        if ($orderIds->isEmpty()) {
            return collect();
        }

        $couponActionTitles = $this->couponActionTitles();

        return DB::table('order_total as totals')
            ->whereIn('totals.order_id', $orderIds->all())
            ->where('totals.code', 'special')
            ->where(function ($query) use ($couponActionTitles) {
                $query->where('totals.title', 'like', 'Kupon %');

                if ($couponActionTitles->isNotEmpty()) {
                    $query->orWhereIn('totals.title', $couponActionTitles->all());
                }
            })
            ->pluck('totals.order_id')
            ->map(fn ($orderId) => (int) $orderId)
            ->unique()
            ->values();
    }

    public function sentOrderIds(): Collection
    {
        return Action::query()
            ->where('title', 'like', self::TITLE_PREFIX . '%')
            ->where('group', 'total')
            ->pluck('title')
            ->map(function ($title) {
                $orderId = Str::after((string) $title, self::TITLE_PREFIX);

                return ctype_digit($orderId) ? (int) $orderId : 0;
            })
            ->filter(fn ($orderId) => $orderId > 0)
            ->unique()
            ->values();
    }

    public function isAllowedDiscount(int $discount): bool
    {
        return in_array($discount, self::ALLOWED_DISCOUNTS, true);
    }

    private function couponActionTitles(): Collection
    {
        return Action::query()
            ->where('group', 'total')
            ->whereNotNull('coupon')
            ->where('coupon', '!=', '')
            ->pluck('title')
            ->map(fn ($title) => trim((string) $title))
            ->filter()
            ->unique()
            ->values();
    }

    private function generateUniqueCode(int $discount): string
    {
        do {
            $code = 'HVALA' . $discount . '-' . Str::upper(Str::random(self::COUPON_SUFFIX_LENGTH));
        } while (
            Action::query()->where('coupon', $code)->exists()
            || GiftVoucher::query()->where('code', $code)->exists()
        );

        return $code;
    }
}
