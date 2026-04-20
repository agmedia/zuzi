<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductActionFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_endpoint_returns_only_items_with_active_action_when_akcija_filter_is_enabled(): void
    {
        $regularProductId = $this->createProduct('Regularni artikl', 'REG-1', 100, null, null, null);
        $actionProductId = $this->createProduct('Artikl na akciji', 'ACT-1', 100, 80, now()->subDay(), now()->addDay());
        $expiredActionProductId = $this->createProduct('Istekla akcija', 'ACT-2', 100, 70, now()->subDays(5), now()->subDay());

        $response = $this->postJson('/api/v2/filter/getProducts', [
            'params' => [
                'ids' => '[' . implode(',', [$regularProductId, $actionProductId, $expiredActionProductId]) . ']',
                'akcija' => 1,
            ],
        ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $actionProductId);
    }

    public function test_toolbar_filters_report_action_checkbox_availability_based_on_listing(): void
    {
        $regularProductId = $this->createProduct('Regularni artikl', 'REG-2', 120, null, null, null);
        $actionProductId = $this->createProduct('Još jedan artikl na akciji', 'ACT-3', 120, 90, now()->subDay(), now()->addDay());

        $withActionResponse = $this->postJson('/api/v2/filter/getToolbarFilters', [
            'params' => [
                'ids' => '[' . implode(',', [$regularProductId, $actionProductId]) . ']',
            ],
        ]);

        $withActionResponse->assertOk();
        $withActionResponse->assertJsonPath('action_available', true);

        $withoutActionResponse = $this->postJson('/api/v2/filter/getToolbarFilters', [
            'params' => [
                'ids' => '[' . $regularProductId . ']',
            ],
        ]);

        $withoutActionResponse->assertOk();
        $withoutActionResponse->assertJsonPath('action_available', false);
    }

    private function createProduct(
        string $name,
        string $sku,
        float $price,
        ?float $special,
        $specialFrom,
        $specialTo
    ): int {
        return (int) DB::table('products')->insertGetId([
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
            'price' => $price,
            'quantity' => 5,
            'tax_id' => 1,
            'special' => $special,
            'special_from' => $specialFrom ? Carbon::parse($specialFrom) : null,
            'special_to' => $specialTo ? Carbon::parse($specialTo) : null,
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
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
