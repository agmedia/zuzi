<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductDestroyActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_destroy_action_keeps_shared_action_records(): void
    {
        $actionId = $this->createAction('category');
        $productId = $this->createProduct('Kategorijska akcija', 'CAT-ACT-1', $actionId);

        $response = $this->postJson('/api/v2/product/delete/action', [
            'id' => $productId,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => 200]);
        $this->assertDatabaseHas('product_actions', [
            'id' => $actionId,
            'group' => 'category',
        ]);

        $product = DB::table('products')->where('id', $productId)->first([
            'action_id',
            'special',
            'special_from',
            'special_to',
            'special_lock',
        ]);

        $this->assertSame(0, (int) $product->action_id);
        $this->assertNull($product->special);
        $this->assertNull($product->special_from);
        $this->assertNull($product->special_to);
        $this->assertSame(0, (int) $product->special_lock);
    }

    public function test_destroy_action_deletes_single_action_records(): void
    {
        $actionId = $this->createAction('single');
        $productId = $this->createProduct('Single akcija', 'SINGLE-ACT-1', $actionId);

        $response = $this->postJson('/api/v2/product/delete/action', [
            'id' => $productId,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => 200]);
        $this->assertDatabaseMissing('product_actions', [
            'id' => $actionId,
        ]);

        $product = DB::table('products')->where('id', $productId)->first([
            'action_id',
            'special',
            'special_from',
            'special_to',
            'special_lock',
        ]);

        $this->assertSame(0, (int) $product->action_id);
        $this->assertNull($product->special);
        $this->assertNull($product->special_from);
        $this->assertNull($product->special_to);
        $this->assertSame(0, (int) $product->special_lock);
    }

    private function createAction(string $group): int
    {
        return (int) DB::table('product_actions')->insertGetId([
            'title' => Str::title($group) . ' akcija',
            'type' => 'P',
            'discount' => 10,
            'group' => $group,
            'links' => json_encode([1]),
            'date_start' => Carbon::now()->subDay(),
            'date_end' => Carbon::now()->addDay(),
            'data' => null,
            'coupon' => null,
            'quantity' => 0,
            'lock' => 0,
            'status' => 1,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    private function createProduct(string $name, string $sku, int $actionId): int
    {
        return (int) DB::table('products')->insertGetId([
            'author_id' => 0,
            'publisher_id' => 0,
            'action_id' => $actionId,
            'name' => $name,
            'sku' => $sku,
            'ean' => null,
            'description' => null,
            'slug' => Str::slug($name . '-' . $sku),
            'url' => '/proizvod/' . Str::slug($name . '-' . $sku),
            'image' => null,
            'price' => 100,
            'quantity' => 5,
            'tax_id' => 1,
            'special' => 90,
            'special_from' => Carbon::now()->subDay(),
            'special_to' => Carbon::now()->addDay(),
            'special_lock' => 0,
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
