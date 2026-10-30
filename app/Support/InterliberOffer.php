<?php

namespace App\Support;

use App\Helpers\Helper;
use App\Models\Back\Marketing\Action;

class InterliberOffer
{
    public const CODE = 'ZUZI20';
    public const PERCENT = 20;

    public function isActive(): bool
    {
        if (! InterliberCampaign::isActive()) {
            return false;
        }

        try {
            foreach ($this->candidateActions() as $action) {
                if ($this->matchesOffer($action) && $action->isValid(self::CODE)) {
                    return true;
                }
            }
        } catch (\Throwable $exception) {
            // Do not advertise a coupon when its live validity cannot be checked.
            return false;
        }

        return false;
    }

    /** @return iterable<Action> */
    protected function candidateActions(): iterable
    {
        return Action::query()
            ->where('coupon', self::CODE)
            ->where('group', 'total')
            ->where('type', 'P')
            ->where('discount', self::PERCENT)
            ->where('status', 1)
            ->get(['id', 'coupon', 'group', 'type', 'discount', 'status', 'date_start', 'date_end']);
    }

    private function matchesOffer(Action $action): bool
    {
        return Helper::couponEquals($action->coupon, self::CODE)
            && (string) $action->group === 'total'
            && (string) $action->type === 'P'
            && (float) $action->discount === (float) self::PERCENT
            && (bool) $action->status;
    }
}
