<?php

namespace App\Console\Commands;

use App\Mail\OrderReviewRequest;
use App\Models\Back\Orders\Order;
use App\Models\Back\Orders\OrderHistory;
use App\Models\Back\Settings\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SendReviewRequestEmails extends Command
{
    /**
     * @var string
     */
    protected $signature = 'send:review-requests';

    /**
     * @var string
     */
    protected $description = 'Send review request emails for completed orders after a configured delay.';

    public function handle(): int
    {
        if (! Schema::hasColumn('orders', 'review_request_sent_at')) {
            $message = 'Missing orders.review_request_sent_at column. Apply database/032_add_review_request_sent_at_to_orders_table.sql before sending review requests.';

            $this->error($message);
            Log::error($message);

            return self::FAILURE;
        }

        $completedStatusId = $this->resolveCompletedStatusId();
        $daysAfterCompleted = max(1, (int) config('settings.order.review_request.days_after_completed', 20));
        $maxOrderAgeDays = max($daysAfterCompleted, (int) config('settings.order.review_request.max_order_age_days', 30));
        $threshold = now()->subDays($daysAfterCompleted);
        $oldestAllowedCompletedAt = now()->subDays($maxOrderAgeDays);

        $completedOrdersSubquery = OrderHistory::query()
            ->selectRaw('order_id, MAX(created_at) as completed_at')
            ->where('status', $completedStatusId)
            ->groupBy('order_id');

        Order::query()
            ->select('orders.*')
            ->joinSub($completedOrdersSubquery, 'completed_history', function ($join) {
                $join->on('completed_history.order_id', '=', 'orders.id');
            })
            ->where('orders.order_status_id', $completedStatusId)
            ->whereNull('orders.review_request_sent_at')
            ->where('completed_history.completed_at', '<=', $threshold)
            ->where('completed_history.completed_at', '>=', $oldestAllowedCompletedAt)
            ->with(['products.real'])
            ->orderBy('orders.id')
            ->chunkById(50, function ($orders) {
                foreach ($orders as $order) {
                    if (blank($order->payment_email)) {
                        continue;
                    }

                    $mailable = new OrderReviewRequest($order);

                    if ($mailable->reviewItems->isEmpty()) {
                        Log::warning('Skipping review request email because no linked products were resolved.', [
                            'order_id' => $order->id,
                        ]);

                        continue;
                    }

                    try {
                        Mail::to($order->payment_email)->send($mailable);

                        $order->forceFill([
                            'review_request_sent_at' => now(),
                        ])->save();
                    } catch (\Throwable $e) {
                        Log::error('Failed to send review request email.', [
                            'order_id' => $order->id,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }
            }, 'orders.id', 'id');

        return self::SUCCESS;
    }

    private function resolveCompletedStatusId(): int
    {
        $configuredTitles = collect((array) config('settings.order.review_request.status_titles', ['Završeno']))
            ->map(fn ($title) => $this->normalizeStatusTitle($title))
            ->filter();

        $statuses = Settings::get('order', 'statuses');

        if ($statuses) {
            $matched = $statuses->first(function ($status) use ($configuredTitles) {
                return $configuredTitles->contains($this->normalizeStatusTitle((string) ($status->title ?? '')));
            });

            if ($matched) {
                return (int) $matched->id;
            }
        }

        return (int) config('settings.order.status.send', config('settings.order.status.paid'));
    }

    private function normalizeStatusTitle(string $title): string
    {
        $normalized = Str::lower(Str::ascii($title));

        return trim((string) preg_replace('/\s+/', ' ', $normalized));
    }
}
