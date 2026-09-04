<?php

namespace App\Services\Catalog;

final class ImportPriceRounder
{
    /**
     * Round an imported EUR price upward to the next 50-cent increment.
     */
    public static function upToHalfEuro(float $price): float
    {
        // Stabilize harmless floating-point noise so an exact 0.50 boundary
        // is not accidentally pushed into the next price increment.
        return ceil(round($price, 8) * 2) / 2;
    }
}
