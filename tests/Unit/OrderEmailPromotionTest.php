<?php

namespace Tests\Unit;

use App\Models\Back\Orders\Order;
use App\Support\InterliberOffer;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OrderEmailPromotionTest extends TestCase
{
    /**
     * @dataProvider promotionDates
     */
    public function test_customer_order_email_advertises_the_offer_only_until_the_coupon_deadline(string $date, bool $visible): void
    {
        Carbon::setTestNow(Carbon::parse($date));
        $this->mockOffer(true);

        try {
            $html = $this->renderOrderEmail('emails.order-sent');

            if ($visible) {
                $this->assertStringContainsString('Poklon za vašu sljedeću kupnju', $html);
                $this->assertStringContainsString('20 % popusta na sve artikle', $html);
                $this->assertStringContainsString('ZUZI20', $html);
                $this->assertStringContainsString('15.11.2026.', $html);
                $this->assertStringContainsString('Iskoristi 20 % popusta', $html);
                $this->assertStringContainsString(
                    'href="' . e(route('interliber.popusti') . '?utm_source=order_confirmation&utm_medium=email&utm_campaign=zuzi20_2026') . '"',
                    $html
                );
                $this->assertLessThan(
                    strpos($html, 'Poklon za vašu sljedeću kupnju'),
                    strpos($html, 'Lijep pozdrav,')
                );
            } else {
                $this->assertStringNotContainsString('Poklon za vašu sljedeću kupnju', $html);
                $this->assertStringNotContainsString('ZUZI20', $html);
            }
        } finally {
            Carbon::setTestNow();
        }
    }

    public function promotionDates(): array
    {
        return [
            'current campaign' => ['2026-11-06 12:00:00 Europe/Zagreb', true],
            'last second in Zagreb' => ['2026-11-14 23:59:59 Europe/Zagreb', true],
            'expired in Zagreb' => ['2026-11-15 00:00:00 Europe/Zagreb', false],
            'last second from UTC' => ['2026-11-14 22:59:59 UTC', true],
            'expired from UTC' => ['2026-11-14 23:00:00 UTC', false],
            'daytime on November 15' => ['2026-11-15 12:00:00 Europe/Zagreb', false],
            'does not recur next year' => ['2027-11-06 12:00:00 Europe/Zagreb', false],
        ];
    }

    public function test_customer_order_email_omits_an_inactive_offer_before_the_deadline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-06 12:00:00 Europe/Zagreb'));
        $this->mockOffer(false);

        try {
            $html = $this->renderOrderEmail('emails.order-sent');

            $this->assertStringNotContainsString('Poklon za vašu sljedeću kupnju', $html);
            $this->assertStringNotContainsString('ZUZI20', $html);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_admin_order_email_does_not_include_the_customer_promotion(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-06 12:00:00 Europe/Zagreb'));
        $this->mockOffer(true);

        try {
            $html = $this->renderOrderEmail('emails.order-received');

            $this->assertStringNotContainsString('Poklon za vašu sljedeću kupnju', $html);
            $this->assertStringNotContainsString('ZUZI20', $html);
        } finally {
            Carbon::setTestNow();
        }
    }

    private function mockOffer(bool $active): void
    {
        $this->mock(InterliberOffer::class, function ($mock) use ($active) {
            $mock->shouldReceive('isActive')->andReturn($active);
        });
    }

    private function renderOrderEmail(string $template): string
    {
        $order = new Order([
            'payment_fname' => 'Ana',
            'payment_code' => 'cod',
            'shipping_state' => 'Croatia',
            'shipping_method' => 'GLS',
        ]);
        $order->id = 123;
        $order->setRelation('products', collect());
        $order->setRelation('totals', collect());

        return view($template, ['order' => $order, 'loyaltyMailData' => null])->render();
    }
}
