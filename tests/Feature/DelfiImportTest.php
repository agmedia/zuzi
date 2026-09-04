<?php

namespace Tests\Feature;

use App\Models\Back\Catalog\DelfiImportProduct;
use App\Models\Back\Catalog\Product\Product;
use App\Models\User;
use App\Models\UserDetail;
use App\Services\Catalog\AuthorResolver;
use App\Services\Delfi\DelfiImportService;
use App\Services\Delfi\DelfiImportSettings;
use App\Services\Delfi\DelfiProductApiClient;
use App\Services\Delfi\DelfiProductListApiClient;
use App\Services\Delfi\DelfiProductListParser;
use App\Services\Delfi\DelfiRetryableException;
use App\Services\Delfi\DelfiTerminalException;
use Bouncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class DelfiImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_description_translation_is_disabled_by_default(): void
    {
        $this->assertFalse(app(DelfiImportSettings::class)->all()['translate_descriptions']);
    }

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
        $this->assertSame('Geri Penton: Test Delfi knjiga', $product->name);
        $this->assertSame('Geri Penton: Test Delfi knjiga', $product->meta_title);
        $this->assertSame('9788652162123', $product->isbn);
        $this->assertSame('9788652162123', $product->ean);
        $this->assertSame($mapping['source_publisher_id'], (int) $product->publisher_id);
        $this->assertSame(12.50, (float) $product->price);
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

    public function test_translation_can_be_disabled_without_calling_external_service(): void
    {
        $mapping = $this->configureImport();
        app(DelfiImportSettings::class)->save(['translate_descriptions' => 0]);
        $source = $this->source(['description' => 'Izvorni Delfi opis.']);
        $payload = $this->detailPayload();
        $payload['data']['product']['description'] = '<p>Izvorni Delfi opis.</p>';

        Http::fake([
            'https://delfi.rs/api/pc-frontend-api/overview/251908' => Http::response($payload, 200),
            'translate.googleapis.com/*' => Http::response([], 429),
        ]);

        $result = app(DelfiImportService::class)->import($source, $mapping['additional_category_id']);
        $product = Product::query()->findOrFail($result['product_id']);

        $this->assertStringContainsString('Izvorni Delfi opis.', $product->description);
        Http::assertNotSent(fn ($request) => Str::startsWith(
            $request->url(),
            'https://translate.googleapis.com/'
        ));
    }

    public function test_free_translation_failure_falls_back_to_source_description(): void
    {
        $mapping = $this->configureImport();
        $source = $this->source(['description' => 'Izvorni Delfi opis nakon rate limita.']);
        $payload = $this->detailPayload();
        $payload['data']['product']['description'] = '<p>Izvorni Delfi opis nakon rate limita.</p>';

        Http::fake([
            'https://delfi.rs/api/pc-frontend-api/overview/251908' => Http::response($payload, 200),
            'translate.googleapis.com/*' => Http::response([], 429),
        ]);

        $result = app(DelfiImportService::class)->import($source, $mapping['additional_category_id']);
        $product = Product::query()->findOrFail($result['product_id']);

        $this->assertStringContainsString('Izvorni Delfi opis nakon rate limita.', $product->description);
        $this->assertStringContainsString('Prijevod opisa nije uspio', $result['message']);
        Http::assertSent(fn ($request) => Str::startsWith(
            $request->url(),
            'https://translate.googleapis.com/translate_a/single'
        ));
    }

    public function test_inspection_matches_exact_title_and_author_when_isbn_is_different(): void
    {
        $authorId = DB::table('authors')->insertGetId([
            'letter' => 'G',
            'title' => 'Geri Penton',
            'normalized_title' => AuthorResolver::normalizedKey('Geri Penton'),
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

    public function test_cached_new_inspection_rechecks_the_local_catalog_without_calling_delfi(): void
    {
        $hash = hash('sha256', 'cached-new-single-match');
        $source = $this->source([
            'isbn' => '9788652162123',
            'ean' => null,
            'source_hash' => $hash,
            'checked_source_hash' => $hash,
            'check_status' => 'new',
            'checked_at' => now(),
        ]);
        $existingId = DB::table('products')->insertGetId([
            'author_id' => 0,
            'name' => 'Već uvezena Delfi knjiga',
            'sku' => '992',
            'itemid' => 992,
            'isbn' => '978-86-521-6212-3',
            'ean' => null,
            'slug' => 'vec-uvezena-delfi-knjiga',
            'url' => '/',
            'price' => 10,
            'quantity' => 1,
            'tax_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Http::fake();

        $inspected = app(DelfiImportService::class)->inspect($source);

        $this->assertSame($existingId, (int) $inspected->product_id);
        $this->assertSame('matched', $inspected->check_status);
        $this->assertStringContainsString('Postojeći Zuzi artikl pronađen', $inspected->check_message);
        Http::assertNothingSent();
    }

    public function test_cached_new_local_recheck_marks_multiple_matches_as_a_conflict_without_calling_delfi(): void
    {
        $hash = hash('sha256', 'cached-new-conflict');
        $source = $this->source([
            'isbn' => '9788652162123',
            'ean' => '9788652162123',
            'source_hash' => $hash,
            'checked_source_hash' => $hash,
            'check_status' => 'new',
            'checked_at' => now(),
        ]);
        $firstId = DB::table('products')->insertGetId([
            'author_id' => 0,
            'name' => 'Prvi postojeći Delfi artikl',
            'sku' => '993',
            'itemid' => 993,
            'isbn' => '9788652162123',
            'ean' => null,
            'slug' => 'prvi-postojeci-delfi-artikl',
            'url' => '/',
            'price' => 10,
            'quantity' => 1,
            'tax_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $secondId = DB::table('products')->insertGetId([
            'author_id' => 0,
            'name' => 'Drugi postojeći Delfi artikl',
            'sku' => '994',
            'itemid' => 994,
            'isbn' => null,
            'ean' => '9788652162123',
            'slug' => 'drugi-postojeci-delfi-artikl',
            'url' => '/',
            'price' => 10,
            'quantity' => 1,
            'tax_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Http::fake();

        $inspected = app(DelfiImportService::class)->recheckCachedNew($source);

        $this->assertNull($inspected->product_id);
        $this->assertSame('conflict', $inspected->check_status);
        $this->assertStringContainsString((string) $firstId, $inspected->check_message);
        $this->assertStringContainsString((string) $secondId, $inspected->check_message);
        Http::assertNothingSent();
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
        Cache::forget('delfi-import-book-genres-by-category');
        Http::fake([
            'https://delfi.rs/api/pc-frontend-api/get-filters-data' => Http::response([
                'data' => ['genresByCategories' => [
                    ['category' => 'Knjiga', 'genres' => [['genreName' => 'Fantastika']]],
                    ['category' => 'Strana knjiga', 'genres' => [['genreName' => 'Fantasy']]],
                ]],
            ], 200),
        ]);

        $response = $this->actingAs($admin)->get(route('delfi-import.index'));

        $response->assertOk()
            ->assertSee('Delfi import')
            ->assertDontSee('Laguna import')
            ->assertSee('Samo Knjiga i Strana knjiga')
            ->assertSee('value="new" selected', false)
            ->assertSee('data-source-row="' . $source->id . '"', false)
            ->assertSee('Delfi kategorija')
            ->assertSee('Delfi podkategorija')
            ->assertSee('Prevedi opis na hrvatski')
            ->assertSee('Mapiranje Delfi žanrova')
            ->assertSee('Fantastika');
    }

    public function test_unavailable_books_are_not_offered_for_import(): void
    {
        $this->configureImport();
        $availableHash = hash('sha256', 'available-delfi-book');
        $available = $this->source([
            'external_id' => 'AVAILABLE-DELFI-BOOK',
            'remote_product_id' => 280001,
            'name' => 'Dostupna Delfi knjiga',
            'source_hash' => $availableHash,
            'checked_source_hash' => $availableHash,
            'check_status' => 'new',
        ]);
        $unavailableHash = hash('sha256', 'unavailable-delfi-book');
        $unavailable = $this->source([
            'external_id' => 'UNAVAILABLE-DELFI-BOOK',
            'remote_product_id' => 280002,
            'feed_position' => 2,
            'name' => 'Nedostupna Delfi knjiga',
            'availability' => 'out of stock',
            'source_hash' => $unavailableHash,
            'checked_source_hash' => $unavailableHash,
            'check_status' => 'new',
        ]);
        Cache::put('delfi-import-book-genres-by-category', [
            'Knjiga' => [],
            'Strana knjiga' => [],
        ]);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('delfi-import.index', ['status' => 'new']))
            ->assertOk()
            ->assertViewHas('products', function ($products) use ($available, $unavailable) {
                $ids = $products->getCollection()->pluck('id');

                return $ids->contains($available->id) && ! $ids->contains($unavailable->id);
            })
            ->assertViewHas('statusCounts', fn (array $counts) => $counts['new'] === 1);

        $this->actingAs($admin)
            ->get(route('delfi-import.index', ['status' => 'all']))
            ->assertOk()
            ->assertSee('data-source-row="' . $unavailable->id . '"', false)
            ->assertDontSee('data-single-action="import" data-source-id="' . $unavailable->id . '"', false);

        $this->actingAs($admin)
            ->postJson(route('delfi-import.import', $unavailable))
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Artikl nije dostupan u Delfi feedu i nije ponuđen za uvoz.',
            ]);

        $this->assertDatabaseMissing('products', ['name' => 'Nedostupna Delfi knjiga']);
    }

    public function test_admin_saved_genre_mapping_remains_visible_with_duplicate_legacy_settings(): void
    {
        $mapping = $this->configureImport();
        DB::table('settings')->insert([
            'code' => 'delfi_import',
            'key' => 'genre_category_map',
            'value' => '{}',
            'json' => 1,
            'created_at' => now()->addSecond(),
            'updated_at' => now()->addSecond(),
        ]);
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('delfi-import.settings'), [
            'exchange_rate' => 117.2,
            'markup_percent' => 30,
            'publisher_parent_category_id' => $mapping['publisher_parent_category_id'],
            'publisher_category_id' => $mapping['fallbackPublisherCategoryId'],
            'publisher_id' => $mapping['fallbackPublisherId'],
            'default_quantity' => 5,
            'existing_action' => 'skip',
            'map_source_publishers' => 1,
            'source_genres' => ['Ljubići'],
            'genre_category_ids' => [$mapping['genre_category_id']],
        ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('delfi-import.index', ['tab' => 'settings']));
        $this->assertSame(
            ['Ljubići' => $mapping['genre_category_id']],
            app(DelfiImportSettings::class)->all()['genre_category_map']
        );
        $this->assertTrue(
            DB::table('settings')
                ->where('code', 'delfi_import')
                ->where('key', 'genre_category_map')
                ->pluck('value')
                ->every(fn (string $value) => $value === json_encode(
                    ['Ljubići' => $mapping['genre_category_id']],
                    JSON_UNESCAPED_UNICODE
                ))
        );

        Cache::put('delfi-import-book-genres-by-category', ['Knjiga' => ['Ljubići']]);
        $this->actingAs($admin)
            ->get(route('delfi-import.index', ['tab' => 'settings']))
            ->assertOk()
            ->assertSee('name="source_genres[]" value="Ljubići"', false);
    }

    public function test_stale_admin_form_cannot_erase_another_admins_genre_mapping(): void
    {
        $mapping = $this->configureImport();
        $settings = app(DelfiImportSettings::class);
        $settings->save(['genre_category_map' => []]);
        $emptyVersion = $settings->genreMapVersion([]);
        $firstAdmin = $this->admin();
        $secondAdmin = $this->admin();
        $basePayload = [
            'exchange_rate' => 117.2,
            'markup_percent' => 30,
            'publisher_parent_category_id' => $mapping['publisher_parent_category_id'],
            'publisher_category_id' => $mapping['fallbackPublisherCategoryId'],
            'publisher_id' => $mapping['fallbackPublisherId'],
            'default_quantity' => 5,
            'existing_action' => 'skip',
            'map_source_publishers' => 1,
        ];

        $this->actingAs($firstAdmin)
            ->post(route('delfi-import.settings'), $basePayload + [
                'source_genres' => ['Publicistika'],
                'genre_category_ids' => [$mapping['genre_category_id']],
                'genre_mapping_version' => $emptyVersion,
                'genre_mapping_touched' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($secondAdmin)
            ->post(route('delfi-import.settings'), array_merge($basePayload, [
                'markup_percent' => 35,
                'genre_mapping_version' => $emptyVersion,
                'genre_mapping_touched' => 0,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['Publicistika' => $mapping['genre_category_id']],
            $settings->all()['genre_category_map']
        );
        $this->assertSame(35.0, $settings->all()['markup_percent']);

        $currentVersion = $settings->genreMapVersion(['Publicistika' => $mapping['genre_category_id']]);
        $this->actingAs($secondAdmin)
            ->getJson(route('delfi-import.genre-mappings'))
            ->assertOk()
            ->assertJson([
                'mappings' => ['Publicistika' => $mapping['genre_category_id']],
                'version' => $currentVersion,
            ]);

        $this->actingAs($secondAdmin)
            ->post(route('delfi-import.settings'), $basePayload + [
                'source_genres' => ['Ljubići'],
                'genre_category_ids' => [$mapping['genre_category_id']],
                'genre_mapping_version' => $emptyVersion,
                'genre_mapping_touched' => 1,
            ])
            ->assertSessionHasErrors('genre_category_map');

        $this->assertSame(
            ['Publicistika' => $mapping['genre_category_id']],
            $settings->all()['genre_category_map']
        );
    }

    public function test_new_filter_on_second_page_hides_rows_reconciled_to_other_statuses(): void
    {
        foreach (range(1, 40) as $position) {
            $hash = hash('sha256', 'delfi-pagination-new-' . $position);
            $this->source([
                'external_id' => 'DELFI-PAGE-' . $position,
                'remote_product_id' => 270000 + $position,
                'feed_position' => $position,
                'name' => 'Delfi nova knjiga ' . $position,
                'source_hash' => $hash,
                'checked_source_hash' => $hash,
                'check_status' => 'new',
            ]);
        }

        $matchedHash = hash('sha256', 'delfi-pagination-matched');
        $matched = $this->source([
            'external_id' => 'DELFI-PAGE-MATCHED',
            'remote_product_id' => 270041,
            'feed_position' => 41,
            'name' => 'Delfi naknadno pronađena knjiga',
            'isbn' => '9788652169101',
            'ean' => '9788652169101',
            'source_hash' => $matchedHash,
            'checked_source_hash' => $matchedHash,
            'check_status' => 'new',
        ]);
        $conflictHash = hash('sha256', 'delfi-pagination-conflict');
        $conflict = $this->source([
            'external_id' => 'DELFI-PAGE-CONFLICT',
            'remote_product_id' => 270042,
            'feed_position' => 42,
            'name' => 'Delfi naknadno konfliktna knjiga',
            'isbn' => '9788652169102',
            'ean' => '9788652169102',
            'source_hash' => $conflictHash,
            'checked_source_hash' => $conflictHash,
            'check_status' => 'new',
        ]);
        $stillNewHash = hash('sha256', 'delfi-pagination-still-new');
        $stillNew = $this->source([
            'external_id' => 'DELFI-PAGE-STILL-NEW',
            'remote_product_id' => 270043,
            'feed_position' => 43,
            'name' => 'Delfi stvarno nova knjiga',
            'isbn' => '9788652169103',
            'ean' => '9788652169103',
            'source_hash' => $stillNewHash,
            'checked_source_hash' => $stillNewHash,
            'check_status' => 'new',
        ]);

        $matchedProductId = DB::table('products')->insertGetId([
            'author_id' => 0,
            'name' => 'Postojeći Delfi artikl',
            'sku' => 'DELFI-PAGE-9101',
            'itemid' => 69101,
            'isbn' => '978-86-521-6910-1',
            'ean' => null,
            'slug' => 'postojeci-delfi-page-artikl',
            'url' => '/',
            'price' => 10,
            'quantity' => 1,
            'tax_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('products')->insert([
            [
                'author_id' => 0,
                'name' => 'Prvi konfliktni Delfi artikl',
                'sku' => 'DELFI-PAGE-9102-A',
                'itemid' => 69102,
                'isbn' => '9788652169102',
                'ean' => null,
                'slug' => 'prvi-konfliktni-delfi-page-artikl',
                'url' => '/',
                'price' => 10,
                'quantity' => 1,
                'tax_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'author_id' => 0,
                'name' => 'Drugi konfliktni Delfi artikl',
                'sku' => 'DELFI-PAGE-9102-B',
                'itemid' => 69103,
                'isbn' => null,
                'ean' => '9788652169102',
                'slug' => 'drugi-konfliktni-delfi-page-artikl',
                'url' => '/',
                'price' => 10,
                'quantity' => 1,
                'tax_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Cache::forget('delfi-import-book-genres-by-category');
        Cache::forget('delfi-import-source-genre-counts-by-category');
        Http::fake();

        $response = $this->actingAs($this->admin())->get(route('delfi-import.index', [
            'status' => 'new',
            'page' => 2,
        ]));

        $response->assertOk()
            ->assertViewHas('products', function ($products) use ($matched, $conflict, $stillNew) {
                $visibleIds = $products->getCollection()->pluck('id');

                return $products->currentPage() === 2
                    && str_contains($products->url(2), 'status=new')
                    && str_contains($products->url(2), 'page=2')
                    && ! $visibleIds->contains($matched->id)
                    && ! $visibleIds->contains($conflict->id)
                    && $visibleIds->contains($stillNew->id)
                    && $products->getCollection()->every(
                        fn (DelfiImportProduct $source) => $source->ui_status === 'new'
                    );
            });

        $matched->refresh();
        $conflict->refresh();
        $this->assertSame($matchedProductId, (int) $matched->product_id);
        $this->assertSame('matched', $matched->check_status);
        $this->assertSame('conflict', $conflict->check_status);
    }

    public function test_admin_feed_filters_by_delfi_category_and_subcategory(): void
    {
        $this->configureImport();
        $domestic = $this->source([
            'external_id' => 'DOMESTIC-FILTER',
            'remote_product_id' => 260001,
            'name' => 'Domaća fantastika',
            'source_category' => 'Knjiga',
            'source_genres' => ['Fantastika', 'Drama'],
            'source_hash' => hash('sha256', 'domestic-filter'),
            'checked_source_hash' => hash('sha256', 'domestic-filter'),
            'check_status' => 'new',
        ]);
        $foreign = $this->source([
            'external_id' => 'FOREIGN-FILTER',
            'remote_product_id' => 260002,
            'feed_position' => 2,
            'name' => 'Foreign fantasy',
            'source_category' => 'Strana knjiga',
            'source_genres' => ['Fantasy', 'Romance'],
            'source_hash' => hash('sha256', 'foreign-filter'),
            'checked_source_hash' => hash('sha256', 'foreign-filter'),
            'check_status' => 'new',
        ]);
        for ($position = 3; $position <= 42; $position++) {
            $hash = hash('sha256', 'foreign-filter-' . $position);
            $this->source([
                'external_id' => 'FOREIGN-FILTER-' . $position,
                'remote_product_id' => 260000 + $position,
                'feed_position' => $position,
                'name' => 'Foreign fantasy ' . $position,
                'source_category' => 'Strana knjiga',
                'source_genres' => ['Fantasy'],
                'source_hash' => $hash,
                'checked_source_hash' => $hash,
                'check_status' => 'new',
            ]);
        }
        Cache::forget('delfi-import-book-genres-by-category');
        Cache::forget('delfi-import-source-genre-counts-by-category');
        Http::fake([
            'https://delfi.rs/api/pc-frontend-api/get-filters-data' => Http::response([
                'data' => ['genresByCategories' => [
                    ['category' => 'Knjiga', 'genres' => [['genreName' => 'Fantastika']]],
                    ['category' => 'Strana knjiga', 'genres' => [['genreName' => 'Fantasy'], ['genreName' => 'Romance']]],
                ]],
            ], 200),
        ]);

        $response = $this->actingAs($this->admin())->get(route('delfi-import.index', [
            'status' => 'all',
            'source_category' => 'Strana knjiga',
            'source_genre' => 'Fantasy',
        ]));

        $response->assertOk()
            ->assertSee('data-source-row="' . $foreign->id . '"', false)
            ->assertDontSee('data-source-row="' . $domestic->id . '"', false)
            ->assertSee('value="Strana knjiga" selected', false)
            ->assertSee('value="Fantasy"', false)
            ->assertSee(route('delfi-import.index', [
                'source_category' => 'Strana knjiga',
                'source_genre' => 'Fantasy',
                'status' => 'new',
            ]))
            ->assertSee(route('delfi-import.index', [
                'status' => 'all',
                'source_category' => 'Strana knjiga',
                'source_genre' => 'Fantasy',
                'page' => 2,
            ]));

        $invalidCombination = $this->get(route('delfi-import.index', [
            'status' => 'all',
            'source_category' => 'Knjiga',
            'source_genre' => 'Fantasy',
        ]));
        $invalidCombination->assertOk()
            ->assertSee('value="Knjiga" selected', false)
            ->assertSee('data-source-row="' . $domestic->id . '"', false)
            ->assertDontSee('value="Fantasy" selected', false);
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

    public function test_bulk_inspection_checks_one_hundred_books_with_batched_queries_and_stops_early(): void
    {
        $feedToken = (string) Str::uuid();
        $sources = collect();
        $items = [];
        foreach (range(1, 100) as $offset) {
            $remoteId = 300000 + $offset;
            $ean = '9780306406157';
            $source = $this->source([
                'external_id' => 'BULK-' . $offset,
                'remote_product_id' => $remoteId,
                'feed_position' => $offset,
                'name' => 'Feed naslov ' . $offset,
                'description' => 'Feed opis ' . $offset,
                'source_url' => 'https://delfi.rs/knjige/' . $remoteId . '-bulk.html',
                'price_rsd' => 1000 + $offset,
                'availability' => 'in stock',
                'author' => 'Autor ' . $offset,
                'source_hash' => hash('sha256', 'bulk-source-' . $offset),
                'feed_token' => $feedToken,
            ]);
            $sources->push($source);
            $items[] = $this->bulkItem($source, [
                'name' => 'API naziv koji ne smije prepisati feed',
                'description' => 'API opis koji ne smije prepisati feed',
                'price_rsd' => 99999,
                'availability' => 'in_stock',
                'isbn' => $ean,
                'ean' => $ean,
                'source_genres' => ['Žanr ' . $offset],
                'format' => '13x20 cm',
            ]);
        }
        $existingId = DB::table('products')->insertGetId([
            'name' => 'Drugi naslov',
            'sku' => 'BULK-EXISTING',
            'itemid' => 900001,
            'isbn' => '9780306406157',
            'ean' => '9780306406157',
            'slug' => 'bulk-existing',
            'url' => '/',
            'price' => 10,
            'quantity' => 1,
            'tax_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rawFirst = ['page' => 1];
        $client = $this->mock(DelfiProductListApiClient::class);
        $client->shouldReceive('fetchPage')->once()->with(0, 100)->andReturn($rawFirst);
        $parser = $this->mock(DelfiProductListParser::class);
        $parser->shouldReceive('parsePage')->once()->with($rawFirst, 0, 100)->andReturn([
            'items' => $items,
            'total' => 101,
            'skip' => 0,
            'limit' => 100,
            'next_skip' => 100,
            'has_more' => true,
        ]);
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });
        $admin = $this->admin();

        $first = $this->actingAs($admin)->postJson(route('delfi-import.inspect-bulk'));
        $first->assertOk()->assertJson([
            'success' => true,
            'processed' => 100,
            'succeeded' => 100,
            'failed' => 0,
            'remaining' => 0,
            'done' => true,
        ]);
        $this->assertNull($first->json('next_cursor'));

        $firstSource = $sources->first()->fresh();
        $this->assertSame($existingId, (int) $firstSource->product_id);
        $this->assertSame('matched', $firstSource->check_status);
        $this->assertSame('Feed naslov 1', $firstSource->name);
        $this->assertSame('Feed opis 1', $firstSource->description);
        $this->assertSame(1001.0, (float) $firstSource->price_rsd);
        $this->assertSame('in stock', $firstSource->availability);
        $this->assertSame(['Žanr 1'], $firstSource->source_genres);
        $this->assertNull($firstSource->detail_payload);

        $productCandidateSelects = collect($queries)->filter(function (string $sql) {
            return str_starts_with(strtolower(ltrim($sql)), 'select')
                && (str_contains($sql, 'from "products"') || str_contains($sql, 'from `products`'));
        });
        $this->assertLessThanOrEqual(3, $productCandidateSelects->count());
        $this->assertFalse($productCandidateSelects->contains(function (string $sql) {
            return stripos($sql, 'replace(') !== false || stripos($sql, 'lower(') !== false;
        }));
    }

    public function test_retryable_bulk_failure_does_not_advance_server_cursor(): void
    {
        $feedToken = (string) Str::uuid();
        $source = $this->source(['feed_token' => $feedToken]);
        $raw = ['page' => 'retry'];
        $client = $this->mock(DelfiProductListApiClient::class);
        $client->shouldReceive('fetchPage')->once()->with(0, 100)
            ->andThrow(new DelfiRetryableException('Delfi bulk rate limit.', 503, 2));
        $client->shouldReceive('fetchPage')->once()->with(0, 100)->andReturn($raw);
        $parser = $this->mock(DelfiProductListParser::class);
        $parser->shouldReceive('parsePage')->once()->with($raw, 0, 100)->andReturn([
            'items' => [$this->bulkItem($source)],
            'total' => 1,
            'skip' => 0,
            'limit' => 100,
            'next_skip' => null,
            'has_more' => false,
        ]);
        $admin = $this->admin();

        $this->actingAs($admin)->postJson(route('delfi-import.inspect-bulk'))
            ->assertStatus(503)
            ->assertHeader('Retry-After', '2')
            ->assertJson(['success' => false, 'retryable' => true]);
        $this->assertNull($source->fresh()->checked_source_hash);

        $this->actingAs($admin)->postJson(route('delfi-import.inspect-bulk'))
            ->assertOk()
            ->assertJson(['processed' => 1, 'done' => true]);
    }

    public function test_bulk_inspection_resumes_the_ascending_cursor_from_server_state(): void
    {
        $feedToken = (string) Str::uuid();
        $firstSource = $this->source(['feed_token' => $feedToken]);
        $secondSource = $this->source([
            'external_id' => 'BULK-RESUME-2',
            'remote_product_id' => 251909,
            'feed_position' => 2,
            'source_url' => 'https://delfi.rs/knjige/251909-resume.html',
            'source_hash' => hash('sha256', 'bulk-resume-2'),
            'feed_token' => $feedToken,
        ]);
        $rawFirst = ['resume' => 1];
        $rawSecond = ['resume' => 2];
        $client = $this->mock(DelfiProductListApiClient::class);
        $client->shouldReceive('fetchPage')->once()->with(0, 1)->andReturn($rawFirst);
        $client->shouldReceive('fetchPage')->once()->with(1, 1)->andReturn($rawSecond);
        $parser = $this->mock(DelfiProductListParser::class);
        $parser->shouldReceive('parsePage')->once()->with($rawFirst, 0, 1)->andReturn([
            'items' => [$this->bulkItem($firstSource)],
            'total' => 2,
            'skip' => 0,
            'limit' => 1,
            'next_skip' => 1,
            'has_more' => true,
        ]);
        $parser->shouldReceive('parsePage')->once()->with($rawSecond, 1, 1)->andReturn([
            'items' => [$this->bulkItem($secondSource)],
            'total' => 2,
            'skip' => 1,
            'limit' => 1,
            'next_skip' => null,
            'has_more' => false,
        ]);
        $admin = $this->admin();
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $this->actingAs($admin)->postJson(route('delfi-import.inspect-bulk'), ['limit' => 1])
            ->assertOk()
            ->assertJson(['processed' => 1, 'remaining' => 1, 'done' => false]);
        $this->actingAs($admin)->postJson(route('delfi-import.inspect-bulk'), ['limit' => 1])
            ->assertOk()
            ->assertJson(['processed' => 1, 'remaining' => 0, 'done' => true]);

        $integrityScans = collect($queries)->filter(function (string $sql) {
            return stripos($sql, 'select distinct') !== false
                && stripos($sql, 'feed_token') !== false
                && stripos($sql, 'delfi_import_products') !== false;
        });
        $this->assertCount(1, $integrityScans, 'Resume pages must not repeat the 130k-row DISTINCT token scan.');
    }

    public function test_bulk_update_leaves_a_feed_row_pending_if_its_snapshot_changes_mid_page(): void
    {
        $source = $this->source();
        $raw = ['stale' => true];
        $this->mock(DelfiProductListApiClient::class)
            ->shouldReceive('fetchPage')->once()->with(0, 100)->andReturn($raw);
        $this->mock(DelfiProductListParser::class)
            ->shouldReceive('parsePage')->once()->with($raw, 0, 100)->andReturn([
                'items' => [$this->bulkItem($source)],
                'total' => 1,
                'skip' => 0,
                'limit' => 100,
                'next_skip' => null,
                'has_more' => false,
            ]);
        $newHash = hash('sha256', 'feed-refreshed-during-bulk');
        $mutated = false;
        DB::listen(function ($query) use (&$mutated, $source, $newHash) {
            $sql = strtolower($query->sql);
            if (! $mutated
                && str_starts_with(ltrim($sql), 'select')
                && (str_contains($sql, 'from "products"') || str_contains($sql, 'from `products`'))) {
                $mutated = true;
                DB::table('delfi_import_products')->where('id', $source->id)->update([
                    'name' => 'Naziv iz novog feed snapshot-a',
                    'source_hash' => $newHash,
                ]);
            }
        });

        $response = $this->actingAs($this->admin())
            ->postJson(route('delfi-import.inspect-bulk'));

        $response->assertOk()->assertJson([
            'processed' => 0,
            'remaining' => 1,
            'done' => false,
            'pass' => 2,
        ]);
        $source->refresh();
        $this->assertTrue($mutated);
        $this->assertSame($newHash, $source->source_hash);
        $this->assertSame('Naziv iz novog feed snapshot-a', $source->name);
        $this->assertNull($source->checked_source_hash);
        $this->assertSame('pending', $source->check_status);
    }

    public function test_bulk_aborts_old_cursor_when_feed_token_changes_mid_page(): void
    {
        $source = $this->source();
        $oldFeedToken = $source->feed_token;
        $newFeedToken = (string) Str::uuid();
        $raw = ['feed-race' => true];
        $this->mock(DelfiProductListApiClient::class)
            ->shouldReceive('fetchPage')->once()->with(0, 100)->andReturn($raw);
        $this->mock(DelfiProductListParser::class)
            ->shouldReceive('parsePage')->once()->with($raw, 0, 100)->andReturn([
                'items' => [$this->bulkItem($source)],
                'total' => 1,
                'skip' => 0,
                'limit' => 100,
                'next_skip' => null,
                'has_more' => false,
            ]);
        $mutated = false;
        DB::listen(function ($query) use (&$mutated, $source, $newFeedToken) {
            $sql = strtolower($query->sql);
            if (! $mutated
                && str_starts_with(ltrim($sql), 'select')
                && (str_contains($sql, 'from "products"') || str_contains($sql, 'from `products`'))) {
                $mutated = true;
                DB::table('delfi_import_products')->where('id', $source->id)->update([
                    'feed_token' => $newFeedToken,
                    'source_hash' => hash('sha256', 'new-feed-token-snapshot'),
                ]);
            }
        });

        $this->actingAs($this->admin())
            ->postJson(route('delfi-import.inspect-bulk'))
            ->assertStatus(409)
            ->assertJson([
                'success' => false,
                'processed' => 0,
                'done' => false,
            ]);

        $source->refresh();
        $this->assertTrue($mutated);
        $this->assertSame($newFeedToken, $source->feed_token);
        $this->assertNull($source->checked_source_hash);
        $this->assertNull(Cache::get('delfi-import-bulk-state:' . $oldFeedToken));
    }

    public function test_empty_page_after_upstream_total_shrink_finishes_pass_without_cursor_loop(): void
    {
        $source = $this->source();
        Cache::put('delfi-import-bulk-state:' . $source->feed_token, [
            'feed_token' => $source->feed_token,
            'skip' => 2,
            'last_old_product_id' => 251900,
            'pass' => 1,
            'remaining' => 1,
            'processed_total' => 0,
            'succeeded_total' => 0,
            'failed_total' => 0,
            'ignored_total' => 2,
            'scan_total' => 2,
            'scan_processed' => 2,
            'done' => false,
            'incomplete' => false,
        ], now()->addDay());
        $raw = ['shrunk-total' => true];
        $this->mock(DelfiProductListApiClient::class)
            ->shouldReceive('fetchPage')->once()->with(2, 100)->andReturn($raw);
        $this->mock(DelfiProductListParser::class)
            ->shouldReceive('parsePage')->once()->with($raw, 2, 100)->andReturn([
                'items' => [],
                'total' => 1,
                'skip' => 2,
                'limit' => 100,
                'next_skip' => null,
                'has_more' => false,
            ]);

        $this->actingAs($this->admin())
            ->postJson(route('delfi-import.inspect-bulk'))
            ->assertOk()
            ->assertJson([
                'processed' => 0,
                'remaining' => 1,
                'done' => false,
                'pass' => 2,
                'scan_processed' => 0,
            ]);
    }

    public function test_manual_click_restarts_a_cached_incomplete_bulk_run(): void
    {
        $source = $this->source();
        Cache::put('delfi-import-bulk-state:' . $source->feed_token, [
            'feed_token' => $source->feed_token,
            'skip' => 1,
            'last_old_product_id' => (int) $source->remote_product_id,
            'pass' => 2,
            'remaining' => 1,
            'processed_total' => 0,
            'succeeded_total' => 0,
            'failed_total' => 0,
            'ignored_total' => 2,
            'scan_total' => 1,
            'scan_processed' => 1,
            'done' => true,
            'incomplete' => true,
        ], now()->addDay());
        $raw = ['manual-retry' => true];
        $this->mock(DelfiProductListApiClient::class)
            ->shouldReceive('fetchPage')->once()->with(0, 100)->andReturn($raw);
        $this->mock(DelfiProductListParser::class)
            ->shouldReceive('parsePage')->once()->with($raw, 0, 100)->andReturn([
                'items' => [$this->bulkItem($source)],
                'total' => 1,
                'skip' => 0,
                'limit' => 100,
                'next_skip' => null,
                'has_more' => false,
            ]);

        $this->actingAs($this->admin())
            ->postJson(route('delfi-import.inspect-bulk'))
            ->assertOk()
            ->assertJson([
                'processed' => 1,
                'remaining' => 0,
                'done' => true,
                'incomplete' => false,
            ]);
    }

    public function test_bulk_inspection_stops_if_current_rows_have_multiple_feed_tokens(): void
    {
        $this->source();
        $this->source([
            'external_id' => 'SECOND-FEED-TOKEN',
            'remote_product_id' => 251909,
            'feed_position' => 2,
            'source_url' => 'https://delfi.rs/knjige/251909-second-token.html',
            'source_hash' => hash('sha256', 'second-token'),
        ]);
        $this->mock(DelfiProductListApiClient::class)->shouldNotReceive('fetchPage');

        $this->actingAs($this->admin())
            ->postJson(route('delfi-import.inspect-bulk'))
            ->assertStatus(409)
            ->assertJson([
                'success' => false,
                'processed' => 0,
                'done' => false,
            ]);
    }

    public function test_bulk_checked_changed_book_fetches_one_fresh_overview_when_imported(): void
    {
        $mapping = $this->configureImport();
        $oldHash = hash('sha256', 'old-source');
        $newHash = hash('sha256', 'new-source');
        $source = $this->source([
            'source_hash' => $newHash,
            'checked_source_hash' => $oldHash,
            'detail_payload' => ['description' => 'Stari detalji'],
        ]);
        $raw = ['page' => 'import'];
        $this->mock(DelfiProductListApiClient::class)
            ->shouldReceive('fetchPage')->once()->with(0, 100)->andReturn($raw);
        $this->mock(DelfiProductListParser::class)
            ->shouldReceive('parsePage')->once()->with($raw, 0, 100)->andReturn([
                'items' => [$this->bulkItem($source)],
                'total' => 1,
                'skip' => 0,
                'limit' => 100,
                'next_skip' => null,
                'has_more' => false,
            ]);

        $this->actingAs($this->admin())
            ->postJson(route('delfi-import.inspect-bulk'))
            ->assertOk()
            ->assertJson(['processed' => 1, 'done' => true]);
        $source->refresh();
        $this->assertSame('new', $source->check_status);
        $this->assertNull($source->detail_payload);

        Http::fake([
            'https://delfi.rs/api/pc-frontend-api/overview/251908' => Http::response($this->detailPayload(), 200),
            'translate.googleapis.com/*' => Http::response([[['Svježi hrvatski opis.', 'Opis knjige.']]], 200),
        ]);
        app(DelfiImportService::class)->import($source, $mapping['additional_category_id']);

        $overviewCalls = Http::recorded(function ($request) {
            return $request->url() === 'https://delfi.rs/api/pc-frontend-api/overview/251908';
        });
        $this->assertCount(1, $overviewCalls);
        $this->assertNotEmpty($source->fresh()->detail_payload);
    }

    public function test_bulk_uses_feed_title_and_api_author_and_clears_stale_identifiers(): void
    {
        $authorId = DB::table('authors')->insertGetId([
            'letter' => 'A',
            'title' => 'API Autor',
            'normalized_title' => AuthorResolver::normalizedKey('API Autor'),
            'slug' => 'api-autor',
            'url' => '/autori/api-autor',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $productId = DB::table('products')->insertGetId([
            'author_id' => $authorId,
            'name' => 'Kanonski naslov iz feeda',
            'sku' => 'BULK-TITLE-AUTHOR',
            'itemid' => 900002,
            'isbn' => '9789999999999',
            'ean' => '9789999999999',
            'slug' => 'kanonski-naslov-iz-feeda',
            'url' => '/',
            'price' => 10,
            'quantity' => 1,
            'tax_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $source = $this->source([
            'name' => 'Kanonski naslov iz feeda',
            'author' => 'Stari autor',
            'isbn' => '9788652162123',
            'ean' => '9788652162123',
        ]);
        $raw = ['title-author' => true];
        $this->mock(DelfiProductListApiClient::class)
            ->shouldReceive('fetchPage')->once()->with(0, 100)->andReturn($raw);
        $this->mock(DelfiProductListParser::class)
            ->shouldReceive('parsePage')->once()->with($raw, 0, 100)->andReturn([
                'items' => [$this->bulkItem($source, [
                    'name' => 'Drukčiji API naslov',
                    'title' => 'Drukčiji API naslov',
                    'author' => 'API Autor',
                    'authors' => ['API Autor'],
                    'isbn' => null,
                    'ean' => null,
                ])],
                'total' => 1,
                'skip' => 0,
                'limit' => 100,
                'next_skip' => null,
                'has_more' => false,
            ]);

        $this->actingAs($this->admin())
            ->postJson(route('delfi-import.inspect-bulk'))
            ->assertOk()
            ->assertJson(['processed' => 1, 'succeeded' => 1, 'done' => true]);

        $source->refresh();
        $this->assertSame($productId, (int) $source->product_id);
        $this->assertSame('matched', $source->check_status);
        $this->assertSame('Kanonski naslov iz feeda', $source->name);
        $this->assertSame('API Autor', $source->author);
        $this->assertNull($source->isbn);
        $this->assertNull($source->ean);
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

    private function bulkItem(DelfiImportProduct $source, array $overrides = []): array
    {
        return array_merge([
            'external_id' => $source->external_id,
            'remote_product_id' => (int) $source->remote_product_id,
            'nav_id' => 'A' . $source->remote_product_id,
            'sku' => 'A' . $source->remote_product_id,
            'name' => $source->name,
            'title' => $source->name,
            'isbn' => '9788652162123',
            'ean' => '9788652162123',
            'author' => $source->author,
            'authors' => [$source->author],
            'source_publisher' => 'Laguna',
            'publisher' => 'Laguna',
            'source_category' => $source->source_category,
            'category' => $source->source_category,
            'genre' => 'Fantastika',
            'source_genres' => ['Fantastika'],
            'format' => '13x20 cm',
            'pages' => 298,
            'letter' => 'Ćirilica',
            'binding' => 'Meki',
            'publication_year' => 2026,
            'year' => 2026,
            'description' => '',
            'meta_description' => 'Opis s liste.',
            'image_url' => null,
            'additional_image_urls' => [],
            'image' => null,
            'images' => [],
            'language' => null,
            'origin' => null,
            'price_rsd' => 1200,
            'sale_price_rsd' => null,
            'availability' => 'in_stock',
            'is_available' => true,
            'quantity' => null,
            'updated_at_for_api' => null,
        ], $overrides);
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
