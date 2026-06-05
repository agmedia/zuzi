<?php

namespace App\Console\Commands;

use App\Models\Back\Orders\Order;
use App\Services\Shipping\BoxNowService;
use App\Services\Shipping\GlsTrackingService;
use App\Services\Shipping\OrderTrackingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SyncShipmentTracking extends Command
{
    protected $signature = 'sync:shipment-tracking
        {--limit=50 : Maximum number of orders to refresh}
        {--stale-minutes=30 : Refresh orders older than this many minutes}';

    protected $description = 'Refresh shipment tracking statuses for GLS and Box Now orders.';

    public function handle(OrderTrackingService $trackingService): int
    {
        if (! Schema::hasColumn('orders', 'shipping_tracking_status_code')) {
            $this->info('Shipment tracking columns are not available yet.');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $staleMinutes = max(1, (int) $this->option('stale-minutes'));
        $orders = Order::query()
            ->where(function ($query) {
                $query->whereIn('shipping_carrier', [GlsTrackingService::CARRIER, BoxNowService::CARRIER])
                    ->orWhere('shipping_method', 'like', '%GLS%')
                    ->orWhere('shipping_method', 'like', '%BoxNow%')
                    ->orWhere('shipping_method', 'like', '%Box Now%')
                    ->orWhere('shipping_code', 'like', '%gls%')
                    ->orWhere('shipping_code', 'like', '%boxnow%');
            })
            ->where(function ($query) {
                $query->where(function ($trackingQuery) {
                    $trackingQuery->whereNotNull('tracking_code')
                        ->where('tracking_code', '<>', '');
                })->orWhere(function ($trackingQuery) {
                    $trackingQuery->whereNotNull('shipping_parcel_id')
                        ->where('shipping_parcel_id', '<>', '');
                });
            })
            ->where(function ($query) {
                $query->whereNull('shipping_tracking_status_code')
                    ->orWhereNotIn('shipping_tracking_status_code', ['5', '92', 'delivered', 'returned', 'expired', 'expired-return', 'canceled', 'cancelled']);
            })
            ->where(function ($query) use ($staleMinutes) {
                $query->whereNull('shipping_tracking_updated_at')
                    ->orWhere('shipping_tracking_updated_at', '<=', now()->subMinutes($staleMinutes));
            })
            ->latest('created_at')
            ->limit($limit)
            ->get();

        $updated = 0;
        $failed = 0;

        foreach ($orders as $order) {
            try {
                $result = $trackingService->refresh($order);

                if ($result['updated']) {
                    $updated++;
                }
            } catch (\Throwable $e) {
                $failed++;

                Log::warning('Scheduled shipment tracking refresh failed.', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Shipment tracking refreshed. Updated: {$updated}. Failed: {$failed}.");

        return self::SUCCESS;
    }
}
