<?php

namespace Tests\Feature;

use App\Helpers\Session\CheckoutSession;
use App\Mail\UnfinishedOrderReminder;
use App\Models\Back\Orders\Order;
use App\Models\Front\AgCart;
use App\Models\User;
use App\Services\AbandonedCartReminderService;
use App\Support\InterliberOffer;
use Darryldecode\Cart\Facades\CartFacade as Cart;
use Illuminate\Cache\RateLimiter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AbandonedCartRecoveryTest extends TestCase
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
        config()->set('cache.default', 'array');
        Cache::purge('array');
        $apiLimiter = app(RateLimiter::class)->limiter('api');
        $rateLimiter = new RateLimiter(Cache::store('array'));
        if ($apiLimiter) {
            $rateLimiter->for('api', $apiLimiter);
        }
        $this->app->instance(RateLimiter::class, $rateLimiter);
        \Illuminate\Support\Facades\RateLimiter::clearResolvedInstance(RateLimiter::class);
        config()->set('settings.special_action.start', null);
        config()->set('settings.loyalty.first_order_points', 0);
        config()->set('settings.loyalty.rewards.orders_per_month', []);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        $this->createSchema();
        $this->travelTo(Carbon::parse('2026-11-06 12:00:00', 'Europe/Zagreb'));
        $this->mockRecovery(true);
        session()->start();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_recovery_rejects_an_unsigned_link_without_touching_the_cart(): void
    {
        $order = $this->order();

        $this->get(route('abandoned-cart.restore', $order->id))->assertForbidden();

        $this->assertFalse(session()->has('zuzi_cart'));
    }

    public function test_recovery_rejects_an_expired_signed_link(): void
    {
        $order = $this->order();
        $url = URL::temporarySignedRoute('abandoned-cart.restore', now()->subSecond(), ['order' => $order->id]);

        $this->get($url)->assertForbidden();

        $this->assertFalse(session()->has('zuzi_cart'));
    }

    public function test_recovery_restores_only_active_available_books_and_caps_quantities_to_stock(): void
    {
        $order = $this->order();
        $availableId = $this->product(['quantity' => 2]);
        $inactiveId = $this->product(['status' => 0]);
        $outOfStockId = $this->product(['quantity' => 0]);
        $this->orderProduct($order, $availableId, 3);
        $this->orderProduct($order, $availableId, 1);
        $this->orderProduct($order, $inactiveId, 1);
        $this->orderProduct($order, $outOfStockId, 1);

        $this->get($this->recoveryUrl($order))->assertRedirect(route('kosarica'));

        $cartId = session('zuzi_cart');
        $items = (new AgCart($cartId))->getCartItems(true);
        $this->assertCount(1, $items);
        $this->assertSame($availableId, (int) $items->first()['id']);
        $this->assertSame(2, (int) $items->first()['quantity']);
        $this->assertSame('ana@example.com', CheckoutSession::getAddress()['email']);
        $this->assertFalse(CheckoutSession::hasOrder(), 'Recovery must not bind an old order that could complete later.');
    }

    public function test_repeated_recovery_does_not_duplicate_or_increase_existing_items(): void
    {
        $order = $this->order();
        $productId = $this->product();
        $this->orderProduct($order, $productId, 3);
        session(['zuzi_cart' => 'existing-cart']);
        Cart::session('existing-cart')->add([
            'id' => $productId, 'name' => 'Već odabrana knjiga', 'price' => 20,
            'quantity' => 1, 'attributes' => [],
        ]);

        $this->get($this->recoveryUrl($order))->assertRedirect(route('kosarica'));
        $this->get($this->recoveryUrl($order))->assertRedirect(route('kosarica'));

        $items = (new AgCart('existing-cart'))->getCartItems(true);
        $this->assertCount(1, $items);
        $this->assertSame(1, (int) $items->first()['quantity']);
        $this->assertSame('existing-cart', session('zuzi_cart'));
    }

    public function test_recovery_preserves_another_checkout_and_does_not_consume_its_coupon(): void
    {
        $order = $this->order();
        $productId = $this->product();
        $this->orderProduct($order, $productId, 1);
        $existingOrder = ['id' => 999];
        $existingAddress = ['email' => 'other@example.com', 'fname' => 'Druga osoba'];
        CheckoutSession::setOrder($existingOrder);
        CheckoutSession::setAddress($existingAddress);
        CheckoutSession::setShipping('pickup');
        CheckoutSession::setPayment('pickup');
        session(['zuzi_cart' => 'existing-checkout', 'zuzi_cart_coupon' => 'EXISTING10']);
        DB::table('product_actions')->insert([
            'title' => 'Postojeći kod', 'coupon' => 'EXISTING10', 'type' => 'P',
            'discount' => 10, 'group' => 'total', 'links' => '["total"]',
            'quantity' => 1, 'status' => 1,
            'date_start' => now()->subDay(), 'date_end' => now()->addDay(),
        ]);

        $this->get($this->recoveryUrl($order))->assertRedirect(route('kosarica'));

        $this->assertSame($existingOrder, CheckoutSession::getOrder());
        $this->assertSame($existingAddress, CheckoutSession::getAddress());
        $this->assertSame('pickup', CheckoutSession::getShipping());
        $this->assertSame('pickup', CheckoutSession::getPayment());
        $this->assertSame('EXISTING10', session('zuzi_cart_coupon'));
        $this->assertEquals(1, DB::table('product_actions')->where('coupon', 'EXISTING10')->value('status'));
    }

    public function test_recovery_rejects_an_authenticated_different_customer(): void
    {
        $order = $this->order();
        $customer = User::factory()->create(['email' => 'someone-else@example.com']);

        $this->actingAs($customer)->get($this->recoveryUrl($order))->assertForbidden();

        $this->assertFalse(session()->has('zuzi_cart'));
        $this->assertFalse(CheckoutSession::hasAddress());
    }

    public function test_unrecoverable_purchase_is_not_restored(): void
    {
        $order = $this->order(['order_status_id' => 3]);
        $this->orderProduct($order, $this->product(), 1);
        $this->mockRecovery(false);

        $this->get($this->recoveryUrl($order))->assertRedirect(route('kosarica'));

        $this->assertFalse(session()->has('zuzi_cart'));
        $this->assertFalse(CheckoutSession::hasOrder());
    }

    public function test_reminder_shows_the_selected_books_escaped_with_a_signed_recovery_link_and_live_promotion(): void
    {
        $order = $this->order();
        $this->orderProduct($order, $this->product(), 2, '<script>unsafe()</script> Testna knjiga');
        $this->mock(InterliberOffer::class, function ($mock) {
            $mock->shouldReceive('isActive')->andReturn(true);
        });
        $url = $this->recoveryUrl($order);
        $mail = new UnfinishedOrderReminder($order, $url, 2);

        $html = $mail->render();

        $this->assertSame('Tvoje knjige još čekaju - Zuzi Shop', $mail->subject);
        $this->assertStringContainsString(e($url), $html);
        $this->assertStringContainsString('Dovrši kupnju', $html);
        $this->assertStringContainsString('&lt;script&gt;unsafe()&lt;/script&gt; Testna knjiga', $html);
        $this->assertStringNotContainsString('<script>unsafe()</script>', $html);
        $this->assertStringContainsString('Količina: 2', $html);
        $this->assertStringContainsString('ZUZI20', $html);
        $this->assertStringContainsString('15.11.2026.', $html);
        $this->assertStringNotContainsString('HVALA', $html);
    }

    public function test_reminder_omits_promotion_after_expiry_and_for_gift_voucher_orders(): void
    {
        $this->mock(InterliberOffer::class, function ($mock) {
            $mock->shouldReceive('isActive')->andReturn(true);
        });
        $order = $this->order(['shipping_code' => 'gift_voucher_email']);
        $mail = new UnfinishedOrderReminder($order, $this->recoveryUrl($order));
        $this->assertFalse($mail->showPromotion);

        $order = $this->order();
        $this->travelTo(Carbon::parse('2026-11-15 00:00:00', 'Europe/Zagreb'));
        $mail = new UnfinishedOrderReminder($order, $this->recoveryUrl($order));
        $this->assertFalse($mail->showPromotion);
        $this->assertStringNotContainsString('ZUZI20', $mail->render());
    }

    private function mockRecovery(bool $canRecover): void
    {
        $this->mock(AbandonedCartReminderService::class, function ($mock) use ($canRecover) {
            $mock->shouldReceive('canRecover')->andReturn($canRecover);
        });
    }

    private function recoveryUrl(Order $order): string
    {
        return URL::temporarySignedRoute('abandoned-cart.restore', now()->addDay(), ['order' => $order->id]);
    }

    private function order(array $attributes = []): Order
    {
        $id = DB::table('orders')->insertGetId(array_merge([
            'user_id' => 0, 'order_status_id' => 8, 'payment_fname' => 'Ana',
            'payment_lname' => 'Anić', 'payment_email' => 'ana@example.com',
            'payment_code' => 'cod', 'shipping_code' => 'gls',
            'created_at' => now()->subHours(2), 'updated_at' => now()->subHours(2),
        ], $attributes));

        return Order::query()->findOrFail($id);
    }

    private function product(array $attributes = []): int
    {
        return (int) DB::table('products')->insertGetId(array_merge([
            'name' => 'Testna knjiga', 'sku' => 'TEST', 'price' => 20,
            'quantity' => 5, 'tax_id' => 1, 'url' => '/testna-knjiga', 'status' => 1,
        ], $attributes));
    }

    private function orderProduct(Order $order, int $productId, int $quantity, string $name = 'Testna knjiga'): void
    {
        DB::table('order_products')->insert([
            'order_id' => $order->id, 'product_id' => $productId, 'name' => $name,
            'quantity' => $quantity, 'price' => 20,
        ]);
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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->default(0);
            $table->integer('order_status_id');
            foreach (['payment_fname', 'payment_lname', 'payment_email', 'payment_phone', 'payment_address', 'payment_city', 'payment_zip', 'payment_state', 'payment_code', 'shipping_code', 'company', 'oib'] as $column) {
                $table->string($column)->nullable();
            }
            $table->timestamp('checkout_processed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('order_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->string('name');
            $table->integer('quantity');
            $table->decimal('price', 15, 4);
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku');
            $table->string('url');
            $table->decimal('price', 15, 4);
            $table->decimal('special', 15, 4)->nullable();
            $table->unsignedBigInteger('action_id')->default(0);
            $table->integer('quantity');
            $table->integer('status');
            $table->integer('tax_id');
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
            $table->boolean('once_per_email')->default(false);
            $table->boolean('status');
            $table->text('data')->nullable();
            $table->timestamps();
        });
    }
}
