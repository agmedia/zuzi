<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class InterliberCampaign
{
    public static function isActive(): bool
    {
        $now = now('Europe/Zagreb');
        $startsAt = CarbonImmutable::create(2026, 10, 26, 0, 0, 0, 'Europe/Zagreb');
        $endsAt = CarbonImmutable::create(2026, 12, 2, 0, 0, 0, 'Europe/Zagreb');

        return $now->gte($startsAt) && $now->lt($endsAt);
    }
}
