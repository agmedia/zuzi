<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductListingDeliveryWindowApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_endpoint_marks_only_delfi_and_laguna_products_for_the_listing_tooltip(): void
    {
        Cache::flush();

        $lagunaPublisherId = $this->createPublisher('Laguna', 'laguna');
        $delfiPublisherId = $this->createPublisher('Delfi', 'delfi');
        $delfinPublisherId = $this->createPublisher('DELFIN', 'delfin');
        $lagunaProductId = $this->createProduct('Laguna knjiga', 'LAG-ICON', $lagunaPublisherId);
        $delfiProductId = $this->createProduct('Delfi knjiga', 'DELFI-ICON', $delfiPublisherId);
        $delfinProductId = $this->createProduct('Delfin knjiga', 'DEL-ICON', $delfinPublisherId);

        $response = $this->postJson('/api/v2/filter/getProducts', [
            'params' => [
                'ids' => '[' . $lagunaProductId . ',' . $delfiProductId . ',' . $delfinProductId . ']',
            ],
        ]);

        $response->assertOk();

        $products = collect($response->json('data'))->keyBy('id');

        $this->assertTrue($products[$lagunaProductId]['uses_twenty_day_delivery_window']);
        $this->assertTrue($products[$delfiProductId]['uses_twenty_day_delivery_window']);
        $this->assertFalse($products[$delfinProductId]['uses_twenty_day_delivery_window']);
    }

    private function createPublisher(string $title, string $slug): int
    {
        return (int) DB::table('publishers')->insertGetId([
            'letter' => mb_substr($title, 0, 1),
            'title' => $title,
            'slug' => $slug,
            'url' => '/nakladnik/' . $slug,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createProduct(string $name, string $sku, int $publisherId): int
    {
        return (int) DB::table('products')->insertGetId([
            'author_id' => 0,
            'publisher_id' => $publisherId,
            'action_id' => 0,
            'name' => $name,
            'sku' => $sku,
            'ean' => null,
            'description' => null,
            'slug' => Str::slug($name . '-' . $sku),
            'url' => '/proizvod/' . Str::slug($name . '-' . $sku),
            'image' => '/media/test/' . Str::slug($sku) . '.webp',
            'price' => 15,
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
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
