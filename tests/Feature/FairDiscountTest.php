<?php

namespace Tests\Feature;

use App\Helpers\Helper;
use App\Helpers\Session\CheckoutSession;
use App\Models\Back\Marketing\Action;
use App\Models\Front\AgCart;
use App\Models\Front\Checkout\ShippingMethod;
use App\Models\User;
use App\Models\UserDetail;
use Bouncer;
use Darryldecode\Cart\Facades\CartFacade as Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FairDiscountTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_fair_discount_with_box_now_enabled(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('marketing.fair-discounts.create'))
            ->assertOk()
            ->assertSee('Sajamski popust ' . now()->year)
            ->assertSee('Besplatna BOX NOW dostava dok akcija traje');

        $response = $this->post(route('marketing.fair-discounts.store'), [
            'title' => 'Sajam test',
            'date_start' => '23.09.2026',
            'date_end' => '28.09.2026',
            'status' => 'on',
            'free_boxnow' => 'on',
            'tiers' => [
                ['min_total' => 0, 'max_total' => 50, 'discount' => 10],
                ['min_total' => 50.01, 'max_total' => 99.99, 'discount' => 15],
                ['min_total' => 100, 'max_total' => '', 'discount' => 20],
            ],
        ]);

        $action = Action::query()->where('group', Action::GROUP_FAIR_DISCOUNT)->firstOrFail();

        $response->assertRedirect(route('marketing.fair-discounts.edit', ['fairDiscount' => $action]));
        $this->assertSame('2026-09-23 00:00:00', $action->date_start);
        $this->assertSame('2026-09-28 23:59:59', $action->date_end);
        $this->assertTrue((bool) data_get($action->data, 'free_boxnow'));
        $this->assertCount(3, Action::normalizeFairDiscountTiers($action->data));
    }

    public function test_fair_discount_resolves_the_configured_amount_ranges(): void
    {
        $action = $this->createFairDiscountAction();

        $this->assertSame(10.0, Action::resolveFairDiscountTierForTotal($action, 50.00)['discount']);
        $this->assertSame(15.0, Action::resolveFairDiscountTierForTotal($action, 50.01)['discount']);
        $this->assertSame(15.0, Action::resolveFairDiscountTierForTotal($action, 99.99)['discount']);
        $this->assertSame(20.0, Action::resolveFairDiscountTierForTotal($action, 100.00)['discount']);
    }

    public function test_fair_discount_condition_uses_the_cart_item_total_and_does_not_accept_a_coupon(): void
    {
        $this->createFairDiscountAction();
        $cart = Cart::session('fair-discount-test');
        $cart->clear();
        $cart->clearCartConditions();
        $cart->add([
            'id' => 1,
            'name' => 'Test product',
            'price' => 100,
            'quantity' => 1,
            'attributes' => [],
        ]);

        $condition = Helper::hasFairDiscountCartCondition($cart);

        $this->assertNotFalse($condition);
        $this->assertSame('Sajamski popust 2026 20%', $condition->getName());
        $this->assertSame(-20.0, (float) $condition->getValue());
        $this->assertSame('fair_discount', $condition->getAttributes()['type']);
        $this->assertFalse(Helper::hasFairDiscountCartCondition($cart, 'KUPON10'));
    }

    public function test_best_automatic_promotion_prevents_fair_and_bogo_discounts_from_stacking(): void
    {
        $this->createFairDiscountAction();
        Action::query()->create([
            'title' => 'BOGO 10',
            'type' => 'P',
            'discount' => 10,
            'group' => Action::GROUP_BOGO,
            'links' => json_encode([Action::GROUP_BOGO]),
            'date_start' => now()->subDay(),
            'date_end' => now()->addDay(),
            'data' => json_encode(['tiers' => [['quantity' => 1, 'discount' => 10]]]),
            'coupon' => null,
            'quantity' => 0,
            'lock' => 0,
            'status' => 1,
        ]);

        $cart = Cart::session('fair-bogo-test');
        $cart->clear();
        $cart->clearCartConditions();
        $cart->add([
            'id' => 1,
            'name' => 'Test product',
            'price' => 100,
            'quantity' => 1,
            'attributes' => [],
        ]);

        $condition = Helper::bestAutomaticCartCondition([
            Helper::hasBogoCartCondition($cart),
            Helper::hasFairDiscountCartCondition($cart),
        ]);

        $this->assertSame('fair_discount', $condition->getAttributes()['type']);
        $this->assertSame(-20.0, (float) $condition->getValue());
    }

    public function test_cart_applies_only_the_best_of_fair_and_bogo_promotions(): void
    {
        $this->createFairDiscountAction();
        Action::query()->create([
            'title' => 'BOGO 10',
            'type' => 'P',
            'discount' => 10,
            'group' => Action::GROUP_BOGO,
            'links' => json_encode([Action::GROUP_BOGO]),
            'date_start' => now()->subDay(),
            'date_end' => now()->addDay(),
            'data' => json_encode(['tiers' => [['quantity' => 1, 'discount' => 10]]]),
            'coupon' => null,
            'quantity' => 0,
            'lock' => 0,
            'status' => 1,
        ]);

        $cartId = 'fair-cart-integration-test';
        $cart = Cart::session($cartId);
        $cart->clear();
        $cart->clearCartConditions();
        $cart->add([
            'id' => 1,
            'name' => 'Test product',
            'price' => 100,
            'quantity' => 1,
            'attributes' => [],
        ]);

        $cartData = (new AgCart($cartId))->get();
        $automaticConditions = collect($cartData['detail_con'])
            ->filter(fn (array $condition) => in_array(data_get($condition, 'attributes.type'), ['bogo', 'fair_discount'], true));

        $this->assertCount(1, $automaticConditions);
        $this->assertSame('fair_discount', data_get($automaticConditions->first(), 'attributes.type'));
        $this->assertEqualsWithDelta(80.0, (float) $cartData['total'], 0.01);
    }

    public function test_box_now_is_free_only_while_an_enabled_fair_action_is_active(): void
    {
        Carbon::setTestNow('2026-09-24 12:00:00');
        $action = $this->createFairDiscountAction([
            'date_start' => '2026-09-23 00:00:00',
            'date_end' => '2026-09-28 23:59:59',
        ]);
        $boxNow = $this->shippingMethod('BoxNow paketomat', 'gls_eu', 3.5);
        $boxNowNewCode = $this->shippingMethod('BOX NOW', 'boxnow', 3.5);
        $gls = $this->shippingMethod('GLS', 'gls', 4.0);

        CheckoutSession::setShipping('gls_eu');

        $promo = Action::activeFairDiscountCartPromo();

        $this->assertSame('Sajamski popust 2026', $promo['title']);
        $this->assertSame('100,00 €+', data_get($promo, 'tiers.2.range_label'));
        $this->assertSame('BOX NOW dostava je besplatna dok traje akcija.', $promo['free_boxnow_label']);
        $this->assertSame(0.0, ShippingMethod::priceForTotal($boxNow, 20));
        $this->assertSame(0.0, ShippingMethod::priceForTotal($boxNowNewCode, 20));
        $this->assertSame(4.0, ShippingMethod::priceForTotal($gls, 20));

        $action->update(['status' => 0]);

        $this->assertSame(3.5, ShippingMethod::priceForTotal($boxNow, 20));
        Carbon::setTestNow();
    }

    public function test_box_now_remains_free_during_the_fair_even_when_a_coupon_is_entered(): void
    {
        Carbon::setTestNow('2026-09-24 12:00:00');
        $this->createFairDiscountAction([
            'date_start' => '2026-09-23 00:00:00',
            'date_end' => '2026-09-28 23:59:59',
        ]);
        session([config('session.cart') . '_coupon' => 'KUPON10']);

        $this->assertSame(
            0.0,
            ShippingMethod::priceForTotal($this->shippingMethod('BOX NOW', 'boxnow', 3.5), 20)
        );

        Carbon::setTestNow();
    }

    private function createFairDiscountAction(array $overrides = []): Action
    {
        return Action::query()->create(array_merge([
            'title' => 'Sajamski popust 2026',
            'type' => 'P',
            'discount' => 20,
            'group' => Action::GROUP_FAIR_DISCOUNT,
            'links' => json_encode([Action::GROUP_FAIR_DISCOUNT]),
            'date_start' => now()->subDay(),
            'date_end' => now()->addDay(),
            'data' => json_encode([
                'tiers' => [
                    ['min_total' => 0, 'max_total' => 50, 'discount' => 10],
                    ['min_total' => 50.01, 'max_total' => 99.99, 'discount' => 15],
                    ['min_total' => 100, 'max_total' => null, 'discount' => 20],
                ],
                'free_boxnow' => true,
            ]),
            'coupon' => null,
            'quantity' => 0,
            'lock' => 0,
            'status' => 1,
        ], $overrides));
    }

    private function shippingMethod(string $title, string $code, float $price): object
    {
        return (object) [
            'title' => $title,
            'code' => $code,
            'geo_zone' => 1,
            'data' => (object) [
                'price' => $price,
                'short_description' => $title,
            ],
        ];
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        UserDetail::query()->create([
            'user_id' => $admin->id,
            'fname' => 'Admin',
            'lname' => 'Sajam',
            'role' => 'admin',
        ]);
        Bouncer::allow($admin)->everything();

        return $admin;
    }
}
