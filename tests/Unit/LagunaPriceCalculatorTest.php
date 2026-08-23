<?php

namespace Tests\Unit;

use App\Services\Laguna\LagunaPriceCalculator;
use InvalidArgumentException;
use Tests\TestCase;

class LagunaPriceCalculatorTest extends TestCase
{
    public function test_it_converts_rsd_to_eur_and_applies_markup(): void
    {
        $price = app(LagunaPriceCalculator::class)->convert(1199, 117.2, 20);

        $this->assertSame(12.28, $price);
    }

    public function test_it_rejects_an_invalid_exchange_rate(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(LagunaPriceCalculator::class)->convert(1199, 0, 20);
    }
}
