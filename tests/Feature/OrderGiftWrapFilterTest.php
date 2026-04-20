<?php

namespace Tests\Feature;

use App\Models\Back\Orders\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderGiftWrapFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_filter_returns_only_orders_with_gift_wrap_when_enabled(): void
    {
        $giftWrapOrderId = $this->createOrder('Ana', 'Anić');
        $regularOrderId = $this->createOrder('Ivo', 'Ivić');

        $this->addOrderProduct($giftWrapOrderId, 123, 'Knjiga test');
        $this->addOrderProduct($giftWrapOrderId, 0, 'Zamatanje - TEST-123 - Knjiga test', 1, 5.00);
        $this->addOrderProduct($regularOrderId, 456, 'Obična knjiga');

        $filteredIds = (new Order())
            ->filter(new Request(['gift_wrap' => 1]))
            ->pluck('id')
            ->all();

        $this->assertSame([$giftWrapOrderId], $filteredIds);
    }

    public function test_order_filter_does_not_limit_results_when_gift_wrap_filter_is_disabled(): void
    {
        $giftWrapOrderId = $this->createOrder('Mia', 'Marić');
        $regularOrderId = $this->createOrder('Luka', 'Lukić');

        $this->addOrderProduct($giftWrapOrderId, 0, 'Zamatanje - TEST-555 - Knjiga', 1, 5.00);
        $this->addOrderProduct($regularOrderId, 789, 'Bez zamatanja');

        $filteredIds = (new Order())
            ->filter(new Request(['gift_wrap' => 0]))
            ->pluck('id')
            ->sort()
            ->values()
            ->all();

        $this->assertSame([$giftWrapOrderId, $regularOrderId], $filteredIds);
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

    private function addOrderProduct(
        int $orderId,
        int $productId,
        string $name,
        int $quantity = 1,
        float $price = 20.00
    ): void {
        DB::table('order_products')->insert([
            'order_id' => $orderId,
            'product_id' => $productId,
            'name' => $name,
            'quantity' => $quantity,
            'org_price' => $price,
            'discount' => 0,
            'price' => $price,
            'total' => $price * $quantity,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
