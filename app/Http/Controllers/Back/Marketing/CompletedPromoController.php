<?php

namespace App\Http\Controllers\Back\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Back\Orders\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CompletedPromoController extends Controller
{
    private const DEFAULT_FROM = '2026-03-01';
    private const DEFAULT_DISCOUNT = 20;
    private const DEFAULT_DELAY_SECONDS = 8;

    public function index(Request $request)
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'delay' => ['nullable', 'integer', 'min:1', 'max:120'],
            'search' => ['nullable', 'string', 'max:191'],
        ]);

        [$from, $to] = $this->resolveDateRange($request);

        if ($to->lt($from)) {
            return redirect()
                ->route('marketing.completed-promo')
                ->with('error', 'Datum do mora biti nakon datuma od.');
        }

        $delaySeconds = $this->resolveDelaySeconds($request);
        $query = $this->candidateQuery($request, $from, $to);
        $candidateCount = (clone $query)->count();
        $orders = (clone $query)
            ->paginate(50)
            ->appends($request->query());

        return view('back.marketing.completed-promo.index', [
            'orders' => $orders,
            'candidateCount' => $candidateCount,
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'delay' => $delaySeconds,
                'search' => (string) $request->input('search', ''),
            ],
            'discount' => self::DEFAULT_DISCOUNT,
        ]);
    }

    public function candidates(Request $request)
    {
        if (! auth()->check()) {
            return response()->json(['error' => 'Niste autorizirani.'], 403);
        }

        $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
            'delay' => ['nullable', 'integer', 'min:1', 'max:120'],
            'search' => ['nullable', 'string', 'max:191'],
        ]);

        [$from, $to] = $this->resolveDateRange($request);

        if ($to->lt($from)) {
            return response()->json(['error' => 'Datum do mora biti nakon datuma od.'], 422);
        }

        $orderIds = $this->candidateQuery($request, $from, $to)
            ->pluck('orders.id')
            ->map(fn ($orderId) => (int) $orderId)
            ->values();

        return response()->json([
            'order_ids' => $orderIds,
            'count' => $orderIds->count(),
            'discount' => self::DEFAULT_DISCOUNT,
            'delay_seconds' => $this->resolveDelaySeconds($request),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }

    private function candidateQuery(Request $request, Carbon $from, Carbon $to)
    {
        $candidateRequest = new Request([
            'completed_without_promo_mail' => 1,
            'search' => $request->input('search'),
        ]);

        return (new Order())
            ->filter($candidateRequest)
            ->whereBetween('orders.created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDateRange(Request $request): array
    {
        return [
            Carbon::parse($request->input('from', self::DEFAULT_FROM))->startOfDay(),
            Carbon::parse($request->input('to', now()->toDateString()))->endOfDay(),
        ];
    }

    private function resolveDelaySeconds(Request $request): int
    {
        $delaySeconds = (int) $request->input('delay', self::DEFAULT_DELAY_SECONDS);

        return min(max($delaySeconds, 1), 120);
    }
}
