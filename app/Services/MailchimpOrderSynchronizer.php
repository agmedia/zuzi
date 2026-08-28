<?php

namespace App\Services;

use App\Models\Back\Orders\Order;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class MailchimpOrderSynchronizer
{
    /** @var MailchimpEcommerceService */
    private $mailchimp;

    /** @var bool|null */
    private $columnsAvailable;

    public function __construct(MailchimpEcommerceService $mailchimp)
    {
        $this->mailchimp = $mailchimp;
    }

    public function isAvailable(): bool
    {
        return $this->columnsAreAvailable() && $this->mailchimp->isConfigured();
    }

    /** @return array{ok:bool,error:?string,stop?:bool} */
    public function ensureStore(): array
    {
        if (! $this->columnsAreAvailable()) {
            return [
                'ok' => false,
                'error' => 'Mailchimp e-commerce migracija nije dostupna.',
                'stop' => true,
            ];
        }

        if (! $this->mailchimp->isConfigured()) {
            return [
                'ok' => false,
                'error' => 'Mailchimp e-commerce nije konfiguriran.',
                'stop' => true,
            ];
        }

        try {
            $result = $this->mailchimp->ensureStore();

            if (! $result['ok']) {
                Log::warning('Mailchimp e-commerce store check failed.', [
                    'stop' => ! empty($result['stop']),
                    'error' => $this->sanitizeError($result['error'] ?? null),
                ]);
            }

            return $result;
        } catch (Throwable $e) {
            Log::warning('Mailchimp e-commerce store check could not run.', [
                'exception' => get_class($e),
            ]);

            return [
                'ok' => false,
                'error' => 'Mailchimp e-commerce trenutno nije dostupan.',
                'stop' => true,
            ];
        }
    }

    /**
     * Freeze attribution and expose a successfully finalized checkout to the
     * asynchronous synchronizer. Repeated success-page visits are harmless.
     */
    public function markCheckoutProcessed(int $orderId): bool
    {
        if ($orderId < 1 || ! $this->columnsAreAvailable()) {
            return false;
        }

        try {
            $processed = DB::table('orders')
                ->where('id', $orderId)
                ->whereIn('order_status_id', $this->finalizedCheckoutStatusIds())
                ->whereNull('checkout_processed_at')
                ->update(['checkout_processed_at' => now()]);

            if ($processed === 1) {
                $this->markForSync($orderId);
            }

            return $processed === 1;
        } catch (Throwable $e) {
            Log::warning('Mailchimp checkout marker could not be stored.', [
                'order_id' => $orderId,
                'exception' => get_class($e),
            ]);

            return false;
        }
    }

    /**
     * Sync one finalized order. Mailchimp applies native contact-based
     * attribution when no explicit campaign ID is available. All exceptions
     * are contained so Mailchimp can never change the checkout outcome.
     *
     * @return array{ok:bool,skipped:bool,error:?string,stop:bool}
     */
    public function syncOrderId(int $orderId): array
    {
        if ($orderId < 1 || ! $this->columnsAreAvailable()) {
            return $this->result(false, true, 'Mailchimp e-commerce migracija nije dostupna.', true);
        }

        if (! $this->mailchimp->isConfigured()) {
            return $this->result(false, true, 'Mailchimp e-commerce nije konfiguriran.', true);
        }

        try {
            $order = Order::query()->find($orderId);

            if (! $order || ! $order->checkout_processed_at) {
                return $this->result(true, true, null, false);
            }

            $financialStatus = $this->mailchimp->financialStatusForStatusId((int) $order->order_status_id);
            if ($financialStatus === null) {
                return $this->result(true, true, null, false);
            }

            if ($order->mailchimp_ecommerce_synced_at
                && (string) $order->mailchimp_ecommerce_financial_status === $financialStatus) {
                return $this->result(true, true, null, false);
            }

            $attemptedAt = now();
            DB::table('orders')->where('id', $order->id)->update([
                'mailchimp_ecommerce_last_attempt_at' => $attemptedAt,
            ]);

            $response = $this->mailchimp->syncOrder($order);

            if ($response['ok']) {
                DB::table('orders')->where('id', $order->id)->update([
                    'mailchimp_ecommerce_synced_at' => now(),
                    'mailchimp_ecommerce_financial_status' => $response['financial_status'] ?? $financialStatus,
                    'mailchimp_ecommerce_last_attempt_at' => $attemptedAt,
                    'mailchimp_ecommerce_last_error' => null,
                ]);

                return $this->result(true, false, null, false);
            }

            $error = $this->sanitizeError($response['error'] ?? null);
            DB::table('orders')->where('id', $order->id)->update([
                'mailchimp_ecommerce_synced_at' => null,
                'mailchimp_ecommerce_last_attempt_at' => $attemptedAt,
                'mailchimp_ecommerce_last_error' => $error,
            ]);

            Log::warning('Mailchimp e-commerce order sync failed.', [
                'order_id' => $order->id,
                'stop' => ! empty($response['stop']),
                'error' => $error,
            ]);

            return $this->result(false, false, $error, ! empty($response['stop']));
        } catch (Throwable $e) {
            Log::warning('Mailchimp e-commerce order sync could not run.', [
                'order_id' => $orderId,
                'exception' => get_class($e),
            ]);

            return $this->result(false, false, 'Mailchimp e-commerce trenutno nije dostupan.', true);
        }
    }

    /**
     * Mark finalized orders as pending without changing business columns or
     * their updated_at timestamps.
     *
     * @param int|array<int|string> $orderIds
     */
    public function markForSync($orderIds): void
    {
        if (! $this->columnsAreAvailable()) {
            return;
        }

        $ids = array_values(array_unique(array_filter(array_map(
            'intval',
            is_array($orderIds) ? $orderIds : [$orderIds]
        ))));

        if ($ids === []) {
            return;
        }

        try {
            DB::table('orders')
                ->whereIn('id', $ids)
                ->where('checkout_processed_at', '>=', $this->syncFrom())
                ->update([
                    'mailchimp_ecommerce_synced_at' => null,
                    'mailchimp_ecommerce_last_attempt_at' => null,
                    'mailchimp_ecommerce_last_error' => null,
                ]);
        } catch (Throwable $e) {
            Log::warning('Mailchimp e-commerce order could not be queued.', [
                'order_count' => count($ids),
                'exception' => get_class($e),
            ]);
        }
    }

    /**
     * Return new finalized orders or synced orders whose financial status
     * changed. The rollout cutoff prevents an accidental historical backfill.
     */
    public function pendingOrders(int $limit = 5): Collection
    {
        if (! $this->isAvailable()) {
            return new Collection();
        }

        $limit = max(1, min($limit, 25));
        $statusMap = $this->statusMap();
        $statusIds = array_keys($statusMap);
        $caseParts = [];
        $bindings = [];

        foreach ($statusMap as $statusId => $financialStatus) {
            $caseParts[] = 'WHEN order_status_id = ? THEN ?';
            $bindings[] = $statusId;
            $bindings[] = $financialStatus;
        }

        $statusCase = 'CASE ' . implode(' ', $caseParts) . " ELSE '' END";

        return Order::query()
            ->whereNotNull('checkout_processed_at')
            ->where('checkout_processed_at', '>=', $this->syncFrom())
            ->whereIn('order_status_id', $statusIds)
            ->where(function ($query) {
                $query->whereNull('mailchimp_ecommerce_last_attempt_at')
                    ->orWhere('mailchimp_ecommerce_last_attempt_at', '<=', now()->subMinutes(15));
            })
            ->where(function ($query) use ($statusCase, $bindings) {
                $query->whereNull('mailchimp_ecommerce_synced_at')
                    ->orWhereRaw(
                        "COALESCE(mailchimp_ecommerce_financial_status, '') <> {$statusCase}",
                        $bindings
                    );
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /** @return array{orders:int,revenue:float,synced:int,waiting:int,failed:int} */
    public function dailySummary(?Carbon $date = null): array
    {
        $date = $date ?: now();
        $excludedStatusIds = array_values(array_unique(array_merge(
            Order::dashboardSalesExcludedStatusIds(),
            [(int) config('settings.order.status.refunded', 6)]
        )));
        $base = Order::query()
            ->whereDate('created_at', $date->toDateString())
            ->whereNotIn('order_status_id', $excludedStatusIds);

        $orders = (clone $base)->count();
        $revenue = (float) (clone $base)->sum('total');

        if (! $this->columnsAreAvailable()) {
            return [
                'orders' => $orders,
                'revenue' => $revenue,
                'synced' => 0,
                'waiting' => $orders,
                'failed' => 0,
            ];
        }

        return [
            'orders' => $orders,
            'revenue' => $revenue,
            'synced' => (clone $base)->whereNotNull('mailchimp_ecommerce_synced_at')->count(),
            'waiting' => (clone $base)
                ->whereNull('mailchimp_ecommerce_synced_at')
                ->whereNull('mailchimp_ecommerce_last_error')
                ->count(),
            'failed' => (clone $base)->whereNotNull('mailchimp_ecommerce_last_error')->count(),
        ];
    }

    private function syncFrom(): Carbon
    {
        $fallback = '2026-08-28 00:00:00';
        $configured = trim((string) config('services.mailchimp.ecommerce_sync_from', $fallback));

        try {
            return Carbon::parse(
                $configured !== '' ? $configured : $fallback,
                config('app.timezone')
            );
        } catch (Throwable $e) {
            return Carbon::parse($fallback, config('app.timezone'));
        }
    }

    /** @return array<int,string> */
    private function statusMap(): array
    {
        $map = [];

        foreach ([
            config('settings.order.status.new', 1),
            config('settings.order.status.awaiting_payment', 2),
            config('settings.order.status.paid', 3),
            config('settings.order.status.send', 4),
            config('settings.order.status.canceled', 5),
            config('settings.order.status.refunded', 6),
            config('settings.order.status.declined', 7),
            config('settings.order.status.completed', 9),
            config('settings.order.status.ready', 10),
            config('settings.order.status.processing', 11),
        ] as $statusId) {
            $financialStatus = $this->mailchimp->financialStatusForStatusId((int) $statusId);

            if ($financialStatus !== null) {
                $map[(int) $statusId] = $financialStatus;
            }
        }

        return $map;
    }

    /** @return array<int> */
    private function finalizedCheckoutStatusIds(): array
    {
        return array_values(array_unique(array_map('intval', [
            config('settings.order.status.new', 1),
            config('settings.order.status.paid', 3),
            config('settings.order.status.send', 4),
        ])));
    }

    private function sanitizeError($error): string
    {
        $error = trim(strip_tags((string) $error));
        $error = (string) preg_replace(
            '/[a-z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+/i',
            '[redacted-email]',
            $error
        );

        return Str::limit($error !== '' ? $error : 'Neočekivana Mailchimp greška.', 1000, '…');
    }

    /** @return array{ok:bool,skipped:bool,error:?string,stop:bool} */
    private function result(bool $ok, bool $skipped, ?string $error, bool $stop): array
    {
        return compact('ok', 'skipped', 'error', 'stop');
    }

    private function columnsAreAvailable(): bool
    {
        if ($this->columnsAvailable !== null) {
            return $this->columnsAvailable;
        }

        try {
            $required = [
                'checkout_processed_at',
                'mailchimp_campaign_id',
                'mailchimp_ecommerce_synced_at',
                'mailchimp_ecommerce_financial_status',
                'mailchimp_ecommerce_last_attempt_at',
                'mailchimp_ecommerce_last_error',
            ];

            $this->columnsAvailable = Schema::hasTable('orders');

            foreach ($required as $column) {
                $this->columnsAvailable = $this->columnsAvailable
                    && Schema::hasColumn('orders', $column);
            }
        } catch (Throwable $e) {
            $this->columnsAvailable = false;
        }

        return $this->columnsAvailable;
    }
}
