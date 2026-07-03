<?php

namespace App\Http\Controllers\Back\Marketing;

use App\Http\Controllers\Controller;
use App\Services\AccountNoticeMailService;
use App\Services\AccountNoticeService;
use Illuminate\Http\Request;

class AccountNoticeController extends Controller
{
    public function edit(AccountNoticeService $account_notice, AccountNoticeMailService $account_notice_mail)
    {
        $notice = $account_notice->get();
        $mailStats = $account_notice_mail->stats($notice);
        $mailDelayOptions = AccountNoticeMailService::DELAY_OPTIONS;
        $mailDefaultDelay = AccountNoticeMailService::DEFAULT_DELAY_SECONDS;
        $mailDefaultLimit = AccountNoticeMailService::DEFAULT_BATCH_LIMIT;
        $mailTestEmail = AccountNoticeMailService::TEST_EMAIL;

        return view('back.marketing.account-notice.edit', compact(
            'notice',
            'mailStats',
            'mailDelayOptions',
            'mailDefaultDelay',
            'mailDefaultLimit',
            'mailTestEmail'
        ));
    }

    public function update(Request $request, AccountNoticeService $account_notice)
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'intro' => ['nullable', 'string', 'max:500'],
            'coupon_label' => ['nullable', 'string', 'max:80'],
            'coupon_code' => ['nullable', 'string', 'max:60'],
            'discount_text' => ['nullable', 'string', 'max:120'],
            'outro' => ['nullable', 'string', 'max:500'],
            'button_text' => ['nullable', 'string', 'max:80'],
            'button_url' => ['nullable', 'string', 'max:255'],
            'valid_until' => ['nullable', 'date'],
        ]);

        $data['active'] = $request->boolean('active');

        if ($account_notice->save($data)) {
            return redirect()->route('account.notice')->with(['success' => 'Obavijest je uspješno snimljena!']);
        }

        return redirect()->back()->withInput()->with(['error' => 'Oops..! Greška prilikom snimanja obavijesti.']);
    }

    public function recipients(Request $request, AccountNoticeService $account_notice, AccountNoticeMailService $account_notice_mail)
    {
        if (! auth()->check()) {
            return response()->json(['error' => 'Niste autorizirani.'], 403);
        }

        $request->validate([
            'delay' => ['nullable', 'integer', 'min:1', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:' . AccountNoticeMailService::MAX_BATCH_LIMIT],
        ]);

        $notice = $account_notice->get();
        $limit = $this->resolveBatchLimit($request);
        $userIds = $account_notice_mail->recipientIds($notice, $limit);
        $stats = $account_notice_mail->stats($notice);

        return response()->json([
            'user_ids' => $userIds,
            'count' => $userIds->count(),
            'batch_limit' => $limit,
            'delay_seconds' => $this->resolveDelaySeconds($request),
            'stats' => $stats,
            'notice_title' => $notice['title'],
        ]);
    }

    public function sendMail(Request $request, AccountNoticeService $account_notice, AccountNoticeMailService $account_notice_mail)
    {
        if (! auth()->check()) {
            return response()->json(['error' => 'Niste autorizirani.'], 403);
        }

        $request->validate([
            'user_id' => ['required', 'integer'],
        ]);

        $notice = $account_notice->get();
        $result = $account_notice_mail->sendToUserId(
            (int) $request->input('user_id'),
            $notice,
            $account_notice->formattedValidUntil($notice)
        );

        if (! $result['sent']) {
            return response()->json(['error' => $result['error']], 422);
        }

        return response()->json([
            'message' => 'Obavijest je uspješno poslana.',
            'email' => $result['email'],
        ]);
    }

    public function sendTestMail(AccountNoticeService $account_notice, AccountNoticeMailService $account_notice_mail)
    {
        if (! auth()->check()) {
            return response()->json(['error' => 'Niste autorizirani.'], 403);
        }

        $notice = $account_notice->get();
        $result = $account_notice_mail->sendTest($notice, $account_notice->formattedValidUntil($notice));

        if (! $result['sent']) {
            return response()->json(['error' => $result['error']], 422);
        }

        return response()->json([
            'message' => 'Testni mail je uspješno poslan.',
            'email' => $result['email'],
        ]);
    }

    private function resolveDelaySeconds(Request $request): int
    {
        $delaySeconds = (int) $request->input('delay', AccountNoticeMailService::DEFAULT_DELAY_SECONDS);

        return min(max($delaySeconds, 1), 120);
    }

    private function resolveBatchLimit(Request $request): int
    {
        $limit = (int) $request->input('limit', AccountNoticeMailService::DEFAULT_BATCH_LIMIT);

        return min(max($limit, 1), AccountNoticeMailService::MAX_BATCH_LIMIT);
    }
}
