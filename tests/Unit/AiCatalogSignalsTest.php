<?php

namespace Tests\Unit;

use App\Helpers\Metatags;
use App\Models\Front\Catalog\Product;
use App\Models\Front\Catalog\ProductImage;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AiCatalogSignalsTest extends TestCase
{
    public function test_llms_txt_exists_and_points_to_catalog_discovery_urls(): void
    {
        $content = (string) file_get_contents(public_path('llms.txt'));

        $this->assertStringContainsString('https://www.zuzi.hr/sitemap.xml', $content);
        $this->assertStringContainsString('https://www.zuzi.hr/sitemap/products.xml', $content);
        $this->assertStringContainsString('https://www.zuzi.hr/robots.txt', $content);
    }

    public function test_global_schema_contains_bookstore_and_website(): void
    {
        $schema = Metatags::globalSchema();

        $this->assertSame('BookStore', $schema[0]['@type']);
        $this->assertSame('WebSite', $schema[1]['@type']);
        $this->assertSame('SearchAction', $schema[1]['potentialAction']['@type']);
    }

    public function test_product_schema_uses_machine_readable_offer_data(): void
    {
        config([
            'app.url' => 'https://www.zuzi.hr',
            'settings.images_domain' => 'https://images.zuzi.hr/',
        ]);
        URL::forceRootUrl('https://www.zuzi.hr');
        URL::forceScheme('https');

        $product = new Product([
            'name' => 'Test knjiga',
            'description' => '<p>Opis knjige za test.</p>',
            'sku' => 'SKU-123',
            'price' => 12.5,
            'special' => 10,
            'quantity' => 3,
            'condition' => 'rabljeno',
            'isbn' => '978-953-123-456-7',
            'image' => 'media/img/products/main.jpg',
            'url' => '/kategorija-proizvoda/test/test-knjiga',
        ]);
        $product->setRelation('action', null);
        $product->setRelation('author', null);
        $product->setRelation('publisher', null);
        $product->setRelation('images', new EloquentCollection([
            new ProductImage(['image' => 'media/img/products/extra.jpg']),
        ]));

        $schema = Metatags::productSchema($product);

        $this->assertSame('https://www.zuzi.hr/kategorija-proizvoda/test/test-knjiga#product', $schema['@id']);
        $this->assertSame('10.00', $schema['offers']['price']);
        $this->assertSame('https://schema.org/InStock', $schema['offers']['availability']);
        $this->assertSame('https://schema.org/UsedCondition', $schema['offers']['itemCondition']);
        $this->assertSame('9789531234567', $schema['gtin13']);
        $imageDebug = json_encode($schema['image']);

        $this->assertContains('https://images.zuzi.hr/media/img/products/main.webp', $schema['image'], $imageDebug);
        $this->assertContains('https://images.zuzi.hr/media/img/products/extra.webp', $schema['image'], $imageDebug);
    }
}
