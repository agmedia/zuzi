<?php

namespace App\Mail;

use App\Models\ContractWithdrawal;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContractWithdrawalReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public $withdrawal;
    public $withdrawalSettings;
    public $returnCostText;

    public function __construct(
        ContractWithdrawal $withdrawal,
        array $withdrawalSettings,
        string $returnCostText
    ) {
        $this->withdrawal = $withdrawal;
        $this->withdrawalSettings = $withdrawalSettings;
        $this->returnCostText = $returnCostText;
    }

    public function build()
    {
        return $this
            ->subject('Potvrda primitka raskida ugovora '.$this->withdrawal->reference)
            ->view('emails.contract-withdrawals.receipt');
    }
}
