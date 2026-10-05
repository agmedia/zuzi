<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasTwentyDayDeliveryWindow
{
    /**
     * Determine whether products from this publisher use the extended delivery window.
     */
    public function usesTwentyDayDeliveryWindow(): bool
    {
        $slug = Str::slug((string) ($this->slug ?: $this->title));

        return in_array($slug, ['delfi', 'laguna', 'laguna-doo'], true);
    }
}
