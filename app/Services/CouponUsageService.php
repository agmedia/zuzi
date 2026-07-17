<?php

namespace App\Services;

use App\Helpers\Helper;
use App\Helpers\Session\CheckoutSession;
use App\Models\Back\Marketing\Action;
use App\Models\Back\Orders\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CouponUsageService
{
    public const ALREADY_USED_MESSAGE = 'Ovaj kupon već je iskorišten za upisanu email adresu.';

    public function isLimitedToOneUsePerEmail(string $coupon): bool
    {
        $coupon = Helper::normalizeCoupon($coupon);

        if (
            $coupon === '' ||
            ! Schema::hasTable('product_actions') ||
            ! Schema::hasColumn('product_actions', 'once_per_email')
        ) {
            return false;
        }

        return Action::query()
            ->where('once_per_email', 1)
            ->whereRaw('UPPER(TRIM(coupon)) = ?', [$coupon])
            ->get()
            ->contains(fn (Action $action) => $action->isValid($coupon));
    }

    public function hasBeenUsed(string $coupon, ?string $email, ?int $excludeOrderId = null): bool
    {
        $coupon = Helper::normalizeCoupon($coupon);
        $email = $this->normalizeEmail($email);

        if (
            $coupon === '' ||
            $email === '' ||
            ! $this->isLimitedToOneUsePerEmail($coupon) ||
            ! Schema::hasTable('orders')
        ) {
            return false;
        }

        $excludedStatuses = Order::dashboardSalesExcludedStatusIds();
        $legacyTotalTitles = $this->legacyTotalTitles($coupon);

        return Order::query()
            ->whereRaw('LOWER(TRIM(payment_email)) = ?', [$email])
            ->when($excludeOrderId, function (Builder $query) use ($excludeOrderId) {
                $query->where('orders.id', '!=', $excludeOrderId);
            })
            ->when(! empty($excludedStatuses), function (Builder $query) use ($excludedStatuses) {
                $query->whereNotIn('order_status_id', $excludedStatuses);
            })
            ->where(function (Builder $query) use ($coupon, $legacyTotalTitles) {
                if (Schema::hasColumn('orders', 'coupon_code')) {
                    $query->whereRaw('UPPER(TRIM(coupon_code)) = ?', [$coupon]);

                    if (! empty($legacyTotalTitles) && Schema::hasTable('order_total')) {
                        $query->orWhereHas('totals', function (Builder $totalQuery) use ($legacyTotalTitles) {
                            $totalQuery->where('code', 'special')
                                ->whereIn('title', $legacyTotalTitles);
                        });
                    }

                    return;
                }

                if (! empty($legacyTotalTitles) && Schema::hasTable('order_total')) {
                    $query->whereHas('totals', function (Builder $totalQuery) use ($legacyTotalTitles) {
                        $totalQuery->where('code', 'special')
                            ->whereIn('title', $legacyTotalTitles);
                    });
                    return;
                }

                $query->whereRaw('1 = 0');
            })
            ->exists();
    }

    public function hasBeenUsedByCurrentCustomer(string $coupon): bool
    {
        return $this->hasBeenUsed($coupon, $this->currentCustomerEmail());
    }

    public function currentCustomerEmail(): string
    {
        $checkoutEmail = data_get(CheckoutSession::getAddress(), 'email');

        if ($this->normalizeEmail($checkoutEmail) !== '') {
            return $this->normalizeEmail($checkoutEmail);
        }

        return $this->normalizeEmail(auth()->user()?->email);
    }

    public function normalizeEmail(?string $email): string
    {
        return Str::lower(trim((string) $email));
    }

    private function legacyTotalTitles(string $coupon): array
    {
        $titles = collect(['Kupon ' . $coupon]);

        if (Schema::hasTable('product_actions')) {
            $titles = $titles->merge(
                Action::query()
                    ->whereRaw('UPPER(TRIM(coupon)) = ?', [$coupon])
                    ->pluck('title')
            );
        }

        if (Schema::hasTable('product_action_archives')) {
            $titles = $titles->merge(
                DB::table('product_action_archives')
                    ->whereRaw('UPPER(TRIM(coupon)) = ?', [$coupon])
                    ->pluck('title')
            );
        }

        return $titles
            ->map(fn ($title) => trim((string) $title))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
