<?php

namespace App\Http\Controllers\Back\Marketing;

use App\Http\Controllers\Controller;
use App\Models\GiftVoucher;
use Illuminate\Http\Request;

class GiftVoucherController extends Controller
{
    public function index(Request $request)
    {
        $query = GiftVoucher::query()->with(['order', 'action']);

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($builder) use ($search) {
                $builder->where('code', 'like', '%' . $search . '%')
                    ->orWhere('recipient_name', 'like', '%' . $search . '%')
                    ->orWhere('recipient_email', 'like', '%' . $search . '%')
                    ->orWhere('buyer_name', 'like', '%' . $search . '%')
                    ->orWhere('buyer_email', 'like', '%' . $search . '%')
                    ->orWhere('order_id', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('status')) {
            $status = $request->input('status');

            if ($status === 'pending') {
                $query->whereNull('email_sent_at');
            }

            if ($status === 'sent') {
                $query->whereNotNull('email_sent_at')
                    ->where(function ($builder) {
                        $builder->whereDoesntHave('action')
                            ->orWhereHas('action', fn ($actionQuery) => $actionQuery->where('status', 1));
                    });
            }

            if ($status === 'redeemed') {
                $query->whereHas('action', fn ($actionQuery) => $actionQuery->where('status', 0));
            }
        }

        $giftVouchers = $query
            ->orderByDesc('created_at')
            ->paginate(config('settings.pagination.back'))
            ->appends($request->query());

        return view('back.marketing.gift-vouchers.index', compact('giftVouchers'));
    }
}
