<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PelionStockSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_pelion_stock_sync_updates_product_quantities_by_itemid(): void
    {
        config(['services.pelion.api_key' => 'test-pelion-key']);

        $this->createProduct('Pelion stock one', 'PELSTOCK001', 1001, 0);
        $this->createProduct('Pelion stock two', 'PELSTOCK002', 1002, 5);
        $this->createProduct('Missing from Pelion', 'PELSTOCK003', 1003, 7);
        $this->createProduct('Missing ItemID', 'PELSTOCK004', null, 9);
        $this->createProduct('Delivery 24h matched', 'PELSTOCK005', 1005, 8, true);
        $this->createProduct('Delivery 24h missing', 'PELSTOCK006', 1006, 6, true);

        Http::fake([
            'https://pelion.test/api/v1/stockList*' => Http::response([
                ['ITEMID' => '1001', 'STOCKQUANTITY' => '2'],
                ['ITEMID' => '1001', 'STOCKQUANTITY' => '3,5'],
                ['ITEMID' => '1002', 'STOCKQUANTITY' => '0'],
                ['ITEMID' => '1005', 'STOCKQUANTITY' => '0'],
                ['ITEMID' => '9999', 'STOCKQUANTITY' => '4'],
                ['ITEMID' => 'bad', 'STOCKQUANTITY' => '1'],
            ]),
        ]);

        $response = $this->postJson(route('api.api.pelion.test'), [
            'action' => 'sync-product-quantities',
            'base_url' => 'https://pelion.test/api/v1',
        ]);

        $response->assertOk();
        $response->assertJsonPath('body.updated', 4);
        $response->assertJsonPath('body.matched_products', 2);
        $response->assertJsonPath('body.quantity_gt_zero', 1);
        $response->assertJsonPath('body.missing_itemid_products', 1);
        $response->assertJsonPath('body.not_in_pelion_products', 1);
        $response->assertJsonPath('body.skipped_delivery_24h_products', 2);
        $response->assertJsonPath('body.pelion_itemids_without_product', 1);
        $response->assertJsonPath('body.skipped_invalid', 1);
        $response->assertJsonPath('body.pelion_stock_items_quantity_gt_1', 2);

        $this->assertDatabaseHas('products', [
            'sku' => 'PELSTOCK001',
            'itemid' => 1001,
            'quantity' => 5,
        ]);

        $this->assertDatabaseHas('products', [
            'sku' => 'PELSTOCK002',
            'itemid' => 1002,
            'quantity' => 0,
        ]);

        $this->assertDatabaseHas('products', [
            'sku' => 'PELSTOCK003',
            'itemid' => 1003,
            'quantity' => 0,
        ]);

        $this->assertDatabaseHas('products', [
            'sku' => 'PELSTOCK004',
            'itemid' => null,
            'quantity' => 0,
        ]);

        $this->assertDatabaseHas('products', [
            'sku' => 'PELSTOCK005',
            'itemid' => 1005,
            'quantity' => 8,
            'delivery_24h' => 1,
        ]);

        $this->assertDatabaseHas('products', [
            'sku' => 'PELSTOCK006',
            'itemid' => 1006,
            'quantity' => 6,
            'delivery_24h' => 1,
        ]);
    }

    public function test_pelion_stock_list_reports_items_with_quantity_greater_than_one(): void
    {
        config(['services.pelion.api_key' => 'test-pelion-key']);

        Http::fake([
            'https://pelion.test/api/v1/stockList*' => Http::response([
                ['ITEMID' => '1001', 'STOCKQUANTITY' => '1'],
                ['ITEMID' => '1002', 'STOCKQUANTITY' => '2'],
                ['ITEMID' => '1003', 'STOCKQUANTITY' => '0,5'],
                ['ITEMID' => '1003', 'STOCKQUANTITY' => '0,6'],
                ['ITEMID' => 'bad', 'STOCKQUANTITY' => '9'],
            ]),
        ]);

        $response = $this->postJson(route('api.api.pelion.test'), [
            'action' => 'stock-list',
            'base_url' => 'https://pelion.test/api/v1',
        ]);

        $response->assertOk();
        $response->assertJsonPath('summary.stock_rows_received', 5);
        $response->assertJsonPath('summary.stock_itemids_received', 3);
        $response->assertJsonPath('summary.stock_items_quantity_gt_1', 2);
        $response->assertJsonPath('summary.stock_rows_quantity_gt_1', 2);
        $response->assertJsonPath('summary.skipped_invalid', 1);
    }

    private function createProduct(string $name, string $sku, ?int $itemid, int $quantity, bool $delivery24h = false): int
    {
        return (int) DB::table('products')->insertGetId([
            'author_id' => 0,
            'publisher_id' => 0,
            'action_id' => 0,
            'name' => $name,
            'sku' => $sku,
            'ean' => null,
            'isbn' => null,
            'itemid' => $itemid,
            'description' => null,
            'slug' => Str::slug($name . '-' . $sku),
            'url' => '/proizvod/' . Str::slug($name . '-' . $sku),
            'image' => null,
            'price' => 100,
            'quantity' => $quantity,
            'delivery_24h' => $delivery24h ? 1 : 0,
            'tax_id' => 1,
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
            'push' => 0,
            'status' => 1,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }
}
