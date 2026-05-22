<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;

class UnfinishedOrderPromoStatsService
{
    public const SOURCE_ADMIN = 'admin';
    public const SOURCE_OTHER = 'other';

    public const SEGMENT_ALL = 'all';
    public const SEGMENT_UNFINISHED = 'unfinished';
    public const SEGMENT_NON_UNFINISHED = 'non_unfinished';

    public function getDashboardData(array $filters = []): array
    {
        $source = $this->normalizeSource($filters['source'] ?? self::SOURCE_ADMIN);
        $segment = $this->normalizeSegment($filters['segment'] ?? self::SEGMENT_UNFINISHED);
        $year = $this->normalizeYear($filters['year'] ?? now()->year);
        $month = $this->normalizeMonth($filters['month'] ?? now()->month);

        $actions = $this->filteredPromoActions($source, $segment, $year, $month);
        $usedPromos = $this->usedPromosForActions($actions);
        $byDiscount = $this->buildDiscountBreakdown($actions, $usedPromos);

        $sentCount = $actions->count();
        $usedCount = $usedPromos->count();
        $usedActionCount = $usedPromos->pluck('action_id')->unique()->count();
        $revenueTotal = (float) $usedPromos->sum('order_total');
        $discountTotal = (float) $usedPromos->sum('discount_total');

        return [
            'summary' => [
                'sent_count' => $sentCount,
                'used_count' => $usedCount,
                'unused_count' => max($sentCount - $usedActionCount, 0),
                'conversion_rate' => $this->resolveRate($usedActionCount, $sentCount),
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
        $years = $this->promoActionYearsFromTable('product_actions');

        if (Schema::hasTable('product_action_archives')) {
            $years = $years->merge($this->promoActionYearsFromTable('product_action_archives'));
        }

        return $years
            ->merge($this->adminPromoHistoryYears())
            ->map(fn ($row) => (int) Carbon::parse($row->created_at)->format('Y'))
            ->filter()
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    private function filteredPromoActions(string $source, string $segment, int $year, int $month): Collection
    {
        $actions = $source === self::SOURCE_ADMIN
            ? $this->adminPromoActions($year, $month)
            : $this->storedPromoActions($source, $year, $month);

        if ($actions->isEmpty()) {
            return collect();
        }

        if ($source === self::SOURCE_OTHER) {
            return $actions;
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

    private function adminPromoActions(int $year, int $month): Collection
    {
        return $this->sortPromoActions(
            $this->storedPromoActions(self::SOURCE_ADMIN, $year, $month)
                ->merge($this->adminPromoActionsFromHistory($year, $month))
                ->unique(fn ($action) => $this->actionIdentity($action))
        );
    }

    private function storedPromoActions(string $source, int $year, int $month): Collection
    {
        return $this->sortPromoActions(
            $this->storedPromoActionRows($source, $year, $month)
                ->map(fn ($row) => $this->mapPromoActionRow($row))
                ->unique(fn ($action) => $this->actionIdentity($action))
        );
    }

    private function storedPromoActionRows(string $source, int $year, int $month): Collection
    {
        $rows = $this->promoActionRowsFromTable('product_actions', $source, $year, $month);

        if (Schema::hasTable('product_action_archives')) {
            $rows = $rows->merge($this->promoActionRowsFromTable('product_action_archives', $source, $year, $month));
        }

        return $rows;
    }

    private function promoActionRowsFromTable(string $table, string $source, int $year, int $month): Collection
    {
        $select = [
            'actions.id',
            'actions.title',
            'actions.type',
            'actions.discount',
            'actions.coupon',
            'actions.created_at',
            'actions.data',
        ];

        if ($table === 'product_action_archives') {
            $select[] = 'actions.product_action_id';
        }

        return $this->basePromoActionsQuery($source, $table)
            ->select($select)
            ->whereYear('actions.created_at', $year)
            ->whereMonth('actions.created_at', $month)
            ->orderBy('actions.created_at')
            ->get();
    }

    private function mapPromoActionRow($row): object
    {
        $data = $this->decodeActionData($row->data ?? null);
        $id = (int) ($row->product_action_id ?? $row->id);

        return (object) [
            'id' => $id !== 0 ? $id : (int) $row->id,
            'title' => (string) $row->title,
            'type' => (string) $row->type,
            'discount' => (int) $row->discount,
            'coupon' => $this->normalizeCoupon($row->coupon ?? null),
            'created_at' => Carbon::parse($row->created_at),
            'source_order_id' => $this->extractSourceOrderId((string) $row->title, $data),
            'source_order_status_id' => $this->extractSourceOrderStatusId($data),
        ];
    }

    private function adminPromoActionsFromHistory(int $year, int $month): Collection
    {
        return DB::table('order_history as history')
            ->select([
                'history.id',
                'history.order_id',
                'history.comment',
                'history.created_at',
            ])
            ->where('history.comment', 'like', 'Poslan promo email za nedovrsenu narudzbu.%Kod:%')
            ->whereYear('history.created_at', $year)
            ->whereMonth('history.created_at', $month)
            ->orderBy('history.created_at')
            ->get()
            ->map(function ($row) {
                $parsed = $this->parseAdminPromoHistoryComment($row->comment ?? null);

                if ($parsed === null) {
                    return null;
                }

                $orderId = (int) $row->order_id;

                return (object) [
                    'id' => -1 * (int) $row->id,
                    'title' => $this->adminPromoTitleForOrderId($orderId),
                    'type' => 'P',
                    'discount' => $parsed['discount'],
                    'coupon' => $parsed['coupon'],
                    'created_at' => Carbon::parse($row->created_at),
                    'source_order_id' => $orderId,
                    'source_order_status_id' => null,
                ];
            })
            ->filter()
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
            ->whereIn('totals.title', $this->totalTitlesForActions($actions)->all())
            ->select([
                'totals.title',
                'totals.value as discount_value',
                'orders.id as order_id',
                'orders.total as order_total',
                'orders.created_at as redeemed_at',
            ])
            ->orderBy('orders.created_at')
            ->get();

        $actionByTotalTitle = $this->actionLookupByTotalTitle($actions);

        return $rows
            ->map(function ($row) use ($actionByTotalTitle) {
                $action = $actionByTotalTitle->get((string) $row->title);

                return (object) [
                    'action_id' => (int) ($action->id ?? 0),
                    'title' => (string) ($action->title ?? $row->title),
                    'type' => (string) ($action->type ?? 'P'),
                    'coupon' => (string) ($action->coupon ?? ''),
                    'discount' => (int) ($action->discount ?? 0),
                    'order_id' => (int) ($row->order_id ?? 0),
                    'order_total' => (float) ($row->order_total ?? 0),
                    'discount_total' => abs((float) ($row->discount_value ?? 0)),
                    'redeemed_at' => Carbon::parse($row->redeemed_at),
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
            ->groupBy(fn ($action) => $this->discountKey((string) ($action->type ?? 'P'), (int) $action->discount))
            ->map(fn (Collection $group) => $group->count());

        $usedByDiscount = $usedPromos
            ->groupBy(fn ($promo) => $this->discountKey((string) ($promo->type ?? 'P'), (int) $promo->discount));

        $discountRows = collect(UnfinishedOrderPromoService::ALLOWED_DISCOUNTS)
            ->map(fn (int $discount) => ['type' => 'P', 'discount' => $discount])
            ->merge($actions->map(fn ($action) => [
                'type' => (string) ($action->type ?? 'P'),
                'discount' => (int) $action->discount,
            ]))
            ->unique(fn (array $row) => $this->discountKey($row['type'], $row['discount']))
            ->sortBy(fn (array $row) => (strtoupper($row['type']) === 'P' ? '0' : '1') . str_pad((string) $row['discount'], 8, '0', STR_PAD_LEFT))
            ->values();

        return $discountRows
            ->map(function (array $discountRow) use ($sentByDiscount, $usedByDiscount) {
                $type = $discountRow['type'];
                $discount = $discountRow['discount'];
                $key = $this->discountKey($type, $discount);
                $sentCount = (int) $sentByDiscount->get($key, 0);
                $usedRows = $usedByDiscount->get($key, collect());
                $usedCount = $usedRows->count();
                $usedActionCount = $usedRows->pluck('action_id')->unique()->count();

                return [
                    'type' => $type,
                    'discount' => $discount,
                    'discount_label' => $this->discountLabel($type, $discount),
                    'sent_count' => $sentCount,
                    'used_count' => $usedCount,
                    'conversion_rate' => $this->resolveRate($usedActionCount, $sentCount),
                    'revenue_total' => (float) $usedRows->sum('order_total'),
                    'discount_total' => (float) $usedRows->sum('discount_total'),
                ];
            })
            ->all();
    }

    private function discountKey(string $type, int $discount): string
    {
        return strtoupper($type ?: 'P') . '|' . $discount;
    }

    private function discountLabel(string $type, int $discount): string
    {
        return strtoupper($type) === 'F' ? $discount . '€' : '-' . $discount . '%';
    }

    private function basePromoActionsQuery(?string $source = null, string $table = 'product_actions')
    {
        $source = $this->normalizeSource($source ?? self::SOURCE_ADMIN);

        $query = $this->allTrackedPromoActionsQuery($table);

        if ($source === self::SOURCE_OTHER) {
            return $query
                ->where(function ($query) {
                    $query->whereNull('actions.data')
                        ->orWhere('actions.data', 'not like', '%"source":"unfinished_order_promo"%');
                })
                ->where('actions.title', 'not like', 'Promo za nedovrsenu narudzbu #%');
        }

        return $query->where(function ($query) {
            $query->where('actions.data', 'like', '%"source":"unfinished_order_promo"%')
                ->orWhere('actions.title', 'like', 'Promo za nedovrsenu narudzbu #%');
        });
    }

    private function allTrackedPromoActionsQuery(string $table = 'product_actions')
    {
        return DB::table($table . ' as actions')
            ->where('actions.group', 'total')
            ->whereNotNull('actions.coupon')
            ->where('actions.coupon', '!=', '')
            ->where('actions.title', 'not like', GiftVoucherService::ACTION_TITLE_PREFIX . '%')
            ->where('actions.coupon', 'not like', GiftVoucherService::COUPON_PREFIX . '%');
    }

    private function promoActionYearsFromTable(string $table): Collection
    {
        return $this->allTrackedPromoActionsQuery($table)
            ->select('actions.created_at')
            ->orderByDesc('actions.created_at')
            ->get();
    }

    private function adminPromoHistoryYears(): Collection
    {
        return DB::table('order_history as history')
            ->select('history.created_at')
            ->where('history.comment', 'like', 'Poslan promo email za nedovrsenu narudzbu.%Kod:%')
            ->orderByDesc('history.created_at')
            ->get();
    }

    private function sortPromoActions(Collection $actions): Collection
    {
        return $actions
            ->sortBy(function ($action) {
                return $action->created_at->format('Y-m-d H:i:s') . '|' . str_pad((string) abs((int) $action->id), 12, '0', STR_PAD_LEFT);
            })
            ->values();
    }

    private function actionIdentity($action): string
    {
        $coupon = $this->normalizeCoupon($action->coupon ?? null);

        if ($coupon !== '') {
            return 'coupon:' . $coupon;
        }

        return 'title:' . (string) ($action->title ?? '') . '|' . $action->created_at->format('Y-m-d H:i:s');
    }

    private function parseAdminPromoHistoryComment(?string $comment): ?array
    {
        $comment = (string) $comment;

        if ($comment === '') {
            return null;
        }

        if (preg_match('/Kod:\s*([A-Z0-9_-]+)/iu', $comment, $couponMatches) !== 1) {
            return null;
        }

        $coupon = $this->normalizeCoupon($couponMatches[1]);

        if ($coupon === '') {
            return null;
        }

        $discount = $this->discountFromCoupon($coupon);

        if (preg_match('/Popust:\s*-?\s*(\d+)\s*%/iu', $comment, $discountMatches) === 1) {
            $discount = (int) $discountMatches[1];
        }

        return [
            'coupon' => $coupon,
            'discount' => $discount,
        ];
    }

    private function discountFromCoupon(string $coupon): int
    {
        if (preg_match('/^HVALA(\d+)/i', $coupon, $matches) === 1) {
            return (int) $matches[1];
        }

        return 0;
    }

    private function adminPromoTitleForOrderId(int $orderId): string
    {
        return 'Promo za nedovrsenu narudzbu #' . $orderId;
    }

    private function totalTitlesForActions(Collection $actions): Collection
    {
        return $actions
            ->flatMap(function ($action) {
                return array_filter([
                    (string) $action->title,
                    $this->couponTotalTitle((string) ($action->coupon ?? '')),
                ]);
            })
            ->filter()
            ->unique()
            ->values();
    }

    private function actionLookupByTotalTitle(Collection $actions): Collection
    {
        return $actions
            ->flatMap(function ($action) {
                $titles = collect([(string) $action->title]);
                $couponTitle = $this->couponTotalTitle((string) ($action->coupon ?? ''));

                if ($couponTitle !== '') {
                    $titles->push($couponTitle);
                }

                return $titles->mapWithKeys(fn (string $title) => [$title => $action]);
            });
    }

    private function couponTotalTitle(string $coupon): string
    {
        $coupon = $this->normalizeCoupon($coupon);

        return $coupon !== '' ? 'Kupon ' . $coupon : '';
    }

    private function normalizeCoupon(?string $coupon = null): string
    {
        return strtoupper(trim((string) $coupon));
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

        if ($segment === self::SEGMENT_ALL) {
            return true;
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

    private function normalizeSource(string $source): string
    {
        return in_array($source, [self::SOURCE_ADMIN, self::SOURCE_OTHER], true)
            ? $source
            : self::SOURCE_ADMIN;
    }

    private function normalizeSegment(string $segment): string
    {
        return in_array($segment, [self::SEGMENT_ALL, self::SEGMENT_UNFINISHED, self::SEGMENT_NON_UNFINISHED], true)
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
