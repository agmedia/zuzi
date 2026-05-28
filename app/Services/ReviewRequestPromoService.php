<?php

namespace App\Services;

use App\Models\Back\Marketing\Action;
use App\Models\Back\Orders\Order;
use App\Models\GiftVoucher;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ReviewRequestPromoService
{
    public const DISCOUNT_PERCENT = 20;
    public const VALID_FOR_DAYS = 7;

    private const COUPON_SUFFIX_LENGTH = 7;
    private const SOURCE = 'review_request_promo';

    public function issueForOrder(Order $order): Action
    {
        if ($existing = $this->findForOrder($order)) {
            return $existing;
        }

        $now = Carbon::now();
        $expiresAt = (clone $now)->addDays(self::VALID_FOR_DAYS);

        return Action::query()->create([
            'title' => $this->titleForOrder($order),
            'type' => 'P',
            'discount' => self::DISCOUNT_PERCENT,
            'group' => 'total',
            'links' => json_encode(['total']),
            'date_start' => $now,
            'date_end' => $expiresAt,
            'data' => json_encode([
                'source' => self::SOURCE,
                'order_id' => (int) $order->id,
                'source_order_status_id' => (int) $order->order_status_id,
                'source_order_status_title' => (string) optional($order->status)->title,
                'discount' => self::DISCOUNT_PERCENT,
            ]),
            'coupon' => $this->generateUniqueCode(),
            'quantity' => 1,
            'lock' => 0,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function previewForOrder(Order $order): Action
    {
        $now = Carbon::now();

        return new Action([
            'title' => $this->titleForOrder($order),
            'type' => 'P',
            'discount' => self::DISCOUNT_PERCENT,
            'group' => 'total',
            'links' => json_encode(['total']),
            'date_start' => $now,
            'date_end' => (clone $now)->addDays(self::VALID_FOR_DAYS),
            'coupon' => 'TEST' . self::DISCOUNT_PERCENT . '-' . Str::upper(Str::random(self::COUPON_SUFFIX_LENGTH)),
            'quantity' => 1,
            'lock' => 0,
            'status' => 1,
        ]);
    }

    public function findForOrder(Order $order): ?Action
    {
        return Action::query()
            ->where('title', $this->titleForOrder($order))
            ->where('group', 'total')
            ->first();
    }

    public function titleForOrder(Order $order): string
    {
        return 'Promo za komentar narudzbe #' . $order->id;
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = 'KOMENTAR' . self::DISCOUNT_PERCENT . '-' . Str::upper(Str::random(self::COUPON_SUFFIX_LENGTH));
        } while (
            Action::query()->where('coupon', $code)->exists()
            || GiftVoucher::query()->where('code', $code)->exists()
        );

        return $code;
    }
}
