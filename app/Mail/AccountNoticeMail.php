<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountNoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var array<string, mixed>
     */
    public $notice;

    /**
     * @var string|null
     */
    public $validUntil;

    /**
     * @var User|null
     */
    public $user;

    public function __construct(array $notice, ?string $validUntil = null, ?User $user = null)
    {
        $this->notice = $notice;
        $this->validUntil = $validUntil;
        $this->user = $user;
    }

    public function build()
    {
        $subject = trim((string) ($this->notice['title'] ?? '')) ?: 'Obavijest iz Zuzi Shop računa';

        return $this->subject($subject)
            ->view('emails.account-notice');
    }
}
