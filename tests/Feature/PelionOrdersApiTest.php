<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PelionOrdersApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.pelion.incoming_token' => 'pelion-secret',
            'services.pelion.orders_from' => '2026-06-19',
            'services.pelion.order_prefix' => 'WEB-',
            'services.pelion.currency' => 'EUR',
            'services.pelion.default_product_tax_rate' => 5,
            'services.pelion.shipping_tax_rate' => 25,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-06-20 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_pelion_orders_require_token(): void
    {
        $this->getJson(route('api.pelion.orders.index'))
            ->assertUnauthorized();
    }

    public function test_pelion_can_fetch_ready_orders_without_loading_full_history(): void
    {
        $publisherId = $this->createPublisher();
        $productId = $this->createProduct($publisherId, 123456);
        $missingItemProductId = $this->createProduct($publisherId, null, 'Knjiga bez itemid');

        $readyOrderId = $this->createOrder([
            'created_at' => Carbon::parse('2026-06-20 09:00:00'),
            'updated_at' => Carbon::parse('2026-06-20 09:05:00'),
        ]);
        $this->addOrderProduct($readyOrderId, $productId);
        $this->addOrderTotals($readyOrderId);

        $oldOrderId = $this->createOrder([
            'created_at' => Carbon::parse('2026-06-01 09:00:00'),
            'updated_at' => Carbon::parse('2026-06-01 09:00:00'),
        ]);
        $this->addOrderProduct($oldOrderId, $productId);
        $this->addOrderTotals($oldOrderId);

        $invoicedOrderId = $this->createOrder([
            'pelion_status' => 'invoiced',
            'pelion_invoice_number' => 'R-1',
            'pelion_invoiced_at' => Carbon::parse('2026-06-20 09:30:00'),
        ]);
        $this->addOrderProduct($invoicedOrderId, $productId);
        $this->addOrderTotals($invoicedOrderId);

        $canceledOrderId = $this->createOrder([
            'order_status_id' => (int) config('settings.order.status.canceled'),
        ]);
        $this->addOrderProduct($canceledOrderId, $productId);
        $this->addOrderTotals($canceledOrderId);

        $missingItemOrderId = $this->createOrder();
        $this->addOrderProduct($missingItemOrderId, $missingItemProductId);
        $this->addOrderTotals($missingItemOrderId);

        $response = $this->withPelionToken()
            ->getJson(route('api.pelion.orders.index', ['status' => 'ready_for_invoice', 'limit' => 50]));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $readyOrderId);
        $response->assertJsonPath('data.0.order_number', 'WEB-' . $readyOrderId);
        $response->assertJsonPath('data.0.payment_method_label', 'Kartica');
        $response->assertJsonPath('data.0.shipping_method_label', 'Dostava GLS');
        $response->assertJsonPath('data.0.shipping_price', 4);
        $response->assertJsonPath('data.0.status', 'ready_for_invoice');
        $response->assertJsonPath('pagination.per_page', 50);
    }

    public function test_pelion_can_fetch_order_detail_with_payment_shipping_and_itemid(): void
    {
        $publisherId = $this->createPublisher();
        $productId = $this->createProduct($publisherId, 123456);
        $orderId = $this->createOrder();

        $this->addOrderProduct($orderId, $productId);
        $this->addOrderTotals($orderId);
        $this->addSuccessfulTransaction($orderId);

        $response = $this->withPelionToken()
            ->getJson(route('api.pelion.orders.show', $orderId));

        $response->assertOk();
        $response->assertJsonPath('data.id', $orderId);
        $response->assertJsonPath('data.items.0.itemid', 123456);
        $response->assertJsonPath('data.items.0.quantity', 2);
        $response->assertJsonPath('data.items.0.unit_price', 12.5);
        $response->assertJsonPath('data.shipping.method', 'gls');
        $response->assertJsonPath('data.shipping.price', 4);
        $response->assertJsonPath('data.shipping.tax_rate', 25);
        $response->assertJsonPath('data.payment.method', 'card');
        $response->assertJsonPath('data.payment.paid', true);
        $response->assertJsonPath('data.payment.transaction_id', 'PG-123');
        $response->assertJsonPath('data.totals.items_total', 25);
        $response->assertJsonPath('data.totals.shipping_total', 4);
        $response->assertJsonPath('data.totals.grand_total', 29);
        $response->assertJsonPath('data.currency', 'EUR');
    }

    public function test_pelion_can_mark_order_as_invoiced(): void
    {
        $publisherId = $this->createPublisher();
        $productId = $this->createProduct($publisherId, 123456);
        $orderId = $this->createOrder();

        $this->addOrderProduct($orderId, $productId);
        $this->addOrderTotals($orderId);

        $response = $this->withPelionToken()
            ->postJson(route('api.pelion.orders.status', $orderId), [
                'status' => 'invoiced',
                'invoice_number' => 'R-2026-000456',
                'invoice_date' => '2026-06-20',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'invoiced');
        $response->assertJsonPath('data.invoice_number', 'R-2026-000456');
        $response->assertJsonPath('data.invoice_date', '2026-06-20');

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'invoice' => 'R-2026-000456',
            'pelion_status' => 'invoiced',
            'pelion_invoice_number' => 'R-2026-000456',
            'pelion_invoice_date' => '2026-06-20',
        ]);

        $this->assertDatabaseHas('order_history', [
            'order_id' => $orderId,
            'comment' => 'Pelion je izradio racun: R-2026-000456.',
        ]);
    }

    public function test_pelion_can_fetch_articles_and_publishers(): void
    {
        $publisherId = $this->createPublisher('Pelion nakladnik');
        $productId = $this->createProduct($publisherId, 555777, 'Pelion knjiga');

        $articlesResponse = $this->withPelionToken()
            ->getJson(route('api.pelion.articles.index', ['query' => 'Pelion knjiga']));

        $articlesResponse->assertOk();
        $articlesResponse->assertJsonPath('data.0.id', $productId);
        $articlesResponse->assertJsonPath('data.0.itemid', 555777);
        $articlesResponse->assertJsonPath('data.0.publisher_id', $publisherId);

        $publishersResponse = $this->withPelionToken()
            ->getJson(route('api.pelion.publishers.index', ['query' => 'Pelion nakladnik']));

        $publishersResponse->assertOk();
        $publishersResponse->assertJsonPath('data.0.id', $publisherId);
        $publishersResponse->assertJsonPath('data.0.name', 'Pelion nakladnik');
    }

    private function withPelionToken(): self
    {
        return $this->withHeaders([
            'Authorization' => 'Bearer pelion-secret',
        ]);
    }

    private function createPublisher(string $title = 'Test nakladnik'): int
    {
        return (int) DB::table('publishers')->insertGetId([
            'letter' => 'T',
            'title' => $title,
            'description' => null,
            'meta_title' => $title,
            'meta_description' => $title,
            'image' => 'media/avatars/avatar0.jpg',
            'lang' => 'hr',
            'sort_order' => 0,
            'status' => true,
            'slug' => Str::slug($title),
            'url' => '/nakladnik/' . Str::slug($title),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createProduct(int $publisherId, ?int $itemid, string $name = 'Test knjiga'): int
    {
        $sku = strtoupper(Str::slug($name, ''));

        return (int) DB::table('products')->insertGetId([
            'author_id' => 0,
            'publisher_id' => $publisherId,
            'action_id' => 0,
            'name' => $name,
            'sku' => substr($sku, 0, 14) ?: 'TESTSKU',
            'ean' => '9789530000000',
            'isbn' => '9789530000000',
            'itemid' => $itemid,
            'description' => null,
            'slug' => Str::slug($name),
            'url' => '/proizvod/' . Str::slug($name),
            'image' => null,
            'price' => 12.50,
            'quantity' => 5,
            'tax_id' => 0,
            'special' => null,
            'special_from' => null,
            'special_to' => null,
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
            'push' => false,
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createOrder(array $overrides = []): int
    {
        return (int) DB::table('orders')->insertGetId(array_merge([
            'user_id' => 0,
            'affiliate_id' => 0,
            'order_status_id' => (int) config('settings.order.status.paid'),
            'invoice' => null,
            'total' => 29.00,
            'payment_fname' => 'Ivan',
            'payment_lname' => 'Horvat',
            'payment_address' => 'Ilica 1',
            'payment_zip' => '10000',
            'payment_city' => 'Zagreb',
            'payment_phone' => '+385911234567',
            'payment_email' => 'ivan@example.com',
            'payment_method' => 'Kartica',
            'payment_code' => 'card',
            'payment_card' => null,
            'payment_installment' => 0,
            'shipping_fname' => 'Ivan',
            'shipping_lname' => 'Horvat',
            'shipping_address' => 'Ilica 1',
            'shipping_zip' => '10000',
            'shipping_city' => 'Zagreb',
            'shipping_phone' => '+385911234567',
            'shipping_email' => 'ivan@example.com',
            'shipping_method' => 'Dostava GLS',
            'shipping_code' => 'gls',
            'company' => '',
            'oib' => '',
            'comment' => null,
            'tracking_code' => '',
            'shipped' => false,
            'printed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function addOrderProduct(int $orderId, int $productId): void
    {
        $product = DB::table('products')->find($productId);

        DB::table('order_products')->insert([
            'order_id' => $orderId,
            'product_id' => $productId,
            'name' => $product->name,
            'quantity' => 2,
            'org_price' => 12.50,
            'discount' => null,
            'price' => 12.50,
            'total' => 25.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function addOrderTotals(int $orderId): void
    {
        DB::table('order_total')->insert([
            [
                'order_id' => $orderId,
                'code' => 'subtotal',
                'title' => 'Ukupno artikli',
                'value' => 25.00,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => $orderId,
                'code' => 'shipping',
                'title' => 'Dostava GLS',
                'value' => 4.00,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => $orderId,
                'code' => 'total',
                'title' => 'Ukupno',
                'value' => 29.00,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    private function addSuccessfulTransaction(int $orderId): void
    {
        DB::table('order_transactions')->insert([
            'order_id' => $orderId,
            'success' => 1,
            'amount' => 29.00,
            'signature' => 'signature',
            'payment_type' => 'card',
            'payment_plan' => null,
            'payment_partner' => 'corvus',
            'datetime' => now(),
            'approval_code' => 'APPROVED',
            'pg_order_id' => 'PG-123',
            'lang' => 'hr',
            'stan' => null,
            'error' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
