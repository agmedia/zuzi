<?php

namespace App\Services;

use App\Mail\ContractWithdrawalAdminMail;
use App\Mail\ContractWithdrawalReceiptMail;
use App\Models\ContractWithdrawal;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContractWithdrawalNotificationService
{
    private $settings;

    public function __construct(ContractWithdrawalSettingsService $settings)
    {
        $this->settings = $settings;
    }

    public function send(ContractWithdrawal $withdrawal): void
    {
        $errors = [];

        try {
            $this->sendConsumerReceipt($withdrawal);
        } catch (\Throwable $exception) {
            $errors[] = 'Korisnik: '.$exception->getMessage();
            Log::error('Contract withdrawal consumer receipt failed', [
                'withdrawal_id' => $withdrawal->id,
                'exception' => $exception,
            ]);
        }

        try {
            $this->sendAdminNotification($withdrawal);
        } catch (\Throwable $exception) {
            $errors[] = 'Administrator: '.$exception->getMessage();
            Log::error('Contract withdrawal admin notification failed', [
                'withdrawal_id' => $withdrawal->id,
                'exception' => $exception,
            ]);
        }

        $withdrawal->forceFill([
            'notification_error' => $errors ? implode("\n", $errors) : null,
        ])->save();
    }

    public function sendConsumerReceipt(ContractWithdrawal $withdrawal): void
    {
        $settings = $this->settings->get();

        Mail::to($withdrawal->email)->send(new ContractWithdrawalReceiptMail(
            $withdrawal,
            $settings,
            $this->settings->returnCostText($settings)
        ));

        $withdrawal->forceFill(['consumer_notified_at' => now()])->save();
    }

    public function sendAdminNotification(ContractWithdrawal $withdrawal): void
    {
        $settings = $this->settings->get();
        $adminEmail = trim((string) ($settings['admin_email'] ?? ''));

        if (! filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Nije postavljena ispravna administratorska e-mail adresa.');
        }

        Mail::to($adminEmail)->send(new ContractWithdrawalAdminMail(
            $withdrawal,
            route('contract-withdrawals.show', $withdrawal)
        ));

        $withdrawal->forceFill(['admin_notified_at' => now()])->save();
    }
}
