<?php

namespace App\Http\Controllers\Back\Settings;

use App\Http\Controllers\Controller;
use App\Services\ContractWithdrawalSettingsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContractWithdrawalSettingsController extends Controller
{
    public function edit(ContractWithdrawalSettingsService $settings)
    {
        return view('back.settings.contract-withdrawals.edit', [
            'settings' => $settings->get(),
        ]);
    }

    public function update(Request $request, ContractWithdrawalSettingsService $settings)
    {
        $validated = $request->validate([
            'admin_email' => ['required', 'email', 'max:191'],
            'return_address' => ['required', 'string', 'max:1000'],
            'return_cost_policy' => ['required', Rule::in(['consumer', 'merchant'])],
            'instructions' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($settings->save($validated)) {
            return redirect()
                ->route('contract-withdrawal-settings.edit')
                ->with('success', 'Postavke jednostranog raskida su spremljene.');
        }

        return redirect()
            ->back()
            ->withInput()
            ->with('error', 'Postavke nije moguće spremiti.');
    }
}
