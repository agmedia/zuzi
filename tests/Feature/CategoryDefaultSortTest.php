<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CategoryDefaultSortTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_listing_defaults_to_popular_sort_when_sort_is_missing(): void
    {
        $categoryId = $this->createCategory('Popularna kategorija');

        $newestButLessViewedId = $this->createProduct('Najnoviji artikl', 'CAT-NEW', [
            'viewed' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mostViewedId = $this->createProduct('Najpopularniji artikl', 'CAT-POP', [
            'viewed' => 200,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $secondMostViewedId = $this->createProduct('Drugi najpopularniji artikl', 'CAT-POP-2', [
            'viewed' => 100,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $this->attachProductToCategory($newestButLessViewedId, $categoryId);
        $this->attachProductToCategory($mostViewedId, $categoryId);
        $this->attachProductToCategory($secondMostViewedId, $categoryId);
        Cache::forget('products-review-summary-v2-' . md5('group=knjigecat=' . $categoryId . 'page='));

        $response = $this->postJson('/api/v2/filter/getProducts', [
            'params' => [
                'group' => 'knjige',
                'cat' => $categoryId,
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $mostViewedId);
        $response->assertJsonPath('data.1.id', $secondMostViewedId);
        $response->assertJsonPath('data.2.id', $newestButLessViewedId);
    }

    public function test_full_offer_listing_defaults_to_newest_when_sort_is_missing(): void
    {
        $categoryId = $this->createCategory('Root kategorija', 0, [
            'group' => Str::slug(config('settings.group_path')),
        ]);

        $oldButMoreViewedId = $this->createProduct('Stariji popularniji artikl', 'ROOT-OLD', [
            'viewed' => 500,
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);

        $newestId = $this->createProduct('Najnoviji artikl na rootu', 'ROOT-NEW', [
            'viewed' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->attachProductToCategory($oldButMoreViewedId, $categoryId);
        $this->attachProductToCategory($newestId, $categoryId);
        Cache::forget('products-review-summary-v2-' . md5('group=' . Str::slug(config('settings.group_path')) . 'page='));

        $response = $this->postJson('/api/v2/filter/getProducts', [
            'params' => [
                'group' => Str::slug(config('settings.group_path')),
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $newestId);
        $response->assertJsonPath('data.1.id', $oldButMoreViewedId);
    }

    private function createCategory(string $title, int $parentId = 0, array $overrides = []): int
    {
        return (int) DB::table('categories')->insertGetId(array_merge([
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
        ], $overrides));
    }

    private function createProduct(string $name, string $sku, array $overrides = []): int
    {
        return (int) DB::table('products')->insertGetId(array_merge([
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
        ], $overrides));
    }

    private function attachProductToCategory(int $productId, int $categoryId): void
    {
        DB::table('product_category')->insert([
            'product_id' => $productId,
            'category_id' => $categoryId,
        ]);
    }
}
