<?php

namespace App\Mail;

use App\Models\Back\Marketing\Action;
use App\Models\Back\Orders\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class OrderReviewRequest extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var Order
     */
    public $order;

    /**
     * @var Action|null
     */
    public $promoAction;

    /**
     * @var Collection<int, array<string, mixed>>
     */
    public $reviewItems;

    public function __construct(Order $order, ?Action $promoAction = null, ?Collection $reviewItems = null)
    {
        $this->order = $order;
        $this->promoAction = $promoAction;
        $this->reviewItems = $reviewItems ?: self::reviewItemsForOrder($order);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function reviewItemsForOrder(Order $order): Collection
    {
        return $order->products
            ->map(function ($orderProduct) {
                $product = $orderProduct->real;
                $productUrl = $product && filled($product->url) ? url($product->url) : null;

                return [
                    'name' => $product->name ?? $orderProduct->name,
                    'product_url' => $productUrl,
                    'review_url' => $productUrl ? $productUrl . '#review-form' : null,
                ];
            })
            ->filter(fn ($item) => filled($item['product_url']) && filled($item['review_url']))
            ->values();
    }

    public function build()
    {
        return $this->subject(__('front/cart.review_request_subject') . $this->order->id)
            ->view('emails.order-review-request');
    }
}
