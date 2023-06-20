<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class ContactFormMessage extends Mailable
{

    use Queueable, SerializesModels;

    /**
     * @var array
     */
    private $contact;

    /**
     * @var mixed
     */
    public $from;


    /**
     * Create a new message instance.
     *
     * @param $contact
     */
    public function __construct($contact)
    {
        $this->contact = $contact;
        $this->from    = $contact['email'];
    }


    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.contact-form')->with(['contact' => $this->contact]);
    }
}
