<?php

namespace Tests\Unit;

use App\Services\Catalog\ImportPriceRounder;
use PHPUnit\Framework\TestCase;

class ImportPriceRounderTest extends TestCase
{
    /**
     * @dataProvider prices
     */
    public function test_it_rounds_up_to_half_euro(float $price, float $expected): void
    {
        $this->assertSame($expected, ImportPriceRounder::upToHalfEuro($price));
    }

    public function prices(): array
    {
        return [
            'below half euro' => [8.13, 8.50],
            'above half euro' => [8.75, 9.00],
            'exact half euro' => [8.50, 8.50],
            'exact euro' => [9.00, 9.00],
            'zero' => [0.00, 0.00],
            'floating point boundary noise' => [8.5000000001, 8.50],
        ];
    }
}
