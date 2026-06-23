<?php

namespace App\Services;

use App\Models\Back\Marketing\Action;
use App\Models\Back\Orders\Order;
use App\Models\GiftVoucher;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    public function hasSentPromoForOrder(Order $order, ?int $discount = null): bool
    {
        return $this->sentOrderIdsForOrderIds(collect([(int) $order->id]), $discount)
            ->contains((int) $order->id);
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

        $specialTotals = DB::table('order_total as totals')
            ->whereIn('totals.order_id', $orderIds->all())
            ->where('totals.code', 'special')
            ->get(['totals.order_id', 'totals.title']);

        if ($specialTotals->isEmpty()) {
            return collect();
        }

        $couponActionTitles = $this->couponActionTitles(
            $specialTotals
                ->pluck('title')
                ->reject(fn ($title) => Str::startsWith(trim((string) $title), 'Kupon '))
        );

        return $specialTotals
            ->filter(function ($total) use ($couponActionTitles) {
                $title = trim((string) $total->title);

                return Str::startsWith($title, 'Kupon ')
                    || $couponActionTitles->contains($title);
            })
            ->pluck('order_id')
            ->map(fn ($orderId) => (int) $orderId)
            ->unique()
            ->values();
    }

    public function sentOrderIds(?int $discount = null): Collection
    {
        $orderIds = $this->sentOrderIdsFromActionTable('product_actions', $discount)
            ->merge($this->sentOrderIdsFromHistory($discount));

        if (Schema::hasTable('product_action_archives')) {
            $orderIds = $orderIds->merge($this->sentOrderIdsFromActionTable('product_action_archives', $discount));
        }

        return $orderIds
            ->filter(fn ($orderId) => $orderId > 0)
            ->unique()
            ->values();
    }

    public function sentOrderIdsForOrderIds(Collection $orderIds, ?int $discount = null): Collection
    {
        $orderIds = $orderIds
            ->map(fn ($orderId) => (int) $orderId)
            ->filter(fn ($orderId) => $orderId > 0)
            ->unique()
            ->values();

        if ($orderIds->isEmpty()) {
            return collect();
        }

        $titles = $orderIds
            ->map(fn ($orderId) => self::TITLE_PREFIX . $orderId)
            ->values()
            ->all();

        $sentOrderIds = $this->sentOrderIdsFromActionTable('product_actions', $discount, $titles)
            ->merge($this->sentOrderIdsFromHistory($discount, $orderIds));

        if (Schema::hasTable('product_action_archives')) {
            $sentOrderIds = $sentOrderIds->merge(
                $this->sentOrderIdsFromActionTable('product_action_archives', $discount, $titles)
            );
        }

        return $sentOrderIds
            ->filter(fn ($orderId) => $orderIds->contains((int) $orderId))
            ->unique()
            ->values();
    }

    public function isAllowedDiscount(int $discount): bool
    {
        return in_array($discount, self::ALLOWED_DISCOUNTS, true);
    }

    private function couponActionTitles(Collection $titles): Collection
    {
        $titles = $titles
            ->map(fn ($title) => trim((string) $title))
            ->filter()
            ->unique()
            ->values();

        if ($titles->isEmpty()) {
            return collect();
        }

        $couponTitles = Action::query()
            ->where('group', 'total')
            ->whereNotNull('coupon')
            ->where('coupon', '!=', '')
            ->whereIn('title', $titles->all())
            ->pluck('title');

        if (Schema::hasTable('product_action_archives')) {
            $couponTitles = $couponTitles->merge(
                DB::table('product_action_archives')
                    ->where('group', 'total')
                    ->whereNotNull('coupon')
                    ->where('coupon', '!=', '')
                    ->whereIn('title', $titles->all())
                    ->pluck('title')
            );
        }

        return $couponTitles
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

    private function sentOrderIdsFromActionTable(string $table, ?int $discount = null, ?array $titles = null): Collection
    {
        $query = DB::table($table)
            ->where('title', 'like', self::TITLE_PREFIX . '%')
            ->where('group', 'total');

        if ($titles !== null) {
            $query->whereIn('title', $titles);
        }

        $query->where(function ($query) use ($discount) {
            if ($discount !== null) {
                $query->where('discount', $discount)
                    ->orWhere('coupon', 'like', 'HVALA' . $discount . '-%')
                    ->orWhere('data', 'like', '%"discount":' . $discount . '%');

                return;
            }

            $query->where('coupon', 'like', 'HVALA%-%')
                ->orWhere('data', 'like', '%"source":"unfinished_order_promo"%');
        });

        return $query
            ->pluck('title')
            ->map(fn ($title) => $this->orderIdFromPromoTitle((string) $title))
            ->filter();
    }

    private function sentOrderIdsFromHistory(?int $discount = null, ?Collection $orderIds = null): Collection
    {
        $query = DB::table('order_history')
            ->where('comment', 'like', 'Poslan promo email za nedovrsenu narudzbu.%Kod:%');

        if ($orderIds !== null) {
            $query->whereIn('order_id', $orderIds->all());
        }

        if ($discount !== null) {
            $query->where(function ($query) use ($discount) {
                $query->where('comment', 'like', '%Kod: HVALA' . $discount . '-%')
                    ->orWhere('comment', 'like', '%Popust: -' . $discount . '%');
            });
        }

        return $query
            ->pluck('order_id')
            ->map(fn ($orderId) => (int) $orderId)
            ->filter();
    }

    private function orderIdFromPromoTitle(string $title): int
    {
        $orderId = Str::after($title, self::TITLE_PREFIX);

        return ctype_digit($orderId) ? (int) $orderId : 0;
    }
}
