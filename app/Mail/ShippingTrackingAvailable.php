<?php

namespace App\Mail;

use App\Models\Back\Orders\Order;
use App\Services\Shipping\BoxNowService;
use App\Services\Shipping\GlsTrackingService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ShippingTrackingAvailable extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var Order
     */
    public $order;

    /**
     * @var string
     */
    public $carrierLabel;

    public function __construct(Order $order)
    {
        $this->order = $order;
        $this->carrierLabel = [
            BoxNowService::CARRIER => 'Box Now',
            GlsTrackingService::CARRIER => 'GLS',
        ][$order->shipping_carrier] ?? 'dostavna služba';
    }

    public function build()
    {
        return $this->subject('Vaša pošiljka je poslana - Zuzi Shop')
            ->view('emails.shipping-tracking-available');
    }
}
