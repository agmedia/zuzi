<?php

namespace Tests\Feature;

use App\Helpers\Session\CheckoutSession;
use App\Http\Controllers\Front\CheckoutController;
use App\Models\Back\Orders\Order as AdminOrder;
use App\Models\Front\Loyalty;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    public function test_success_flow_redeems_loyalty_and_clears_cart_session(): void
    {
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

    private function createOrder(User $user, float $total, $createdAt = null): AdminOrder
    {
        $createdAt = $createdAt ?: now();

        return AdminOrder::query()->create([
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
        ]);
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
