<?php

namespace Tests\Feature;

use App\Helpers\OrderHelper;
use App\Helpers\Session\CheckoutSession;
use App\Models\Back\Marketing\Action;
use App\Models\Back\Orders\Order;
use App\Models\Front\AgCart;
use App\Models\Front\Loyalty;
use App\Models\User;
use Darryldecode\Cart\Facades\CartFacade as Cart;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LoyaltyCouponCombinationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-02 12:00:00', 'Europe/Zagreb'));
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        config()->set('session.cart', 'zuzi_cart');
        config()->set('settings.special_action.start', null);
        config()->set('settings.loyalty.first_order_points', 0);
        config()->set('settings.loyalty.rewards.orders_per_month', []);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        $this->createSchema();
    }

    /**
     * @dataProvider combinationOrders
     */
    public function test_zuzi20_and_loyalty_combine_in_either_order_and_survive_reloading(
        bool $couponFirst,
        int $points,
        float $reward
    ): void {
        $user = $this->customerWithPoints(200);
        $this->createCoupon('ZUZI20', 20);
        $cart = $this->bookCart();

        if ($couponFirst) {
            $this->assertTrue($cart->coupon(' zuzi20 ')['success']);
            $this->assertSame(200, $cart->loyalty($points));
        } else {
            $this->assertSame(200, $cart->loyalty($points));
            $this->assertTrue($cart->coupon('zuzi20')['success']);
        }

        foreach ([$cart, new AgCart('combination-cart')] as $currentCart) {
            $data = $currentCart->get();
            $this->assertSame('ZUZI20', $data['coupon']);
            $this->assertTrue($data['loyalty_coupon_compatible']);
            $this->assertSame($points, $data['loyalty']);
            $this->assertSame($points, session('zuzi_cart_loyalty'));
            $this->assertCondition($data, 'Kupon ZUZI20', -20);
            $this->assertCondition($data, 'Loyalty', -$reward);
            $this->assertEqualsWithDelta(80 - $reward, $data['total'], 0.001);
        }

        $this->assertSame(200, Loyalty::hasLoyaltyTotal($user->id));
    }

    public function combinationOrders(): array
    {
        return [
            'coupon then 100 points' => [true, 100, 5.0],
            '100 points then coupon' => [false, 100, 5.0],
            'coupon then 200 points' => [true, 200, 12.0],
            '200 points then coupon' => [false, 200, 12.0],
        ];
    }

    public function test_other_coupons_still_clear_loyalty_and_reject_reselection(): void
    {
        $this->customerWithPoints(100);
        $this->createCoupon('HVALA10', 10);
        $cart = $this->bookCart();
        $this->assertSame(100, $cart->loyalty(100));

        $response = $cart->coupon('HVALA10');

        $this->assertTrue($response['success']);
        $this->assertFalse($response['cart']['loyalty_coupon_compatible']);
        $this->assertSame('', $response['cart']['loyalty']);
        $this->assertFalse(session()->has('zuzi_cart_loyalty'));
        $this->assertSame(0, $cart->loyalty(100));
        $this->assertNoLoyaltyCondition($cart->get());
        $this->assertEqualsWithDelta(90, $cart->get()['total'], 0.001);
    }

    public function test_insufficient_points_do_not_apply_a_reward_or_keep_a_selection(): void
    {
        $user = $this->customerWithPoints(99);
        $this->createCoupon('ZUZI20', 20);
        $cart = $this->bookCart();
        $this->assertTrue($cart->coupon('ZUZI20')['success']);

        $cart->loyalty(100);
        $data = $cart->get();

        $this->assertNoLoyaltyCondition($data);
        $this->assertSame('', $data['loyalty']);
        $this->assertFalse(session()->has('zuzi_cart_loyalty'));
        $this->assertEqualsWithDelta(80, $data['total'], 0.001);
        $this->assertSame(99, Loyalty::hasLoyaltyTotal($user->id));
    }

    public function test_loyalty_cannot_consume_the_remaining_merchandise_value_or_discount_shipping(): void
    {
        $this->customerWithPoints(100);
        $this->createCoupon('ZUZI20', 20);
        $this->addShipping(5);
        $cart = $this->bookCart(6.25);
        $this->assertTrue($cart->coupon('ZUZI20')['success']);

        $cart->loyalty(100);
        $data = $cart->get();

        $this->assertCondition($data, 'Kupon ZUZI20', -1.25);
        $this->assertCondition($data, 'Test dostava', 5);
        $this->assertNoLoyaltyCondition($data);
        $this->assertSame('', $data['loyalty']);
        $this->assertFalse(session()->has('zuzi_cart_loyalty'));
        $this->assertEqualsWithDelta(10, $data['total'], 0.001);
    }

    public function test_an_expired_zuzi20_coupon_is_rejected_without_removing_a_valid_loyalty_reward(): void
    {
        $this->customerWithPoints(100);
        $coupon = $this->createCoupon('ZUZI20', 20);
        $coupon->update(['date_end' => now()->subMinute()]);
        $cart = $this->bookCart();
        $this->assertSame(100, $cart->loyalty(100));

        $response = $cart->coupon('ZUZI20');

        $this->assertFalse($response['success']);
        $this->assertSame('', $response['coupon']);
        $this->assertSame(100, $response['cart']['loyalty']);
        $this->assertCondition($response['cart'], 'Loyalty', -5);
        $this->assertEqualsWithDelta(95, $response['cart']['total'], 0.001);
    }

    public function test_gift_voucher_purchase_still_excludes_both_coupon_and_loyalty(): void
    {
        $this->customerWithPoints(100);
        $this->createCoupon('ZUZI20', 20);
        $cart = $this->bookCart();
        $cart->loyalty(100);
        Cart::session('combination-cart')->add([
            'id' => 'gift-voucher-test', 'name' => 'Poklon bon', 'price' => 20,
            'quantity' => 1, 'attributes' => ['item_type' => 'gift_voucher'],
        ]);

        $data = $cart->get();

        $this->assertSame('', $data['loyalty']);
        $this->assertFalse(session()->has('zuzi_cart_loyalty'));
        $this->assertNoLoyaltyCondition($data);
        $this->assertFalse($cart->coupon('ZUZI20')['success']);
        $this->assertSame(0, $cart->loyalty(100));
    }

    public function test_fair_discount_still_excludes_both_coupon_and_loyalty(): void
    {
        $this->customerWithPoints(100);
        $this->createCoupon('ZUZI20', 20);
        Action::query()->create([
            'title' => 'Test sajam', 'type' => 'P', 'discount' => 20,
            'group' => Action::GROUP_FAIR_DISCOUNT, 'links' => json_encode([Action::GROUP_FAIR_DISCOUNT]),
            'date_start' => now()->subDay(), 'date_end' => now()->addDay(),
            'coupon' => null, 'quantity' => 0, 'status' => 1,
            'data' => json_encode(['tiers' => [['min_total' => 0, 'max_total' => null, 'discount' => 20]]]),
        ]);
        $cart = $this->bookCart();

        $this->assertFalse($cart->coupon('ZUZI20')['success']);
        $this->assertSame(0, $cart->loyalty(100));
        $data = $cart->get();
        $this->assertSame('', $data['coupon']);
        $this->assertSame('', $data['loyalty']);
        $this->assertNoLoyaltyCondition($data);
        $this->assertEqualsWithDelta(80, $data['total'], 0.001);
    }

    public function test_combined_order_spends_points_once_and_earns_on_its_final_total(): void
    {
        $user = $this->customerWithPoints(100);
        $this->createCoupon('ZUZI20', 20);
        $cart = $this->bookCart();
        $this->assertTrue($cart->coupon('ZUZI20')['success']);
        $cart->loyalty(100);
        $data = $cart->get();
        $this->assertEqualsWithDelta(75, $data['total'], 0.001);

        $order = Order::query()->create([
            'user_id' => $user->id,
            'total' => $data['total'],
            'payment_email' => $user->email,
            'payment_code' => 'cod',
            'order_status_id' => 1,
        ]);
        foreach ($data['detail_con'] as $condition) {
            DB::table('order_total')->insert([
                'order_id' => $order->id,
                'code' => $condition['type'],
                'title' => $condition['name'],
                'value' => $condition['value'],
            ]);
        }

        (new OrderHelper($order->id))->addLoyaltyPoints();
        (new OrderHelper($order->id))->addLoyaltyPoints();

        $this->assertSame(75, Loyalty::hasLoyaltyTotal($user->id));
        $this->assertSame(100, (int) Loyalty::query()->where('reference_id', $order->id)->sum('spend'));
        $this->assertSame(75, (int) Loyalty::query()->where('reference_id', $order->id)->sum('earned'));
    }

    private function customerWithPoints(int $points): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Loyalty::addPoints($points, 0, 'admin', 'Opening balance', $user->id);

        return $user;
    }

    private function createCoupon(string $code, int $discount): Action
    {
        return Action::query()->create([
            'title' => 'Test coupon ' . $code,
            'type' => 'P',
            'discount' => $discount,
            'group' => 'total',
            'links' => json_encode(['total']),
            'date_start' => now()->subDay(),
            'date_end' => now()->addDay(),
            'coupon' => $code,
            'quantity' => 1,
            'status' => 1,
        ]);
    }

    private function bookCart(float $price = 100): AgCart
    {
        $id = DB::table('products')->insertGetId([
            'name' => 'Testna knjiga', 'sku' => 'TEST', 'price' => $price,
            'quantity' => 5, 'tax_id' => 1, 'url' => '/testna-knjiga',
        ]);
        Cart::session('combination-cart')->add([
            'id' => $id, 'name' => 'Testna knjiga', 'price' => $price,
            'quantity' => 1, 'attributes' => [],
        ]);

        return new AgCart('combination-cart');
    }

    private function addShipping(float $price): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('key');
            $table->text('value');
            $table->boolean('json');
        });
        DB::table('settings')->insert([
            'code' => 'shipping',
            'key' => 'list.test',
            'json' => 1,
            'value' => json_encode([[
                'code' => 'test', 'title' => 'Test dostava', 'status' => true,
                'geo_zone' => 1, 'data' => ['price' => $price, 'short_description' => ''],
            ]]),
        ]);
        CheckoutSession::setShipping('test');
    }

    private function assertCondition(array $cart, string $name, float $value): void
    {
        $condition = collect($cart['detail_con'])->firstWhere('name', $name);
        $this->assertNotNull($condition, $name . ' is missing');
        $this->assertEqualsWithDelta($value, (float) $condition['value'], 0.001);
    }

    private function assertNoLoyaltyCondition(array $cart): void
    {
        $this->assertFalse(collect($cart['detail_con'])->contains('name', 'Loyalty'));
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('loyalty', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id');
            $table->bigInteger('reference_id');
            $table->string('reference');
            $table->string('target')->nullable();
            $table->bigInteger('earned')->nullable();
            $table->bigInteger('spend')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku');
            $table->decimal('price', 15, 4);
            $table->integer('quantity');
            $table->integer('tax_id');
            $table->string('url');
            $table->decimal('special', 15, 4)->nullable();
            $table->unsignedBigInteger('action_id')->default(0);
        });
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->default(0);
            $table->string('title');
        });
        Schema::create('product_category', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('category_id');
        });
        Schema::create('product_actions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('type');
            $table->decimal('discount', 15, 4);
            $table->string('group');
            $table->text('links');
            $table->timestamp('date_start')->nullable();
            $table->timestamp('date_end')->nullable();
            $table->string('coupon')->nullable();
            $table->integer('quantity')->default(0);
            $table->boolean('status');
            $table->text('data')->nullable();
            $table->timestamps();
        });
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->decimal('total', 15, 4);
            $table->string('payment_email');
            $table->string('payment_code');
            $table->integer('order_status_id');
            $table->timestamps();
        });
        Schema::create('order_total', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('code');
            $table->string('title');
            $table->decimal('value', 15, 4);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('gift_vouchers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->timestamps();
        });
    }
}
