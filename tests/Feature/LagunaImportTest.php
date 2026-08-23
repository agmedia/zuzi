<?php

namespace Tests\Feature;

use App\Models\Back\Catalog\LagunaImportProduct;
use App\Models\Back\Catalog\Product\Product;
use App\Models\User;
use App\Models\UserDetail;
use App\Services\Laguna\LagunaFeedSynchronizer;
use App\Services\Laguna\LagunaImportService;
use App\Services\Laguna\LagunaImportSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Bouncer;
use Tests\TestCase;

class LagunaImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_settings_default_to_skipping_existing_products(): void
    {
        $this->assertSame('skip', app(LagunaImportSettings::class)->all()['existing_action']);
    }

    public function test_feed_sync_is_incremental_and_preserves_product_links(): void
    {
        $firstFeed = $this->temporaryFeed([
            $this->feedItem('A100', 'Prva knjiga', 1000),
            $this->feedItem('A100', 'Prva knjiga - Potpisan primerak', 1000),
            $this->feedItem('A200', 'Druga knjiga', 1500),
            $this->feedItem('I100', 'Igračka', 1800, 'Igračke'),
            $this->feedItem('G100', 'Poklon bez cijene', 0, 'Gift'),
            $this->feedItem('A000', 'Knjiga bez cijene', 0),
        ]);
        $secondFeed = $this->temporaryFeed([
            $this->feedItem('A100', 'Prva knjiga', 1200),
            $this->feedItem('A300', 'Treća knjiga', 2000),
        ]);

        try {
            $synchronizer = app(LagunaFeedSynchronizer::class);
            $first = $synchronizer->syncFile($firstFeed);

            $this->assertSame(2, $first['staged']);
            $this->assertSame(1, $first['skipped']);
            $this->assertSame(1, $first['duplicates']);
            $this->assertDatabaseMissing('laguna_import_products', ['external_id' => 'I100']);
            $this->assertDatabaseMissing('laguna_import_products', ['external_id' => 'G100']);
            $this->assertDatabaseMissing('laguna_import_products', ['external_id' => 'A000']);
            LagunaImportProduct::query()->where('external_id', 'A100')->update([
                'product_id' => 987,
                'isbn' => '9788652000100',
                'check_status' => 'matched',
            ]);

            $second = $synchronizer->syncFile($secondFeed);

            $this->assertSame(2, $second['staged']);
            $this->assertSame(2, $second['current']);
            $this->assertSame(1, $second['retired']);

            $firstProduct = LagunaImportProduct::query()->where('external_id', 'A100')->firstOrFail();
            $this->assertSame('Prva knjiga', $firstProduct->name);
            $this->assertSame(987, (int) $firstProduct->product_id);
            $this->assertSame('9788652000100', $firstProduct->isbn);
            $this->assertSame('matched', $firstProduct->check_status);
            $this->assertSame(1200.0, $firstProduct->price_rsd);
            $this->assertSame(1, $firstProduct->feed_position);
            $this->assertTrue($firstProduct->is_current);

            $this->assertFalse(LagunaImportProduct::query()->where('external_id', 'A200')->firstOrFail()->is_current);
            $thirdProduct = LagunaImportProduct::query()->where('external_id', 'A300')->firstOrFail();
            $this->assertTrue($thirdProduct->is_current);
            $this->assertSame(2, $thirdProduct->feed_position);
            $this->assertSame(
                ['A100', 'A300'],
                LagunaImportProduct::query()
                    ->where('is_current', true)
                    ->orderBy('feed_position')
                    ->pluck('external_id')
                    ->all()
            );
        } finally {
            @unlink($firstFeed);
            @unlink($secondFeed);
        }
    }

    public function test_existing_product_is_matched_by_normalized_isbn_and_skipped(): void
    {
        $originalCategoryId = $this->createCategory('Postojeća kategorija');
        $mapping = $this->configureLagunaImport();
        $existingId = $this->createExistingProduct('Već postoji', '978-86-521-6434-9');
        DB::table('product_category')->insert([
            'product_id' => $existingId,
            'category_id' => $originalCategoryId,
        ]);
        $source = $this->createSource();

        Http::fake([
            $source->source_url => Http::response($this->productPage(), 200),
        ]);

        $result = app(LagunaImportService::class)->import($source, $mapping['additional_category_id']);

        $this->assertSame('skipped', $result['action']);
        $this->assertSame($existingId, $result['product_id']);
        $this->assertSame(1, Product::query()->count());
        $this->assertDatabaseHas('product_category', ['product_id' => $existingId, 'category_id' => $originalCategoryId]);
        $this->assertDatabaseHas('product_category', ['product_id' => $existingId, 'category_id' => $mapping['parent_category_id']]);
        $this->assertDatabaseHas('product_category', ['product_id' => $existingId, 'category_id' => $mapping['publisher_category_id']]);
        $this->assertDatabaseHas('product_category', ['product_id' => $existingId, 'category_id' => $mapping['additional_parent_category_id']]);
        $this->assertDatabaseHas('product_category', ['product_id' => $existingId, 'category_id' => $mapping['additional_category_id']]);

        $source->refresh();
        $this->assertSame($existingId, (int) $source->product_id);
        $this->assertSame('matched', $source->check_status);
        $this->assertNull($source->imported_at);
    }

    public function test_inspection_queue_contains_the_entire_current_feed_beyond_one_page(): void
    {
        $expectedIds = [];
        foreach (range(1, 45) as $position) {
            $source = $this->createSource([
                'external_id' => 'QUEUE-' . $position,
                'name' => 'Knjiga za provjeru ' . $position,
                'feed_position' => $position,
                'source_hash' => hash('sha256', 'queue-' . $position),
                'checked_source_hash' => $position === 1 ? hash('sha256', 'stara-verzija') : null,
                'product_id' => $position === 1 ? 987 : null,
            ]);
            $expectedIds[] = $source->id;
        }

        $checkedSource = $this->createSource([
            'external_id' => 'QUEUE-CHECKED',
            'source_hash' => hash('sha256', 'checked'),
            'checked_source_hash' => hash('sha256', 'checked'),
            'image_url' => 'https://laguna.rs/media/test-cover.jpg',
            'feed_position' => 46,
        ]);
        $this->createSource([
            'external_id' => 'QUEUE-MISSING',
            'source_hash' => hash('sha256', 'missing'),
            'checked_source_hash' => null,
            'feed_position' => 0,
            'is_current' => false,
        ]);

        $admin = User::factory()->create();
        UserDetail::query()->create([
            'user_id' => $admin->id,
            'fname' => 'Admin',
            'lname' => 'Test',
            'role' => 'admin',
        ]);
        Bouncer::allow($admin)->everything();

        $response = $this->actingAs($admin)->getJson(route('laguna-import.inspection-queue', ['limit' => 50]));

        $response->assertOk()->assertJson([
            'remaining' => 45,
        ]);
        $this->assertSame($expectedIds, collect($response->json('items'))->pluck('id')->all());

        $this->get(route('laguna-import.index'))
            ->assertOk()
            ->assertSee('Provjeri sve neprovjerene')
            ->assertSee('data-count="45"', false)
            ->assertSee('value="new" selected', false);

        $allResponse = $this->get(route('laguna-import.index', ['status' => 'all']));
        $allResponse->assertOk()
            ->assertSee('data-single-action="inspect" data-source-id="' . $expectedIds[0] . '"', false);

        $checkedResponse = $this->get(route('laguna-import.index', [
            'status' => 'all',
            'search' => 'QUEUE-CHECKED',
        ]));
        $checkedResponse->assertOk()
            ->assertSee('class="img-link img-link-zoom-in img-lightbox"', false)
            ->assertSee('href="https://laguna.rs/media/test-cover.jpg"', false)
            ->assertDontSee('data-single-action="inspect" data-source-id="' . $checkedSource->id . '"', false);
    }

    public function test_existing_product_is_matched_by_exact_title_and_author_when_isbn_differs(): void
    {
        $mapping = $this->configureLagunaImport();
        $authorId = $this->createAuthor('Test Autor');
        $existingId = $this->createExistingProduct('TEST KNJIGA', '9789999999999', $authorId);
        $source = $this->createSource();

        Http::fake([
            $source->source_url => Http::response($this->productPage(), 200),
        ]);

        $result = app(LagunaImportService::class)->import($source, $mapping['additional_category_id']);

        $this->assertSame('skipped', $result['action']);
        $this->assertSame($existingId, $result['product_id']);
        $this->assertSame(1, Product::query()->count());
        $this->assertDatabaseHas('product_category', [
            'product_id' => $existingId,
            'category_id' => $mapping['publisher_category_id'],
        ]);
    }

    public function test_new_product_maps_laguna_fields_translates_description_and_converts_price(): void
    {
        $mapping = $this->configureLagunaImport();

        $source = $this->createSource([
            'price_rsd' => 1200,
            'sale_price_rsd' => 1000,
            'description' => 'Ovo je opis knjige na srpskom jeziku.',
        ]);

        Http::fake([
            $source->source_url => Http::response($this->productPage(), 200),
            'translate.googleapis.com/*' => Http::response([[['Ovo je prevedeni hrvatski opis.', 'Ovo je opis knjige.']]], 200),
        ]);

        $result = app(LagunaImportService::class)->import($source, $mapping['additional_category_id']);

        $this->assertSame('created', $result['action']);
        $product = Product::query()->findOrFail($result['product_id']);
        $this->assertSame('Test knjiga', $product->name);
        $this->assertSame('9788652164349', $product->isbn);
        $this->assertSame('9788652164349', $product->ean);
        $this->assertSame(12.29, (float) $product->price);
        $this->assertSame(10.24, (float) $product->special);
        $this->assertSame(3, (int) $product->quantity);
        $this->assertSame('344', $product->pages);
        $this->assertSame('13x20', $product->dimensions);
        $this->assertSame('Latinica', $product->letter);
        $this->assertSame('Meki', $product->binding);
        $this->assertSame('2026', $product->year);
        $this->assertSame('Srpski', $product->language);
        $this->assertSame('Beograd', $product->origin);
        $this->assertSame('Nova knjiga', $product->condition);
        $this->assertSame(0, (int) $product->status);
        $this->assertSame($mapping['publisher_id'], (int) $product->publisher_id);
        $this->assertStringContainsString('Ovo je prevedeni hrvatski opis.', $product->description);

        $this->assertDatabaseHas('authors', ['id' => $product->author_id, 'title' => 'Test Autor']);
        $this->assertDatabaseHas('product_category', ['product_id' => $product->id, 'category_id' => $mapping['parent_category_id']]);
        $this->assertDatabaseHas('product_category', ['product_id' => $product->id, 'category_id' => $mapping['publisher_category_id']]);
        $this->assertDatabaseHas('product_category', ['product_id' => $product->id, 'category_id' => $mapping['additional_parent_category_id']]);
        $this->assertDatabaseHas('product_category', ['product_id' => $product->id, 'category_id' => $mapping['additional_category_id']]);

        $source->refresh();
        $this->assertSame($product->id, (int) $source->product_id);
        $this->assertSame('Ovo je prevedeni hrvatski opis.', $source->translated_description);
        $this->assertSame($source->source_hash, $source->imported_hash);
        $this->assertNotNull($source->imported_at);

        Http::assertSent(function ($request) {
            return Str::startsWith($request->url(), 'https://translate.googleapis.com/translate_a/single')
                && $request['q'] === 'Ovo je opis knjige na srpskom jeziku.';
        });
    }

    private function createSource(array $overrides = []): LagunaImportProduct
    {
        return LagunaImportProduct::query()->create(array_merge([
            'external_id' => 'A335684',
            'name' => 'Test knjiga',
            'description' => 'Opis na srpskom jeziku.',
            'product_type' => 'Knjige',
            'source_category' => 'Knjige',
            'source_url' => 'https://laguna.rs/proizvodi/knjige/test-knjiga/',
            'image_url' => null,
            'additional_image_urls' => [],
            'price_rsd' => 1200,
            'sale_price_rsd' => null,
            'availability' => 'in stock',
            'source_hash' => hash('sha256', 'A335684-v1'),
            'feed_token' => (string) Str::uuid(),
            'is_current' => true,
            'check_status' => 'pending',
            'last_seen_at' => now(),
        ], $overrides));
    }

    private function createExistingProduct(string $name, string $isbn, int $authorId = 0): int
    {
        return DB::table('products')->insertGetId([
            'author_id' => $authorId,
            'name' => $name,
            'sku' => '25',
            'itemid' => 25,
            'isbn' => $isbn,
            'ean' => null,
            'slug' => Str::slug($name),
            'url' => '/',
            'price' => 10,
            'quantity' => 1,
            'tax_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createAuthor(string $title): int
    {
        return DB::table('authors')->insertGetId([
            'letter' => mb_substr($title, 0, 1),
            'title' => $title,
            'description' => null,
            'meta_title' => $title,
            'meta_description' => null,
            'lang' => 'hr',
            'sort_order' => 0,
            'status' => 1,
            'slug' => Str::slug($title),
            'url' => '/autori/' . Str::slug($title),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function configureLagunaImport(): array
    {
        $parentCategoryId = $this->createCategory('Nakladnici');
        $publisherCategoryId = $this->createCategory('Laguna', $parentCategoryId);
        $additionalParentCategoryId = $this->createCategory('Žanrovi');
        $additionalCategoryId = $this->createCategory('Krimići', $additionalParentCategoryId);
        $publisherId = $this->createPublisher();

        app(LagunaImportSettings::class)->save([
            'exchange_rate' => 117.2,
            'markup_percent' => 20,
            'publisher_parent_category_id' => $parentCategoryId,
            'publisher_category_id' => $publisherCategoryId,
            'publisher_id' => $publisherId,
            'default_quantity' => 3,
            'activate_new_products' => 0,
            'existing_action' => 'skip',
        ]);

        return [
            'parent_category_id' => $parentCategoryId,
            'publisher_category_id' => $publisherCategoryId,
            'additional_parent_category_id' => $additionalParentCategoryId,
            'additional_category_id' => $additionalCategoryId,
            'publisher_id' => $publisherId,
        ];
    }

    private function createCategory(string $title, int $parentId = 0): int
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

    private function createPublisher(): int
    {
        return DB::table('publishers')->insertGetId([
            'letter' => 'L',
            'title' => 'Laguna',
            'slug' => 'laguna',
            'url' => '/izdavaci/laguna',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function temporaryFeed(array $items): string
    {
        $path = tempnam(sys_get_temp_dir(), 'laguna-sync-test-');
        file_put_contents(
            $path,
            '<?xml version="1.0" encoding="UTF-8"?>'
                . '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"><channel>'
                . implode('', $items)
                . '</channel></rss>'
        );

        return $path;
    }

    private function feedItem(string $id, string $title, int $price, string $category = 'Knjige'): string
    {
        return '<item>'
            . '<title>' . $title . '</title>'
            . '<link>https://laguna.rs/proizvodi/knjige/' . Str::slug($title) . '/</link>'
            . '<description>Opis artikla</description>'
            . '<category>' . $category . '</category>'
            . '<g:id>' . $id . '</g:id>'
            . '<g:title>' . $title . '</g:title>'
            . '<g:description>Opis artikla</g:description>'
            . '<g:link>https://laguna.rs/proizvodi/knjige/' . Str::slug($title) . '/</g:link>'
            . '<g:availability>in stock</g:availability>'
            . '<g:price>' . $price . ' RSD</g:price>'
            . '<g:product_type>Knjige</g:product_type>'
            . '</item>';
    }

    private function productPage(): string
    {
        return '<html><head><script type="application/ld+json">' . json_encode([
            '@context' => 'https://schema.org',
            '@type' => ['Product', 'Book'],
            'name' => 'Test knjiga',
            'isbn' => '978-86-521-6434-9',
            'author' => [['@type' => 'Person', 'name' => 'Test Autor']],
            'genre' => 'Knjige za decu',
            'numberOfPages' => 344,
            'sku' => 'A335684',
        ], JSON_UNESCAPED_UNICODE) . '</script></head><body>'
            . $this->detailRow('Format:', '13x20')
            . $this->detailRow('Broj strana:', '344')
            . $this->detailRow('Pismo:', 'Latinica')
            . $this->detailRow('Povez:', 'Mek')
            . $this->detailRow('Godina izdanja:', '21. avgust 2026.')
            . $this->detailRow('ISBN:', '978-86-521-6434-9')
            . $this->detailRow('Prevodilac:', 'Dijana Đelošević')
            . $this->detailRow('Šifra proizvoda:', 'A335684')
            . '</body></html>';
    }

    private function detailRow(string $label, string $value): string
    {
        return '<div><span>' . $label . '</span><span>' . $value . '</span></div>';
    }
}
