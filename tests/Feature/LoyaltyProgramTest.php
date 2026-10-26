<?php

namespace Tests\Feature;

use App\Console\Commands\CheckUsersBirthday;
use App\Helpers\OrderHelper;
use App\Mail\OrderSent;
use App\Models\Back\Orders\Order as AdminOrder;
use App\Models\Front\AgCart;
use App\Models\Front\Loyalty;
use App\Models\User;
use App\Models\UserDetail;
use Darryldecode\Cart\Facades\CartFacade as Cart;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LoyaltyProgramTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-25 12:00:00', 'Europe/Zagreb'));

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        config()->set('session.cart', 'zuzi_cart');
        config()->set('settings.loyalty.first_order_points', 50);
        config()->set('settings.loyalty.birthday_points', 100);
        config()->set('settings.loyalty.points_per_euro', 1);
        config()->set('settings.loyalty.redemption_paused_from', '2026-10-26');
        config()->set('settings.loyalty.redemption_resumes_at', '2026-12-01');
        config()->set('settings.loyalty.rewards.orders_per_month', [
            3 => 20,
            2 => 10,
        ]);
        config()->set('settings.loyalty.orders_discount', [
            200 => 12,
            100 => 5,
        ]);
        config()->set('settings.order.status.paid', 3);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
    }

    public function test_exact_threshold_points_unlock_loyalty_discount(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Loyalty::addPoints(100, 0, 'admin', 'Manual top-up', $user->id);

        $this->assertSame(100, Loyalty::hasLoyalty());
        $this->assertSame(5, Loyalty::calculateLoyalty(100));
    }

    /**
     * @dataProvider redemptionPauseBoundaries
     */
    public function test_redemption_pause_uses_zagreb_dates_without_changing_balance(
        string $date,
        string $timezone,
        bool $enabled
    ): void {
        $this->travelTo(Carbon::parse($date, $timezone));

        $user = User::factory()->create();
        $this->actingAs($user);
        Loyalty::addPoints(100, 0, 'admin', 'Opening balance', $user->id);

        $this->assertSame($enabled, Loyalty::redemptionEnabled());
        $this->assertSame($enabled ? 100 : 0, Loyalty::hasLoyalty());
        $this->assertSame($enabled ? 5 : 0, Loyalty::calculateLoyalty(100));
        $this->assertSame(100, Loyalty::hasLoyaltyTotal($user->id));
    }

    public function redemptionPauseBoundaries(): array
    {
        return [
            'last instant before the pause' => ['2026-10-25 23:59:59.999999', 'Europe/Zagreb', true],
            'pause starts at local midnight' => ['2026-10-26 00:00:00', 'Europe/Zagreb', false],
            'November remains paused' => ['2026-11-15 12:00:00', 'Europe/Zagreb', false],
            'last instant in November' => ['2026-11-30 23:59:59.999999', 'Europe/Zagreb', false],
            'redemption resumes at local midnight' => ['2026-12-01 00:00:00', 'Europe/Zagreb', true],
            'UTC instant before local start' => ['2026-10-25 22:59:59.999999', 'UTC', true],
            'UTC instant at local start' => ['2026-10-25 23:00:00', 'UTC', false],
            'UTC instant before local end' => ['2026-11-30 22:59:59.999999', 'UTC', false],
            'UTC instant at local end' => ['2026-11-30 23:00:00', 'UTC', true],
        ];
    }

    public function test_order_and_welcome_points_are_earned_during_pause_without_spending_stale_selection(): void
    {
        $this->travelTo(Carbon::parse('2026-11-15 12:00:00', 'Europe/Zagreb'));

        $user = User::factory()->create();
        $this->actingAs($user);
        Loyalty::addPoints(100, 0, 'admin', 'Opening balance', $user->id);

        $order = $this->createOrder($user, 25);

        (new OrderHelper($order->id))->addLoyaltyPoints(100);

        $this->assertSame(175, Loyalty::hasLoyaltyTotal($user->id));
        $this->assertSame(0, (int) Loyalty::query()->where('user_id', $user->id)->sum('spend'));
        $this->assertDatabaseHas('loyalty', [
            'user_id' => $user->id,
            'reference_id' => $order->id,
            'reference' => 'order',
            'earned' => 25,
            'spend' => 0,
        ]);
        $this->assertDatabaseHas('loyalty', [
            'user_id' => $user->id,
            'reference_id' => $order->id,
            'reference' => 'order',
            'comment' => 'First order reward.',
            'earned' => 50,
            'spend' => 0,
        ]);
        $this->assertSame(0, Loyalty::hasLoyalty());
        $this->assertSame(0, Loyalty::calculateLoyalty(100));
    }

    public function test_legacy_order_resolution_earns_points_without_spending_during_pause(): void
    {
        $this->travelTo(Carbon::parse('2026-11-30 23:59:59', 'Europe/Zagreb'));

        $user = User::factory()->create();
        $this->actingAs($user);
        Loyalty::addPoints(100, 0, 'admin', 'Opening balance', $user->id);
        $order = $this->createOrder($user, 25);

        $this->assertTrue(Loyalty::resolveOrder(['loyalty' => 100], $order));

        $this->assertSame(125, Loyalty::hasLoyaltyTotal($user->id));
        $this->assertDatabaseHas('loyalty', [
            'user_id' => $user->id,
            'reference_id' => $order->id,
            'reference' => 'order',
            'earned' => 25,
            'spend' => 0,
        ]);
    }

    public function test_cart_clears_stale_redemption_and_refuses_selection_until_december(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Loyalty::addPoints(100, 0, 'admin', 'Opening balance', $user->id);

        $cartId = 'loyalty-redemption-pause';
        $loyaltySessionKey = config('session.cart') . '_loyalty';
        Cart::session($cartId)->add([
            'id' => 1,
            'name' => 'Test book',
            'price' => 100,
            'quantity' => 1,
            'attributes' => [],
        ]);

        $cart = new AgCart($cartId);
        $this->assertSame(100, $cart->loyalty(100));
        $beforePause = $cart->get();
        $this->assertTrue($beforePause['loyalty_redemption_enabled']);
        $this->assertSame(100, $beforePause['loyalty']);
        $this->assertSame(100, session($loyaltySessionKey));
        $this->assertTrue(collect($beforePause['detail_con'])->contains('name', 'Loyalty'));
        $this->assertEquals(95, $beforePause['total']);

        $this->travelTo(Carbon::parse('2026-10-26 00:00:00', 'Europe/Zagreb'));
        $cart = new AgCart($cartId);
        $duringPause = $cart->get();

        $this->assertFalse($duringPause['loyalty_redemption_enabled']);
        $this->assertSame('', $duringPause['loyalty']);
        $this->assertSame(0, $duringPause['has_loyalty']);
        $this->assertFalse(session()->has($loyaltySessionKey));
        $this->assertFalse(collect($duringPause['detail_con'])->contains('name', 'Loyalty'));
        $this->assertFalse(Cart::session($cartId)->getConditions()->contains(
            fn ($condition) => $condition->getName() === 'Loyalty'
        ));
        $this->assertEquals(100, $duringPause['total']);
        $this->assertSame(0, $cart->loyalty(100));
        $this->assertFalse(session()->has($loyaltySessionKey));
        $this->assertEquals(100, $cart->get()['total']);

        $this->travelTo(Carbon::parse('2026-12-01 00:00:00', 'Europe/Zagreb'));
        $cart = new AgCart($cartId);
        $afterPause = $cart->get();
        $this->assertTrue($afterPause['loyalty_redemption_enabled']);
        $this->assertSame(100, $afterPause['has_loyalty']);
        $this->assertSame('', $afterPause['loyalty']);
        $this->assertEquals(100, $afterPause['total']);
        $this->assertSame(100, $cart->loyalty(100));
        $this->assertEquals(95, $cart->get()['total']);
        $this->assertSame(100, Loyalty::hasLoyaltyTotal($user->id));
    }

    public function test_first_order_receives_welcome_points(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $order = $this->createOrder($user, 25);

        (new OrderHelper($order->id))->addLoyaltyPoints();

        $this->assertSame(75, Loyalty::hasLoyaltyTotal($user->id));
        $this->assertDatabaseHas('loyalty', [
            'user_id' => $user->id,
            'reference_id' => $order->id,
            'reference' => 'order',
            'comment' => 'First order reward.',
            'earned' => 50,
        ]);
    }

    public function test_loyalty_discount_spends_points_when_order_is_completed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Loyalty::addPoints(100, 0, 'admin', 'Opening balance', $user->id);
        $this->createOrder($user, 15, now()->subMonths(2));

        $order = $this->createOrder($user, 20);

        DB::table('order_total')->insert([
            'order_id' => $order->id,
            'code' => 'special',
            'title' => 'Loyalty',
            'value' => -5,
            'sort_order' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new OrderHelper($order->id))->addLoyaltyPoints(100);

        $this->assertSame(20, Loyalty::hasLoyaltyTotal($user->id));
        $this->assertDatabaseHas('loyalty', [
            'user_id' => $user->id,
            'reference_id' => $order->id,
            'reference' => 'order',
            'comment' => 'Loyalty discount redemption.',
            'spend' => 100,
        ]);
    }

    public function test_birthday_command_awards_points_only_once_per_year(): void
    {
        $user = User::factory()->create();

        UserDetail::query()->create([
            'user_id' => $user->id,
            'fname' => 'Test',
            'lname' => 'User',
            'role' => 'customer',
            'birthday' => now()->setTime(12, 0),
        ]);

        Artisan::call(CheckUsersBirthday::class);
        Artisan::call(CheckUsersBirthday::class);

        $this->assertSame(
            intval(config('settings.loyalty.birthday_points')),
            Loyalty::hasLoyaltyTotal($user->id)
        );
        $this->assertEquals(1, Loyalty::query()
            ->where('user_id', $user->id)
            ->where('reference', 'birthday')
            ->where('target', (string) now()->year)
            ->count());
    }

    public function test_registered_order_email_contains_loyalty_points_and_account_link(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $order = $this->createOrder(
            $user,
            25,
            now(),
            config('settings.order.status.paid'),
            'corvus'
        );

        (new OrderHelper($order->id))->addLoyaltyPoints();

        $html = (new OrderSent($order->fresh()))->render();

        $this->assertStringContainsString('75 Loyalty bodova', $html);
        $this->assertStringContainsString('/moj-racun/loyalty', $html);
        $this->assertStringContainsString('Ovom narudžbom ostvarili ste', $html);
    }

    public function test_user_get_affiliate_link_generates_missing_affiliate_name(): void
    {
        $user = User::factory()->create([
            'name' => 'Ana Maric',
        ]);

        UserDetail::query()->create([
            'user_id' => $user->id,
            'fname' => 'Ana',
            'lname' => 'Maric',
            'role' => 'customer',
            'affiliate_name' => null,
        ]);

        $affiliateLink = $user->fresh()->getAffiliateLink();

        $this->assertNotEmpty($affiliateLink);
        $this->assertStringStartsWith('ana', $affiliateLink);
        $this->assertDatabaseHas('user_details', [
            'user_id' => $user->id,
            'affiliate_name' => $affiliateLink,
        ]);
    }

    private function createOrder(
        User $user,
        float $total,
        $createdAt = null,
        ?int $status = null,
        string $paymentCode = 'cod'
    ): AdminOrder
    {
        $createdAt = $createdAt ?: now();
        $status = $status ?: 1;

        $order = AdminOrder::query()->create([
            'user_id' => $user->id,
            'affiliate_id' => 0,
            'order_status_id' => $status,
            'invoice' => '',
            'total' => $total,
            'payment_fname' => 'Test',
            'payment_lname' => 'User',
            'payment_address' => 'Test 1',
            'payment_zip' => '10000',
            'payment_city' => 'Zagreb',
            'payment_phone' => '',
            'payment_email' => $user->email,
            'payment_method' => 'Plaćanje pouzećem',
            'payment_code' => $paymentCode,
            'payment_card' => '',
            'payment_installment' => 0,
            'shipping_fname' => 'Test',
            'shipping_lname' => 'User',
            'shipping_address' => 'Test 1',
            'shipping_zip' => '10000',
            'shipping_city' => 'Zagreb',
            'shipping_phone' => '',
            'shipping_email' => $user->email,
            'shipping_method' => 'Dostava',
            'shipping_code' => 'gls',
            'company' => '',
            'oib' => '',
            'comment' => '',
            'tracking_code' => '',
        ]);

        $order->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $order;
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('user_details', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('user_id');
            $table->string('fname');
            $table->string('lname')->nullable();
            $table->string('affiliate_name')->nullable();
            $table->dateTime('birthday')->nullable();
            $table->string('role');
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id')->unsigned()->default(0);
            $table->bigInteger('affiliate_id')->unsigned()->default(0);
            $table->integer('order_status_id')->unsigned();
            $table->string('invoice')->nullable();
            $table->decimal('total', 15, 4)->default(0);
            $table->string('payment_fname');
            $table->string('payment_lname');
            $table->string('payment_address');
            $table->string('payment_zip');
            $table->string('payment_city');
            $table->string('payment_phone')->nullable();
            $table->string('payment_email');
            $table->string('payment_method');
            $table->string('payment_code')->nullable();
            $table->string('payment_card')->nullable();
            $table->integer('payment_installment')->unsigned()->default(0);
            $table->string('shipping_fname');
            $table->string('shipping_lname');
            $table->string('shipping_address');
            $table->string('shipping_zip');
            $table->string('shipping_city');
            $table->string('shipping_phone')->nullable();
            $table->string('shipping_email');
            $table->string('shipping_method');
            $table->string('shipping_code')->nullable();
            $table->string('company');
            $table->string('oib');
            $table->text('comment')->nullable();
            $table->string('tracking_code');
            $table->boolean('shipped')->default(false);
            $table->boolean('printed')->default(false);
            $table->timestamps();
        });

        Schema::create('gift_vouchers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id')->index();
            $table->timestamps();
        });

        Schema::create('order_total', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id')->index();
            $table->string('code');
            $table->string('title');
            $table->decimal('value', 15, 4)->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('order_products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('product_id')->default(0);
            $table->string('name')->nullable();
            $table->integer('quantity')->default(0);
            $table->decimal('org_price', 15, 4)->default(0);
            $table->decimal('discount', 15, 4)->nullable();
            $table->decimal('price', 15, 4)->default(0);
            $table->decimal('total', 15, 4)->default(0);
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
    }
}
