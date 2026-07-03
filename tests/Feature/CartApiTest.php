<?php

namespace Tests\Feature;

use App\Helpers\Helper;
use App\Models\Back\Marketing\Action;
use Darryldecode\Cart\Facades\CartFacade as Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
