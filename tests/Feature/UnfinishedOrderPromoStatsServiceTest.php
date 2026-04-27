<?php

namespace Tests\Feature;

use App\Services\UnfinishedOrderPromoStatsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UnfinishedOrderPromoStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_dashboard_statistics_for_unfinished_order_promos(): void
    {
        Carbon::setTestNow('2026-04-27 14:45:00');

        $firstTitle = $this->createPromoAction(35731, 10, '2026-04-25 10:00:00');
        $secondTitle = $this->createPromoAction(35732, 15, '2026-04-26 12:00:00');
        $thirdTitle = $this->createPromoAction(35733, 20, '2026-04-27 09:00:00');

        $this->createUsedPromoOrder($firstTitle, 50, -5, '2026-04-26 08:30:00');
        $this->createUsedPromoOrder($thirdTitle, 80, -10, '2026-04-27 11:15:00');

        $stats = app(UnfinishedOrderPromoStatsService::class)->getDashboardData();

        $this->assertSame(3, $stats['summary']['sent_count']);
        $this->assertSame(2, $stats['summary']['used_count']);
        $this->assertSame(1, $stats['summary']['unused_count']);
        $this->assertSame(66.7, $stats['summary']['conversion_rate']);
        $this->assertSame(130.0, $stats['summary']['revenue_total']);
        $this->assertSame(15.0, $stats['summary']['discount_total']);
        $this->assertSame(65.0, $stats['summary']['average_revenue_per_used']);
        $this->assertSame(7.5, $stats['summary']['average_discount_per_used']);
        $this->assertSame(20, $stats['summary']['best_discount']['discount']);
        $this->assertSame(100.0, $stats['summary']['best_discount']['conversion_rate']);

        $byDiscount = collect($stats['by_discount'])->keyBy('discount');

        $this->assertSame(1, $byDiscount[10]['sent_count']);
        $this->assertSame(1, $byDiscount[10]['used_count']);
        $this->assertSame(50.0, $byDiscount[10]['revenue_total']);

        $this->assertSame(1, $byDiscount[15]['sent_count']);
        $this->assertSame(0, $byDiscount[15]['used_count']);
        $this->assertSame(0.0, $byDiscount[15]['revenue_total']);

        $this->assertSame(1, $byDiscount[20]['sent_count']);
        $this->assertSame(1, $byDiscount[20]['used_count']);
        $this->assertSame(80.0, $byDiscount[20]['revenue_total']);

        $this->assertSame(3, array_sum($stats['chart']['sent']));
        $this->assertSame(2, array_sum($stats['chart']['used']));

        Carbon::setTestNow();
    }

    public function test_it_does_not_count_promos_used_on_unfinished_orders(): void
    {
        Carbon::setTestNow('2026-04-27 14:45:00');

        $unfinishedTitle = $this->createPromoAction(35756, 10, '2026-04-27 09:00:00');
        $finishedTitle = $this->createPromoAction(35757, 15, '2026-04-27 10:00:00');

        $this->createUsedPromoOrder(
            $unfinishedTitle,
            42.34,
            -2.66,
            '2026-04-27 11:00:00',
            (int) config('settings.order.status.unfinished')
        );

        $this->createUsedPromoOrder(
            $finishedTitle,
            56.34,
            -4.48,
            '2026-04-27 12:00:00'
        );

        $stats = app(UnfinishedOrderPromoStatsService::class)->getDashboardData();

        $this->assertSame(2, $stats['summary']['sent_count']);
        $this->assertSame(1, $stats['summary']['used_count']);
        $this->assertSame(1, $stats['summary']['unused_count']);
        $this->assertSame(50.0, $stats['summary']['conversion_rate']);
        $this->assertSame(56.34, $stats['summary']['revenue_total']);
        $this->assertSame(4.48, $stats['summary']['discount_total']);
        $this->assertSame(56.34, $stats['summary']['average_revenue_per_used']);
        $this->assertSame(4.48, $stats['summary']['average_discount_per_used']);
        $this->assertSame(15, $stats['summary']['best_discount']['discount']);

        Carbon::setTestNow();
    }

    private function createPromoAction(int $orderId, int $discount, string $createdAt): string
    {
        $title = 'Promo za nedovrsenu narudzbu #' . $orderId;

        DB::table('product_actions')->insert([
            'title' => $title,
            'type' => 'P',
            'discount' => $discount,
            'group' => 'total',
            'links' => json_encode(['total']),
            'date_start' => Carbon::parse($createdAt),
            'date_end' => Carbon::parse($createdAt)->addDays(7),
            'data' => json_encode([
                'source' => 'unfinished_order_promo',
                'order_id' => $orderId,
                'discount' => $discount,
            ]),
            'coupon' => 'HVALA' . $discount . '-ABCDE' . $discount,
            'quantity' => 1,
            'lock' => 0,
            'status' => 1,
            'created_at' => Carbon::parse($createdAt),
            'updated_at' => Carbon::parse($createdAt),
        ]);

        return $title;
    }

    private function createUsedPromoOrder(
        string $title,
        float $total,
        float $discountValue,
        string $createdAt,
        int $statusId = 4
    ): void
    {
        $orderId = DB::table('orders')->insertGetId([
            'user_id' => 0,
            'affiliate_id' => 0,
            'order_status_id' => $statusId,
            'invoice' => null,
            'total' => $total,
            'payment_fname' => 'Test',
            'payment_lname' => 'Kupac',
            'payment_address' => 'Test ulica 1',
            'payment_zip' => '10000',
            'payment_city' => 'Zagreb',
            'payment_phone' => null,
            'payment_email' => 'promo@example.com',
            'payment_method' => 'Kartice',
            'payment_code' => 'corvus',
            'payment_card' => null,
            'payment_installment' => 0,
            'shipping_fname' => 'Test',
            'shipping_lname' => 'Kupac',
            'shipping_address' => 'Test ulica 1',
            'shipping_zip' => '10000',
            'shipping_city' => 'Zagreb',
            'shipping_phone' => null,
            'shipping_email' => 'promo@example.com',
            'shipping_method' => 'Dostava',
            'shipping_code' => 'gls',
            'company' => '',
            'oib' => '',
            'comment' => null,
            'tracking_code' => '',
            'shipped' => false,
            'printed' => false,
            'created_at' => Carbon::parse($createdAt),
            'updated_at' => Carbon::parse($createdAt),
        ]);

        DB::table('order_total')->insert([
            'order_id' => $orderId,
            'code' => 'special',
            'title' => $title,
            'value' => $discountValue,
            'sort_order' => 1,
            'created_at' => Carbon::parse($createdAt),
            'updated_at' => Carbon::parse($createdAt),
        ]);
    }
}
