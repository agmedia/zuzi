<?php

namespace Tests\Feature;

use App\Models\Back\Catalog\Product\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductStockRestoredMarkerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_remove_the_quantity_star_from_an_individual_product(): void
    {
        $this->actingAs(User::factory()->create());

        $categoryId = DB::table('categories')->insertGetId([
            'parent_id' => 0,
            'title' => 'Test kategorija',
            'slug' => 'test-kategorija',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $product = Product::query()->create([
            'name' => 'Artikl s oznakom',
            'sku' => 'STAR-1',
            'slug' => 'artikl-s-oznakom',
            'url' => '/',
            'price' => 10,
            'quantity' => 5,
            'tax_id' => 1,
            'itemid' => 12345,
            'stock_restored_from_backup' => true,
            'status' => true,
        ]);

        DB::table('product_category')->insert([
            'product_id' => $product->id,
            'category_id' => $categoryId,
        ]);

        $this->get(route('products.edit', ['product' => $product]))
            ->assertOk()
            ->assertSee('name="stock_restored_from_backup"', false)
            ->assertSee('Zvjezdica uz količinu');

        $response = $this->patch(route('products.update', ['product' => $product]), [
            'name' => $product->name,
            'sku' => $product->sku,
            'slug' => $product->slug,
            'isbn' => '',
            'itemid' => $product->itemid,
            'price' => $product->price,
            'quantity' => $product->quantity,
            'tax_id' => $product->tax_id,
            'category' => [$categoryId],
            'stock_restored_from_backup' => 0,
            'status' => 'on',
        ]);

        $response->assertRedirect(route('products'));
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_restored_from_backup' => 0,
        ]);

        $this->get(route('products'))
            ->assertOk()
            ->assertDontSee('Količina vraćena iz oldzuzi backup razlike');
    }
}
