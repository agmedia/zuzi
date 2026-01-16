<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class WishlistArrived extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var array
     */
    private $product;

    /**
     * Create a new message instance.
     *
     * @param $contact
     */
    public function __construct($product)
    {
        $this->product = $product;
    }


    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.wishlist-arrived')->with(['product' => $this->product]);
    }
}
