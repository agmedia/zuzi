<?php

namespace App\Mail;

use App\Helpers\OrderHelper;
use App\Models\Back\Orders\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class StatusPaid extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var Order
     */
    public $order;

    /**
     * @var array<string, mixed>|null
     */
    public $loyaltyMailData;


    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
        $this->loyaltyMailData = OrderHelper::resolveLoyaltyMailData($order);
    }


    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Hvala vam za narudžbu - Zuzi Shop' )
            ->view('emails.status-paid');
    }
}
