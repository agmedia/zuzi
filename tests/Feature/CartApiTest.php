<?php

namespace Tests\Feature;

use App\Helpers\Helper;
use App\Helpers\Session\CheckoutSession;
use App\Models\Back\Marketing\Action;
use App\Models\Front\Loyalty;
use App\Models\User;
use App\Services\CouponUsageService;
use App\Services\GiftVoucherService;
use Darryldecode\Cart\Facades\CartFacade as Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    public function test_invalid_new_coupon_clears_previously_saved_coupon(): void
    {
        $this->createTotalCouponAction('HVALA', 10);

        $this->postJson('/api/v2/cart/coupon', [
            'coupon' => 'HVALA',
        ])->assertOk()
            ->assertJson([
                'success' => true,
                'coupon' => 'HVALA',
            ]);

        $response = $this->postJson('/api/v2/cart/coupon', [
            'coupon' => 'NEPOSTOJI',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => false,
                'coupon' => '',
            ])
            ->assertJsonPath('cart.coupon', '');

        $response->assertSessionMissing(config('session.cart') . '_coupon');
    }

    public function test_coupon_clears_loyalty_and_prevents_it_from_being_applied_again(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Loyalty::addPoints(100, 0, 'admin', 'Test balance', $user->id);

        $productId = $this->createProduct('Artikl za loyalty test', 'LOYALTY-COUPON');
        $this->createTotalCouponAction('HVALA10', 10);

        $this->postJson('/api/v2/cart/add', [
            'item' => [
                'id' => $productId,
                'quantity' => 1,
            ],
        ])->assertOk();

        $loyaltyResponse = $this->getJson('/api/v2/cart/loyalty/100');

        $loyaltyResponse->assertOk();
        $this->assertSame(100, $loyaltyResponse->json());
        $loyaltyResponse->assertSessionHas(config('session.cart') . '_loyalty', 100);

        $couponResponse = $this->postJson('/api/v2/cart/coupon', [
            'coupon' => 'HVALA10',
        ]);

        $couponResponse->assertOk()
            ->assertJson([
                'success' => true,
                'coupon' => 'HVALA10',
            ])
            ->assertJsonPath('cart.loyalty', '')
            ->assertJsonPath('cart.has_loyalty', 0);

        $couponResponse->assertSessionMissing(config('session.cart') . '_loyalty');
        $this->assertFalse($this->cartHasDetailCondition($couponResponse->json('cart'), 'Loyalty'));

        $blockedResponse = $this->getJson('/api/v2/cart/loyalty/100');

        $blockedResponse->assertOk();
        $this->assertSame(0, $blockedResponse->json());
        $blockedResponse->assertSessionMissing(config('session.cart') . '_loyalty');
    }

    public function test_gift_voucher_purchase_clears_selected_loyalty(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Loyalty::addPoints(100, 0, 'admin', 'Test balance', $user->id);

        $this->getJson('/api/v2/cart/loyalty/100')
            ->assertOk()
            ->assertSessionHas(config('session.cart') . '_loyalty', 100);

        $response = $this->postJson(
            '/api/v2/cart/add',
            GiftVoucherService::buildCartItemRequest([
                'amount' => 50,
                'recipient_name' => 'Ana',
                'recipient_email' => 'ana@example.test',
                'sender_name' => 'Iva',
                'message' => 'Sretan rođendan!',
            ])
        );

        $response->assertOk()
            ->assertJsonPath('has_gift_voucher', true)
            ->assertJsonPath('loyalty', '')
            ->assertJsonPath('has_loyalty', 0);

        $response->assertSessionMissing(config('session.cart') . '_loyalty');
        $this->assertFalse($this->cartHasDetailCondition($response->json(), 'Loyalty'));
    }

    public function test_coupon_limited_per_email_is_rejected_after_that_email_used_it(): void
    {
        $this->createTotalCouponAction('JEDNOM', 10, [
            'quantity' => 0,
            'once_per_email' => 1,
        ]);
        $this->createCouponOrder('kupac@example.test', 'JEDNOM', 1);

        CheckoutSession::setAddress(['email' => ' KUPAC@example.test ']);

        $response = $this->postJson('/api/v2/cart/coupon', [
            'coupon' => 'jednom',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => false,
                'coupon' => '',
                'message' => CouponUsageService::ALREADY_USED_MESSAGE,
            ]);

        $response->assertSessionMissing(config('session.cart') . '_coupon');
    }

    public function test_coupon_limited_per_email_remains_available_to_another_email(): void
    {
        $this->createTotalCouponAction('JEDNOM', 10, [
            'quantity' => 0,
            'once_per_email' => 1,
        ]);
        $this->createCouponOrder('prvi@example.test', 'JEDNOM', 1);

        CheckoutSession::setAddress(['email' => 'drugi@example.test']);

        $this->postJson('/api/v2/cart/coupon', [
            'coupon' => 'jednom',
        ])->assertOk()
            ->assertJson([
                'success' => true,
                'coupon' => 'JEDNOM',
            ]);
    }

    public function test_canceled_order_does_not_consume_coupon_limited_per_email(): void
    {
        $this->createTotalCouponAction('JEDNOM', 10, [
            'quantity' => 0,
            'once_per_email' => 1,
        ]);
        $this->createCouponOrder('kupac@example.test', 'JEDNOM', 5);

        CheckoutSession::setAddress(['email' => 'kupac@example.test']);

        $this->postJson('/api/v2/cart/coupon', [
            'coupon' => 'jednom',
        ])->assertOk()
            ->assertJson([
                'success' => true,
                'coupon' => 'JEDNOM',
            ]);
    }

    public function test_current_order_can_be_excluded_from_per_email_usage_check(): void
    {
        $this->createTotalCouponAction('JEDNOM', 10, [
            'quantity' => 0,
            'once_per_email' => 1,
        ]);
        $orderId = $this->createCouponOrder('kupac@example.test', 'JEDNOM', 3);

        $service = app(CouponUsageService::class);

        $this->assertTrue($service->hasBeenUsed('JEDNOM', 'kupac@example.test'));
        $this->assertFalse($service->hasBeenUsed('JEDNOM', 'kupac@example.test', $orderId));
    }

    public function test_action_save_prefers_per_email_limit_over_global_single_use(): void
    {
        $request = Request::create('/admin/marketing/action', 'POST', [
            'title' => 'Jednom po kupcu',
            'type' => 'P',
            'discount' => 10,
            'group' => 'total',
            'coupon' => 'jednom',
            'coupon_quantity' => 'on',
            'coupon_once_per_email' => 'on',
            'status' => 'on',
        ]);

        $action = (new Action())->validateRequest($request)->create();

        $this->assertSame('JEDNOM', $action->coupon);
        $this->assertSame(0, (int) $action->quantity);
        $this->assertSame(1, (int) $action->once_per_email);
    }

    public function test_inactive_total_coupon_is_not_accepted(): void
    {
        $this->createTotalCouponAction('NEAKTIVAN', 10, ['status' => 0]);

        $this->postJson('/api/v2/cart/coupon', [
            'coupon' => 'neaktivan',
        ])->assertOk()
            ->assertJson([
                'success' => false,
                'coupon' => '',
            ]);
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

    public function test_product_action_coupon_is_accepted_when_it_discounts_cart_item(): void
    {
        $actionId = (int) Action::query()->insertGetId([
            'title' => 'Hvala od srca',
            'type' => 'P',
            'discount' => 20,
            'group' => 'product',
            'links' => json_encode([]),
            'date_start' => now()->subDay(),
            'date_end' => now()->addDay(),
            'coupon' => 'HVALAODSRCA',
            'quantity' => 1,
            'lock' => 0,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = $this->createProduct('Artikl s kupon akcijom', 'HVALA-1', [
            'action_id' => $actionId,
            'price' => 100,
            'special' => 80,
            'special_from' => now()->subDay(),
            'special_to' => now()->addDay(),
        ]);

        $this->postJson('/api/v2/cart/add', [
            'item' => [
                'id' => $productId,
                'quantity' => 1,
            ],
        ])->assertOk();

        $response = $this->postJson('/api/v2/cart/coupon', [
            'coupon' => 'hvalaodsrca',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'coupon' => 'HVALAODSRCA',
            ]);

        $response->assertSessionHas(config('session.cart') . '_coupon', 'HVALAODSRCA');
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

    public function test_total_coupon_does_not_stack_with_equal_product_discount(): void
    {
        $this->createTotalCouponAction('HVALAODSRCA', 20);

        $productId = $this->createProduct('Artikl na akciji', 'AKCIJA-20', [
            'price' => 70,
            'special' => 56,
            'special_from' => now()->subDay(),
            'special_to' => now()->addDay(),
        ]);

        $this->postJson('/api/v2/cart/add', [
            'item' => [
                'id' => $productId,
                'quantity' => 1,
            ],
        ])->assertOk();

        $response = $this->postJson('/api/v2/cart/coupon', [
            'coupon' => 'HVALAODSRCA',
        ]);

        $response->assertOk();

        $this->assertEqualsWithDelta(56.0, (float) $response->json('cart.total'), 0.01);
        $this->assertFalse($this->cartHasDetailCondition($response->json('cart'), 'Kupon HVALAODSRCA'));
    }

    public function test_total_coupon_replaces_smaller_product_discount(): void
    {
        $this->createTotalCouponAction('HVALAODSRCA', 20);

        $productId = $this->createProduct('Artikl s manjom akcijom', 'AKCIJA-10', [
            'price' => 70,
            'special' => 63,
            'special_from' => now()->subDay(),
            'special_to' => now()->addDay(),
        ]);

        $this->postJson('/api/v2/cart/add', [
            'item' => [
                'id' => $productId,
                'quantity' => 1,
            ],
        ])->assertOk();

        $response = $this->postJson('/api/v2/cart/coupon', [
            'coupon' => 'HVALAODSRCA',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'coupon' => 'HVALAODSRCA',
            ]);

        $this->assertEqualsWithDelta(70.0, (float) $response->json('cart.subtotal'), 0.01);
        $this->assertEqualsWithDelta(56.0, (float) $response->json('cart.total'), 0.01);
        $this->assertTrue($this->cartHasDetailCondition($response->json('cart'), 'Kupon HVALAODSRCA', -14.0));
    }

    private function createProduct(string $name, string $sku, array $overrides = []): int
    {
        return (int) DB::table('products')->insertGetId(array_merge([
            'author_id' => 0,
            'publisher_id' => 0,
            'action_id' => 0,
            'name' => $name,
            'sku' => $sku,
            'ean' => null,
            'description' => null,
            'slug' => Str::slug($name . '-' . $sku),
            'url' => '/proizvod/' . Str::slug($name . '-' . $sku),
            'image' => null,
            'price' => 100,
            'quantity' => 5,
            'tax_id' => 1,
            'special' => null,
            'special_from' => null,
            'special_to' => null,
            'special_lock' => 0,
            'meta_title' => $name,
            'meta_description' => $name,
            'related_products' => null,
            'pages' => null,
            'dimensions' => null,
            'origin' => null,
            'letter' => null,
            'condition' => null,
            'binding' => null,
            'year' => null,
            'viewed' => 0,
            'sort_order' => 0,
            'push' => 0,
            'status' => 1,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ], $overrides));
    }

    private function createTotalCouponAction(string $coupon, int $discount, array $overrides = []): Action
    {
        return Action::query()->create(array_merge([
            'title' => 'Test coupon ' . $coupon,
            'type' => 'P',
            'discount' => $discount,
            'group' => 'total',
            'links' => json_encode(['total']),
            'date_start' => now()->subDay(),
            'date_end' => now()->addDay(),
            'coupon' => $coupon,
            'quantity' => 1,
            'status' => 1,
        ], $overrides));
    }

    private function createCouponOrder(string $email, string $coupon, int $status): int
    {
        return (int) DB::table('orders')->insertGetId([
            'user_id' => 0,
            'affiliate_id' => 0,
            'order_status_id' => $status,
            'invoice' => '',
            'total' => 90,
            'coupon_code' => $coupon,
            'payment_fname' => 'Test',
            'payment_lname' => 'Kupac',
            'payment_address' => 'Testna 1',
            'payment_zip' => '10000',
            'payment_city' => 'Zagreb',
            'payment_phone' => '0991234567',
            'payment_email' => $email,
            'payment_method' => 'Pouzeće',
            'payment_code' => 'cod',
            'payment_card' => '',
            'payment_installment' => 0,
            'shipping_fname' => 'Test',
            'shipping_lname' => 'Kupac',
            'shipping_address' => 'Testna 1',
            'shipping_zip' => '10000',
            'shipping_city' => 'Zagreb',
            'shipping_phone' => '0991234567',
            'shipping_email' => $email,
            'shipping_method' => 'Dostava',
            'shipping_code' => 'gls',
            'company' => '',
            'oib' => '',
            'comment' => '',
            'tracking_code' => '',
            'shipped' => 0,
            'printed' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function cartHasDetailCondition(?array $cart, string $name, ?float $value = null): bool
    {
        return collect($cart['detail_con'] ?? [])->contains(function (array $condition) use ($name, $value) {
            if (($condition['name'] ?? '') !== $name) {
                return false;
            }

            if ($value === null) {
                return true;
            }

            return abs((float) ($condition['value'] ?? 0) - $value) < 0.01;
        });
    }
}
