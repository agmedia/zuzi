<?php

namespace App\Console\Commands;

use App\Services\MailchimpOrderSynchronizer;
use Illuminate\Console\Command;

class SyncMailchimpEcommerceOrders extends Command
{
    protected $signature = 'mailchimp:sync-ecommerce-orders
        {--limit=5 : Maximum number of orders per run}
        {--max-seconds=50 : Stop before the next scheduler minute}
        {--today : Prikaži današnji promet i Mailchimp sync status}';

    protected $description = 'Sigurno sinkronizira završene narudžbe u Mailchimp e-commerce.';

    public function handle(MailchimpOrderSynchronizer $synchronizer): int
    {
        if (! $synchronizer->isAvailable()) {
            $this->printTodaySummary($synchronizer);
            $this->warn('Mailchimp e-commerce nije konfiguriran ili migracija još nije pokrenuta.');

            return self::FAILURE;
        }

        $store = $synchronizer->ensureStore();
        if (! $store['ok']) {
            $this->printTodaySummary($synchronizer);
            $this->error($store['error'] ?? 'Mailchimp store nije moguće pripremiti.');

            return self::FAILURE;
        }

        $limit = max(1, min((int) $this->option('limit'), 25));
        $maxSeconds = max(5, min((int) $this->option('max-seconds'), 55));
        $startedAt = microtime(true);
        $orders = $synchronizer->pendingOrders($limit);
        $synced = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($orders as $order) {
            if ((microtime(true) - $startedAt) >= $maxSeconds) {
                break;
            }

            $result = $synchronizer->syncOrderId((int) $order->id);

            if ($result['skipped']) {
                $skipped++;
            } elseif ($result['ok']) {
                $synced++;
            } else {
                $failed++;
            }

            if ($result['stop']) {
                break;
            }
        }

        $this->info(sprintf(
            'Mailchimp e-commerce sync završen. Sinkronizirano: %d, neuspjelo: %d, preskočeno: %d.',
            $synced,
            $failed,
            $skipped
        ));

        $this->printTodaySummary($synchronizer);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function printTodaySummary(MailchimpOrderSynchronizer $synchronizer): void
    {
        if (! $this->option('today')) {
            return;
        }

        $summary = $synchronizer->dailySummary();
        $this->line(sprintf(
            'Danas: %d narudžbi, %s EUR. Mailchimp: %d sinkronizirano, %d čeka, %d s greškom.',
            $summary['orders'],
            number_format($summary['revenue'], 2, ',', '.'),
            $summary['synced'],
            $summary['waiting'],
            $summary['failed']
        ));
    }
}
