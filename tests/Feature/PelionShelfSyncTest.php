<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PelionShelfSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_pelion_shelf_sync_updates_product_shelves_by_itemid(): void
    {
        config(['services.pelion.api_key' => 'test-pelion-key']);

        $this->createProduct('Pelion shelf one', 'PELSHELF001', 1001, null);
        $this->createProduct('Pelion shelf two', 'PELSHELF002', 1002, 'OLD');
        $this->createProduct('Pelion shelf unchanged', 'PELSHELF003', 1003, 'ALT6');
        $this->createProduct('Generic group skipped', 'PELSHELF004', 1004, 'KEEP');
        $this->createProduct('Missing group skipped', 'PELSHELF005', 1005, 'KEEP2');

        Http::fake([
            'https://pelion.test/api/v1/itemGroupList*' => Http::response([
                ['ITEMGROUPID' => '20    ', 'ITEMGROUPNAME' => 'ALT6                                    '],
                ['ITEMGROUPID' => '21    ', 'ITEMGROUPNAME' => 'DJ1                                     '],
                ['ITEMGROUPID' => '2     ', 'ITEMGROUPNAME' => 'Trgovačka roba                          '],
            ]),
            'https://pelion.test/api/v1/itemList*' => Http::response([
                ['ITEMID' => '1001', 'ITEMGROUPID' => '20    ', 'ITEMCODE' => 'SKU-1001', 'ITEMNAME' => 'Book one'],
                ['ITEMID' => '1002', 'ITEMGROUPID' => '21    ', 'ITEMCODE' => 'SKU-1002', 'ITEMNAME' => 'Book two'],
                ['ITEMID' => '1003', 'ITEMGROUPID' => '20    ', 'ITEMCODE' => 'SKU-1003', 'ITEMNAME' => 'Book three'],
                ['ITEMID' => '1004', 'ITEMGROUPID' => '2     ', 'ITEMGRUPNAME' => 'Trgovačka roba'],
                ['ITEMID' => '1005', 'ITEMGROUPID' => '999   '],
                ['ITEMID' => '9999', 'ITEMGROUPID' => '20    ', 'ITEMCODE' => 'SKU-9999', 'ITEMNAME' => 'Missing local'],
                ['ITEMID' => 'bad', 'ITEMGROUPID' => '20    '],
            ]),
        ]);

        $response = $this->postJson(route('api.api.pelion.test'), [
            'action' => 'sync-product-shelves',
            'base_url' => 'https://pelion.test/api/v1',
        ]);

        $response->assertOk();
        $response->assertJsonPath('body.updated', 2);
        $response->assertJsonPath('body.unchanged', 1);
        $response->assertJsonPath('body.matched_products', 3);
        $response->assertJsonPath('body.missing_products', 1);
        $response->assertJsonPath('body.skipped_generic_groups', 1);
        $response->assertJsonPath('body.skipped_generic_item_groups', 1);
        $response->assertJsonPath('body.skipped_missing_group', 1);
        $response->assertJsonPath('body.skipped_invalid_items', 1);

        $this->assertDatabaseHas('products', [
            'sku' => 'PELSHELF001',
            'itemid' => 1001,
            'polica' => 'ALT6',
        ]);

        $this->assertDatabaseHas('products', [
            'sku' => 'PELSHELF002',
            'itemid' => 1002,
            'polica' => 'DJ1',
        ]);

        $this->assertDatabaseHas('products', [
            'sku' => 'PELSHELF003',
            'itemid' => 1003,
            'polica' => 'ALT6',
        ]);

        $this->assertDatabaseHas('products', [
            'sku' => 'PELSHELF004',
            'itemid' => 1004,
            'polica' => 'KEEP',
        ]);

        $this->assertDatabaseHas('products', [
            'sku' => 'PELSHELF005',
            'itemid' => 1005,
            'polica' => 'KEEP2',
        ]);
    }

    private function createProduct(string $name, string $sku, ?int $itemid, ?string $polica): int
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
            'polica' => $polica,
            'description' => null,
            'slug' => Str::slug($name . '-' . $sku),
            'url' => '/proizvod/' . Str::slug($name . '-' . $sku),
            'image' => null,
            'price' => 100,
            'quantity' => 0,
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
