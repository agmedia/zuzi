<?php

namespace App\Console\Commands;

use App\Mail\OrderReviewRequest;
use App\Models\Back\Orders\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendReviewRequestTestEmail extends Command
{
    /**
     * @var string
     */
    protected $signature = 'send:review-request-test {email : Address that should receive the test email} {orderId? : Optional order ID to use as the email source}';

    /**
     * @var string
     */
    protected $description = 'Send one review-request test email without marking the order as already processed.';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $orderId = $this->argument('orderId');

        $order = $orderId
            ? Order::query()->with(['products.real'])->find($orderId)
            : $this->resolveFallbackOrder();

        if (! $order) {
            $this->error('No suitable order was found for a review-request test email.');

            return self::FAILURE;
        }

        $mailable = new OrderReviewRequest($order);

        if ($mailable->reviewItems->isEmpty()) {
            $this->error('Selected order has no reviewable products with public product URLs.');

            return self::FAILURE;
        }

        Mail::to($email)->send($mailable);

        $this->info(sprintf(
            'Sent review-request test email for order #%d to %s.',
            $order->id,
            $email
        ));

        return self::SUCCESS;
    }

    private function resolveFallbackOrder(): ?Order
    {
        return Order::query()
            ->with(['products.real'])
            ->whereNotNull('payment_email')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->first(function (Order $order) {
                return (new OrderReviewRequest($order))->reviewItems->isNotEmpty();
            });
    }
}
