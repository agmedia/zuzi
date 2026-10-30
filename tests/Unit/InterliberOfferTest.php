<?php

namespace Tests\Unit;

use App\Models\Back\Marketing\Action;
use App\Support\InterliberOffer;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InterliberOfferTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-30 12:00:00', 'Europe/Zagreb'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @dataProvider actionProvider */
    public function test_offer_requires_the_active_twenty_percent_coupon(array $attributes, bool $expected): void
    {
        $action = new Action(array_merge([
            'coupon' => 'ZUZI20',
            'group' => 'total',
            'type' => 'P',
            'discount' => 20,
            'status' => 1,
            'date_start' => '2026-10-26 00:00:00',
            'date_end' => '2026-12-01 23:59:59',
        ], $attributes));

        $offer = new class($action) extends InterliberOffer {
            private Action $action;

            public function __construct(Action $action)
            {
                $this->action = $action;
            }

            protected function candidateActions(): iterable
            {
                return [$this->action];
            }
        };

        $this->assertSame($expected, $offer->isActive());
    }

    public static function actionProvider(): array
    {
        return [
            'active offer' => [[], true],
            'disabled coupon' => [['status' => 0], false],
            'expired coupon' => [['date_end' => '2026-10-29 23:59:59'], false],
            'future coupon' => [['date_start' => '2026-11-01 00:00:00'], false],
            'different coupon' => [['coupon' => 'OTHER20'], false],
            'different discount' => [['discount' => 10], false],
            'fixed discount' => [['type' => 'F'], false],
            'restricted scope' => [['group' => 'category'], false],
        ];
    }

    public function test_offer_is_inactive_when_the_campaign_window_has_ended(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-12-02 00:00:00', 'Europe/Zagreb'));

        $offer = new class extends InterliberOffer {
            protected function candidateActions(): iterable
            {
                throw new \LogicException('Expired campaigns must not read coupon data.');
            }
        };

        $this->assertFalse($offer->isActive());
    }

    public function test_unavailable_coupon_data_never_advertises_the_offer(): void
    {
        $offer = new class extends InterliberOffer {
            protected function candidateActions(): iterable
            {
                throw new \RuntimeException('Coupon data unavailable.');
            }
        };

        $this->assertFalse($offer->isActive());
    }
}
