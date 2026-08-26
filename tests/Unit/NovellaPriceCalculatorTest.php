<?php

namespace Tests\Unit;

use App\Services\Novella\NovellaPriceCalculator;
use InvalidArgumentException;
use Tests\TestCase;

class NovellaPriceCalculatorTest extends TestCase
{
    public function test_it_applies_markup_directly_to_eur_price(): void
    {
        $calculator = app(NovellaPriceCalculator::class);

        $this->assertSame(12.50, $calculator->calculate(10, 25));
        $this->assertSame(12.50, $calculator->convert(10, 117.2, 25));
    }

    public function test_it_rejects_negative_markup(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(NovellaPriceCalculator::class)->calculate(10, -1);
    }
}
