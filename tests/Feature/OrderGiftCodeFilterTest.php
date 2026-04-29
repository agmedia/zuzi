<?php

namespace Tests\Feature;

use App\Models\Back\Marketing\Action;
use App\Models\Back\Orders\Order;
use App\Services\GiftVoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderGiftCodeFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_filter_returns_only_orders_with_used_gift_code_when_enabled(): void
    {
        $giftCodeOrderId = $this->createOrder('Ana', 'Anić');
        $promoCouponOrderId = $this->createOrder('Ivo', 'Ivić');
        $regularOrderId = $this->createOrder('Mia', 'Marić');

        $giftVoucherTitle = GiftVoucherService::ACTION_TITLE_PREFIX . '777';

        Action::query()->create([
            'title' => $giftVoucherTitle,
            'type' => 'F',
            'discount' => 25,
            'group' => 'total',
            'links' => json_encode(['total']),
            'date_start' => now()->subDay(),
            'date_end' => now()->addDay(),
            'coupon' => GiftVoucherService::COUPON_PREFIX . 'ABCDEFGH',
            'quantity' => 1,
            'status' => 1,
        ]);

        Action::query()->create([
            'title' => 'Promo za nedovrsenu narudzbu #888',
            'type' => 'P',
            'discount' => 10,
            'group' => 'total',
            'links' => json_encode(['total']),
            'date_start' => now()->subDay(),
            'date_end' => now()->addDay(),
            'coupon' => 'HVALA10-ABCDE10',
            'quantity' => 1,
            'status' => 1,
        ]);

        $this->addOrderTotal($giftCodeOrderId, 'special', $giftVoucherTitle, -25.00);
        $this->addOrderTotal($promoCouponOrderId, 'special', 'Promo za nedovrsenu narudzbu #888', -10.00);
        $this->addOrderTotal($regularOrderId, 'shipping', 'Dostava', 3.99);

        $filteredIds = (new Order())
            ->filter(new Request(['gift_code' => 1]))
            ->pluck('id')
            ->all();

        $this->assertSame([$giftCodeOrderId], $filteredIds);
    }

    public function test_order_filter_does_not_limit_results_when_gift_code_filter_is_disabled(): void
    {
        $giftCodeOrderId = $this->createOrder('Luka', 'Lukić');
        $regularOrderId = $this->createOrder('Sara', 'Sarić');

        Action::query()->create([
            'title' => GiftVoucherService::ACTION_TITLE_PREFIX . '999',
            'type' => 'F',
            'discount' => 40,
            'group' => 'total',
            'links' => json_encode(['total']),
            'date_start' => now()->subDay(),
            'date_end' => now()->addDay(),
            'coupon' => GiftVoucherService::COUPON_PREFIX . 'ZXCVBNMA',
            'quantity' => 1,
            'status' => 1,
        ]);

        $this->addOrderTotal($giftCodeOrderId, 'special', GiftVoucherService::ACTION_TITLE_PREFIX . '999', -40.00);

        $filteredIds = (new Order())
            ->filter(new Request(['gift_code' => 0]))
            ->pluck('id')
            ->sort()
            ->values()
            ->all();

        $this->assertSame([$giftCodeOrderId, $regularOrderId], $filteredIds);
    }

    private function createOrder(string $firstName, string $lastName): int
    {
        return (int) DB::table('orders')->insertGetId([
            'user_id' => 0,
            'affiliate_id' => 0,
            'order_status_id' => 1,
            'invoice' => null,
            'total' => 25,
            'payment_fname' => $firstName,
            'payment_lname' => $lastName,
            'payment_address' => 'Test ulica 1',
            'payment_zip' => '10000',
            'payment_city' => 'Zagreb',
            'payment_phone' => null,
            'payment_email' => strtolower($firstName) . '@example.com',
            'payment_method' => 'Kartice',
            'payment_code' => 'corvus',
            'payment_card' => null,
            'payment_installment' => 0,
            'shipping_fname' => $firstName,
            'shipping_lname' => $lastName,
            'shipping_address' => 'Test ulica 1',
            'shipping_zip' => '10000',
            'shipping_city' => 'Zagreb',
            'shipping_phone' => null,
            'shipping_email' => strtolower($firstName) . '@example.com',
            'shipping_method' => 'Dostava',
            'shipping_code' => 'gls',
            'company' => '',
            'oib' => '',
            'comment' => null,
            'tracking_code' => '',
            'shipped' => false,
            'printed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function addOrderTotal(int $orderId, string $code, string $title, float $value): void
    {
        DB::table('order_total')->insert([
            'order_id' => $orderId,
            'code' => $code,
            'title' => $title,
            'value' => $value,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
