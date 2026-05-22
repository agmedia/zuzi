<?php

namespace App\Http\Controllers\Back\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Back\Marketing\Action;
use App\Services\UnfinishedOrderPromoStatsService;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public function index(Request $request)
    {
        $promoStatsService = app(UnfinishedOrderPromoStatsService::class);
        $promoYears = $promoStatsService->getAvailableYears();

        if (empty($promoYears)) {
            $promoYears = [(int) now()->format('Y')];
        }

        $promoYear = (int) $request->query('year', $promoYears[0]);
        if (! in_array($promoYear, $promoYears, true)) {
            $promoYear = $promoYears[0];
        }

        $promoMonth = (int) $request->query('month', now()->format('n'));
        if ($promoMonth < 1 || $promoMonth > 12) {
            $promoMonth = (int) now()->format('n');
        }

        $promoStats = [
            'filters' => [
                'years' => $promoYears,
                'year' => $promoYear,
                'month' => $promoMonth,
            ],
            'admin' => $promoStatsService->getDashboardData([
                'source' => UnfinishedOrderPromoStatsService::SOURCE_ADMIN,
                'segment' => UnfinishedOrderPromoStatsService::SEGMENT_ALL,
                'year' => $promoYear,
                'month' => $promoMonth,
            ]),
            'other' => $promoStatsService->getDashboardData([
                'source' => UnfinishedOrderPromoStatsService::SOURCE_OTHER,
                'segment' => UnfinishedOrderPromoStatsService::SEGMENT_ALL,
                'year' => $promoYear,
                'month' => $promoMonth,
            ]),
        ];

        $expiredCouponCount = $this->expiredCouponActionsQuery()->count();

        return view('back.marketing.statistics.index', compact('promoStats', 'expiredCouponCount'));
    }

    public function destroyExpiredCoupons(Request $request)
    {
        $deletedCount = 0;
        $failedCount = 0;

        $this->expiredCouponActionsQuery()
            ->orderBy('id')
            ->get()
            ->each(function (Action $action) use (&$deletedCount, &$failedCount) {
                if ($action->remove()) {
                    $deletedCount++;
                    return;
                }

                $failedCount++;
            });

        if ($failedCount > 0) {
            return redirect()
                ->route('marketing.statistics', $request->only(['year', 'month']))
                ->with('error', 'Obrisano je ' . $deletedCount . ' isteklih kodova, ali ' . $failedCount . ' nije obrisano.');
        }

        if ($deletedCount === 0) {
            return redirect()
                ->route('marketing.statistics', $request->only(['year', 'month']))
                ->with('success', 'Nema isteklih kodova za brisanje.');
        }

        return redirect()
            ->route('marketing.statistics', $request->only(['year', 'month']))
            ->with('success', 'Obrisano je ' . $deletedCount . ' isteklih promo kodova.');
    }

    private function expiredCouponActionsQuery()
    {
        return Action::query()
            ->whereNotNull('coupon')
            ->where('coupon', '!=', '')
            ->whereNotNull('date_end')
            ->where('date_end', '<', now());
    }
}
