<?php

namespace App\Console\Commands;

use App\Mail\OrderReviewRequest;
use App\Models\Back\Orders\Order;
use App\Models\Back\Orders\OrderHistory;
use App\Models\Back\Settings\Settings;
use App\Services\ReviewRequestPromoService;
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
    protected $signature = 'send:review-requests
        {--min-days= : Minimum days since the order was completed}
        {--max-days= : Maximum days since the order was completed}
        {--limit= : Maximum number of emails to send in this run}
        {--sleep=0 : Seconds to wait between successfully sent emails}
        {--force-coupon : Issue review-request coupons even during the configured pause window}';

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
        $configuredDaysAfterCompleted = max(1, (int) config('settings.order.review_request.days_after_completed', 20));
        $configuredMaxOrderAgeDays = max($configuredDaysAfterCompleted, (int) config('settings.order.review_request.max_order_age_days', 30));
        $minDaysOption = $this->positiveIntegerOption('min-days');
        $maxDaysOption = $this->positiveIntegerOption('max-days');
        $limit = $this->nonNegativeIntegerOption('limit');
        $sleepSeconds = $this->nonNegativeIntegerOption('sleep') ?? 0;
        $minDays = $minDaysOption ?? $configuredDaysAfterCompleted;
        $maxDays = $maxDaysOption ?? ($minDaysOption === null ? $configuredMaxOrderAgeDays : null);

        if ($maxDays !== null && $maxDays < $minDays) {
            $this->error('The --max-days option must be greater than or equal to --min-days.');

            return self::FAILURE;
        }

        $threshold = now()->subDays($minDays);
        $oldestAllowedCompletedAt = $maxDays !== null ? now()->subDays($maxDays) : null;
        $promoService = app(ReviewRequestPromoService::class);
        $shouldIssuePromoCoupons = (bool) $this->option('force-coupon') || $promoService->shouldIssueCoupons();

        $completedOrdersSubquery = OrderHistory::query()
            ->selectRaw('order_id, MAX(created_at) as completed_at')
            ->where('status', $completedStatusId)
            ->groupBy('order_id');

        $query = Order::query()
            ->select('orders.*')
            ->joinSub($completedOrdersSubquery, 'completed_history', function ($join) {
                $join->on('completed_history.order_id', '=', 'orders.id');
            })
            ->where('orders.order_status_id', $completedStatusId)
            ->whereNull('orders.review_request_sent_at')
            ->where('completed_history.completed_at', '<=', $threshold)
            ->whereRaw("NULLIF(TRIM(orders.payment_email), '') IS NOT NULL")
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('order_products')
                    ->join('products', 'products.id', '=', 'order_products.product_id')
                    ->whereColumn('order_products.order_id', 'orders.id')
                    ->whereRaw("NULLIF(TRIM(products.url), '') IS NOT NULL");
            });

        if ($oldestAllowedCompletedAt) {
            $query->where('completed_history.completed_at', '>=', $oldestAllowedCompletedAt);
        }

        $sent = 0;

        if ($limit !== null && $limit > 0) {
            $candidates = $query
                ->with(['products.real'])
                ->orderBy('completed_history.completed_at')
                ->orderBy('orders.id')
                ->limit(max($limit * 5, $limit))
                ->get();

            foreach ($candidates as $order) {
                if ($sent >= $limit) {
                    break;
                }

                $wasSent = $this->sendReviewRequestForOrder($order, $promoService, $shouldIssuePromoCoupons);

                if ($wasSent) {
                    $sent++;

                    if ($sent < $limit && $sleepSeconds > 0) {
                        sleep($sleepSeconds);
                    }
                }
            }
        } else {
            $query
                ->with(['products.real'])
                ->orderBy('orders.id')
                ->chunkById(50, function ($orders) use ($promoService, $shouldIssuePromoCoupons, $sleepSeconds, &$sent) {
                    foreach ($orders as $order) {
                        $wasSent = $this->sendReviewRequestForOrder($order, $promoService, $shouldIssuePromoCoupons);

                        if ($wasSent) {
                            $sent++;
                        }

                        if ($wasSent && $sleepSeconds > 0) {
                            sleep($sleepSeconds);
                        }
                    }
                }, 'orders.id', 'id');
        }

        $this->info("Sent {$sent} review request email(s).");

        return self::SUCCESS;
    }

    private function sendReviewRequestForOrder(Order $order, ReviewRequestPromoService $promoService, bool $shouldIssuePromoCoupons): bool
    {
        $reviewItems = OrderReviewRequest::reviewItemsForOrder($order);

        if ($reviewItems->isEmpty()) {
            Log::warning('Skipping review request email because no linked products were resolved.', [
                'order_id' => $order->id,
            ]);

            return false;
        }

        $promoAction = null;
        $createdPromoAction = false;

        if ($shouldIssuePromoCoupons) {
            $existingPromoAction = $promoService->findForOrder($order);
            $promoAction = $existingPromoAction;

            try {
                if (! $promoAction) {
                    $promoAction = $promoService->issueForOrder($order);
                    $createdPromoAction = true;
                }
            } catch (\Throwable $e) {
                Log::error('Failed to issue review request promo coupon.', [
                    'order_id' => $order->id,
                    'payment_email' => $order->payment_email,
                    'error' => $e->getMessage(),
                ]);

                return false;
            }
        }

        try {
            Mail::to($order->payment_email)->send(new OrderReviewRequest($order, $promoAction, $reviewItems));
        } catch (\Throwable $e) {
            if ($createdPromoAction && $promoAction) {
                try {
                    $promoAction->delete();
                } catch (\Throwable $deleteException) {
                    Log::warning('Failed to rollback review request promo coupon after email failure.', [
                        'order_id' => $order->id,
                        'coupon' => $promoAction->coupon ?? null,
                        'error' => $deleteException->getMessage(),
                    ]);
                }
            }

            Log::error('Failed to send review request email.', [
                'order_id' => $order->id,
                'payment_email' => $order->payment_email,
                'coupon' => $promoAction->coupon ?? null,
                'message' => $e->getMessage(),
            ]);

            return false;
        }

        try {
            $order->forceFill([
                'review_request_sent_at' => now(),
            ])->save();
        } catch (\Throwable $e) {
            Log::warning('Review request email was sent, but marking the order as processed failed.', [
                'order_id' => $order->id,
                'coupon' => $promoAction->coupon ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }

    private function positiveIntegerOption(string $option): ?int
    {
        $value = $this->option($option);

        if ($value === null || $value === '') {
            return null;
        }

        return max(1, (int) $value);
    }

    private function nonNegativeIntegerOption(string $option): ?int
    {
        $value = $this->option($option);

        if ($value === null || $value === '') {
            return null;
        }

        return max(0, (int) $value);
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
