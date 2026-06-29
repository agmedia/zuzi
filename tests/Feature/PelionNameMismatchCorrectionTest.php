<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PelionNameMismatchCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        config(['services.pelion.api_key' => 'test-pelion-key']);
    }

    public function test_scan_finds_candidate_when_pelion_name_is_truncated(): void
    {
        $productId = $this->createProduct('Priča o amuletu i drugim čudima', 'OLDAMULET', 9001, '9789530000000', 7);
        $this->createProduct('Sasvim druga knjiga', 'OTHERBOOK', 9002, '9789530000001', 3);

        Http::fake([
            'https://pelion.test/api/v1/itemList*' => Http::response([
                ['ITEMID' => '1001', 'ITEMCODE' => 'PEL1001', 'ITEMBARCODE' => '9789531111111', 'ITEMNAME' => 'PRIČA O AMUL'],
                ['ITEMID' => '9001', 'ITEMCODE' => 'WRONG001', 'ITEMBARCODE' => '9789532222222', 'ITEMNAME' => 'KRIVI ARTIKL'],
                ['ITEMID' => '1002', 'ITEMCODE' => 'OTHER001', 'ITEMBARCODE' => '9789533333333', 'ITEMNAME' => 'NEPOVEZAN NASLOV'],
            ]),
            'https://pelion.test/api/v1/stockList*' => Http::response([
                ['ITEMID' => '1001', 'STOCKQUANTITY' => '4'],
                ['ITEMID' => '1001', 'STOCKQUANTITY' => '2'],
                ['ITEMID' => '9001', 'STOCKQUANTITY' => '1'],
            ]),
        ]);

        $response = $this->postJson(route('api.api.pelion.test'), [
            'action' => 'scan-name-mismatches',
            'base_url' => 'https://pelion.test/api/v1',
            'min_score' => 88,
            'limit' => 10,
        ]);

        $response->assertOk();
        $response->assertJsonPath('body.candidates_found', 1);
        $response->assertJsonPath('body.candidates.0.product.id', $productId);
        $response->assertJsonPath('body.candidates.0.candidate.ITEMID', 1001);
        $response->assertJsonPath('body.candidates.0.candidate.STOCKQUANTITY', 6);
        $response->assertJsonPath('body.candidates.0.current_pelion_item.STOCKQUANTITY', 1);
        $this->assertGreaterThanOrEqual(88, $response->json('body.candidates.0.score'));
    }

    public function test_apply_updates_identifiers_and_quantity_from_stock_list(): void
    {
        $productId = $this->createProduct('Priča o amuletu i drugim čudima', 'OLDAMULET', 9001, '9789530000000', 7);

        Http::fake([
            'https://pelion.test/api/v1/itemList*' => Http::response([
                ['ITEMID' => '1001', 'ITEMCODE' => 'PEL1001', 'ITEMBARCODE' => '9789531111111', 'ITEMNAME' => 'PRIČA O AMUL'],
            ]),
            'https://pelion.test/api/v1/stockList*' => Http::response([
                ['ITEMID' => '1001', 'STOCKQUANTITY' => '3'],
                ['ITEMID' => '1001', 'STOCKQUANTITY' => '2'],
            ]),
        ]);

        $response = $this->postJson(route('api.api.pelion.test'), [
            'action' => 'apply-name-mismatches',
            'base_url' => 'https://pelion.test/api/v1',
            'matches' => [
                ['product_id' => $productId, 'item_id' => 1001],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('body.applied', 1);
        $response->assertJsonPath('body.quantity_updated', 1);

        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'sku' => 'OLDAMULET',
            'isbn' => '9789531111111',
            'ean' => '9789530000000',
            'itemid' => 1001,
            'quantity' => 5,
        ]);
    }

    public function test_apply_skips_itemid_that_belongs_to_unselected_product(): void
    {
        $productId = $this->createProduct('Priča o amuletu i drugim čudima', 'OLDAMULET', 9001, '9789530000000', 7);
        $ownerId = $this->createProduct('Već povezani Pelion artikl', 'OWNER', 1001, '9789539999999', 1);

        Http::fake([
            'https://pelion.test/api/v1/itemList*' => Http::response([
                ['ITEMID' => '1001', 'ITEMCODE' => 'PEL1001', 'ITEMBARCODE' => '9789531111111', 'ITEMNAME' => 'PRIČA O AMUL'],
            ]),
            'https://pelion.test/api/v1/stockList*' => Http::response([
                ['ITEMID' => '1001', 'STOCKQUANTITY' => '5'],
            ]),
        ]);

        $response = $this->postJson(route('api.api.pelion.test'), [
            'action' => 'apply-name-mismatches',
            'base_url' => 'https://pelion.test/api/v1',
            'matches' => [
                ['product_id' => $productId, 'item_id' => 1001],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('body.applied', 0);
        $response->assertJsonPath('body.skipped', 1);
        $response->assertJsonPath('body.examples.skipped.0.reason', 'itemid_taken_by_other_product');

        $this->assertDatabaseHas('products', [
            'id' => $ownerId,
            'itemid' => 1001,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'itemid' => 9001,
            'quantity' => 7,
        ]);
    }

    private function createProduct(string $name, string $sku, ?int $itemid, ?string $isbn, int $quantity): int
    {
        return (int) DB::table('products')->insertGetId([
            'author_id' => 0,
            'publisher_id' => 0,
            'action_id' => 0,
            'name' => $name,
            'sku' => $sku,
            'ean' => $isbn,
            'isbn' => $isbn,
            'itemid' => $itemid,
            'description' => null,
            'slug' => Str::slug($name . '-' . $sku),
            'url' => '/proizvod/' . Str::slug($name . '-' . $sku),
            'image' => null,
            'price' => 100,
            'quantity' => $quantity,
            'delivery_24h' => 0,
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
