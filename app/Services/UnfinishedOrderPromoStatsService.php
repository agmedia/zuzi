<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class UnfinishedOrderPromoStatsService
{
    public const SEGMENT_UNFINISHED = 'unfinished';
    public const SEGMENT_NON_UNFINISHED = 'non_unfinished';

    public function getDashboardData(array $filters = []): array
    {
        $segment = $this->normalizeSegment($filters['segment'] ?? self::SEGMENT_UNFINISHED);
        $year = $this->normalizeYear($filters['year'] ?? now()->year);
        $month = $this->normalizeMonth($filters['month'] ?? now()->month);

        $actions = $this->filteredPromoActions($segment, $year, $month);
        $usedPromos = $this->usedPromosForActions($actions);
        $byDiscount = $this->buildDiscountBreakdown($actions, $usedPromos);

        $sentCount = $actions->count();
        $usedCount = $usedPromos->count();
        $revenueTotal = (float) $usedPromos->sum('order_total');
        $discountTotal = (float) $usedPromos->sum('discount_total');

        return [
            'summary' => [
                'sent_count' => $sentCount,
                'used_count' => $usedCount,
                'unused_count' => max($sentCount - $usedCount, 0),
                'conversion_rate' => $this->resolveRate($usedCount, $sentCount),
                'revenue_total' => $revenueTotal,
                'discount_total' => $discountTotal,
                'average_revenue_per_used' => $this->resolveAverage($revenueTotal, $usedCount),
                'average_discount_per_used' => $this->resolveAverage($discountTotal, $usedCount),
                'best_discount' => $this->resolveBestDiscount($byDiscount),
            ],
            'chart' => $this->buildChartData($actions, $usedPromos, $year, $month),
            'by_discount' => $byDiscount,
        ];
    }

    public function getAvailableYears(): array
    {
        return $this->basePromoActionsQuery()
            ->select('actions.created_at')
            ->orderByDesc('actions.created_at')
            ->get()
            ->map(fn ($row) => (int) Carbon::parse($row->created_at)->format('Y'))
            ->filter()
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    private function filteredPromoActions(string $segment, int $year, int $month): Collection
    {
        $actions = $this->basePromoActionsQuery()
            ->select([
                'actions.id',
                'actions.title',
                'actions.discount',
                'actions.created_at',
                'actions.data',
            ])
            ->whereYear('actions.created_at', $year)
            ->whereMonth('actions.created_at', $month)
            ->orderBy('actions.created_at')
            ->get()
            ->map(function ($row) {
                $data = $this->decodeActionData($row->data ?? null);

                return (object) [
                    'id' => (int) $row->id,
                    'title' => (string) $row->title,
                    'discount' => (int) $row->discount,
                    'created_at' => Carbon::parse($row->created_at),
                    'source_order_id' => $this->extractSourceOrderId((string) $row->title, $data),
                    'source_order_status_id' => $this->extractSourceOrderStatusId($data),
                ];
            });

        if ($actions->isEmpty()) {
            return collect();
        }

        $sourceOrderStatuses = $this->sourceOrderStatuses(
            $actions->pluck('source_order_id')->filter()->unique()->values()
        );
        $historyStatuses = $this->sourceOrderHistoryStatuses($actions);

        return $actions
            ->map(function ($action) use ($sourceOrderStatuses, $historyStatuses) {
                if ($action->source_order_status_id === null && $action->source_order_id !== null) {
                    $resolvedStatusId = $historyStatuses->get($action->id);

                    if ($resolvedStatusId === null) {
                        $resolvedStatusId = $sourceOrderStatuses->get($action->source_order_id);
                    }

                    $action->source_order_status_id = $resolvedStatusId !== null ? (int) $resolvedStatusId : null;
                }

                return $action;
            })
            ->filter(fn ($action) => $this->matchesSegment($action->source_order_status_id, $segment))
            ->values();
    }

    private function usedPromosForActions(Collection $actions): Collection
    {
        if ($actions->isEmpty()) {
            return collect();
        }

        $rows = DB::table('order_total as totals')
            ->join('orders', 'orders.id', '=', 'totals.order_id')
            ->whereNotIn('orders.order_status_id', $this->excludedOrderStatuses())
            ->where('totals.code', 'special')
            ->whereIn('totals.title', $actions->pluck('title')->all())
            ->select([
                'totals.title',
                'totals.value as discount_value',
                'orders.total as order_total',
                'orders.created_at as redeemed_at',
            ])
            ->orderBy('orders.created_at')
            ->get();

        $actionByTitle = $actions->keyBy('title');

        return $rows
            ->groupBy('title')
            ->map(function (Collection $group, string $title) use ($actionByTitle) {
                $action = $actionByTitle->get($title);
                $first = $group->first();

                return (object) [
                    'title' => $title,
                    'discount' => (int) ($action->discount ?? 0),
                    'order_total' => (float) ($first->order_total ?? 0),
                    'discount_total' => abs((float) ($first->discount_value ?? 0)),
                    'redeemed_at' => Carbon::parse($first->redeemed_at),
                ];
            })
            ->values();
    }

    private function buildChartData(Collection $actions, Collection $usedPromos, int $year, int $month): array
    {
        $from = Carbon::create($year, $month, 1)->startOfDay();
        $to = (clone $from)->endOfMonth();

        $sentByDay = $actions
            ->groupBy(fn ($action) => $action->created_at->format('Y-m-d'))
            ->map(fn (Collection $group) => $group->count());

        $usedByDay = $usedPromos
            ->groupBy(fn ($promo) => $promo->redeemed_at->format('Y-m-d'))
            ->map(fn (Collection $group) => $group->count());

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

    private function buildDiscountBreakdown(Collection $actions, Collection $usedPromos): array
    {
        $sentByDiscount = $actions
            ->groupBy('discount')
            ->map(fn (Collection $group) => $group->count());

        $usedByDiscount = $usedPromos
            ->groupBy('discount');

        return collect(UnfinishedOrderPromoService::ALLOWED_DISCOUNTS)
            ->map(function (int $discount) use ($sentByDiscount, $usedByDiscount) {
                $sentCount = (int) $sentByDiscount->get($discount, 0);
                $usedRows = $usedByDiscount->get($discount, collect());
                $usedCount = $usedRows->count();

                return [
                    'discount' => $discount,
                    'sent_count' => $sentCount,
                    'used_count' => $usedCount,
                    'conversion_rate' => $this->resolveRate($usedCount, $sentCount),
                    'revenue_total' => (float) $usedRows->sum('order_total'),
                    'discount_total' => (float) $usedRows->sum('discount_total'),
                ];
            })
            ->all();
    }

    private function basePromoActionsQuery()
    {
        return DB::table('product_actions as actions')
            ->where('actions.group', 'total')
            ->where(function ($query) {
                $query->where('actions.data', 'like', '%"source":"unfinished_order_promo"%')
                    ->orWhere('actions.title', 'like', 'Promo za nedovrsenu narudzbu #%');
            });
    }

    private function decodeActionData($data): array
    {
        if (is_array($data)) {
            return $data;
        }

        if (! is_string($data) || $data === '') {
            return [];
        }

        $decoded = json_decode($data, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function extractSourceOrderId(string $title, array $data): ?int
    {
        $orderId = (int) ($data['order_id'] ?? 0);

        if ($orderId > 0) {
            return $orderId;
        }

        if (preg_match('/#(\d+)$/', $title, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    private function extractSourceOrderStatusId(array $data): ?int
    {
        $statusId = $data['source_order_status_id'] ?? $data['order_status_id'] ?? null;

        if ($statusId === null || $statusId === '') {
            return null;
        }

        return (int) $statusId;
    }

    private function sourceOrderStatuses(Collection $orderIds): Collection
    {
        if ($orderIds->isEmpty()) {
            return collect();
        }

        return DB::table('orders')
            ->whereIn('id', $orderIds->all())
            ->pluck('order_status_id', 'id');
    }

    private function sourceOrderHistoryStatuses(Collection $actions): Collection
    {
        $orderIds = $actions->pluck('source_order_id')->filter()->unique()->values();

        if ($orderIds->isEmpty()) {
            return collect();
        }

        $historyByOrderId = DB::table('order_history')
            ->whereIn('order_id', $orderIds->all())
            ->where('status', '>', 0)
            ->orderBy('order_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy('order_id');

        return $actions->mapWithKeys(function ($action) use ($historyByOrderId) {
            if ($action->source_order_id === null) {
                return [$action->id => null];
            }

            $history = $historyByOrderId->get($action->source_order_id, collect())
                ->filter(function ($row) use ($action) {
                    return Carbon::parse($row->created_at)->lte($action->created_at);
                })
                ->last();

            return [$action->id => $history ? (int) $history->status : null];
        });
    }

    private function matchesSegment(?int $statusId, string $segment): bool
    {
        if ($statusId === null) {
            return false;
        }

        $isUnfinished = $statusId === (int) config('settings.order.status.unfinished');

        if ($segment === self::SEGMENT_NON_UNFINISHED) {
            return ! $isUnfinished;
        }

        return $isUnfinished;
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

    private function resolveAverage(float $total, int $count): float
    {
        if ($count === 0) {
            return 0.0;
        }

        return round($total / $count, 2);
    }

    private function normalizeSegment(string $segment): string
    {
        return in_array($segment, [self::SEGMENT_UNFINISHED, self::SEGMENT_NON_UNFINISHED], true)
            ? $segment
            : self::SEGMENT_UNFINISHED;
    }

    private function normalizeYear($year): int
    {
        $year = (int) $year;

        return $year > 0 ? $year : (int) now()->format('Y');
    }

    private function normalizeMonth($month): int
    {
        $month = (int) $month;

        return $month >= 1 && $month <= 12 ? $month : (int) now()->format('n');
    }

    private function resolveBestDiscount(array $rows): ?array
    {
        return collect($rows)
            ->filter(fn (array $row) => $row['sent_count'] > 0)
            ->reduce(function (?array $best, array $row) {
                if ($best === null) {
                    return $row;
                }

                if ($row['conversion_rate'] !== $best['conversion_rate']) {
                    return $row['conversion_rate'] > $best['conversion_rate'] ? $row : $best;
                }

                if ($row['used_count'] !== $best['used_count']) {
                    return $row['used_count'] > $best['used_count'] ? $row : $best;
                }

                if ($row['revenue_total'] !== $best['revenue_total']) {
                    return $row['revenue_total'] > $best['revenue_total'] ? $row : $best;
                }

                return $row['discount'] < $best['discount'] ? $row : $best;
            });
    }
}
