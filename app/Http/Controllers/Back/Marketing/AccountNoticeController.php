<?php

namespace App\Http\Controllers\Back\Marketing;

use App\Http\Controllers\Controller;
use App\Services\AccountNoticeService;
use Illuminate\Http\Request;

class AccountNoticeController extends Controller
{
    public function edit(AccountNoticeService $account_notice)
    {
        $notice = $account_notice->get();

        return view('back.marketing.account-notice.edit', compact('notice'));
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
}
