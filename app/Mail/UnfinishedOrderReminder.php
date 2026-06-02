<?php

namespace App\Mail;

use App\Models\Back\Orders\Order;
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

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function build()
    {
        return $this->subject('Podsjetnik na nedovršenu narudžbu - Zuzi Shop')
            ->view('emails.unfinished-order-reminder');
    }
}
