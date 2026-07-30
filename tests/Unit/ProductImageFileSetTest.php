<?php

namespace Tests\Unit;

use App\Support\ProductImageFileSet;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageFileSetTest extends TestCase
{
    public function test_it_resolves_the_same_family_for_all_generated_variants(): void
    {
        $this->assertSame(
            '123/book-title',
            ProductImageFileSet::familyKeyFromStoredPath('media/img/products/123/book-title.jpg')
        );
        $this->assertSame(
            '123/book-title',
            ProductImageFileSet::familyKeyFromStoredPath('/media/img/products/123/book-title.webp')
        );
        $this->assertSame(
            '123/book-title',
            ProductImageFileSet::familyKeyFromStoredPath(
                'https://cdn.example.test/media/img/products/123/book-title-thumb.webp?version=2'
            )
        );
    }

    public function test_it_rejects_paths_outside_the_products_disk(): void
    {
        $this->assertNull(ProductImageFileSet::familyKeyFromStoredPath('media/img/blog/post.jpg'));
        $this->assertNull(ProductImageFileSet::familyKeyFromStoredPath('../../outside.jpg'));
    }

    public function test_it_deletes_every_variant_in_an_image_set(): void
    {
        Storage::fake('products');
        Storage::disk('products')->put('123/book.jpg', 'jpg');
        Storage::disk('products')->put('123/book.webp', 'webp');
        Storage::disk('products')->put('123/book-thumb.webp', 'thumb');

        $deleted = ProductImageFileSet::deleteForStoredPath('media/img/products/123/book.jpg');

        $this->assertCount(3, $deleted);
        Storage::disk('products')->assertMissing('123/book.jpg');
        Storage::disk('products')->assertMissing('123/book.webp');
        Storage::disk('products')->assertMissing('123/book-thumb.webp');
    }
}
