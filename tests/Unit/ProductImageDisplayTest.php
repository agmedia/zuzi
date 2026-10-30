<?php

namespace Tests\Unit;

use App\Models\Back\Catalog\Product\Product;
use App\Support\ProductImageDisplay;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageDisplayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'filesystems.disks.products.url' => 'media/img/products/',
            'settings.images_domain' => 'https://www.zuzi.hr',
        ]);
        Storage::fake('products');
        Storage::disk('products')->put('123/book-title-abcd.webp', $this->webp());
    }

    /** @dataProvider displayPathProvider */
    public function test_it_uses_paired_webp_only_for_supported_local_product_paths(string $stored, string $expected): void
    {
        $this->assertSame(asset($expected), ProductImageDisplay::url($stored));
    }

    public static function displayPathProvider(): array
    {
        return [
            'relative jpg' => ['media/img/products/123/book-title-abcd.jpg', 'media/img/products/123/book-title-abcd.webp'],
            'root relative uppercase jpeg' => ['/media/img/products/123/book-title-abcd.JPEG', '/media/img/products/123/book-title-abcd.webp'],
            'known image host and query' => ['https://www.zuzi.hr/media/img/products/123/book-title-abcd.JPG?v=2#cover', 'https://www.zuzi.hr/media/img/products/123/book-title-abcd.webp?v=2#cover'],
            'protocol relative known host' => ['//www.zuzi.hr/media/img/products/123/book-title-abcd.jpg', '//www.zuzi.hr/media/img/products/123/book-title-abcd.webp'],
            'unrelated external host' => ['https://external.test/media/img/products/123/book-title-abcd.jpg', 'https://external.test/media/img/products/123/book-title-abcd.jpg'],
            'unrelated origin port' => ['https://www.zuzi.hr:9443/media/img/products/123/book-title-abcd.jpg', 'https://www.zuzi.hr:9443/media/img/products/123/book-title-abcd.jpg'],
            'outside product directory' => ['media/img/blog/123/book-title-abcd.jpg', 'media/img/blog/123/book-title-abcd.jpg'],
            'encoded traversal' => ['media/img/products/123/%2e%2e/book-title-abcd.jpg', 'media/img/products/123/%2e%2e/book-title-abcd.jpg'],
            'already webp' => ['media/img/products/123/book-title-abcd.webp', 'media/img/products/123/book-title-abcd.webp'],
        ];
    }

    public function test_missing_or_invalid_webp_keeps_original_and_does_not_write_files(): void
    {
        $original = 'media/img/products/123/missing.jpg';
        Storage::disk('products')->put('123/missing.jpg', 'original jpg');

        $this->assertSame(asset($original), ProductImageDisplay::url($original));
        Storage::disk('products')->put('123/missing.webp', 'not a webp image');
        $this->assertSame(asset($original), ProductImageDisplay::url($original));
        $this->assertSame('original jpg', Storage::disk('products')->get('123/missing.jpg'));
        $this->assertSame('not a webp image', Storage::disk('products')->get('123/missing.webp'));
    }

    public function test_display_selection_preserves_back_model_raw_image_and_crud_metadata(): void
    {
        $original = 'media/img/products/123/book-title-abcd.jpg';
        $product = new Product();
        $product->setRawAttributes(['id' => 123, 'image' => $original], true);

        $this->assertSame(asset('media/img/products/123/book-title-abcd.webp'), ProductImageDisplay::url($product->image));
        $this->assertSame($original, $product->image);
        $this->assertSame($original, $product->getRawOriginal('image'));
        $this->assertSame('book-title', $product->imageName());
        $this->assertSame([], $product->getDirty());
    }

    public function test_product_editor_previews_use_webp_without_changing_titles_or_input_names(): void
    {
        $product = new Product();
        $product->setRawAttributes([
            'id' => 123,
            'image' => 'media/img/products/123/book-title-abcd.jpg',
            'image_alt' => 'Izvorni opis',
        ], true);
        $product->setRelation('images', collect());
        $image = [
            'id' => 456,
            'image' => 'media/img/products/123/book-title-abcd.JPEG',
            'title' => 'Izvorni naslov dodatne slike',
            'alt' => 'Dodatna slika',
            'sort_order' => 1,
            'published' => 1,
        ];

        $html = view('back.catalog.product.edit-photos', ['product' => $product, 'data' => ['images' => [$image]]])->render();

        $this->assertSame(2, substr_count($html, 'src="' . asset('media/img/products/123/book-title-abcd.webp') . '"'));
        $this->assertStringContainsString('name="slim[0][image]"', $html);
        $this->assertStringContainsString('name="slim[456][image]"', $html);
        $this->assertStringContainsString('value="book-title"', $html);
        $this->assertStringContainsString('value="Izvorni naslov dodatne slike"', $html);
        $this->assertSame('media/img/products/123/book-title-abcd.jpg', $product->getRawOriginal('image'));
        $this->assertSame('media/img/products/123/book-title-abcd.JPEG', $image['image']);
    }

    private function webp(): string
    {
        return base64_decode('UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEADsD+JaQAA3AAAAAA');
    }
}
