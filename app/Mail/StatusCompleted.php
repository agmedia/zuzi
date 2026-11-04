<?php

namespace App\Mail;

use App\Models\Back\Orders\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StatusCompleted extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var Order
     */
    public $order;


    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }


    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $isPersonalPickup = $this->order->shipping_code === 'pickup';

        return $this->subject($isPersonalPickup
            ? 'Vaša narudžba je završena - Zuzi Shop'
            : 'Vasa knjiga je poslana - Zuzi Shop')
            ->view('emails.status-completed')
            ->with('isPersonalPickup', $isPersonalPickup);
    }
}
