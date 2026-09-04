<?php

namespace Tests\Unit;

use App\Services\Delfi\DelfiPriceCalculator;
use InvalidArgumentException;
use Tests\TestCase;

class DelfiPriceCalculatorTest extends TestCase
{
    public function test_it_converts_rsd_to_eur_and_applies_markup(): void
    {
        $this->assertSame(12.50, app(DelfiPriceCalculator::class)->convert(1199, 117.2, 20));
    }

    public function test_it_rounds_up_without_markup_too(): void
    {
        $calculator = app(DelfiPriceCalculator::class);

        $this->assertSame(8.50, $calculator->convert(813, 100, 0));
        $this->assertSame(9.00, $calculator->convert(875, 100, 0));
    }

    public function test_it_rejects_an_invalid_exchange_rate(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(DelfiPriceCalculator::class)->convert(1199, 0, 20);
    }
}
