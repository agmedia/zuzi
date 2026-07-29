<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Models\ContractWithdrawal;
use App\Services\ContractWithdrawalNotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContractWithdrawalController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(array_keys(ContractWithdrawal::statuses()))],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $status = (string) ($filters['status'] ?? '');

        $withdrawals = ContractWithdrawal::query()
            ->with(['order:id', 'handler:id,name'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested
                        ->where('reference', 'like', '%'.$search.'%')
                        ->orWhere('order_number', 'like', '%'.$search.'%')
                        ->orWhere('full_name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->when($status !== '', function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->latest('submitted_at')
            ->paginate(25)
            ->appends($request->query());

        return view('back.contract-withdrawals.index', [
            'withdrawals' => $withdrawals,
            'statuses' => ContractWithdrawal::statuses(),
            'statusColors' => ContractWithdrawal::statusColors(),
            'search' => $search,
            'selectedStatus' => $status,
        ]);
    }

    public function show(ContractWithdrawal $withdrawal)
    {
        $withdrawal->load(['order', 'user:id,name,email', 'handler:id,name']);

        return view('back.contract-withdrawals.show', [
            'withdrawal' => $withdrawal,
            'statuses' => ContractWithdrawal::statuses(),
            'statusColors' => ContractWithdrawal::statusColors(),
        ]);
    }

    public function update(Request $request, ContractWithdrawal $withdrawal)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(ContractWithdrawal::statuses()))],
            'internal_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $status = (string) $validated['status'];
        $closed = in_array($status, [
            ContractWithdrawal::STATUS_COMPLETED,
            ContractWithdrawal::STATUS_DECLINED,
        ], true);

        $withdrawal->forceFill([
            'status' => $status,
            'internal_note' => trim((string) ($validated['internal_note'] ?? '')) ?: null,
            'handled_by' => optional($request->user())->id,
            'handled_at' => now(),
            'completed_at' => $closed ? ($withdrawal->completed_at ?: now()) : null,
        ])->save();

        return redirect()
            ->route('contract-withdrawals.show', $withdrawal)
            ->with('success', 'Status raskida ugovora je spremljen.');
    }

    public function resend(
        ContractWithdrawal $withdrawal,
        ContractWithdrawalNotificationService $notifications
    ) {
        $withdrawal->forceFill(['notification_error' => null])->save();
        $notifications->send($withdrawal);
        $withdrawal->refresh();

        if ($withdrawal->notification_error) {
            return redirect()
                ->route('contract-withdrawals.show', $withdrawal)
                ->with('warning', 'Slanje nije u cijelosti uspjelo. Provjerite prikazanu pogrešku.');
        }

        return redirect()
            ->route('contract-withdrawals.show', $withdrawal)
            ->with('success', 'Potvrda korisniku i obavijest administratoru ponovno su poslane.');
    }
}
