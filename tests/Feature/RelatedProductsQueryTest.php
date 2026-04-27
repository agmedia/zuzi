<?php

namespace Tests\Feature;

use App\Helpers\Helper;
use App\Models\Front\Catalog\Category as FrontCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RelatedProductsQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_related_products_query_avoids_rand_sorting_for_category_products(): void
    {
        $categoryId = $this->createCategory('Preporuceno');
        $productIds = collect();

        foreach (range(1, 14) as $index) {
            $productIds->push($this->createProduct("Povezani proizvod {$index}", "REL-{$index}", $categoryId));
        }

        $category = FrontCategory::query()->findOrFail($categoryId);
        $queries = new Collection();

        DB::listen(function ($query) use ($queries) {
            $queries->push($query->sql);
        });

        $related = Helper::getRelated($category);

        $this->assertCount(10, $related);
        $this->assertTrue($related->pluck('id')->every(fn ($id) => $productIds->contains((int) $id)));
        $this->assertTrue($queries->isNotEmpty());
        $this->assertFalse(
            $queries->contains(fn (string $sql) => str_contains(strtolower($sql), 'rand(')),
            'Related products query should not use RAND() ordering.'
        );
    }

    private function createCategory(string $title, int $parentId = 0): int
    {
        return (int) DB::table('categories')->insertGetId([
            'parent_id' => $parentId,
            'title' => $title,
            'description' => null,
            'meta_title' => null,
            'meta_description' => null,
            'image' => 'media/test/category.jpg',
            'group' => 'knjige',
            'lang' => 'hr',
            'sort_order' => 0,
            'status' => 1,
            'slug' => Str::slug($title . '-' . $parentId . '-' . uniqid()),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    private function createProduct(string $name, string $sku, int $categoryId): int
    {
        $productId = (int) DB::table('products')->insertGetId([
            'author_id' => 0,
            'publisher_id' => 0,
            'action_id' => 0,
            'name' => $name,
            'sku' => $sku,
            'ean' => null,
            'description' => null,
            'slug' => Str::slug($name . '-' . $sku),
            'url' => '/proizvod/' . Str::slug($name . '-' . $sku),
            'image' => null,
            'price' => 25,
            'quantity' => 5,
            'tax_id' => 1,
            'special' => null,
            'special_from' => null,
            'special_to' => null,
            'special_lock' => 0,
            'meta_title' => $name,
            'meta_description' => null,
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

        DB::table('product_category')->insert([
            'product_id' => $productId,
            'category_id' => $categoryId,
        ]);

        return $productId;
    }
}
