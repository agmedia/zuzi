<?php

namespace Tests\Feature;

use App\Helpers\Helper;
use App\Models\Back\Marketing\Action;
use Darryldecode\Cart\Facades\CartFacade as Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_check_returns_consistent_payload_for_empty_ids(): void
    {
        $response = $this->postJson('/api/v2/cart/check', [
            'ids' => [],
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'cart' => [
                    'id',
                    'count',
                    'items',
                    'total',
                    'has_gift_voucher',
                    'gift_voucher_only',
                ],
                'message',
            ]);
    }

    public function test_cart_check_ignores_non_numeric_ids_and_still_returns_cart_payload(): void
    {
        $response = $this->postJson('/api/v2/cart/check', [
            'ids' => ['gift-voucher', 'abc'],
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'cart' => [
                    'id',
                    'count',
                    'items',
                    'total',
                    'has_gift_voucher',
                    'gift_voucher_only',
                ],
                'message',
            ]);
    }

    public function test_valid_coupon_can_be_saved_before_cart_has_items(): void
    {
        Action::query()->create([
            'title' => 'Kupon za praznu kosaricu',
            'type' => 'P',
            'discount' => 20,
            'group' => 'total',
            'links' => json_encode(['total']),
            'date_start' => now()->subDay(),
            'date_end' => now()->addDay(),
            'coupon' => 'HVALAODZUZI',
            'quantity' => 1,
            'status' => 1,
        ]);

        $response = $this->postJson('/api/v2/cart/coupon', [
            'coupon' => 'hvalaodzuzi',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'coupon' => 'HVALAODZUZI',
            ])
            ->assertJsonPath('cart.count', 0);

        $response->assertSessionHas(config('session.cart') . '_coupon', 'HVALAODZUZI');
    }

    public function test_invalid_coupon_is_not_saved_before_cart_has_items(): void
    {
        $response = $this->postJson('/api/v2/cart/coupon', [
            'coupon' => 'NEPOSTOJI',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => false,
                'coupon' => '',
            ])
            ->assertJsonPath('cart.count', 0);

        $response->assertSessionMissing(config('session.cart') . '_coupon');
    }

    public function test_coupon_total_condition_uses_customer_facing_coupon_name(): void
    {
        Action::query()->create([
            'title' => 'Internal campaign title',
            'type' => 'P',
            'discount' => 20,
            'group' => 'total',
            'links' => json_encode(['total']),
            'date_start' => now()->subDay(),
            'date_end' => now()->addDay(),
            'coupon' => 'HVALA',
            'quantity' => 1,
            'status' => 1,
        ]);

        $cart = Cart::session('coupon-name-test');
        $cart->clear();
        $cart->clearCartConditions();
        $cart->add([
            'id' => 1,
            'name' => 'Test product',
            'price' => 40,
            'quantity' => 1,
            'attributes' => [],
        ]);

        $condition = Helper::hasCouponCartConditions($cart, 'hvala');

        $this->assertNotFalse($condition);
        $this->assertSame('Kupon HVALA', $condition->getName());
    }

    public function test_bogo_condition_applies_best_quantity_tier_without_coupon(): void
    {
        Action::query()->create([
            'title' => 'BOGO test',
            'type' => 'P',
            'discount' => 20,
            'group' => Action::GROUP_BOGO,
            'links' => json_encode([Action::GROUP_BOGO]),
            'date_start' => now()->subDay(),
            'date_end' => now()->addDay(),
            'data' => json_encode([
                'tiers' => [
                    ['quantity' => 2, 'discount' => 5],
                    ['quantity' => 3, 'discount' => 10],
                    ['quantity' => 5, 'discount' => 20],
                ],
            ]),
            'coupon' => null,
            'quantity' => 0,
            'lock' => 0,
            'status' => 1,
        ]);

        $cart = Cart::session('bogo-test');
        $cart->clear();
        $cart->clearCartConditions();
        $cart->add([
            'id' => 1,
            'name' => 'Test product',
            'price' => 10,
            'quantity' => 3,
            'attributes' => [],
        ]);

        $condition = Helper::hasBogoCartCondition($cart);

        $this->assertNotFalse($condition);
        $this->assertSame('BOGO test 10%', $condition->getName());
        $this->assertSame(-3.0, (float) $condition->getValue());
        $this->assertSame('bogo', $condition->getAttributes()['type']);
    }

    public function test_bogo_condition_is_not_combined_with_coupon(): void
    {
        Action::query()->create([
            'title' => 'BOGO coupon guard',
            'type' => 'P',
            'discount' => 10,
            'group' => Action::GROUP_BOGO,
            'links' => json_encode([Action::GROUP_BOGO]),
            'date_start' => now()->subDay(),
            'date_end' => now()->addDay(),
            'data' => json_encode([
                'tiers' => [
                    ['quantity' => 2, 'discount' => 10],
                ],
            ]),
            'coupon' => null,
            'quantity' => 0,
            'lock' => 0,
            'status' => 1,
        ]);

        $cart = Cart::session('bogo-coupon-test');
        $cart->clear();
        $cart->clearCartConditions();
        $cart->add([
            'id' => 1,
            'name' => 'Test product',
            'price' => 10,
            'quantity' => 2,
            'attributes' => [],
        ]);

        $this->assertFalse(Helper::hasBogoCartCondition($cart, 'HVALA'));
    }
}
