<?php

namespace App\Mail;

use App\Models\ContractWithdrawal;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContractWithdrawalAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public $withdrawal;
    public $adminUrl;

    public function __construct(ContractWithdrawal $withdrawal, string $adminUrl)
    {
        $this->withdrawal = $withdrawal;
        $this->adminUrl = $adminUrl;
    }

    public function build()
    {
        return $this
            ->subject('[Raskid ugovora] '.$this->withdrawal->order_number.' — '.$this->withdrawal->reference)
            ->replyTo($this->withdrawal->email, $this->withdrawal->full_name)
            ->view('emails.contract-withdrawals.admin');
    }
}
