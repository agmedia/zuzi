<?php

namespace App\Services\Znanje;

use App\Services\Catalog\ImportPriceRounder;
use InvalidArgumentException;

class ZnanjePriceCalculator
{
    /**
     * Potpis je kompatibilan s ostalim importerima; tečaj se ne primjenjuje
     * jer Znanje objavljuje cijene u eurima.
     */
    public function convert($priceEur, $exchangeRateIgnored, $markupPercent): float
    {
        return $this->calculate($priceEur, $markupPercent);
    }

    public function calculate($priceEur, $markupPercent): float
    {
        $priceEur = (float) $priceEur;
        $markupPercent = (float) $markupPercent;

        if (! is_finite($priceEur) || ! is_finite($markupPercent)
            || $priceEur < 0 || $markupPercent < 0) {
            throw new InvalidArgumentException(
                'EUR cijena i postotak uvećanja moraju biti valjani pozitivni brojevi.'
            );
        }

        return ImportPriceRounder::upToHalfEuro(
            $priceEur * (1 + ($markupPercent / 100))
        );
    }
}
