<?php

namespace App\Mail;

use App\Models\Back\Marketing\Action;
use App\Models\Back\Orders\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UnfinishedOrderPromo extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var Order
     */
    public $order;

    /**
     * @var Action
     */
    public $promoAction;

    public function __construct(Order $order, Action $promoAction)
    {
        $this->order = $order;
        $this->promoAction = $promoAction;
    }

    public function build()
    {
        return $this->subject('Hvala na kupnji - evo 10% za sljedecu kupnju')
            ->view('emails.unfinished-order-promo');
    }
}
