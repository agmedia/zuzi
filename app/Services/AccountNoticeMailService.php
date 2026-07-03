<?php

namespace App\Services;

use App\Mail\AccountNoticeMail;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class AccountNoticeMailService
{
    public const DEFAULT_DELAY_SECONDS = 8;
    public const DEFAULT_BATCH_LIMIT = 200;
    public const MAX_BATCH_LIMIT = 5000;
    public const DELAY_OPTIONS = [5, 8, 10];
    public const TEST_EMAIL = 'tomislav@agmedia.hr';

    public function stats(array $notice): array
    {
        return [
            'total' => (int) $this->baseRecipientsQuery()->count(),
            'sent' => (int) $this->sentRecipientsQuery($notice)->count(),
            'remaining' => (int) $this->remainingRecipientsQuery($notice)->count(),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function recipientIds(array $notice, ?int $limit = null)
    {
        $query = $this->remainingRecipientsQuery($notice);

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    public function sendToUserId(int $userId, array $notice, ?string $validUntil = null): array
    {
        $user = $this->baseRecipientsQuery()
            ->with('details')
            ->where('users.id', $userId)
            ->first();

        if (! $user) {
            return [
                'sent' => false,
                'error' => 'Korisnik nije pronađen ili nije customer.',
            ];
        }

        if ($this->hasSentToUser($user, $notice)) {
            return [
                'sent' => false,
                'error' => 'Obavijest je već poslana ovom korisniku.',
            ];
        }

        if (! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            return [
                'sent' => false,
                'error' => 'Korisnik nema ispravnu e-mail adresu.',
            ];
        }

        $result = $this->send($user->email, $notice, $validUntil, $user);

        if ($result['sent']) {
            $this->logSent($user, $notice);
        }

        return $result;
    }

    public function sendTest(array $notice, ?string $validUntil = null): array
    {
        return $this->send(self::TEST_EMAIL, $notice, $validUntil);
    }

    private function send(string $email, array $notice, ?string $validUntil = null, ?User $user = null): array
    {
        try {
            Mail::to($email)->send(new AccountNoticeMail($notice, $validUntil, $user));
        } catch (\Throwable $e) {
            Log::error('Failed to send account notice email.', [
                'email' => $email,
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'sent' => false,
                'error' => 'Greška..! Slanje maila nije uspjelo.',
            ];
        }

        return [
            'sent' => true,
            'email' => $email,
        ];
    }

    public function noticeHash(array $notice): string
    {
        $keys = [
            'title',
            'intro',
            'coupon_label',
            'coupon_code',
            'discount_text',
            'outro',
            'button_text',
            'button_url',
            'valid_until',
        ];

        $payload = [];

        foreach ($keys as $key) {
            $payload[$key] = (string) ($notice[$key] ?? '');
        }

        return sha1(json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    private function baseRecipientsQuery(): Builder
    {
        return User::query()
            ->select('users.*')
            ->join('user_details', 'user_details.user_id', '=', 'users.id')
            ->where('user_details.role', 'customer')
            ->whereNotNull('users.email')
            ->where('users.email', '!=', '')
            ->orderBy('users.id');
    }

    private function remainingRecipientsQuery(array $notice): Builder
    {
        $query = $this->baseRecipientsQuery();

        if (! Schema::hasTable('account_notice_mail_logs')) {
            return $query;
        }

        $noticeHash = $this->noticeHash($notice);

        return $query->whereNotExists(function ($subquery) use ($noticeHash) {
            $subquery->select(DB::raw(1))
                ->from('account_notice_mail_logs')
                ->whereColumn('account_notice_mail_logs.user_id', 'users.id')
                ->where('account_notice_mail_logs.notice_hash', $noticeHash);
        });
    }

    private function sentRecipientsQuery(array $notice): Builder
    {
        $query = $this->baseRecipientsQuery();

        if (! Schema::hasTable('account_notice_mail_logs')) {
            return $query->whereRaw('1 = 0');
        }

        $noticeHash = $this->noticeHash($notice);

        return $query->whereExists(function ($subquery) use ($noticeHash) {
            $subquery->select(DB::raw(1))
                ->from('account_notice_mail_logs')
                ->whereColumn('account_notice_mail_logs.user_id', 'users.id')
                ->where('account_notice_mail_logs.notice_hash', $noticeHash);
        });
    }

    private function hasSentToUser(User $user, array $notice): bool
    {
        if (! Schema::hasTable('account_notice_mail_logs')) {
            return false;
        }

        return DB::table('account_notice_mail_logs')
            ->where('user_id', $user->id)
            ->where('notice_hash', $this->noticeHash($notice))
            ->exists();
    }

    private function logSent(User $user, array $notice): void
    {
        if (! Schema::hasTable('account_notice_mail_logs')) {
            return;
        }

        DB::table('account_notice_mail_logs')->insertOrIgnore([
            'user_id' => $user->id,
            'email' => $user->email,
            'notice_hash' => $this->noticeHash($notice),
            'notice_title' => (string) ($notice['title'] ?? ''),
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
