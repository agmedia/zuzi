<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class UnfinishedOrderPromoStatsService
{
    public function getDashboardData(): array
    {
        $promoActionsQuery = $this->promoActionsQuery();
        $usedPromoOrdersQuery = $this->usedPromoOrdersQuery();

        $sentCount = (clone $promoActionsQuery)->count();
        $usedCount = (clone $usedPromoOrdersQuery)
            ->select('promo_actions.id')
            ->distinct()
            ->get()
            ->count();

        $revenueTotal = (float) ((clone $usedPromoOrdersQuery)->sum('orders.total') ?: 0);
        $discountTotal = (float) ((clone $usedPromoOrdersQuery)->sum(DB::raw('ABS(totals.value)')) ?: 0);

        return [
            'summary' => [
                'sent_count' => $sentCount,
                'used_count' => $usedCount,
                'conversion_rate' => $this->resolveRate($usedCount, $sentCount),
                'revenue_total' => $revenueTotal,
                'discount_total' => $discountTotal,
            ],
            'chart' => $this->buildChartData(),
            'by_discount' => $this->buildDiscountBreakdown(),
        ];
    }

    private function buildChartData(): array
    {
        $from = now()->subDays(29)->startOfDay();
        $to = now()->endOfDay();

        $sentByDay = (clone $this->promoActionsQuery())
            ->whereBetween('actions.created_at', [$from, $to])
            ->selectRaw('DATE(actions.created_at) as day, COUNT(*) as sent_count')
            ->groupBy('day')
            ->pluck('sent_count', 'day');

        $usedByDay = (clone $this->usedPromoOrdersQuery())
            ->whereBetween('orders.created_at', [$from, $to])
            ->selectRaw('DATE(orders.created_at) as day, COUNT(DISTINCT promo_actions.id) as used_count')
            ->groupBy('day')
            ->pluck('used_count', 'day');

        $labels = [];
        $sent = [];
        $used = [];

        foreach (CarbonPeriod::create($from, '1 day', $to) as $day) {
            $key = $day->format('Y-m-d');
            $labels[] = $day->format('d.m.');
            $sent[] = (int) ($sentByDay[$key] ?? 0);
            $used[] = (int) ($usedByDay[$key] ?? 0);
        }

        return [
            'labels' => $labels,
            'sent' => $sent,
            'used' => $used,
        ];
    }

    private function buildDiscountBreakdown(): array
    {
        $sentByDiscount = (clone $this->promoActionsQuery())
            ->select('actions.discount')
            ->selectRaw('COUNT(*) as sent_count')
            ->groupBy('actions.discount')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->discount => (int) $row->sent_count]);

        $usedByDiscount = (clone $this->usedPromoOrdersQuery())
            ->select('promo_actions.discount')
            ->selectRaw('COUNT(DISTINCT promo_actions.id) as used_count')
            ->selectRaw('COALESCE(SUM(orders.total), 0) as revenue_total')
            ->selectRaw('COALESCE(SUM(ABS(totals.value)), 0) as discount_total')
            ->groupBy('promo_actions.discount')
            ->get()
            ->keyBy(fn ($row) => (int) $row->discount);

        return collect(UnfinishedOrderPromoService::ALLOWED_DISCOUNTS)
            ->map(function (int $discount) use ($sentByDiscount, $usedByDiscount) {
                $sentCount = (int) ($sentByDiscount[$discount] ?? 0);
                $usedRow = $usedByDiscount->get($discount);
                $usedCount = (int) ($usedRow->used_count ?? 0);

                return [
                    'discount' => $discount,
                    'sent_count' => $sentCount,
                    'used_count' => $usedCount,
                    'conversion_rate' => $this->resolveRate($usedCount, $sentCount),
                    'revenue_total' => (float) ($usedRow->revenue_total ?? 0),
                    'discount_total' => (float) ($usedRow->discount_total ?? 0),
                ];
            })
            ->all();
    }

    private function promoActionsQuery()
    {
        return DB::table('product_actions as actions')
            ->where('actions.group', 'total')
            ->where(function ($query) {
                $query->where('actions.data', 'like', '%"source":"unfinished_order_promo"%')
                    ->orWhere('actions.title', 'like', 'Promo za nedovrsenu narudzbu #%');
            });
    }

    private function usedPromoOrdersQuery()
    {
        return DB::table('order_total as totals')
            ->joinSub(
                $this->promoActionsQuery()->select([
                    'actions.id',
                    'actions.title',
                    'actions.discount',
                    'actions.created_at',
                ]),
                'promo_actions',
                function ($join) {
                    $join->on('promo_actions.title', '=', 'totals.title');
                }
            )
            ->join('orders', 'orders.id', '=', 'totals.order_id')
            ->whereNotIn('orders.order_status_id', $this->excludedOrderStatuses())
            ->where('totals.code', 'special');
    }

    private function excludedOrderStatuses(): array
    {
        return array_values(array_filter([
            config('settings.order.status.canceled'),
            config('settings.order.status.declined'),
            config('settings.order.status.unfinished'),
        ], static fn ($status) => $status !== null));
    }

    private function resolveRate(int $usedCount, int $sentCount): float
    {
        if ($sentCount === 0) {
            return 0.0;
        }

        return round(($usedCount / $sentCount) * 100, 1);
    }
}
