<?php

namespace App\Mail;

use App\Models\GiftVoucher;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GiftVoucherDelivered extends Mailable
{
    use Queueable, SerializesModels;

    public $giftVoucher;

    public function __construct(GiftVoucher $giftVoucher)
    {
        $this->giftVoucher = $giftVoucher;
    }

    public function build()
    {
        return $this
            ->subject('Za vas je stigao Zuzi poklon bon')
            ->view('emails.gift-voucher-delivered');
    }
}
