<?php

namespace Tests\Feature;

use App\Models\Back\Catalog\NovellaImportProduct;
use App\Models\Back\Catalog\Product\Product;
use App\Services\Novella\NovellaImportService;
use App\Services\Novella\NovellaImportSettings;
use App\Services\Novella\NovellaProductDetailParser;
use App\Services\Novella\NovellaProductPageClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NovellaImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_a_checked_book_with_eur_markup_and_category_mapping(): void
    {
        $publisherParent = $this->category('Nakladnici');
        $publisherCategory = $this->category('Novella', $publisherParent);
        $booksParent = $this->category('Knjige');
        $mappedCategory = $this->category('Književnost', $booksParent);
        $publisherId = DB::table('publishers')->insertGetId([
            'title' => 'Novella',
            'slug' => 'novella',
            'url' => '/izdavaci/novella',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        app(NovellaImportSettings::class)->save([
            'markup_percent' => 20,
            'publisher_parent_category_id' => $publisherParent,
            'publisher_category_id' => $publisherCategory,
            'publisher_id' => $publisherId,
            'category_map' => ['Književnost' => $mappedCategory],
            'default_quantity' => 3,
            'activate_new_products' => 0,
            'existing_action' => 'skip',
        ]);
        $hash = hash('sha256', 'novella-checked-v1');
        $source = NovellaImportProduct::query()->create([
            'external_id' => '22905',
            'remote_product_id' => 22905,
            'name' => 'Agentica',
            'description' => 'Hrvatski opis knjige.',
            'source_category' => 'Knjige',
            'source_categories' => ['Knjige', 'Književnost'],
            'source_publisher' => 'Novella',
            'source_url' => 'https://novella.hr/proizvod/agentica/',
            'image_url' => null,
            'additional_image_urls' => [],
            'price_eur' => 10,
            'sale_price_eur' => 8,
            'availability' => 'in_stock',
            'isbn' => '9789534002261',
            'ean' => '9789534002261',
            'author' => 'Katherine Center',
            'source_genres' => ['Književnost'],
            'genre' => 'Književnost',
            'format' => '13 × 20 cm',
            'pages' => 320,
            'letter' => 'Latinica',
            'binding' => 'Meki',
            'publication_year' => 2026,
            'language' => 'Hrvatski',
            'source_hash' => $hash,
            'checked_source_hash' => $hash,
            'feed_token' => (string) Str::uuid(),
            'is_current' => true,
            'check_status' => 'new',
            'checked_at' => now(),
            'last_seen_at' => now(),
        ]);

        $result = app(NovellaImportService::class)->import($source);

        $this->assertSame('created', $result['action']);
        $product = Product::query()->findOrFail($result['product_id']);
        $this->assertSame('Agentica', $product->name);
        $this->assertSame('9789534002261', $product->isbn);
        $this->assertSame(12.0, (float) $product->price);
        $this->assertSame(9.6, (float) $product->special);
        $this->assertSame(3, (int) $product->quantity);
        $this->assertSame($publisherId, (int) $product->publisher_id);
        $this->assertSame(0, (int) $product->status);
        $this->assertStringContainsString('Hrvatski opis knjige.', $product->description);
        $this->assertDatabaseHas('product_category', [
            'product_id' => $product->id,
            'category_id' => $publisherCategory,
        ]);
        $this->assertDatabaseHas('product_category', [
            'product_id' => $product->id,
            'category_id' => $mappedCategory,
        ]);
        $source->refresh();
        $this->assertSame($hash, $source->imported_hash);
        $this->assertNotNull($source->imported_at);
    }

    public function test_inspection_keeps_feed_isbn_when_detail_page_omits_identifiers(): void
    {
        $productId = DB::table('products')->insertGetId([
            'author_id' => 0,
            'name' => 'Najnovija knjiga',
            'sku' => '501',
            'itemid' => 501,
            'isbn' => null,
            'ean' => '3858895382377',
            'slug' => 'najnovija-knjiga',
            'url' => '/',
            'price' => 10,
            'quantity' => 1,
            'tax_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $hash = hash('sha256', 'novella-feed-23300');
        $source = NovellaImportProduct::query()->create([
            'external_id' => '23300',
            'remote_product_id' => 23300,
            'name' => 'Najnovija knjiga',
            'source_category' => 'Knjige',
            'source_categories' => ['Knjige'],
            'source_url' => 'https://novella.hr/proizvod/najnovija-knjiga/',
            'price_eur' => 15,
            'availability' => 'in_stock',
            'isbn' => '9789534002261',
            'ean' => '3858895382377',
            'source_hash' => $hash,
            'feed_token' => (string) Str::uuid(),
            'is_current' => true,
            'check_status' => 'pending',
            'last_seen_at' => now(),
        ]);
        $this->mock(NovellaProductPageClient::class)
            ->shouldReceive('fetch')
            ->once()
            ->with($source->source_url)
            ->andReturn('<html>trusted product</html>');
        $this->mock(NovellaProductDetailParser::class)
            ->shouldReceive('parse')
            ->once()
            ->andReturn([
                'external_id' => '23300',
                'remote_product_id' => 23300,
                'source_url' => $source->source_url,
                'isbn' => null,
                'ean' => null,
                'author' => null,
                'source_categories' => ['Knjige'],
                'source_genres' => [],
                'images' => [],
                'description' => 'Opis sa stranice.',
            ]);

        $inspected = app(NovellaImportService::class)->inspect($source);

        $this->assertSame('9789534002261', $inspected->isbn);
        $this->assertSame('3858895382377', $inspected->ean);
        $this->assertSame($productId, (int) $inspected->product_id);
        $this->assertSame('matched', $inspected->check_status);
    }

    public function test_inspection_keeps_ean_only_feed_identifier_out_of_isbn(): void
    {
        $productId = DB::table('products')->insertGetId([
            'author_id' => 0,
            'name' => 'EAN knjiga',
            'sku' => '502',
            'itemid' => 502,
            'isbn' => null,
            'ean' => '3858895382377',
            'slug' => 'ean-knjiga',
            'url' => '/',
            'price' => 10,
            'quantity' => 1,
            'tax_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $hash = hash('sha256', 'novella-ean-only-23300');
        $source = NovellaImportProduct::query()->create([
            'external_id' => '23300',
            'remote_product_id' => 23300,
            'name' => 'EAN knjiga',
            'source_category' => 'Knjige',
            'source_categories' => ['Knjige'],
            'source_url' => 'https://novella.hr/proizvod/ean-knjiga/',
            'price_eur' => 15,
            'availability' => 'in_stock',
            'isbn' => null,
            'ean' => '3858895382377',
            'source_hash' => $hash,
            'feed_token' => (string) Str::uuid(),
            'is_current' => true,
            'check_status' => 'pending',
            'last_seen_at' => now(),
        ]);
        $this->mock(NovellaProductPageClient::class)
            ->shouldReceive('fetch')->once()->with($source->source_url)
            ->andReturn('<html>trusted product</html>');
        $this->mock(NovellaProductDetailParser::class)
            ->shouldReceive('parse')->once()->andReturn([
                'external_id' => '23300',
                'remote_product_id' => 23300,
                'source_url' => $source->source_url,
                'isbn' => null,
                'ean' => null,
                'author' => null,
                'source_categories' => ['Knjige'],
                'source_genres' => [],
                'images' => [],
                'description' => 'Opis sa stranice.',
            ]);

        $inspected = app(NovellaImportService::class)->inspect($source);

        $this->assertNull($inspected->isbn);
        $this->assertSame('3858895382377', $inspected->ean);
        $this->assertSame($productId, (int) $inspected->product_id);
        $this->assertSame('matched', $inspected->check_status);
    }

    public function test_ean_only_book_without_author_is_still_safely_classified_as_new(): void
    {
        $hash = hash('sha256', 'novella-ean-only-new');
        $source = NovellaImportProduct::query()->create([
            'external_id' => '23301',
            'remote_product_id' => 23301,
            'name' => 'Nova EAN knjiga',
            'source_category' => 'Knjige',
            'source_categories' => ['Knjige'],
            'source_url' => 'https://novella.hr/proizvod/nova-ean-knjiga/',
            'price_eur' => 15,
            'availability' => 'in_stock',
            'isbn' => null,
            'ean' => '3858895382377',
            'source_hash' => $hash,
            'feed_token' => (string) Str::uuid(),
            'is_current' => true,
            'check_status' => 'pending',
            'last_seen_at' => now(),
        ]);
        $this->mock(NovellaProductPageClient::class)
            ->shouldReceive('fetch')->once()->with($source->source_url)
            ->andReturn('<html>trusted product</html>');
        $this->mock(NovellaProductDetailParser::class)
            ->shouldReceive('parse')->once()->andReturn([
                'external_id' => '23301',
                'remote_product_id' => 23301,
                'source_url' => $source->source_url,
                'isbn' => null,
                'ean' => null,
                'author' => null,
                'source_categories' => ['Knjige'],
                'source_genres' => [],
                'images' => [],
                'description' => 'Opis sa stranice.',
            ]);

        $inspected = app(NovellaImportService::class)->inspect($source);

        $this->assertNull($inspected->isbn);
        $this->assertSame('3858895382377', $inspected->ean);
        $this->assertNull($inspected->product_id);
        $this->assertSame('new', $inspected->check_status);
    }

    public function test_cached_new_inspection_rechecks_current_catalog_without_fetching_novella_again(): void
    {
        $hash = hash('sha256', 'novella-cached-new-anne-frank');
        $source = NovellaImportProduct::query()->create([
            'external_id' => '5156',
            'remote_product_id' => 5156,
            'name' => 'Anne Frank',
            'source_category' => 'Knjige',
            'source_categories' => ['Knjige'],
            'source_url' => 'https://novella.hr/proizvod/anne-frank/',
            'price_eur' => 9.90,
            'availability' => 'in_stock',
            'isbn' => '9789538551093',
            'ean' => '9789538551093',
            'author' => 'Maria Cecilia Cavallone',
            'source_hash' => $hash,
            'checked_source_hash' => $hash,
            'feed_token' => (string) Str::uuid(),
            'is_current' => true,
            'check_status' => 'new',
            'check_message' => 'ISBN, EAN ni kombinacija naziva i autora nisu pronađeni u Zuzi katalogu.',
            'checked_at' => now()->subMinute(),
            'last_seen_at' => now(),
        ]);
        $productId = DB::table('products')->insertGetId([
            'author_id' => 0,
            'name' => 'Maria Cecilia Cavallone: Anne Frank',
            'sku' => '754',
            'itemid' => 123,
            'isbn' => '978-953-8551-09-3',
            'ean' => null,
            'slug' => 'maria-cecilia-cavallone-anne-frank',
            'url' => '/',
            'price' => 9.90,
            'quantity' => 0,
            'tax_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->mock(NovellaProductPageClient::class)
            ->shouldNotReceive('fetch');

        $inspected = app(NovellaImportService::class)->inspect($source);

        $this->assertSame($productId, (int) $inspected->product_id);
        $this->assertSame('matched', $inspected->check_status);
        $this->assertSame('existing', $inspected->ui_status);
        $this->assertStringContainsString('Postojeći Zuzi artikl pronađen', $inspected->check_message);
    }

    public function test_inspection_uses_changed_feed_fallbacks_and_keeps_unrelated_enrichment(): void
    {
        $oldFeed = [
            'description' => 'Stari feed opis.',
            'source_publisher' => 'Novella',
            'image_url' => 'https://novella.hr/wp-content/uploads/old-feed.jpg',
            'additional_image_urls' => [],
            'isbn' => '9789534002261',
            'ean' => '9789534002261',
            'author' => 'Stari feed autor',
        ];
        $newFeed = array_merge($oldFeed, [
            'description' => 'Novi feed opis.',
            'image_url' => 'https://novella.hr/wp-content/uploads/new-feed.jpg',
            'isbn' => '9789534002995',
            'ean' => '9789534002995',
            'author' => 'Novi feed autor',
        ]);
        $hash = hash('sha256', 'novella-feed-changed-after-inspection');
        $source = NovellaImportProduct::query()->create([
            'external_id' => '23302',
            'remote_product_id' => 23302,
            'name' => 'Promijenjena knjiga',
            'description' => 'Detaljni stari opis.',
            'source_category' => 'Knjige',
            'source_categories' => ['Knjige'],
            'source_publisher' => 'Detaljni nakladnik',
            'source_url' => 'https://novella.hr/proizvod/promijenjena-knjiga/',
            'image_url' => 'https://novella.hr/wp-content/uploads/detail-old.jpg',
            'additional_image_urls' => ['https://novella.hr/wp-content/uploads/detail-old-2.jpg'],
            'price_eur' => 15,
            'availability' => 'in_stock',
            'isbn' => '9789534002777',
            'ean' => '9789534002777',
            'author' => 'Detaljni stari autor',
            'detail_payload' => [
                '_novella_feed' => $newFeed,
                '_novella_checked_feed' => $oldFeed,
            ],
            'source_hash' => $hash,
            'checked_source_hash' => hash('sha256', 'previous-feed-version'),
            'feed_token' => (string) Str::uuid(),
            'is_current' => true,
            'check_status' => 'pending',
            'checked_at' => now()->subMinute(),
            'last_seen_at' => now(),
        ]);
        $this->mock(NovellaProductPageClient::class)
            ->shouldReceive('fetch')->once()->with($source->source_url)
            ->andReturn('<html>trusted product</html>');
        $this->mock(NovellaProductDetailParser::class)
            ->shouldReceive('parse')->once()->andReturn([
                'external_id' => '23302',
                'remote_product_id' => 23302,
                'source_url' => $source->source_url,
                'isbn' => null,
                'ean' => null,
                'author' => null,
                'publisher' => null,
                'source_categories' => ['Knjige'],
                'source_genres' => [],
                'images' => [],
                'description' => '',
            ]);

        $inspected = app(NovellaImportService::class)->inspect($source);

        $this->assertSame('Novi feed opis.', $inspected->description);
        $this->assertSame('Detaljni nakladnik', $inspected->source_publisher);
        $this->assertSame('Novi feed autor', $inspected->author);
        $this->assertSame('9789534002995', $inspected->isbn);
        $this->assertSame('9789534002995', $inspected->ean);
        $this->assertSame('https://novella.hr/wp-content/uploads/new-feed.jpg', $inspected->image_url);
        $this->assertSame(
            ['https://novella.hr/wp-content/uploads/detail-old-2.jpg'],
            $inspected->additional_image_urls
        );
        $this->assertEquals(
            $newFeed,
            $inspected->detail_payload['_novella_checked_feed']
        );
    }

    private function category(string $title, int $parentId = 0): int
    {
        return DB::table('categories')->insertGetId([
            'parent_id' => $parentId,
            'group' => 'Knjige',
            'title' => $title,
            'slug' => Str::slug($title),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
