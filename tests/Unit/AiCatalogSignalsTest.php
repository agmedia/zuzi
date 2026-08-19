<?php

namespace Tests\Unit;

use App\Helpers\Breadcrumb;
use App\Helpers\Metatags;
use App\Helpers\OpenAiProductFeed;
use App\Models\Front\Catalog\Author;
use App\Models\Front\Catalog\Category;
use App\Models\Front\Catalog\Product;
use App\Models\Front\Catalog\ProductImage;
use App\Models\Front\Catalog\Publisher;
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
        $this->assertStringContainsString('https://www.zuzi.hr/feeds/openai-products.jsonl', $content);
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
        $this->assertArrayNotHasKey('availability', $schema['offers']);
        $this->assertSame('https://schema.org/UsedCondition', $schema['offers']['itemCondition']);
        $this->assertSame('9789531234567', $schema['gtin13']);
        $this->assertStringNotContainsString(
            'product:availability',
            (string) file_get_contents(resource_path('views/front/catalog/product/index.blade.php'))
        );
        $imageDebug = json_encode($schema['image']);

        $this->assertContains('https://images.zuzi.hr/media/img/products/main.webp', $schema['image'], $imageDebug);
        $this->assertContains('https://images.zuzi.hr/media/img/products/extra.webp', $schema['image'], $imageDebug);
    }

    public function test_other_json_ld_offers_keep_price_and_ratings_without_availability(): void
    {
        config(['app.url' => 'https://www.zuzi.hr']);
        URL::forceRootUrl('https://www.zuzi.hr');
        URL::forceScheme('https');

        $listSchema = Metatags::itemListSchema([
            [
                'name' => 'Test knjiga',
                'url' => 'https://www.zuzi.hr/test-knjiga',
                'price' => '15.00',
                'availability' => 'https://schema.org/OutOfStock',
                'reviews_count' => 4,
                'reviews_avg_stars' => 4.8,
            ],
        ], 'https://www.zuzi.hr/knjige');
        $listItem = $listSchema['itemListElement'][0]['item'];

        $this->assertSame('15.00', $listItem['offers']['price']);
        $this->assertArrayNotHasKey('availability', $listItem['offers']);
        $this->assertSame(4.8, $listItem['aggregateRating']['ratingValue']);
        $this->assertSame(4, $listItem['aggregateRating']['reviewCount']);

        $product = new Product([
            'name' => 'Test knjiga',
            'description' => 'Opis knjige.',
            'sku' => 'SKU-BOOK',
            'price' => '15.00',
            'special' => '15.00',
            'quantity' => 0,
            'image' => 'https://images.zuzi.hr/test-knjiga.webp',
            'url' => '/test-knjiga',
        ]);
        $product->setRelation('action', null);
        $product->setRelation('author', null);
        $product->setRelation('publisher', null);

        $bookSchema = (new Breadcrumb())->productBookSchema($product);

        $this->assertSame('15.00', $bookSchema['offers']['price']);
        $this->assertArrayNotHasKey('availability', $bookSchema['offers']);

        $offerCatalog = Metatags::offerCatalogSchema(
            'Akcijska ponuda',
            'Test ponuda.',
            'https://www.zuzi.hr/akcija',
            [['name' => 'Knjige', 'url' => 'https://www.zuzi.hr/knjige']]
        );

        $this->assertArrayNotHasKey('availability', $offerCatalog['itemListElement'][0]);
    }

    public function test_openai_product_feed_maps_book_metadata(): void
    {
        config([
            'app.url' => 'https://www.zuzi.hr',
            'settings.images_domain' => 'https://images.zuzi.hr/',
        ]);
        URL::forceRootUrl('https://www.zuzi.hr');
        URL::forceScheme('https');

        $product = new Product([
            'name' => 'Čudnovate zgode šegrta Hlapića',
            'description' => '<p>Klasični hrvatski roman za djecu.</p>',
            'sku' => 'SKU-HLAPIC-01',
            'price' => 19.99,
            'special' => 14.2,
            'special_from' => now()->subDay()->toDateTimeString(),
            'special_to' => now()->addDay()->toDateTimeString(),
            'quantity' => 2,
            'condition' => 'vrlo dobro',
            'isbn' => '978-953-0-12345-6',
            'image' => 'media/img/products/hlapic.jpg',
            'image_alt' => 'Naslovnica knjige Čudnovate zgode šegrta Hlapića',
            'url' => '/kategorija-proizvoda/djecje-knjige/bajke/hlapic',
            'pages' => 176,
            'binding' => 'tvrdi uvez',
            'year' => 2024,
            'language' => 'hrvatski',
            'shipping_time' => '1-3 radna dana',
            'delivery_24h' => 1,
        ]);
        $product->id = 123;

        $rootCategory = new Category([
            'title' => 'Dječje knjige',
            'group' => 'Knjige',
            'parent_id' => 0,
        ]);
        $rootCategory->id = 10;

        $childCategory = new Category([
            'title' => 'Bajke',
            'group' => 'Knjige',
            'parent_id' => 10,
        ]);
        $childCategory->id = 11;

        $product->setRelation('action', null);
        $product->setRelation('author', new Author(['title' => 'Ivana Brlić-Mažuranić']));
        $product->setRelation('publisher', new Publisher(['title' => 'Školska knjiga']));
        $product->setRelation('images', new EloquentCollection([
            new ProductImage(['image' => 'media/img/products/hlapic-extra.jpg']),
        ]));
        $product->setRelation('categories', new EloquentCollection([$rootCategory, $childCategory]));

        $row = (new OpenAiProductFeed())->transform($product);

        $this->assertNotNull($row);
        $this->assertTrue($row['is_eligible_search']);
        $this->assertFalse($row['is_eligible_checkout']);
        $this->assertSame('123', $row['item_id']);
        $this->assertSame('9789530123456', $row['gtin']);
        $this->assertSame('SKUHLAPIC01', $row['mpn']);
        $this->assertSame('Čudnovate zgode šegrta Hlapića', $row['title']);
        $this->assertStringContainsString('Autor: Ivana Brlić-Mažuranić', $row['description']);
        $this->assertStringContainsString('Izdavač: Školska knjiga', $row['description']);
        $this->assertSame('https://www.zuzi.hr/kategorija-proizvoda/djecje-knjige/bajke/hlapic', $row['url']);
        $this->assertSame('Školska knjiga', $row['brand']);
        $this->assertSame('secondhand', $row['condition']);
        $this->assertSame('Knjige > Dječje knjige > Bajke', $row['product_category']);
        $this->assertStringStartsWith('https://', $row['image_url']);
        $this->assertStringStartsWith('https://', $row['additional_image_urls']);
        $this->assertStringEndsWith('/media/img/products/hlapic.jpg', $row['image_url']);
        $this->assertStringEndsWith('/media/img/products/hlapic-extra.jpg', $row['additional_image_urls']);
        $this->assertSame('19.99 EUR', $row['price']);
        $this->assertSame('14.20 EUR', $row['sale_price']);
        $this->assertSame('in_stock', $row['availability']);
    }
}
