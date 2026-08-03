<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MigrateProductImagesToWebpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('products');
    }

    public function test_dry_run_does_not_change_database_paths_or_files(): void
    {
        $productId = $this->createProduct('media/img/products/10/active.jpg');
        $this->putImageSet('10/active');

        $this->artisan('images:migrate-products-to-webp')
            ->assertExitCode(0);

        $this->assertSame(
            'media/img/products/10/active.jpg',
            DB::table('products')->where('id', $productId)->value('image')
        );
        Storage::disk('products')->assertExists('10/active.jpg');
        Storage::disk('products')->assertExists('10/active.webp');
        Storage::disk('products')->assertExists('10/active-thumb.webp');
    }

    public function test_apply_updates_all_product_references_before_deleting_jpg_files(): void
    {
        $productId = $this->createProduct('media/img/products/10/active.jpg');
        $imageId = (int) DB::table('product_images')->insertGetId([
            'product_id' => $productId,
            'image' => 'media/img/products/10/gallery.jpg',
            'alt' => 'Gallery',
            'published' => 1,
            'sort_order' => 0,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        $this->putImageSet('10/active');
        $this->putImageSet('10/gallery');

        $this->artisan('images:migrate-products-to-webp', ['--apply' => true])
            ->assertExitCode(0);

        $this->assertSame(
            'media/img/products/10/active.webp',
            DB::table('products')->where('id', $productId)->value('image')
        );
        $this->assertSame(
            'media/img/products/10/gallery.webp',
            DB::table('product_images')->where('id', $imageId)->value('image')
        );
        Storage::disk('products')->assertMissing('10/active.jpg');
        Storage::disk('products')->assertMissing('10/gallery.jpg');
        Storage::disk('products')->assertExists('10/active.webp');
        Storage::disk('products')->assertExists('10/active-thumb.webp');
        Storage::disk('products')->assertExists('10/gallery.webp');
        Storage::disk('products')->assertExists('10/gallery-thumb.webp');
    }

    public function test_apply_keeps_jpg_reference_and_file_when_webp_is_missing(): void
    {
        $productId = $this->createProduct('media/img/products/10/missing-webp.jpg');
        Storage::disk('products')->put('10/missing-webp.jpg', 'jpg');

        $this->artisan('images:migrate-products-to-webp', ['--apply' => true])
            ->assertExitCode(0);

        $this->assertSame(
            'media/img/products/10/missing-webp.jpg',
            DB::table('products')->where('id', $productId)->value('image')
        );
        Storage::disk('products')->assertExists('10/missing-webp.jpg');
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
            'name' => 'WebP migration test',
            'sku' => 'WEBP-1',
            'ean' => null,
            'description' => null,
            'slug' => 'webp-migration-test',
            'url' => '/webp-migration-test',
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
