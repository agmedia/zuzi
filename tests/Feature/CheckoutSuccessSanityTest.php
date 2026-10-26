<?php

namespace Tests\Feature;

use App\Helpers\Session\CheckoutSession;
use App\Http\Controllers\Front\CheckoutController;
use App\Models\Back\Orders\Order as AdminOrder;
use App\Models\Front\Loyalty;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Tests\TestCase;

class CheckoutSuccessSanityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        config()->set('session.cart', 'zuzi_cart');
        config()->set('settings.loyalty.first_order_points', 50);
        config()->set('settings.loyalty.points_per_euro', 1);
        config()->set('settings.loyalty.rewards.orders_per_month', []);
        config()->set('settings.loyalty.orders_discount', [
            200 => 12,
            100 => 5,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_success_flow_redeems_loyalty_and_clears_cart_session(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-12-01 12:00:00', 'Europe/Zagreb'));
        Bus::fake();

        $user = User::factory()->create();

        $this->actingAs($user);

        Loyalty::addPoints(100, 0, 'admin', 'Opening balance', $user->id);

        $this->createOrder($user, 15, now()->subMonth());
        $order = $this->createOrder($user, 20);

        DB::table('order_total')->insert([
            [
                'order_id' => $order->id,
                'code' => 'special',
                'title' => 'Loyalty',
                'value' => -5,
                'sort_order' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => $order->id,
                'code' => 'total',
                'title' => 'Sveukupno',
                'value' => 20,
                'sort_order' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        session()->start();
        session([
            config('session.cart') => 'manual-cart',
            config('session.cart') . '_coupon' => 'TEST',
            config('session.cart') . '_loyalty' => 100,
        ]);
        CheckoutSession::setOrder(['id' => $order->id]);

        $response = app(CheckoutController::class)->success(Request::create('/uspjeh', 'GET'));

        $this->assertInstanceOf(View::class, $response);
        $this->assertNotNull($order->fresh()->checkout_processed_at);
        $this->assertSame(20, Loyalty::hasLoyaltyTotal($user->id));
        $this->assertDatabaseHas('loyalty', [
            'user_id' => $user->id,
            'reference_id' => $order->id,
            'reference' => 'order',
            'comment' => 'Loyalty discount redemption.',
            'spend' => 100,
        ]);
        $this->assertFalse(session()->has(config('session.cart') . '_coupon'));
        $this->assertFalse(session()->has(config('session.cart') . '_loyalty'));
        $this->assertFalse(CheckoutSession::hasOrder());
        $this->assertDatabaseHas('carts', [
            'user_id' => $user->id,
            'session_id' => 'manual-cart',
        ]);
    }

    public function test_finished_order_is_marked_before_the_success_page_is_visited(): void
    {
        Bus::fake();
        config()->set('services.pelion.checkout_stock_check_enabled', false);

        $user = User::factory()->create();
        $this->actingAs($user);
        $order = $this->createOrder($user, 20, null, [
            'order_status_id' => (int) config('settings.order.status.unfinished'),
            'checkout_processed_at' => null,
        ]);

        session()->start();
        session([config('session.cart') => 'manual-cart']);

        $response = app(CheckoutController::class)->order(Request::create(
            '/narudzba',
            'POST',
            ['provjera' => (string) $order->id]
        ));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('checkout.success'), $response->getTargetUrl());
        $this->assertSame(
            (int) config('settings.order.status.new'),
            (int) $order->fresh()->order_status_id
        );
        $this->assertNotNull($order->fresh()->checkout_processed_at);
    }

    /**
     * @dataProvider offlinePaymentCodes
     */
    public function test_saved_loyalty_discount_requires_review_during_redemption_pause(string $paymentCode): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-30 23:59:59', 'Europe/Zagreb'));
        Bus::fake();
        config()->set('services.pelion.checkout_stock_check_enabled', false);

        $user = User::factory()->create();
        $this->actingAs($user);
        Loyalty::addPoints(100, 0, 'admin', 'Opening balance', $user->id);
        $order = $this->createOrder($user, 20, Carbon::parse('2026-10-25 12:00:00'), [
            'order_status_id' => (int) config('settings.order.status.unfinished'),
            'payment_code' => $paymentCode,
            'checkout_processed_at' => null,
        ]);
        $this->createLoyaltyDiscount($order);

        session()->start();
        session([
            config('session.cart') => 'saved-loyalty-cart',
            config('session.cart') . '_loyalty' => 100,
        ]);
        CheckoutSession::setOrder(['id' => $order->id]);

        $response = app(CheckoutController::class)->order(Request::create(
            '/narudzba',
            'GET',
            ['provjera' => (string) $order->id]
        ));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('pregled'), $response->getTargetUrl());
        $this->assertSame(
            'Loyalty popust trenutačno nije dostupan. Osvježite pregled narudžbe prije potvrde.',
            session('error')
        );
        $this->assertSame(
            (int) config('settings.order.status.unfinished'),
            (int) $order->fresh()->order_status_id
        );
        $this->assertNull($order->fresh()->checkout_processed_at);
        $this->assertSame(100, Loyalty::hasLoyaltyTotal($user->id));
        $this->assertTrue(CheckoutSession::hasOrder());
    }

    public static function offlinePaymentCodes(): array
    {
        return [
            'cash on delivery' => ['cod'],
            'bank transfer' => ['bank'],
            'pay on pickup' => ['pickup'],
        ];
    }

    public function test_saved_loyalty_discount_can_finish_when_redemption_resumes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-12-01 00:00:00', 'Europe/Zagreb'));
        Bus::fake();
        config()->set('services.pelion.checkout_stock_check_enabled', false);

        $user = User::factory()->create();
        $this->actingAs($user);
        $order = $this->createOrder($user, 20, null, [
            'order_status_id' => (int) config('settings.order.status.unfinished'),
            'checkout_processed_at' => null,
        ]);
        $this->createLoyaltyDiscount($order);

        session()->start();
        session([config('session.cart') => 'saved-loyalty-cart']);

        $response = app(CheckoutController::class)->order(Request::create(
            '/narudzba',
            'GET',
            ['provjera' => (string) $order->id]
        ));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('checkout.success'), $response->getTargetUrl());
        $this->assertSame(
            (int) config('settings.order.status.new'),
            (int) $order->fresh()->order_status_id
        );
        $this->assertNotNull($order->fresh()->checkout_processed_at);
    }

    /**
     * @dataProvider existingPaymentFinalizations
     */
    public function test_pause_preserves_completed_orders_and_successful_card_callbacks(
        string $paymentCode,
        string $initialStatus,
        string $expectedStatus
    ): void {
        Carbon::setTestNow(Carbon::parse('2026-11-30 12:00:00', 'Europe/Zagreb'));
        Bus::fake();
        config()->set('services.pelion.checkout_stock_check_enabled', false);

        Schema::create('order_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->boolean('success');
            $table->timestamps();
        });

        $user = User::factory()->create();
        $this->actingAs($user);
        $order = $this->createOrder($user, 20, null, [
            'order_status_id' => (int) config('settings.order.status.' . $initialStatus),
            'payment_code' => $paymentCode,
            'checkout_processed_at' => $initialStatus === 'new' ? now()->subDay() : null,
        ]);
        $this->createLoyaltyDiscount($order);

        session()->start();
        session([config('session.cart') => 'saved-loyalty-cart']);
        $parameters = $paymentCode === 'corvus'
            ? ['order_number' => (string) $order->id, 'approval_code' => 'approved-payment']
            : ['provjera' => (string) $order->id];

        $response = app(CheckoutController::class)->order(Request::create('/narudzba', 'GET', $parameters));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('checkout.success'), $response->getTargetUrl());
        $this->assertSame(
            (int) config('settings.order.status.' . $expectedStatus),
            (int) $order->fresh()->order_status_id
        );
        $this->assertEquals(20, $order->fresh()->total);
        $this->assertNotNull($order->fresh()->checkout_processed_at);
        $this->assertNull(session('error'));

        if ($paymentCode === 'corvus') {
            $this->assertDatabaseHas('order_transactions', [
                'order_id' => $order->id,
                'success' => 1,
            ]);
        }
    }

    public static function existingPaymentFinalizations(): array
    {
        return [
            'completed cash on delivery order' => ['cod', 'new', 'new'],
            'successful card callback' => ['corvus', 'unfinished', 'paid'],
        ];
    }

    public function test_success_flow_saves_first_checkout_address_to_customer_details(): void
    {
        Bus::fake();

        $user = User::factory()->create([
            'email' => 'ana@example.test',
        ]);

        $this->actingAs($user);

        $order = $this->createOrder($user, 44.50, null, [
            'payment_fname' => 'Ana',
            'payment_lname' => 'Anic',
            'payment_address' => 'Malesnica bb',
            'payment_zip' => '10000',
            'payment_city' => 'Zagreb',
            'payment_state' => 'Croatia',
            'payment_phone' => '0992153975',
            'shipping_fname' => 'Ana',
            'shipping_lname' => 'Anic',
            'shipping_address' => 'Malesnica bb',
            'shipping_zip' => '10000',
            'shipping_city' => 'Zagreb',
            'shipping_state' => 'Croatia',
            'shipping_phone' => '0992153975',
            'company' => 'Zuzi Obrt',
            'oib' => '12345678901',
        ]);

        session()->start();
        session([
            config('session.cart') => 'manual-cart',
        ]);
        CheckoutSession::setOrder(['id' => $order->id]);

        $response = app(CheckoutController::class)->success(Request::create('/uspjeh', 'GET'));

        $this->assertInstanceOf(View::class, $response);
        $this->assertDatabaseHas('user_details', [
            'user_id' => $user->id,
            'fname' => 'Ana',
            'lname' => 'Anic',
            'address' => 'Malesnica bb',
            'zip' => '10000',
            'city' => 'Zagreb',
            'state' => 'Croatia',
            'phone' => '0992153975',
            'company' => 'Zuzi Obrt',
            'oib' => '12345678901',
            'role' => 'customer',
            'status' => 1,
        ]);
    }

    private function createLoyaltyDiscount(AdminOrder $order): void
    {
        DB::table('order_total')->insert([
            'order_id' => $order->id,
            'code' => 'special',
            'title' => 'Loyalty',
            'value' => -5,
            'sort_order' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createOrder(User $user, float $total, $createdAt = null, array $overrides = []): AdminOrder
    {
        $createdAt = $createdAt ?: now();

        return AdminOrder::query()->create(array_merge([
            'user_id' => $user->id,
            'affiliate_id' => 0,
            'order_status_id' => 1,
            'invoice' => '',
            'total' => $total,
            'payment_fname' => 'Test',
            'payment_lname' => 'User',
            'payment_address' => 'Test 1',
            'payment_zip' => '10000',
            'payment_city' => 'Zagreb',
            'payment_state' => 'Croatia',
            'payment_phone' => '',
            'payment_email' => $user->email,
            'payment_method' => 'Plaćanje pouzećem',
            'payment_code' => 'cod',
            'payment_card' => '',
            'payment_installment' => 0,
            'shipping_fname' => 'Test',
            'shipping_lname' => 'User',
            'shipping_address' => 'Test 1',
            'shipping_zip' => '10000',
            'shipping_city' => 'Zagreb',
            'shipping_state' => 'Croatia',
            'shipping_phone' => '',
            'shipping_email' => $user->email,
            'shipping_method' => 'Dostava',
            'shipping_code' => 'gls',
            'company' => '',
            'oib' => '',
            'comment' => '',
            'tracking_code' => '',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ], $overrides));
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
            $table->string('payment_state')->nullable();
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
            $table->string('shipping_state')->nullable();
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
            $table->timestamp('checkout_processed_at')->nullable();
            $table->string('mailchimp_campaign_id', 100)->nullable();
            $table->timestamp('mailchimp_ecommerce_synced_at')->nullable();
            $table->string('mailchimp_ecommerce_financial_status', 20)->nullable();
            $table->timestamp('mailchimp_ecommerce_last_attempt_at')->nullable();
            $table->text('mailchimp_ecommerce_last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('user_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index();
            $table->string('fname');
            $table->string('lname')->nullable();
            $table->string('address')->nullable();
            $table->string('zip')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->string('oib')->nullable();
            $table->string('avatar')->nullable();
            $table->longText('bio')->nullable();
            $table->string('social')->nullable();
            $table->string('role');
            $table->boolean('status')->default(1);
            $table->timestamps();
        });

        Schema::create('order_products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('product_id')->default(0);
            $table->string('name');
            $table->integer('quantity')->default(1);
            $table->decimal('org_price', 15, 4)->default(0);
            $table->decimal('discount', 15, 4)->nullable();
            $table->decimal('price', 15, 4)->default(0);
            $table->decimal('total', 15, 4)->default(0);
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

        Schema::create('gift_vouchers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id')->index();
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

        Schema::create('carts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index();
            $table->string('session_id');
            $table->longText('cart_data')->nullable();
            $table->timestamps();
        });
    }
}
