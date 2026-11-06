<?php

namespace App\Mail;

use App\Models\Back\Orders\Order;
use App\Services\AbandonedCartReminderService;
use App\Services\GiftVoucherService;
use App\Support\InterliberOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UnfinishedOrderReminder extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var Order
     */
    public $order;

    public $recoveryUrl;

    public $sequence;

    public $showPromotion;

    public function __construct(Order $order, ?string $recoveryUrl = null, int $sequence = 1)
    {
        $this->order = $order->loadMissing('products');
        $this->recoveryUrl = $recoveryUrl ?: app(AbandonedCartReminderService::class)->recoveryUrl($order);
        $this->sequence = $sequence === 2 ? 2 : 1;
        $this->showPromotion = now('Europe/Zagreb')->lt('2026-11-15 00:00:00')
            && ! GiftVoucherService::isGiftVoucherShipping($order->shipping_code)
            && ! $this->order->products->contains(function ($item) {
                return (int) $item->product_id === 0
                    && str_starts_with(mb_strtolower(trim((string) $item->name)), 'poklon bon');
            })
            && app(InterliberOffer::class)->isActive();
    }

    public function build()
    {
        return $this->subject($this->sequence === 2
            ? 'Tvoje knjige još čekaju - Zuzi Shop'
            : 'Podsjetnik na nedovršenu narudžbu - Zuzi Shop')
            ->view('emails.unfinished-order-reminder');
    }
}
