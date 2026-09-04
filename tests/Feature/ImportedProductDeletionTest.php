<?php

namespace Tests\Feature;

use App\Models\Back\Catalog\DelfiImportProduct;
use App\Models\Back\Catalog\LagunaImportProduct;
use App\Models\Back\Catalog\NovellaImportProduct;
use App\Models\Back\Catalog\Product\Product;
use App\Models\Back\Catalog\ZnanjeImportProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImportedProductDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_product_returns_linked_sources_to_new_in_all_imports(): void
    {
        $productId = DB::table('products')->insertGetId([
            'name' => 'Artikl za brisanje',
            'sku' => '990001',
            'itemid' => 990001,
            'isbn' => '9789539900011',
            'slug' => 'artikl-za-brisanje',
            'url' => '/',
            'price' => 10,
            'quantity' => 1,
            'tax_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $hash = hash('sha256', 'unchanged-import-source');
        $sources = [
            LagunaImportProduct::query()->create($this->sourceAttributes('laguna', $productId, $hash)),
            DelfiImportProduct::query()->create($this->sourceAttributes('delfi', $productId, $hash)),
            NovellaImportProduct::query()->create($this->sourceAttributes('novella', $productId, $hash)),
            ZnanjeImportProduct::query()->create($this->sourceAttributes('znanje', $productId, $hash)),
        ];

        Product::query()->findOrFail($productId)->delete();

        foreach ($sources as $source) {
            $source->refresh();
            $this->assertNull($source->product_id);
            $this->assertNull($source->imported_hash);
            $this->assertNull($source->imported_at);
            $this->assertSame('new', $source->check_status);
            $this->assertSame('new', $source->ui_status);
        }
    }

    public function test_changed_source_requires_inspection_after_linked_product_is_deleted(): void
    {
        $productId = DB::table('products')->insertGetId([
            'name' => 'Promijenjeni artikl za brisanje',
            'sku' => '990002',
            'itemid' => 990002,
            'isbn' => '9789539900028',
            'slug' => 'promijenjeni-artikl-za-brisanje',
            'url' => '/',
            'price' => 10,
            'quantity' => 1,
            'tax_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $source = DelfiImportProduct::query()->create(array_merge(
            $this->sourceAttributes('delfi-changed', $productId, hash('sha256', 'new-source')),
            ['checked_source_hash' => hash('sha256', 'old-source')]
        ));

        Product::query()->findOrFail($productId)->delete();

        $source->refresh();
        $this->assertNull($source->product_id);
        $this->assertSame('pending', $source->check_status);
        $this->assertSame('pending', $source->ui_status);
    }

    private function sourceAttributes(string $source, int $productId, string $hash): array
    {
        return [
            'external_id' => strtoupper($source) . '-DELETE-1',
            'product_id' => $productId,
            'name' => 'Uvezeni artikl ' . $source,
            'source_category' => 'Knjiga',
            'source_url' => 'https://example.com/' . $source,
            'source_hash' => $hash,
            'checked_source_hash' => $hash,
            'imported_hash' => $hash,
            'feed_token' => (string) Str::uuid(),
            'is_current' => true,
            'check_status' => 'matched',
            'imported_at' => now(),
        ];
    }
}
