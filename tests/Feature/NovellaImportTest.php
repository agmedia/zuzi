<?php

namespace Tests\Feature;

use App\Models\Back\Catalog\Category;
use App\Models\Back\Catalog\NovellaImportProduct;
use App\Models\User;
use App\Models\UserDetail;
use App\Services\Novella\NovellaImportSettings;
use Bouncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NovellaImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_page_defaults_to_new_and_lists_newest_novella_title_first(): void
    {
        $this->configureImport();
        $older = $this->source([
            'external_id' => '1001',
            'remote_product_id' => 1001,
            'feed_position' => 1,
            'name' => 'Starija Novella knjiga',
        ]);
        $newer = $this->source([
            'external_id' => '2002',
            'remote_product_id' => 2002,
            'feed_position' => 2,
            'name' => 'Novija Novella knjiga',
            'source_genres' => ['Fantastika'],
            'source_categories' => ['Knjige', 'Fantastika'],
        ]);

        $response = $this->actingAs($this->admin())->get(route('novella-import.index'));

        $response->assertOk()
            ->assertSee('Novella import')
            ->assertSee('Samo kategorija Knjige')
            ->assertSee('value="new" selected', false)
            ->assertSee('Novella kategorija')
            ->assertSee('Novella podkategorija')
            ->assertSee('Mapiranje Novella podkategorija')
            ->assertSee('15,00 EUR')
            ->assertDontSee('RSD za 1 EUR')
            ->assertDontSee('Prevedi opis na hrvatski')
            ->assertSeeInOrder([
                'data-source-row="' . $newer->id . '"',
                'data-source-row="' . $older->id . '"',
            ], false);
    }

    public function test_admin_page_reconciles_a_cached_new_row_with_the_current_catalog(): void
    {
        $this->configureImport();
        $source = $this->source([
            'external_id' => '5156',
            'remote_product_id' => 5156,
            'name' => 'Anne Frank',
            'isbn' => '9789538551093',
            'ean' => '9789538551093',
            'author' => 'Maria Cecilia Cavallone',
            'check_message' => 'ISBN, EAN ni kombinacija naziva i autora nisu pronađeni u Zuzi katalogu.',
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

        $response = $this->actingAs($this->admin())->get(route('novella-import.index'));

        $response->assertOk()
            ->assertSee('Već postoji')
            ->assertSee('Zuzi #' . $productId)
            ->assertSee('Postojeći Zuzi artikl pronađen');
        $source->refresh();
        $this->assertSame($productId, (int) $source->product_id);
        $this->assertSame('matched', $source->check_status);

        $importResponse = $this->postJson(route('novella-import.import', [
            'novellaImportProduct' => $source,
        ]));
        $importResponse->assertOk()->assertJson([
            'success' => true,
            'action' => 'skipped',
            'status' => 'existing',
            'message' => 'Artikl već postoji u Zuzi katalogu i preskočen je. Dodane su odabrane kategorije.',
            'check_message' => 'Artikl već postoji u Zuzi katalogu i preskočen je. Dodane su odabrane kategorije.',
            'product_id' => $productId,
            'product_url' => route('products.edit', ['product' => $productId]),
        ]);
    }

    public function test_admin_feed_filters_by_novella_category_and_subcategory(): void
    {
        $this->configureImport();
        $fantasy = $this->source([
            'external_id' => '3003',
            'remote_product_id' => 3003,
            'name' => 'Novella fantastika',
            'source_genres' => ['Fantastika'],
            'source_categories' => ['Knjige', 'Fantastika'],
        ]);
        $children = $this->source([
            'external_id' => '3004',
            'remote_product_id' => 3004,
            'feed_position' => 2,
            'name' => 'Novella dječja knjiga',
            'source_genres' => ['Dječje knjige'],
            'source_categories' => ['Knjige', 'Dječje knjige'],
        ]);

        $response = $this->actingAs($this->admin())->get(route('novella-import.index', [
            'status' => 'all',
            'source_category' => 'Knjige',
            'source_genre' => 'Fantastika',
        ]));

        $response->assertOk()
            ->assertSee('data-source-row="' . $fantasy->id . '"', false)
            ->assertDontSee('data-source-row="' . $children->id . '"', false)
            ->assertSee('value="Knjige" selected', false)
            ->assertSee('value="Fantastika"', false)
            ->assertSee(route('novella-import.index', [
                'source_category' => 'Knjige',
                'source_genre' => 'Fantastika',
                'status' => 'new',
            ]));
    }

    public function test_admin_can_save_novella_markup_mapping_and_activation_without_exchange_rate(): void
    {
        $mapping = $this->configureImport();

        $response = $this->actingAs($this->admin())->post(route('novella-import.settings'), [
            'markup_percent' => 15,
            'publisher_parent_category_id' => $mapping['publisher_parent_category_id'],
            'publisher_category_id' => $mapping['publisher_category_id'],
            'publisher_id' => $mapping['publisher_id'],
            'default_quantity' => 4,
            'existing_action' => 'price_stock',
            'activate_new_products' => 1,
            'source_genres' => ['Fantastika'],
            'genre_category_ids' => [$mapping['mapped_category_id']],
        ]);

        $response->assertRedirect(route('novella-import.index', ['tab' => 'settings']));
        $settings = app(NovellaImportSettings::class)->all();
        $this->assertSame(15.0, $settings['markup_percent']);
        $this->assertSame(4, $settings['default_quantity']);
        $this->assertSame('price_stock', $settings['existing_action']);
        $this->assertTrue($settings['activate_new_products']);
        $this->assertSame(
            ['Fantastika' => $mapping['mapped_category_id']],
            $settings['category_map']
        );
    }

    private function source(array $overrides = []): NovellaImportProduct
    {
        $externalId = (string) ($overrides['external_id'] ?? '1000');
        $hash = hash('sha256', 'novella-' . $externalId);

        return NovellaImportProduct::query()->create(array_merge([
            'external_id' => $externalId,
            'remote_product_id' => (int) $externalId,
            'feed_position' => 1,
            'name' => 'Test Novella knjiga',
            'description' => 'Opis knjige na hrvatskom jeziku.',
            'source_category' => 'Knjige',
            'source_categories' => ['Knjige'],
            'source_publisher' => 'Novella',
            'source_url' => 'https://novella.hr/proizvod/test-novella-knjiga/',
            'image_url' => null,
            'additional_image_urls' => [],
            'price_eur' => 10,
            'sale_price_eur' => null,
            'availability' => 'in_stock',
            'sku' => '9789530000000',
            'author' => 'Autor Test',
            'source_genres' => [],
            'source_hash' => $hash,
            'checked_source_hash' => $hash,
            'feed_token' => (string) Str::uuid(),
            'is_current' => true,
            'check_status' => 'new',
            'last_seen_at' => now(),
        ], $overrides));
    }

    private function configureImport(): array
    {
        $publisherParentCategoryId = $this->category('Nakladnici');
        $publisherCategoryId = $this->category('Novella', $publisherParentCategoryId);
        $bookCategoryId = $this->category('Knjige');
        $mappedCategoryId = $this->category('Fantastika', $bookCategoryId);
        $publisherId = DB::table('publishers')->insertGetId([
            'letter' => 'N',
            'title' => 'Novella',
            'slug' => 'novella',
            'url' => '/izdavaci/novella',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Category::forgetAdminListCache();

        app(NovellaImportSettings::class)->save([
            'markup_percent' => 0,
            'publisher_parent_category_id' => $publisherParentCategoryId,
            'publisher_category_id' => $publisherCategoryId,
            'publisher_id' => $publisherId,
            'map_source_publishers' => 0,
            'category_map' => [],
            'default_quantity' => 1,
            'activate_new_products' => 0,
            'existing_action' => 'skip',
        ]);
        Cache::forget('novella-import-source-genre-counts');

        return [
            'publisher_parent_category_id' => $publisherParentCategoryId,
            'publisher_category_id' => $publisherCategoryId,
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
            'lname' => 'Novella',
            'role' => 'admin',
        ]);
        Bouncer::allow($admin)->everything();

        return $admin;
    }
}
