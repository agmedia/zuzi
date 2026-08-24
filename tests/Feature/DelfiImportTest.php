<?php

namespace Tests\Feature;

use App\Models\Back\Catalog\DelfiImportProduct;
use App\Models\Back\Catalog\Product\Product;
use App\Models\User;
use App\Models\UserDetail;
use App\Services\Delfi\DelfiImportService;
use App\Services\Delfi\DelfiImportSettings;
use App\Services\Delfi\DelfiProductApiClient;
use App\Services\Delfi\DelfiRetryableException;
use App\Services\Delfi\DelfiTerminalException;
use Bouncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class DelfiImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_book_uses_source_publisher_genre_mapping_translation_and_eur_price(): void
    {
        $mapping = $this->configureImport();
        $source = $this->source();

        Http::fake([
            'https://delfi.rs/api/pc-frontend-api/overview/251908' => Http::response($this->detailPayload(), 200),
            'translate.googleapis.com/*' => Http::response([[['Prevedeni hrvatski opis.', 'Opis knjige.']]], 200),
        ]);

        $result = app(DelfiImportService::class)->import($source, $mapping['additional_category_id']);

        $this->assertSame('created', $result['action']);
        $product = Product::query()->findOrFail($result['product_id']);
        $this->assertSame('Test Delfi knjiga', $product->name);
        $this->assertSame('9788652162123', $product->isbn);
        $this->assertSame('9788652162123', $product->ean);
        $this->assertSame($mapping['source_publisher_id'], (int) $product->publisher_id);
        $this->assertSame(12.29, (float) $product->price);
        $this->assertSame(3, (int) $product->quantity);
        $this->assertSame('298', $product->pages);
        $this->assertSame('13x20 cm', $product->dimensions);
        $this->assertSame('Ćirilica', $product->letter);
        $this->assertSame('Meki', $product->binding);
        $this->assertSame('2026', $product->year);
        $this->assertSame('Srpski', $product->language);
        $this->assertSame('Beograd', $product->origin);
        $this->assertSame(0, (int) $product->status);
        $this->assertStringContainsString('Prevedeni hrvatski opis.', $product->description);

        foreach ([
            $mapping['publisher_parent_category_id'],
            $mapping['source_publisher_category_id'],
            $mapping['genre_parent_category_id'],
            $mapping['genre_category_id'],
            $mapping['additional_parent_category_id'],
            $mapping['additional_category_id'],
        ] as $categoryId) {
            $this->assertDatabaseHas('product_category', [
                'product_id' => $product->id,
                'category_id' => $categoryId,
            ]);
        }

        $source->refresh();
        $this->assertSame(['Fantastika', 'Knjige za decu'], $source->source_genres);
        $this->assertSame('Laguna', $source->source_publisher);
        $this->assertSame('A335680', $source->nav_id);
        $this->assertSame($source->source_hash, $source->imported_hash);
        $this->assertNotNull($source->imported_at);
    }

    public function test_inspection_matches_exact_title_and_author_when_isbn_is_different(): void
    {
        $authorId = DB::table('authors')->insertGetId([
            'letter' => 'G',
            'title' => 'Geri Penton',
            'slug' => 'geri-penton',
            'url' => '/autori/geri-penton',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $existingId = DB::table('products')->insertGetId([
            'author_id' => $authorId,
            'name' => 'Test Delfi knjiga',
            'sku' => '991',
            'itemid' => 991,
            'isbn' => '9789999999999',
            'slug' => 'test-delfi-knjiga',
            'url' => '/',
            'price' => 10,
            'quantity' => 1,
            'tax_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $source = $this->source();
        Http::fake([
            'https://delfi.rs/api/pc-frontend-api/overview/251908' => Http::response($this->detailPayload(), 200),
        ]);
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $inspected = app(DelfiImportService::class)->inspect($source);

        $this->assertSame($existingId, (int) $inspected->product_id);
        $this->assertSame('matched', $inspected->check_status);
        $this->assertFalse(collect($queries)->contains(function (string $sql) {
            return str_contains($sql, 'REPLACE(REPLACE')
                || str_contains($sql, "LOWER(TRIM(COALESCE(products.name");
        }));
    }

    public function test_inspection_rejects_a_detail_payload_for_another_delfi_product(): void
    {
        $source = $this->source();
        $payload = $this->detailPayload();
        $payload['data']['product']['oldProductId'] = 251999;
        Http::fake([
            'https://delfi.rs/api/pc-frontend-api/overview/251908' => Http::response($payload, 200),
        ]);

        try {
            app(DelfiImportService::class)->inspect($source);
            $this->fail('Pogrešan Delfi identitet mora zaustaviti provjeru.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('drugog artikla', $exception->getMessage());
        }

        $source->refresh();
        $this->assertSame('error', $source->check_status);
        $this->assertSame($source->source_hash, $source->checked_source_hash);
    }

    public function test_import_keeps_distinct_isbn_and_ean_values(): void
    {
        $mapping = $this->configureImport();
        $source = $this->source();
        $payload = $this->detailPayload();
        $payload['data']['product']['barcode'] = '9780306406157';
        $payload['data']['product']['attributes'][] = ['k' => 'isbn', 'v' => '0-306-40615-2'];
        Http::fake([
            'https://delfi.rs/api/pc-frontend-api/overview/251908' => Http::response($payload, 200),
            'translate.googleapis.com/*' => Http::response([[['Prevedeni hrvatski opis.', 'Opis knjige.']]], 200),
        ]);

        $result = app(DelfiImportService::class)->import($source, $mapping['additional_category_id']);
        $product = Product::query()->findOrFail($result['product_id']);

        $this->assertSame('0306406152', $product->isbn);
        $this->assertSame('9780306406157', $product->ean);
    }

    public function test_admin_page_is_separate_defaults_to_new_and_shows_discovered_genre_mapping(): void
    {
        $this->configureImport();
        $source = $this->source([
            'source_genres' => ['Fantastika'],
            'checked_source_hash' => hash('sha256', 'delfi-source-v1'),
            'check_status' => 'new',
        ]);
        $admin = User::factory()->create();
        UserDetail::query()->create([
            'user_id' => $admin->id,
            'fname' => 'Admin',
            'lname' => 'Test',
            'role' => 'admin',
        ]);
        Bouncer::allow($admin)->everything();

        $response = $this->actingAs($admin)->get(route('delfi-import.index'));

        $response->assertOk()
            ->assertSee('Delfi import')
            ->assertSee('Samo Knjiga i Strana knjiga')
            ->assertSee('value="new" selected', false)
            ->assertSee('data-source-row="' . $source->id . '"', false)
            ->assertSee('Mapiranje Delfi žanrova')
            ->assertSee('Fantastika');
    }

    public function test_retryable_inspection_errors_return_429_or_503_and_leave_rows_pending(): void
    {
        $rateLimited = $this->source();
        $unavailable = $this->source([
            'external_id' => '6a87ee201e2e7104ded45e45',
            'remote_product_id' => 251909,
            'feed_position' => 2,
            'source_url' => 'https://delfi.rs/knjige/251909-druga-knjiga.html',
            'source_hash' => hash('sha256', 'delfi-source-v2'),
        ]);
        $api = $this->mock(DelfiProductApiClient::class);
        $api->shouldReceive('fetch')->twice()->andReturnUsing(function (int $productId) {
            if ($productId === 251908) {
                throw new DelfiRetryableException('Delfi rate limit.', 429, 7);
            }

            throw new DelfiRetryableException('Delfi privremeno nije dostupan.', 503, 3);
        });
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson(route('delfi-import.inspect', $rateLimited), ['only_if_pending' => 1])
            ->assertStatus(429)
            ->assertHeader('Retry-After', '7')
            ->assertJson(['success' => false, 'retryable' => true]);
        $this->actingAs($admin)
            ->postJson(route('delfi-import.inspect', $unavailable), ['only_if_pending' => 1])
            ->assertStatus(503)
            ->assertHeader('Retry-After', '3')
            ->assertJson(['success' => false, 'retryable' => true]);

        foreach ([$rateLimited, $unavailable] as $source) {
            $source->refresh();
            $this->assertNull($source->checked_source_hash);
            $this->assertSame('pending', $source->check_status);
        }
    }

    public function test_terminal_inspection_error_is_marked_checked_and_returns_422(): void
    {
        $source = $this->source();
        $api = $this->mock(DelfiProductApiClient::class);
        $api->shouldReceive('fetch')->once()->andThrow(new DelfiTerminalException('Delfi API vratio je HTTP 404.'));

        $this->actingAs($this->admin())
            ->postJson(route('delfi-import.inspect', $source), ['only_if_pending' => 1])
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $source->refresh();
        $this->assertSame($source->source_hash, $source->checked_source_hash);
        $this->assertSame('error', $source->check_status);
    }

    public function test_inspection_queue_uses_a_cursor_and_only_counts_when_requested(): void
    {
        $sources = [];
        foreach (range(1, 5) as $position) {
            $sources[] = $this->source([
                'external_id' => 'QUEUE-' . $position,
                'remote_product_id' => 260000 + $position,
                'feed_position' => $position === 5 ? null : $position,
                'source_url' => 'https://delfi.rs/knjige/' . (260000 + $position) . '-queue.html',
                'source_hash' => hash('sha256', 'queue-' . $position),
            ]);
        }
        $admin = $this->admin();

        $first = $this->actingAs($admin)->getJson(route('delfi-import.inspection-queue', [
            'limit' => 2,
            'include_count' => 1,
        ]));
        $first->assertOk()->assertJson([
            'remaining' => 5,
            'has_more' => true,
        ]);
        $this->assertSame(
            [(int) $sources[0]->id, (int) $sources[1]->id],
            collect($first->json('items'))->pluck('id')->all()
        );
        $cursor = $first->json('next_cursor');
        $this->assertIsString($cursor);
        $this->assertNotSame('', $cursor);

        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });
        $second = $this->actingAs($admin)->getJson(route('delfi-import.inspection-queue', [
            'limit' => 2,
            'cursor' => $cursor,
        ]));
        $second->assertOk()->assertJson(['has_more' => true]);
        $this->assertArrayNotHasKey('remaining', $second->json());
        $this->assertSame(
            [(int) $sources[2]->id, (int) $sources[3]->id],
            collect($second->json('items'))->pluck('id')->all()
        );
        $this->assertFalse(collect($queries)->contains(function (string $sql) {
            return stripos($sql, 'count(') !== false && stripos($sql, 'delfi_import_products') !== false;
        }));

        $last = $this->actingAs($admin)->getJson(route('delfi-import.inspection-queue', [
            'limit' => 2,
            'cursor' => $second->json('next_cursor'),
        ]));
        $last->assertOk()->assertJson(['has_more' => false]);
        $this->assertSame([(int) $sources[4]->id], collect($last->json('items'))->pluck('id')->all());
    }

    private function configureImport(): array
    {
        $publisherParentCategoryId = $this->category('Nakladnici');
        $fallbackPublisherCategoryId = $this->category('Delfi', $publisherParentCategoryId);
        $sourcePublisherCategoryId = $this->category('Laguna', $publisherParentCategoryId);
        $genreParentCategoryId = $this->category('Žanrovi');
        $genreCategoryId = $this->category('Fantastika', $genreParentCategoryId);
        $additionalParentCategoryId = $this->category('Knjige');
        $additionalCategoryId = $this->category('Novi naslovi', $additionalParentCategoryId);
        $fallbackPublisherId = $this->publisher('Delfi');
        $sourcePublisherId = $this->publisher('Laguna');

        app(DelfiImportSettings::class)->save([
            'exchange_rate' => 117.2,
            'markup_percent' => 20,
            'publisher_parent_category_id' => $publisherParentCategoryId,
            'publisher_category_id' => $fallbackPublisherCategoryId,
            'publisher_id' => $fallbackPublisherId,
            'map_source_publishers' => 1,
            'genre_category_map' => ['Fantastika' => $genreCategoryId],
            'default_quantity' => 3,
            'activate_new_products' => 0,
            'translate_descriptions' => 1,
            'existing_action' => 'skip',
        ]);

        return compact(
            'publisherParentCategoryId',
            'fallbackPublisherCategoryId',
            'sourcePublisherCategoryId',
            'genreParentCategoryId',
            'genreCategoryId',
            'additionalParentCategoryId',
            'additionalCategoryId',
            'fallbackPublisherId',
            'sourcePublisherId'
        ) + [
            'publisher_parent_category_id' => $publisherParentCategoryId,
            'source_publisher_category_id' => $sourcePublisherCategoryId,
            'genre_parent_category_id' => $genreParentCategoryId,
            'genre_category_id' => $genreCategoryId,
            'additional_parent_category_id' => $additionalParentCategoryId,
            'additional_category_id' => $additionalCategoryId,
            'source_publisher_id' => $sourcePublisherId,
        ];
    }

    private function source(array $overrides = []): DelfiImportProduct
    {
        return DelfiImportProduct::query()->create(array_merge([
            'external_id' => '6a87ee201e2e7104ded45e44',
            'remote_product_id' => 251908,
            'feed_position' => 1,
            'name' => 'Test Delfi knjiga',
            'description' => 'Opis knjige na izvornom jeziku.',
            'source_category' => 'Knjiga',
            'source_publisher' => 'Laguna',
            'source_url' => 'https://delfi.rs/knjige/251908-test-delfi-knjiga-knjiga.html',
            'image_url' => null,
            'additional_image_urls' => [],
            'price_rsd' => 1200,
            'sale_price_rsd' => null,
            'availability' => 'in stock',
            'author' => 'Geri Penton',
            'source_genres' => [],
            'source_hash' => hash('sha256', 'delfi-source-v1'),
            'feed_token' => (string) Str::uuid(),
            'is_current' => true,
            'check_status' => 'pending',
            'last_seen_at' => now(),
        ], $overrides));
    }

    private function detailPayload(): array
    {
        return [
            'data' => [
                'product' => [
                    '_id' => '6a87ee201e2e7104ded45e44',
                    'oldProductId' => 251908,
                    'navId' => 'A335680',
                    'barcode' => '978-86-521-6212-3',
                    'publisher' => 'Laguna',
                    'authors' => [['authorName' => 'Geri Penton']],
                    'genres' => [
                        ['genreName' => 'Fantastika'],
                        ['genreName' => 'Knjige za decu'],
                    ],
                    'attributes' => [
                        ['k' => 'numberOfPages', 'v' => '298'],
                        ['k' => 'format', 'v' => '13x20 cm'],
                    ],
                    'alphabets' => ['cyrillic'],
                    'cover' => 'Mek',
                    'releaseDate' => '21. avgust 2026.',
                    'category' => 'Knjiga',
                    'description' => '<p>Opis knjige na izvornom jeziku.</p>',
                    'images' => [],
                ],
            ],
        ];
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

    private function publisher(string $title): int
    {
        return DB::table('publishers')->insertGetId([
            'title' => $title,
            'slug' => Str::slug($title),
            'url' => '/izdavaci/' . Str::slug($title),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        UserDetail::query()->create([
            'user_id' => $admin->id,
            'fname' => 'Admin',
            'lname' => 'Test',
            'role' => 'admin',
        ]);
        Bouncer::allow($admin)->everything();

        return $admin;
    }
}
