<?php

namespace App\Services\Laguna;

use InvalidArgumentException;

class LagunaPriceCalculator
{
    public function convert($priceRsd, $rsdPerEuro, $markupPercent): float
    {
        $priceRsd = (float) $priceRsd;
        $rsdPerEuro = (float) $rsdPerEuro;
        $markupPercent = (float) $markupPercent;

        if (! is_finite($priceRsd) || ! is_finite($rsdPerEuro) || ! is_finite($markupPercent)
            || $priceRsd < 0 || $rsdPerEuro <= 0 || $markupPercent < 0) {
            throw new InvalidArgumentException('Cijena, tečaj i postotak uvećanja moraju biti valjani pozitivni brojevi.');
        }

        return round(($priceRsd / $rsdPerEuro) * (1 + ($markupPercent / 100)), 2, PHP_ROUND_HALF_UP);
    }
}
