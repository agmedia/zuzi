<?php

namespace App\Console\Commands;

use App\Models\AbandonedCartReminder;
use App\Services\AbandonedCartReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendAbandonedCartReminders extends Command
{
    protected $signature = 'orders:send-abandoned-cart-reminders
                            {--dry-run : Prikaži kandidate bez slanja i promjene evidencije}
                            {--limit= : Najveći broj obrađenih poruka u jednom pokretanju (zadano 25)}';

    protected $description = 'Šalje podsjetnike za nedovršene narudžbe nakon jednog sata i sljedeći dan';

    public function handle(AbandonedCartReminderService $service): int
    {
        if (! config('abandoned_cart.enabled', true)) {
            $this->warn('Podsjetnici za nedovršene narudžbe su isključeni.');

            return 0;
        }

        if (! $service->isAvailable()) {
            $this->error('Evidencija podsjetnika nije spremna. Primijenite migraciju 2026_11_06_120000_add_abandoned_cart_reminders.');

            return 1;
        }

        $limit = $this->option('limit') !== null
            ? max(1, (int) $this->option('limit'))
            : max(1, (int) config('abandoned_cart.batch_size', 25));
        $dryRun = (bool) $this->option('dry-run');
        $candidates = collect();
        $orderIds = [];

        // Take the whole batch before sending so one run cannot advance an order
        // through both reminders when an earlier scheduler run was missed.
        foreach ([2, 1] as $sequence) {
            $remaining = $limit - $candidates->count();

            if ($remaining <= 0) {
                break;
            }

            foreach ($service->candidatesForSequence($sequence, $remaining) as $order) {
                if (isset($orderIds[(int) $order->id])) {
                    continue;
                }

                $candidates->push(['order' => $order, 'sequence' => $sequence]);
                $orderIds[(int) $order->id] = true;

                if ($candidates->count() >= $limit) {
                    break;
                }
            }
        }

        $rows = [];
        $sent = 0;
        $failed = 0;

        foreach ($candidates as $candidate) {
            $order = $candidate['order'];
            $sequence = $candidate['sequence'];

            if ($dryRun) {
                $rows[] = [
                    $order->id,
                    $sequence,
                    $service->scheduledFor($order, $sequence)->format('d.m.Y. H:i'),
                    $this->maskEmail((string) $order->payment_email),
                ];

                continue;
            }

            try {
                $service->send($order, $sequence, AbandonedCartReminder::SOURCE_AUTOMATIC);
                $sent++;
                $this->info("POSLANO: narudžba #{$order->id}, {$sequence}. podsjetnik");
            } catch (\Throwable $exception) {
                $failed++;
                $this->error("NEUSPJELO: narudžba #{$order->id}, {$sequence}. podsjetnik. Provjerite dnevnik aplikacije.");
                Log::warning('Automatic abandoned cart reminder failed.', [
                    'order_id' => $order->id,
                    'sequence' => $sequence,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($dryRun) {
            $this->table(['Narudžba', 'Podsjetnik', 'Vrijeme slanja', 'E-mail'], $rows);
            $this->info('Dry-run: ' . count($rows) . ' poruka spremno; ništa nije poslano niti evidentirano.');

            return 0;
        }

        $this->info("Podsjetnici završeni: {$sent} poslano, {$failed} neuspjelo.");

        return $failed === 0 ? 0 : 1;
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', trim($email), 2), 2, '');

        return Str::substr($name, 0, 1) . '***@' . $domain;
    }
}
