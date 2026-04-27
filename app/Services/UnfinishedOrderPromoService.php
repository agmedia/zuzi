<?php

namespace App\Services;

use App\Models\Back\Marketing\Action;
use App\Models\Back\Orders\Order;
use App\Models\GiftVoucher;
use Carbon\Carbon;
use Illuminate\Support\Str;

class UnfinishedOrderPromoService
{
    public const ALLOWED_DISCOUNTS = [10, 15, 20];
    public const VALID_FOR_DAYS = 7;
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
        return 'Promo za nedovrsenu narudzbu #' . $order->id;
    }

    public function findForOrder(Order $order): ?Action
    {
        return Action::query()
            ->where('title', $this->titleForOrder($order))
            ->where('group', 'total')
            ->first();
    }

    public function isAllowedDiscount(int $discount): bool
    {
        return in_array($discount, self::ALLOWED_DISCOUNTS, true);
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
