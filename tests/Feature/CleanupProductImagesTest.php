<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CleanupProductImagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('products');
    }

    public function test_dry_run_reports_orphans_without_deleting_files(): void
    {
        $this->createProduct('media/img/products/10/active.jpg');
        $this->putImageSet('10/active');
        $this->putImageSet('99/orphan');

        $this->artisan('images:cleanup-products', ['--min-age' => 0])
            ->assertExitCode(0);

        Storage::disk('products')->assertExists('10/active.jpg');
        Storage::disk('products')->assertExists('10/active.webp');
        Storage::disk('products')->assertExists('10/active-thumb.webp');
        Storage::disk('products')->assertExists('99/orphan.jpg');
        Storage::disk('products')->assertExists('99/orphan.webp');
        Storage::disk('products')->assertExists('99/orphan-thumb.webp');
    }

    public function test_delete_removes_only_unreferenced_image_sets(): void
    {
        $this->createProduct('https://images.example.test/media/img/products/10/active.jpg?version=1');
        $this->putImageSet('10/active');
        $this->putImageSet('99/orphan');

        $this->artisan('images:cleanup-products', [
            '--delete' => true,
            '--min-age' => 0,
        ])->assertExitCode(0);

        Storage::disk('products')->assertExists('10/active.jpg');
        Storage::disk('products')->assertExists('10/active.webp');
        Storage::disk('products')->assertExists('10/active-thumb.webp');
        Storage::disk('products')->assertMissing('99/orphan.jpg');
        Storage::disk('products')->assertMissing('99/orphan.webp');
        Storage::disk('products')->assertMissing('99/orphan-thumb.webp');
    }

    public function test_product_image_table_also_protects_a_complete_image_set(): void
    {
        $productId = $this->createProduct(null);

        DB::table('product_images')->insert([
            'product_id' => $productId,
            'image' => 'media/img/products/10/gallery.jpg',
            'alt' => 'Gallery',
            'published' => 1,
            'sort_order' => 0,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $this->putImageSet('10/gallery');

        $this->artisan('images:cleanup-products', [
            '--delete' => true,
            '--min-age' => 0,
        ])->assertExitCode(0);

        Storage::disk('products')->assertExists('10/gallery.jpg');
        Storage::disk('products')->assertExists('10/gallery.webp');
        Storage::disk('products')->assertExists('10/gallery-thumb.webp');
    }

    private function putImageSet(string $family): void
    {
        Storage::disk('products')->put($family . '.jpg', 'jpg');
        Storage::disk('products')->put($family . '.webp', 'webp');
        Storage::disk('products')->put($family . '-thumb.webp', 'thumb');
    }

    private function createProduct(?string $image): int
    {
        return (int) DB::table('products')->insertGetId([
            'author_id' => 0,
            'publisher_id' => 0,
            'action_id' => 0,
            'name' => 'Cleanup test',
            'sku' => 'CLEANUP-1',
            'ean' => null,
            'description' => null,
            'slug' => 'cleanup-test',
            'url' => '/cleanup-test',
            'image' => $image,
            'price' => 10,
            'quantity' => 1,
            'tax_id' => 1,
            'special' => null,
            'special_from' => null,
            'special_to' => null,
            'meta_title' => null,
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
    }
}
