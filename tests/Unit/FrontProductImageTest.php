<?php

namespace Tests\Unit;

use App\Models\Front\Catalog\Product;
use Tests\TestCase;

class FrontProductImageTest extends TestCase
{
    public function test_missing_image_uses_the_catalog_placeholder_instead_of_the_site_root(): void
    {
        config(['settings.images_domain' => 'https://www.zuzi.hr/']);
        $product = new Product();
        $product->setRawAttributes(['image' => null]);

        $this->assertSame(
            'https://www.zuzi.hr/media/img/knjiga-detalj.jpg',
            $product->image
        );
        $this->assertSame($product->image, $product->thumb);
    }

    public function test_existing_jpg_paths_are_still_exposed_as_webp(): void
    {
        config(['settings.images_domain' => 'https://www.zuzi.hr/']);
        $product = new Product();
        $product->setRawAttributes(['image' => 'media/img/products/1/cover.jpg']);

        $this->assertSame(
            'https://www.zuzi.hr/media/img/products/1/cover.webp',
            $product->image
        );
    }
}
