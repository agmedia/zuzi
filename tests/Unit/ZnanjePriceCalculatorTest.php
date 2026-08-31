<?php

namespace Tests\Unit;

use App\Services\Znanje\ZnanjePriceCalculator;
use InvalidArgumentException;
use Tests\TestCase;

class ZnanjePriceCalculatorTest extends TestCase
{
    public function test_it_applies_markup_directly_to_eur_price(): void
    {
        $calculator = app(ZnanjePriceCalculator::class);

        $this->assertSame(12.50, $calculator->calculate(10, 25));
        $this->assertSame(12.50, $calculator->convert(10, 117.2, 25));
        $this->assertSame(11.99, $calculator->calculate(11.99, 0));
    }

    public function test_it_rejects_negative_or_non_finite_values(): void
    {
        $calculator = app(ZnanjePriceCalculator::class);

        foreach ([[-1, 0], [10, -1], [INF, 0], [10, INF]] as [$price, $markup]) {
            try {
                $calculator->calculate($price, $markup);
                $this->fail('Nevaljana vrijednost cijene mora biti odbijena.');
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString('EUR cijena', $exception->getMessage());
            }
        }
    }
}
