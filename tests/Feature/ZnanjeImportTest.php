<?php

namespace Tests\Feature;

use App\Models\Back\Catalog\Category;
use App\Models\Back\Catalog\ZnanjeImportProduct;
use App\Models\User;
use App\Models\UserDetail;
use App\Services\Znanje\ZnanjeFeedSynchronizer;
use App\Services\Znanje\ZnanjeImportSettings;
use Bouncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ZnanjeImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_page_defaults_to_new_lists_newest_first_and_reuses_complete_book_ui(): void
    {
        $this->configureImport();
        $older = $this->source([
            'external_id' => '1001',
            'remote_product_id' => 1001,
            'publication_year' => 2024,
            'name' => 'Starija Znanje knjiga',
        ]);
        $newer = $this->source([
            'external_id' => '2002',
            'remote_product_id' => 2002,
            'publication_year' => 2026,
            'name' => 'Novija Znanje knjiga',
            'source_category' => 'Strane knjige',
            'source_categories' => ['Strane knjige', 'Fantasy'],
            'source_genres' => ['Fantasy'],
            'source_url' => 'https://znanje.hr/product/novija-znanje-knjiga/2002',
            'image_url' => 'https://znanje.hr/images/novija-znanje-knjiga.jpg',
        ]);

        $response = $this->actingAs($this->admin())->get(route('znanje-import.index'));

        $response->assertOk()
            ->assertSee('Znanje import')
            ->assertSee('Samo dostupne knjige iz kategorija Knjige i Strane knjige')
            ->assertSee('value="new" selected', false)
            ->assertSee('Znanje kategorija')
            ->assertSee('Znanje podkategorija')
            ->assertSee('Mapiranje Znanje kategorija i podkategorija')
            ->assertSee('15,00 EUR')
            ->assertDontSee('RSD za 1 EUR')
            ->assertDontSee('Prevedi opis na hrvatski')
            ->assertSee('href="' . $newer->source_url . '" target="_blank"', false)
            ->assertSee('class="img-link img-link-zoom-in img-lightbox"', false)
            ->assertSee('data-feed-refresh-state', false)
            ->assertSee(json_encode(route('znanje-import.refresh-start')), false)
            ->assertSee(json_encode(route('znanje-import.refresh-step')), false)
            ->assertViewHas('importUi', function (array $importUi) {
                return $importUi['supports_batched_refresh'] === true
                    && $importUi['refresh_start_route'] === 'znanje-import.refresh-start'
                    && $importUi['refresh_step_route'] === 'znanje-import.refresh-step';
            })
            ->assertSeeInOrder([
                'data-source-row="' . $newer->id . '"',
                'data-source-row="' . $older->id . '"',
            ], false);
    }

    public function test_refresh_start_ajax_only_starts_a_batched_session(): void
    {
        $token = (string) Str::uuid();
        $synchronizer = $this->mock(ZnanjeFeedSynchronizer::class);
        $synchronizer->shouldReceive('start')->once()->andReturn([
            'token' => $token,
            'phase' => 'crawling',
            'processed_pages' => 0,
            'total_pages' => 0,
            'staged' => 0,
            'ready_to_finalize' => false,
            'completed' => false,
            'message' => 'Znanje katalog je pripremljen za preuzimanje.',
        ]);
        $synchronizer->shouldNotReceive('refresh');
        $synchronizer->shouldNotReceive('step');
        $synchronizer->shouldNotReceive('finalize');

        $response = $this->actingAs($this->admin())
            ->postJson(route('znanje-import.refresh-start'));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('done', false)
            ->assertJsonPath('token', $token)
            ->assertJsonPath('phase', 'crawling');
    }

    public function test_refresh_step_ajax_hard_caps_one_page_and_finalizes_a_ready_session(): void
    {
        // Admin requests stay short even if a live environment accidentally
        // configures a larger batch.
        config(['znanje_import.refresh_pages_per_request' => 99]);
        $token = (string) Str::uuid();
        Cache::put('znanje-import-source-genre-counts-by-category', ['stale'], 60);
        Cache::put('znanje-import-source-category-counts', ['stale'], 60);

        $synchronizer = $this->mock(ZnanjeFeedSynchronizer::class);
        $synchronizer->shouldReceive('step')->once()->with($token, 1)->andReturn([
            'token' => $token,
            'phase' => 'ready_to_finalize',
            'processed_pages' => 290,
            'total_pages' => 290,
            'staged' => 12,
            'ready_to_finalize' => true,
            'completed' => false,
            'message' => 'Sve stranice su preuzete.',
        ]);
        $synchronizer->shouldReceive('finalize')->once()->with($token)->andReturn([
            'staged' => 12,
            'current' => 12,
            'retired' => 5,
            'retired_now' => 2,
            'skipped' => 1,
            'snapshot_warning' => null,
        ]);
        $synchronizer->shouldNotReceive('refresh');
        $synchronizer->shouldNotReceive('start');

        $response = $this->actingAs($this->admin())->postJson(
            route('znanje-import.refresh-step'),
            ['token' => $token]
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('done', true)
            ->assertJsonPath('staged', 12)
            ->assertJsonPath('current', 12)
            ->assertJsonPath('retired_now', 2)
            ->assertJsonPath(
                'message',
                'Znanje feed je osvježen: 12 knjiga, 12 aktualnih, 2 uklonjenih i 1 nepotpunih preskočenih.'
            );
        $this->assertFalse(Cache::has('znanje-import-source-genre-counts-by-category'));
        $this->assertFalse(Cache::has('znanje-import-source-category-counts'));
    }

    public function test_admin_filters_both_znanje_roots_and_their_subcategories(): void
    {
        $this->configureImport();
        $crime = $this->source([
            'external_id' => '3003',
            'remote_product_id' => 3003,
            'name' => 'Znanje krimić',
            'source_category' => 'Knjige',
            'source_categories' => ['Knjige', 'Krimići'],
            'source_genres' => ['Krimići'],
        ]);
        $fantasy = $this->source([
            'external_id' => '3004',
            'remote_product_id' => 3004,
            'name' => 'Znanje strani fantasy',
            'source_category' => 'Strane knjige',
            'source_categories' => ['Strane knjige', 'Fantasy'],
            'source_genres' => ['Fantasy'],
        ]);

        $response = $this->actingAs($this->admin())->get(route('znanje-import.index', [
            'status' => 'all',
            'source_category' => 'Strane knjige',
            'source_genre' => 'Fantasy',
        ]));

        $response->assertOk()
            ->assertSee('data-source-row="' . $fantasy->id . '"', false)
            ->assertDontSee('data-source-row="' . $crime->id . '"', false)
            ->assertSee('value="Knjige"', false)
            ->assertSee('value="Strane knjige" selected', false)
            ->assertSee('value="Fantasy"', false)
            ->assertSee(route('znanje-import.index', [
                'source_category' => 'Strane knjige',
                'source_genre' => 'Fantasy',
                'status' => 'new',
            ]));
    }

    public function test_admin_saves_eur_markup_source_mapping_activation_and_existing_action(): void
    {
        $mapping = $this->configureImport();

        $response = $this->actingAs($this->admin())->post(route('znanje-import.settings'), [
            'markup_percent' => 12.5,
            'publisher_parent_category_id' => $mapping['publisher_parent_category_id'],
            'publisher_category_id' => $mapping['publisher_category_id'],
            'publisher_id' => $mapping['publisher_id'],
            'default_quantity' => 4,
            'existing_action' => 'price_stock',
            'map_source_publishers' => 1,
            'activate_new_products' => 1,
            'source_genres' => ['Knjige', 'Knjige › Krimići'],
            'genre_category_ids' => [
                $mapping['books_category_id'],
                $mapping['mapped_category_id'],
            ],
        ]);

        $response->assertRedirect(route('znanje-import.index', ['tab' => 'settings']));
        $settings = app(ZnanjeImportSettings::class)->all();
        $this->assertSame(1.0, $settings['exchange_rate']);
        $this->assertSame(12.5, $settings['markup_percent']);
        $this->assertSame(4, $settings['default_quantity']);
        $this->assertSame('price_stock', $settings['existing_action']);
        $this->assertTrue($settings['map_source_publishers']);
        $this->assertTrue($settings['activate_new_products']);
        $this->assertSame([
            'Knjige' => $mapping['books_category_id'],
            'Knjige › Krimići' => $mapping['mapped_category_id'],
        ], $settings['category_map']);
    }

    public function test_settings_distinguish_same_subcategory_under_both_source_roots(): void
    {
        $this->configureImport();
        $this->source([
            'external_id' => '3010',
            'remote_product_id' => 3010,
            'source_category' => 'Knjige',
            'source_categories' => ['Knjige', 'Publicistika'],
            'source_genres' => ['Publicistika'],
        ]);
        $this->source([
            'external_id' => '3011',
            'remote_product_id' => 3011,
            'source_category' => 'Strane knjige',
            'source_categories' => ['Strane knjige', 'Publicistika'],
            'source_genres' => ['Publicistika'],
        ]);
        Cache::forget('znanje-import-source-genre-counts-by-category');
        Cache::forget('znanje-import-source-category-counts');

        $response = $this->actingAs($this->admin())->get(route('znanje-import.index', [
            'tab' => 'settings',
        ]));

        $response->assertOk()
            ->assertSee('value="Knjige › Publicistika"', false)
            ->assertSee('value="Strane knjige › Publicistika"', false);
    }

    public function test_inspection_queue_contains_only_current_unchecked_books(): void
    {
        $this->configureImport();
        $pending = $this->source([
            'external_id' => '4001',
            'remote_product_id' => 4001,
            'name' => 'Znanje knjiga za provjeru',
            'checked_source_hash' => null,
            'check_status' => 'pending',
        ]);
        $this->source([
            'external_id' => '4002',
            'remote_product_id' => 4002,
            'name' => 'Već provjerena Znanje knjiga',
        ]);
        $this->source([
            'external_id' => '4003',
            'remote_product_id' => 4003,
            'name' => 'Uklonjena Znanje knjiga',
            'is_current' => false,
            'checked_source_hash' => null,
        ]);

        $response = $this->actingAs($this->admin())->getJson(route('znanje-import.inspection-queue'));

        $response->assertOk()
            ->assertJsonPath('remaining', 1)
            ->assertJsonPath('items.0.id', $pending->id)
            ->assertJsonPath('items.0.name', 'Znanje knjiga za provjeru')
            ->assertJsonCount(1, 'items');
    }

    public function test_new_filter_on_later_pages_does_not_render_rows_reconciled_as_existing(): void
    {
        $this->configureImport();
        foreach (range(1, 40) as $position) {
            $this->source([
                'external_id' => (string) (8000 + $position),
                'remote_product_id' => 8000 + $position,
                'publication_year' => 2027,
                'name' => 'Nova Znanje knjiga ' . $position,
            ]);
        }
        $matched = $this->source([
            'external_id' => '5156',
            'remote_product_id' => 5156,
            'publication_year' => 2026,
            'name' => 'Anne Frank',
            'isbn' => '9789538551093',
            'ean' => '9789538551093',
            'author' => 'Maria Cecilia Cavallone',
        ]);
        $stillNew = $this->source([
            'external_id' => '5155',
            'remote_product_id' => 5155,
            'publication_year' => 2025,
            'name' => 'Stvarno nova Znanje knjiga',
            'isbn' => '9789538551999',
            'ean' => '9789538551999',
        ]);
        $productId = DB::table('products')->insertGetId([
            'author_id' => 0,
            'name' => 'Anne Frank',
            'sku' => '9754',
            'itemid' => 9754,
            'isbn' => '978-953-8551-09-3',
            'ean' => null,
            'slug' => 'anne-frank-znanje-existing',
            'url' => '/',
            'price' => 9.90,
            'quantity' => 1,
            'tax_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin())->get(route('znanje-import.index', [
            'status' => 'new',
            'page' => 2,
        ]));

        $response->assertOk()
            ->assertViewHas('products', function ($products) use ($matched, $stillNew) {
                $visibleIds = $products->getCollection()->pluck('id');

                return $products->currentPage() === 2
                    && $products->total() === 41
                    && str_contains($products->url(2), 'status=new')
                    && ! $visibleIds->contains($matched->id)
                    && $visibleIds->contains($stillNew->id)
                    && $products->getCollection()->every(
                        fn (ZnanjeImportProduct $source) => $source->ui_status === 'new'
                    );
            });
        $matched->refresh();
        $this->assertSame($productId, (int) $matched->product_id);
        $this->assertSame('matched', $matched->check_status);
    }

    public function test_new_filter_keeps_reconciling_when_more_than_one_page_became_existing(): void
    {
        $this->configureImport();
        $productId = DB::table('products')->insertGetId([
            'author_id' => 0,
            'name' => 'Već postojeće izdanje',
            'sku' => '9764',
            'itemid' => 9764,
            'isbn' => '9789538551777',
            'ean' => null,
            'slug' => 'vec-postojece-izdanje-znanje',
            'url' => '/',
            'price' => 9.90,
            'quantity' => 1,
            'tax_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach (range(1, 41) as $position) {
            $this->source([
                'external_id' => (string) (9000 + $position),
                'remote_product_id' => 9000 + $position,
                'publication_year' => 2027,
                'name' => 'Stale novi red ' . $position,
                'isbn' => '978-953-8551-77-7',
                'ean' => null,
            ]);
        }
        $stillNew = $this->source([
            'external_id' => '8999',
            'remote_product_id' => 8999,
            'publication_year' => 2025,
            'name' => 'Jedini stvarno novi naslov',
            'isbn' => '9789538551888',
            'ean' => null,
        ]);

        $response = $this->actingAs($this->admin())->get(route('znanje-import.index', [
            'status' => 'new',
        ]));

        $response->assertOk()->assertViewHas('products', function ($products) use ($stillNew) {
            return $products->total() === 1
                && $products->getCollection()->pluck('id')->all() === [$stillNew->id]
                && $products->getCollection()->every(
                    fn (ZnanjeImportProduct $source) => $source->ui_status === 'new'
                );
        });
        $this->assertSame(41, ZnanjeImportProduct::query()
            ->where('product_id', $productId)
            ->where('check_status', 'matched')
            ->count());
    }

    private function source(array $overrides = []): ZnanjeImportProduct
    {
        $externalId = (string) ($overrides['external_id'] ?? random_int(100000, 999999));
        $hash = hash('sha256', 'znanje-' . $externalId);

        return ZnanjeImportProduct::query()->create(array_merge([
            'external_id' => $externalId,
            'remote_product_id' => (int) $externalId,
            'feed_position' => 1,
            'name' => 'Test Znanje knjiga',
            'description' => 'Opis knjige na hrvatskom jeziku.',
            'source_category' => 'Knjige',
            'source_categories' => ['Knjige'],
            'source_publisher' => 'Znanje',
            'source_url' => 'https://znanje.hr/product/test-znanje-knjiga/' . $externalId,
            'image_url' => null,
            'additional_image_urls' => [],
            'price_eur' => 10,
            'sale_price_eur' => null,
            'availability' => 'Dostupno',
            'isbn' => null,
            'ean' => null,
            'author' => 'Autor Test',
            'source_genres' => [],
            'format' => '13 × 20 cm',
            'pages' => 200,
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
        ], $overrides));
    }

    private function configureImport(): array
    {
        $publisherParentCategoryId = $this->category('Nakladnici');
        $publisherCategoryId = $this->category('Znanje', $publisherParentCategoryId);
        $booksCategoryId = $this->category('Knjige');
        $mappedCategoryId = $this->category('Krimići', $booksCategoryId);
        $publisherId = DB::table('publishers')->insertGetId([
            'title' => 'Znanje',
            'slug' => 'znanje',
            'url' => '/izdavaci/znanje',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Category::forgetAdminListCache();

        app(ZnanjeImportSettings::class)->save([
            'markup_percent' => 0,
            'publisher_parent_category_id' => $publisherParentCategoryId,
            'publisher_category_id' => $publisherCategoryId,
            'publisher_id' => $publisherId,
            'map_source_publishers' => 1,
            'category_map' => [],
            'default_quantity' => 1,
            'activate_new_products' => 0,
            'existing_action' => 'skip',
        ]);
        Cache::forget('znanje-import-source-genre-counts-by-category');

        return [
            'publisher_parent_category_id' => $publisherParentCategoryId,
            'publisher_category_id' => $publisherCategoryId,
            'books_category_id' => $booksCategoryId,
            'mapped_category_id' => $mappedCategoryId,
            'publisher_id' => $publisherId,
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

    private function admin(): User
    {
        $admin = User::factory()->create();
        UserDetail::query()->create([
            'user_id' => $admin->id,
            'fname' => 'Admin',
            'lname' => 'Znanje',
            'role' => 'admin',
        ]);
        Bouncer::allow($admin)->everything();

        return $admin;
    }
}
