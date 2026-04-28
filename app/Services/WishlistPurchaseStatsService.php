<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WishlistPurchaseStatsService
{
    public function getForProduct(int $productId): array
    {
        return $this->getForProductIds([$productId])[$productId] ?? $this->emptyProductStats();
    }


    public function getForProductIds(iterable $productIds): array
    {
        $productIds = collect($productIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return [];
        }

        $stats = $productIds->mapWithKeys(function (int $productId) {
            return [$productId => $this->emptyProductStats()];
        })->all();

        $sentRows = $this->sentEntriesQuery($productIds)
            ->select('w.product_id')
            ->selectRaw('COUNT(*) as sent_entries_count')
            ->groupBy('w.product_id')
            ->get();

        $convertedEntryRows = $this->sentEntriesQuery($productIds)
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('orders as o')
                    ->join('order_products as op', function ($join) {
                        $join->on('op.order_id', '=', 'o.id')
                            ->whereColumn('op.product_id', 'w.product_id');
                    })
                    ->whereNotIn('o.order_status_id', $this->excludedOrderStatuses())
                    ->whereColumn('o.created_at', '>=', 'w.sent_at')
                    ->where(function ($emailQuery) {
                        $emailQuery->whereRaw('LOWER(o.payment_email) = LOWER(w.email)')
                            ->orWhereRaw('LOWER(o.shipping_email) = LOWER(w.email)');
                    });
            })
            ->select('w.product_id')
            ->selectRaw('COUNT(DISTINCT w.id) as converted_entries_count')
            ->groupBy('w.product_id')
            ->get();

        $matchedOrderRows = DB::table('order_products as op')
            ->join('orders as o', 'o.id', '=', 'op.order_id')
            ->whereIn('op.product_id', $productIds)
            ->whereNotIn('o.order_status_id', $this->excludedOrderStatuses())
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('wishlist as w')
                    ->where('w.sent', 1)
                    ->whereNotNull('w.sent_at')
                    ->whereColumn('w.product_id', 'op.product_id')
                    ->whereColumn('o.created_at', '>=', 'w.sent_at')
                    ->where(function ($emailQuery) {
                        $emailQuery->whereRaw('LOWER(o.payment_email) = LOWER(w.email)')
                            ->orWhereRaw('LOWER(o.shipping_email) = LOWER(w.email)');
                    });
            })
            ->select('op.product_id')
            ->selectRaw('COUNT(DISTINCT o.id) as matched_orders_count')
            ->selectRaw('COALESCE(SUM(op.quantity), 0) as matched_units_count')
            ->selectRaw('COALESCE(SUM(op.total), 0) as matched_revenue_total')
            ->groupBy('op.product_id')
            ->get();

        foreach ($sentRows as $row) {
            $productId = (int) $row->product_id;
            $stats[$productId]['sent_entries_count'] = (int) $row->sent_entries_count;
        }

        foreach ($convertedEntryRows as $row) {
            $productId = (int) $row->product_id;
            $stats[$productId]['converted_entries_count'] = (int) $row->converted_entries_count;
        }

        foreach ($matchedOrderRows as $row) {
            $productId = (int) $row->product_id;
            $stats[$productId]['matched_orders_count'] = (int) $row->matched_orders_count;
            $stats[$productId]['matched_units_count'] = (int) $row->matched_units_count;
            $stats[$productId]['matched_revenue_total'] = round((float) $row->matched_revenue_total, 2);
        }

        foreach ($stats as $productId => $row) {
            $stats[$productId]['conversion_rate'] = $this->resolveRate(
                (int) $row['converted_entries_count'],
                (int) $row['sent_entries_count']
            );
        }

        return $stats;
    }


    public function getForWishlistIds(iterable $wishlistIds): array
    {
        $wishlistIds = collect($wishlistIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($wishlistIds->isEmpty()) {
            return [];
        }

        $stats = $wishlistIds->mapWithKeys(function (int $wishlistId) {
            return [$wishlistId => [
                'matched_orders_count' => 0,
                'first_order_at' => null,
            ]];
        })->all();

        $rows = DB::table('wishlist as w')
            ->join('order_products as op', function ($join) {
                $join->on('op.product_id', '=', 'w.product_id');
            })
            ->join('orders as o', 'o.id', '=', 'op.order_id')
            ->whereIn('w.id', $wishlistIds)
            ->where('w.sent', 1)
            ->whereNotNull('w.sent_at')
            ->whereNotIn('o.order_status_id', $this->excludedOrderStatuses())
            ->whereColumn('o.created_at', '>=', 'w.sent_at')
            ->where(function ($emailQuery) {
                $emailQuery->whereRaw('LOWER(o.payment_email) = LOWER(w.email)')
                    ->orWhereRaw('LOWER(o.shipping_email) = LOWER(w.email)');
            })
            ->select('w.id as wishlist_id')
            ->selectRaw('COUNT(DISTINCT o.id) as matched_orders_count')
            ->selectRaw('MIN(o.created_at) as first_order_at')
            ->groupBy('w.id')
            ->get();

        foreach ($rows as $row) {
            $wishlistId = (int) $row->wishlist_id;
            $stats[$wishlistId] = [
                'matched_orders_count' => (int) $row->matched_orders_count,
                'first_order_at' => $row->first_order_at,
            ];
        }

        return $stats;
    }


    private function sentEntriesQuery(Collection $productIds)
    {
        return DB::table('wishlist as w')
            ->where('w.sent', 1)
            ->whereNotNull('w.sent_at')
            ->whereIn('w.product_id', $productIds);
    }


    private function excludedOrderStatuses(): array
    {
        return array_values(array_filter([
            config('settings.order.status.canceled'),
            config('settings.order.status.declined'),
            config('settings.order.status.unfinished'),
        ], static fn ($status) => $status !== null));
    }


    private function emptyProductStats(): array
    {
        return [
            'sent_entries_count' => 0,
            'converted_entries_count' => 0,
            'matched_orders_count' => 0,
            'matched_units_count' => 0,
            'matched_revenue_total' => 0.0,
            'conversion_rate' => 0.0,
        ];
    }


    private function resolveRate(int $convertedEntriesCount, int $sentEntriesCount): float
    {
        if ($sentEntriesCount === 0) {
            return 0.0;
        }

        return round(($convertedEntriesCount / $sentEntriesCount) * 100, 1);
    }
}
