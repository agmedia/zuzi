<?php

namespace Tests\Feature;

use App\Models\Back\Orders\Order;
use App\Services\MailchimpAttributionService;
use App\Services\MailchimpEcommerceService;
use App\Services\MailchimpOrderSynchronizer;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use ReflectionProperty;
use RuntimeException;
use Tests\TestCase;

class MailchimpEcommerceTrackingTest extends TestCase
{
    /** @var int */
    private $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'app.url' => 'https://shop.example.test',
            'services.mailchimp.api_key' => 'test-secret-us7',
            'services.mailchimp.server_prefix' => 'us7',
            'services.mailchimp.audience_id' => 'audience-123',
            'services.mailchimp.ecommerce_store_id' => 'store-123',
            'services.mailchimp.ecommerce_store_name' => 'Zuzi test store',
            'services.mailchimp.ecommerce_currency_code' => 'EUR',
            'services.mailchimp.ecommerce_automations_enabled' => false,
            'services.mailchimp.ecommerce_sync_from' => '2026-08-28 00:00:00',
            'services.mailchimp.storefront_url' => 'https://shop.example.test/knjige',
        ]);

        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->createTables();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::disconnect('sqlite');

        parent::tearDown();
    }

    public function test_attribution_requires_valid_cookie_and_explicit_marketing_consent(): void
    {
        $order = $this->makeOrder([
            'checkout_processed_at' => null,
            'mailchimp_campaign_id' => null,
        ]);
        $service = app(MailchimpAttributionService::class);
        $withoutConsent = Request::create('/', 'GET', [], [
            MailchimpAttributionService::CAMPAIGN_COOKIE => 'AbC_123-test',
        ]);

        $this->assertFalse($service->attachToOrder($order->id, $withoutConsent));
        $this->assertNull($order->fresh()->mailchimp_campaign_id);

        $validRequest = Request::create('/', 'GET', [], [
            MailchimpAttributionService::CAMPAIGN_COOKIE => 'AbC_123-test',
            MailchimpAttributionService::CONSENT_COOKIE => 'granted',
        ]);

        $this->assertTrue($service->attachToOrder($order->id, $validRequest));
        $this->assertSame('AbC_123-test', $order->fresh()->mailchimp_campaign_id);

        $invalidRequest = Request::create('/', 'GET', [], [
            MailchimpAttributionService::CAMPAIGN_COOKIE => 'campaign<script>',
            MailchimpAttributionService::CONSENT_COOKIE => 'granted',
        ]);

        $this->assertFalse($service->attachToOrder($order->id, $invalidRequest));
        $this->assertSame('AbC_123-test', $order->fresh()->mailchimp_campaign_id);
        $this->assertFalse($service->attachToOrder($order->id, Request::create('/', 'GET')));
        $this->assertSame('AbC_123-test', $order->fresh()->mailchimp_campaign_id);

        $withdrawn = Request::create('/', 'GET', [], [
            MailchimpAttributionService::CONSENT_COOKIE => 'denied',
        ]);

        $this->assertFalse($service->attachToOrder($order->id, $withdrawn));
        $this->assertNull($order->fresh()->mailchimp_campaign_id);

        DB::table('orders')->where('id', $order->id)->update([
            'checkout_processed_at' => now(),
            'mailchimp_campaign_id' => 'locked-after-checkout',
        ]);

        $this->assertFalse($service->attachToOrder($order->id, $validRequest));
        $this->assertSame('locked-after-checkout', $order->fresh()->mailchimp_campaign_id);
    }

    public function test_campaign_landing_boots_consent_immediately_and_plain_cookies_are_server_readable(): void
    {
        $source = file_get_contents(
            resource_path('views/front/layouts/partials/cookie-consent.blade.php')
        );
        $snapshotDeclaration = strpos($source, 'const pendingMailchimpCampaignId =');
        $attributionSync = strpos($source, 'const syncMailchimpAttribution =');

        $this->assertNotFalse($snapshotDeclaration);
        $this->assertNotFalse($attributionSync);
        $this->assertLessThan($attributionSync, $snapshotDeclaration);
        $this->assertStringContainsString("searchParams.get('mc_cid')", $source);
        $this->assertStringContainsString('zuzi_marketing_consent', $source);
        $this->assertMatchesRegularExpression(
            '/hasStoredCookieConsent\(\)\s*\|\|\s*validMailchimpIdentifier\(pendingMailchimpCampaignId\)/',
            $source
        );

        $middleware = app(\App\Http\Middleware\EncryptCookies::class);
        $except = new ReflectionProperty($middleware, 'except');
        $except->setAccessible(true);

        $this->assertContains('zuzi_mc_cid', $except->getValue($middleware));
        $this->assertContains('zuzi_marketing_consent', $except->getValue($middleware));
    }

    public function test_service_creates_store_then_customer_product_and_order_with_safe_payloads(): void
    {
        Carbon::setTestNow('2026-08-28 07:20:00');
        $order = $this->makeOrder([], true);
        $requests = [];

        $this->fakeSuccessfulMailchimp($requests);

        $result = app(MailchimpEcommerceService::class)->syncOrder($order);

        $this->assertTrue($result['ok'], json_encode($result));
        $this->assertSame('paid', $result['financial_status']);
        $this->assertSame(['GET', 'POST', 'PUT', 'PUT', 'PUT'], array_column($requests, 'method'));

        $store = $requests[1];
        $this->assertSame('store-123', $store['data']['id']);
        $this->assertSame('audience-123', $store['data']['list_id']);
        $this->assertSame('Zuzi test store', $store['data']['name']);
        $this->assertSame('EUR', $store['data']['currency_code']);
        $this->assertTrue($store['data']['is_syncing']);

        $customer = $requests[2];
        $this->assertStringContainsString('/customers/' . md5('buyer@example.test'), $customer['url']);
        $this->assertSame('buyer@example.test', $customer['data']['email_address']);
        $this->assertFalse($customer['data']['opt_in_status']);

        $product = $requests[3];
        $this->assertSame('501', $product['data']['id']);
        $this->assertSame('501', $product['data']['variants'][0]['id']);

        $remoteOrder = $requests[4];
        $this->assertSame('campaign_abc', $remoteOrder['data']['campaign_id']);
        $this->assertSame('paid', $remoteOrder['data']['financial_status']);
        $this->assertSame(
            $order->fresh()->checkout_processed_at->toIso8601String(),
            $remoteOrder['data']['processed_at_foreign']
        );
        $this->assertSame(9.5, $remoteOrder['data']['tax_total']);
        $this->assertSame(4.9, $remoteOrder['data']['shipping_total']);
        $this->assertSame(['id' => md5('buyer@example.test')], $remoteOrder['data']['customer']);
        $this->assertSame('501', $remoteOrder['data']['lines'][0]['product_id']);
        $this->assertSame(2, $remoteOrder['data']['lines'][0]['quantity']);
        $this->assertSame(19.95, $remoteOrder['data']['lines'][0]['price']);
        $this->assertTrue($requests[0]['authenticated']);
        Http::assertSentCount(5);
    }

    public function test_checkout_marker_and_synchronizer_are_idempotent(): void
    {
        Carbon::setTestNow('2026-08-28 07:30:00');
        $order = $this->makeOrder(['checkout_processed_at' => null], true);
        $requests = [];
        $this->fakeSuccessfulMailchimp($requests);
        $synchronizer = app(MailchimpOrderSynchronizer::class);

        $this->assertTrue($synchronizer->markCheckoutProcessed($order->id));
        $this->assertFalse($synchronizer->markCheckoutProcessed($order->id));
        $this->assertSame(
            '2026-08-28 07:30:00',
            $order->fresh()->checkout_processed_at->format('Y-m-d H:i:s')
        );

        $first = $synchronizer->syncOrderId($order->id);
        $this->assertTrue($first['ok']);
        $this->assertFalse($first['skipped']);
        $this->assertSame('paid', $order->fresh()->mailchimp_ecommerce_financial_status);
        $this->assertCount(5, $requests);

        Carbon::setTestNow('2026-08-28 07:31:00');
        $second = $synchronizer->syncOrderId($order->id);
        $this->assertTrue($second['ok']);
        $this->assertTrue($second['skipped']);
        $this->assertCount(5, $requests);

        DB::table('orders')->where('id', $order->id)->update(['order_status_id' => 5]);
        $synchronizer->markForSync($order->id);
        $this->assertNull($order->fresh()->mailchimp_ecommerce_synced_at);

        $third = $synchronizer->syncOrderId($order->id);
        $this->assertTrue($third['ok']);
        $this->assertFalse($third['skipped']);
        $this->assertSame('cancelled', $order->fresh()->mailchimp_ecommerce_financial_status);
        $this->assertSame('cancelled', end($requests)['data']['financial_status']);
        $this->assertCount(8, $requests);

        $unfinished = $this->makeOrder([
            'order_status_id' => 8,
            'checkout_processed_at' => null,
        ]);
        $this->assertFalse($synchronizer->markCheckoutProcessed($unfinished->id));
        $this->assertNull($unfinished->fresh()->checkout_processed_at);

        $declined = $this->makeOrder([
            'order_status_id' => 7,
            'checkout_processed_at' => null,
        ]);
        $this->assertFalse($synchronizer->markCheckoutProcessed($declined->id));
        $this->assertNull($declined->fresh()->checkout_processed_at);
    }

    public function test_financial_statuses_follow_zuzi_order_lifecycle(): void
    {
        $service = app(MailchimpEcommerceService::class);

        $this->assertSame('pending', $service->financialStatusForStatusId(1));
        $this->assertSame('pending', $service->financialStatusForStatusId(2));
        $this->assertSame('paid', $service->financialStatusForStatusId(3));
        $this->assertSame('paid', $service->financialStatusForStatusId(9));
        $this->assertSame('paid', $service->financialStatusForStatusId(10));
        $this->assertSame('paid', $service->financialStatusForStatusId(11));
        $this->assertSame('refunded', $service->financialStatusForStatusId(6));
        $this->assertSame('cancelled', $service->financialStatusForStatusId(7));
        $this->assertNull($service->financialStatusForStatusId(8));
    }

    public function test_gift_voucher_only_order_is_reported_with_a_synthetic_product(): void
    {
        Carbon::setTestNow('2026-08-28 07:35:00');
        $order = $this->makeOrder(['total' => 50]);
        DB::table('order_products')->insert([
            'order_id' => $order->id,
            'product_id' => 0,
            'name' => 'Poklon bon - 50,00 €',
            'quantity' => 1,
            'org_price' => 50,
            'discount' => null,
            'price' => 50,
            'total' => 50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $requests = [];
        $this->fakeSuccessfulMailchimp($requests);

        $result = app(MailchimpEcommerceService::class)->syncOrder($order);

        $this->assertTrue($result['ok'], json_encode($result));
        $remoteProduct = $requests[3]['data'];
        $remoteOrder = $requests[4]['data'];
        $this->assertSame('zuzi-gift-voucher-5000', $remoteProduct['id']);
        $this->assertSame('zuzi-gift-voucher-5000', $remoteOrder['lines'][0]['product_id']);
        $this->assertSame(50.0, $remoteOrder['lines'][0]['price']);
    }

    public function test_mailchimp_failure_is_fail_open_redacted_and_retried_only_after_backoff(): void
    {
        Carbon::setTestNow('2026-08-28 07:40:00');
        $order = $this->makeOrder([], true);
        $originalUpdatedAt = $order->updated_at->format('Y-m-d H:i:s');

        Http::fake(function (ClientRequest $request) {
            if ($request->method() === 'GET'
                && $request->url() === 'https://us7.api.mailchimp.com/3.0/ecommerce/stores/store-123') {
                return Http::response([
                    'title' => 'Forbidden',
                    'detail' => 'The API key for buyer@example.test was rejected.',
                ], 403);
            }

            throw new RuntimeException('Unexpected request: ' . $request->method() . ' ' . $request->url());
        });

        $synchronizer = app(MailchimpOrderSynchronizer::class);
        $result = $synchronizer->syncOrderId($order->id);

        $this->assertFalse($result['ok']);
        $this->assertFalse($result['skipped']);
        $this->assertTrue($result['stop']);
        $this->assertStringContainsString('403', (string) $result['error']);
        $this->assertStringNotContainsString('buyer@example.test', (string) $result['error']);

        $order->refresh();
        $this->assertSame(3, (int) $order->order_status_id);
        $this->assertSame($originalUpdatedAt, $order->updated_at->format('Y-m-d H:i:s'));
        $this->assertNull($order->mailchimp_ecommerce_synced_at);
        $this->assertStringContainsString('403', (string) $order->mailchimp_ecommerce_last_error);
        $this->assertStringNotContainsString('buyer@example.test', (string) $order->mailchimp_ecommerce_last_error);
        $this->assertCount(0, $synchronizer->pendingOrders(5));

        Carbon::setTestNow('2026-08-28 07:56:00');
        $this->assertSame([$order->id], $synchronizer->pendingOrders(5)->pluck('id')->all());
        Http::assertSentCount(1);
    }

    public function test_pending_orders_include_recent_unattributed_sales_and_exclude_pre_rollout_history(): void
    {
        Carbon::setTestNow('2026-08-28 08:00:00');
        $attributed = $this->makeOrder(['mailchimp_campaign_id' => 'campaign-new']);
        $recentUnattributed = $this->makeOrder(['mailchimp_campaign_id' => null]);
        $preRollout = $this->makeOrder([
            'checkout_processed_at' => '2026-08-27 23:59:59',
            'mailchimp_campaign_id' => null,
        ]);

        $pendingIds = app(MailchimpOrderSynchronizer::class)
            ->pendingOrders(25)
            ->pluck('id')
            ->all();

        $this->assertSame([$attributed->id, $recentUnattributed->id], $pendingIds);
        $this->assertNotContains($preRollout->id, $pendingIds);
        Http::assertNothingSent();
    }

    public function test_migration_backfills_only_finalized_rollout_orders(): void
    {
        Carbon::setTestNow('2026-08-28 08:00:00');
        $finalized = $this->makeOrder([
            'order_status_id' => 1,
            'checkout_processed_at' => null,
        ]);
        $unfinished = $this->makeOrder([
            'order_status_id' => 8,
            'checkout_processed_at' => null,
        ]);
        $declined = $this->makeOrder([
            'order_status_id' => 7,
            'checkout_processed_at' => null,
        ]);

        require_once database_path(
            'migrations/2026_08_28_203500_add_mailchimp_ecommerce_tracking_to_orders_table.php'
        );
        (new \AddMailchimpEcommerceTrackingToOrdersTable())->up();

        $this->assertNotNull($finalized->fresh()->checkout_processed_at);
        $this->assertNull($unfinished->fresh()->checkout_processed_at);
        $this->assertNull($declined->fresh()->checkout_processed_at);
    }

    public function test_command_syncs_recent_orders_and_provisions_an_empty_store(): void
    {
        Carbon::setTestNow('2026-08-28 08:00:00');
        $recent = $this->makeOrder(['mailchimp_campaign_id' => null], true);
        $old = $this->makeOrder([
            'checkout_processed_at' => '2026-08-27 23:59:59',
            'mailchimp_campaign_id' => null,
        ], true);
        $this->makeOrder([
            'order_status_id' => 6,
            'mailchimp_ecommerce_synced_at' => now(),
            'mailchimp_ecommerce_financial_status' => 'refunded',
        ]);
        $requests = [];
        $this->fakeSuccessfulMailchimp($requests);

        $this->artisan('mailchimp:sync-ecommerce-orders', [
            '--limit' => 25,
            '--max-seconds' => 50,
            '--today' => true,
        ])
            ->expectsOutput('Mailchimp e-commerce sync završen. Sinkronizirano: 1, neuspjelo: 0, preskočeno: 0.')
            ->expectsOutput('Danas: 2 narudžbi, 99,80 EUR. Mailchimp: 1 sinkronizirano, 1 čeka, 0 s greškom.')
            ->assertExitCode(0);

        $this->assertNotNull($recent->fresh()->mailchimp_ecommerce_synced_at);
        $this->assertNull($old->fresh()->mailchimp_ecommerce_synced_at);
        $remoteRecent = collect($requests)->first(function (array $request) use ($recent) {
            return $request['method'] === 'PUT'
                && substr($request['url'], -strlen('/orders/' . $recent->id)) === '/orders/' . $recent->id;
        });
        $this->assertNotNull($remoteRecent);
        $this->assertArrayNotHasKey('campaign_id', $remoteRecent['data']);

        // A fresh service instance still provisions the store when no orders
        // are eligible, which makes the connection visible in Mailchimp.
        DB::table('orders')->update([
            'mailchimp_ecommerce_synced_at' => now(),
            'mailchimp_ecommerce_financial_status' => 'paid',
        ]);
        DB::table('orders')
            ->where('order_status_id', 6)
            ->update(['mailchimp_ecommerce_financial_status' => 'refunded']);
        app()->forgetInstance(MailchimpEcommerceService::class);
        app()->forgetInstance(MailchimpOrderSynchronizer::class);
        $emptyRequests = [];
        $this->fakeSuccessfulMailchimp($emptyRequests);

        $this->artisan('mailchimp:sync-ecommerce-orders', [
            '--limit' => 25,
            '--max-seconds' => 50,
        ])
            ->expectsOutput('Mailchimp e-commerce sync završen. Sinkronizirano: 0, neuspjelo: 0, preskočeno: 0.')
            ->assertExitCode(0);

        $this->assertSame(['GET', 'POST'], array_column($emptyRequests, 'method'));
    }

    public function test_today_summary_is_still_printed_when_mailchimp_store_is_unavailable(): void
    {
        Carbon::setTestNow('2026-08-28 08:10:00');
        $this->makeOrder([
            'total' => 12.34,
            'mailchimp_campaign_id' => null,
        ]);

        Http::fake([
            '*' => Http::response([
                'title' => 'Unauthorized',
                'detail' => 'Invalid API key.',
            ], 401),
        ]);

        $this->artisan('mailchimp:sync-ecommerce-orders', [
            '--today' => true,
        ])
            ->expectsOutput('Danas: 1 narudžbi, 12,34 EUR. Mailchimp: 0 sinkronizirano, 1 čeka, 0 s greškom.')
            ->assertExitCode(1);
    }

    private function createTables(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_status_id');
            $table->string('payment_email')->nullable();
            $table->string('payment_fname')->nullable();
            $table->string('payment_lname')->nullable();
            $table->decimal('total', 15, 4)->default(0);
            $table->timestamp('checkout_processed_at')->nullable();
            $table->string('mailchimp_campaign_id', 100)->nullable();
            $table->timestamp('mailchimp_ecommerce_synced_at')->nullable();
            $table->string('mailchimp_ecommerce_financial_status', 20)->nullable();
            $table->timestamp('mailchimp_ecommerce_last_attempt_at')->nullable();
            $table->text('mailchimp_ecommerce_last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->integer('quantity')->default(0);
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->timestamps();
        });

        Schema::create('order_products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->string('name');
            $table->integer('quantity');
            $table->decimal('org_price', 15, 4)->nullable();
            $table->decimal('discount', 15, 4)->nullable();
            $table->decimal('price', 15, 4);
            $table->decimal('total', 15, 4);
            $table->timestamps();
        });

        Schema::create('order_total', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->string('code');
            $table->string('title');
            $table->decimal('value', 15, 4);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    private function makeOrder(array $overrides = [], bool $withLines = false): Order
    {
        $this->sequence++;

        $attributes = array_merge([
            'order_status_id' => 3,
            'payment_email' => ' Buyer@Example.test ',
            'payment_fname' => 'Ana',
            'payment_lname' => 'Anić',
            'total' => 49.9,
            'checkout_processed_at' => now(),
            'mailchimp_campaign_id' => 'campaign_abc',
            'mailchimp_ecommerce_synced_at' => null,
            'mailchimp_ecommerce_financial_status' => null,
            'mailchimp_ecommerce_last_attempt_at' => null,
            'mailchimp_ecommerce_last_error' => null,
        ], $overrides, [
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('orders')->insertGetId($attributes);
        $order = Order::query()->findOrFail($orderId);

        if ($withLines) {
            $productId = 500 + $this->sequence;
            DB::table('products')->insert([
                'id' => $productId,
                'name' => 'Test knjiga',
                'quantity' => 7,
                'description' => '<p>Opis testne knjige.</p>',
                'image' => 'image/test.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('order_products')->insert([
                'order_id' => $order->id,
                'product_id' => $productId,
                'name' => 'Test knjiga',
                'quantity' => 2,
                'org_price' => 19.95,
                'discount' => null,
                'price' => 19.95,
                'total' => 39.9,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('order_total')->insert([
                [
                    'order_id' => $order->id,
                    'code' => 'tax',
                    'title' => 'PDV',
                    'value' => 9.5,
                    'sort_order' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'order_id' => $order->id,
                    'code' => 'shipping',
                    'title' => 'Dostava',
                    'value' => 4.9,
                    'sort_order' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        return $order;
    }

    /** @param array<int,array{method:string,url:string,data:array,authenticated:bool}> $requests */
    private function fakeSuccessfulMailchimp(array &$requests): void
    {
        Http::fake(function (ClientRequest $request) use (&$requests) {
            $requests[] = [
                'method' => $request->method(),
                'url' => $request->url(),
                'data' => $request->data(),
                'authenticated' => $request->hasHeader(
                    'Authorization',
                    'Basic ' . base64_encode('anystring:test-secret-us7')
                ),
            ];

            if ($request->method() === 'GET'
                && $request->url() === 'https://us7.api.mailchimp.com/3.0/ecommerce/stores/store-123') {
                return Http::response(['title' => 'Not Found'], 404);
            }

            if ($request->method() === 'POST'
                && $request->url() === 'https://us7.api.mailchimp.com/3.0/ecommerce/stores') {
                return Http::response(['id' => 'store-123'], 200);
            }

            if ($request->method() === 'PUT'
                && strpos($request->url(), '/ecommerce/stores/store-123/customers/') !== false) {
                return Http::response(['id' => basename($request->url())], 200);
            }

            if ($request->method() === 'PUT'
                && strpos($request->url(), '/ecommerce/stores/store-123/products/') !== false) {
                return Http::response(['id' => basename($request->url())], 200);
            }

            if ($request->method() === 'PUT'
                && strpos($request->url(), '/ecommerce/stores/store-123/orders/') !== false) {
                return Http::response(['id' => basename($request->url())], 200);
            }

            throw new RuntimeException('Unexpected request: ' . $request->method() . ' ' . $request->url());
        });
    }
}
