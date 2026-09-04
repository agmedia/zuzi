<?php

namespace Tests\Feature;

use App\Models\Back\Catalog\Product\Product;
use App\Models\Back\Catalog\ZnanjeImportProduct;
use App\Services\Catalog\AuthorResolver;
use App\Services\ProductIdentifierAllocator;
use App\Services\Znanje\ZnanjeImportService;
use App\Services\Znanje\ZnanjeImportSettings;
use App\Services\Znanje\ZnanjeProductDetailParser;
use App\Services\Znanje\ZnanjeProductPageClient;
use App\Services\Znanje\ZnanjeRetryableException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ZnanjeImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_settings_enable_source_publisher_mapping_with_znanje_fallback(): void
    {
        $publisherParent = $this->category('Nakladnici');
        $publisherCategory = $this->category('Znanje', $publisherParent);
        $publisherId = $this->publisher('Znanje');

        $settings = app(ZnanjeImportSettings::class)->all();

        $this->assertTrue($settings['map_source_publishers']);
        $this->assertSame($publisherParent, $settings['publisher_parent_category_id']);
        $this->assertSame($publisherCategory, $settings['publisher_category_id']);
        $this->assertSame($publisherId, $settings['publisher_id']);
        $this->assertFalse($settings['activate_new_products']);
        $this->assertSame('skip', $settings['existing_action']);
    }

    public function test_it_imports_an_inactive_book_with_eur_markup_and_mapped_categories(): void
    {
        $publisherParent = $this->category('Nakladnici');
        $publisherCategory = $this->category('Znanje', $publisherParent);
        $booksParent = $this->category('Knjige');
        $mappedCategory = $this->category('Krimići', $booksParent);
        $manualCategory = $this->category('Novo u ponudi', $booksParent);
        $publisherId = $this->publisher('Znanje');

        app(ZnanjeImportSettings::class)->save([
            'markup_percent' => 25,
            'publisher_parent_category_id' => $publisherParent,
            'publisher_category_id' => $publisherCategory,
            'publisher_id' => $publisherId,
            'map_source_publishers' => 0,
            'category_map' => ['Krimići' => $mappedCategory],
            'default_quantity' => 4,
            'activate_new_products' => 0,
            'existing_action' => 'skip',
        ]);

        $source = $this->checkedSource([
            'name' => 'Jedno zlatno ljeto',
            'description' => "Prvi odlomak.\n\nDrugi odlomak.",
            'source_categories' => ['Knjige', 'Krimići'],
            'source_genres' => ['Krimići'],
            'isbn' => '9789530000001',
            'ean' => '9789530000001',
            'author' => '  Ivo   Horvat  ',
            'price_eur' => 20,
            'sale_price_eur' => 16,
            'availability' => 'Dostupno',
        ]);
        $this->mockFreshDetail($source);

        $result = app(ZnanjeImportService::class)->import($source, $manualCategory);

        $this->assertSame('created', $result['action']);
        $product = Product::query()->findOrFail($result['product_id']);
        $this->assertSame('Ivo Horvat: Jedno zlatno ljeto', $product->name);
        $this->assertSame('Ivo Horvat: Jedno zlatno ljeto', $product->meta_title);
        $this->assertSame('9789530000001', $product->isbn);
        $this->assertSame('9789530000001', $product->ean);
        $this->assertSame(25.0, (float) $product->price);
        $this->assertSame(20.0, (float) $product->special);
        $this->assertSame(4, (int) $product->quantity);
        $this->assertSame($publisherId, (int) $product->publisher_id);
        $this->assertSame(0, (int) $product->status);
        $this->assertStringContainsString('<p>Prvi odlomak.</p>', $product->description);
        $this->assertSame('Ivo Horvat', (string) optional($product->author)->title);
        foreach ([$publisherCategory, $mappedCategory, $manualCategory] as $categoryId) {
            $this->assertDatabaseHas('product_category', [
                'product_id' => $product->id,
                'category_id' => $categoryId,
            ]);
        }
        $source->refresh();
        $this->assertSame($source->source_hash, $source->imported_hash);
        $this->assertNotNull($source->imported_at);
    }

    public function test_new_product_and_source_link_are_written_in_the_same_transaction(): void
    {
        $this->configuredSettings();
        $source = $this->checkedSource(['isbn' => '9789530000094']);
        $this->mockFreshDetail($source);
        $this->mock(ProductIdentifierAllocator::class)
            ->shouldReceive('confirm')
            ->once()
            ->andReturnUsing(function ($token, $callback) use ($source) {
                $this->assertNull($token);
                $outcome = $callback(['sku' => 765431, 'itemid' => 765432]);

                $linked = ZnanjeImportProduct::query()->findOrFail($source->id);
                $this->assertTrue($outcome['created']);
                $this->assertSame((int) $outcome['product']->id, (int) $linked->product_id);
                $this->assertSame('matched', $linked->check_status);
                $this->assertSame($linked->source_hash, $linked->imported_hash);
                $this->assertNotNull($linked->imported_at);

                return $outcome;
            });

        $result = app(ZnanjeImportService::class)->import($source);

        $this->assertSame('created', $result['action']);
        $this->assertSame(
            $result['product_id'],
            (int) ZnanjeImportProduct::query()->findOrFail($source->id)->product_id
        );
    }

    public function test_composite_taxonomy_mapping_distinguishes_roots_and_overrides_flat_fallback(): void
    {
        $publisherParent = $this->category('Nakladnici');
        $publisherCategory = $this->category('Znanje', $publisherParent);
        $booksParent = $this->category('Knjige');
        $legacyCategory = $this->category('Stara Publicistika', $booksParent);
        $croatianCategory = $this->category('Hrvatska Publicistika', $booksParent);
        $foreignCategory = $this->category('Strana Publicistika', $booksParent);
        $publisherId = $this->publisher('Znanje');
        app(ZnanjeImportSettings::class)->save([
            'publisher_parent_category_id' => $publisherParent,
            'publisher_category_id' => $publisherCategory,
            'publisher_id' => $publisherId,
            'map_source_publishers' => 0,
            'category_map' => [
                'Publicistika' => $legacyCategory,
                'Knjige › Publicistika' => $croatianCategory,
                'Strane knjige › Publicistika' => $foreignCategory,
            ],
            'default_quantity' => 1,
            'existing_action' => 'skip',
        ]);

        $croatian = $this->checkedSource([
            'source_category' => 'Knjige',
            'source_categories' => ['Knjige', 'Publicistika'],
            'source_genres' => ['Publicistika'],
            'isbn' => '9789530000018',
        ]);
        $this->mockFreshDetail($croatian);
        $croatianResult = app(ZnanjeImportService::class)->import($croatian);

        $foreign = $this->checkedSource([
            'source_category' => 'Strane knjige',
            'source_categories' => ['Strane knjige', 'Publicistika'],
            'source_genres' => ['Publicistika'],
            'isbn' => '9789530000025',
        ]);
        $this->mockFreshDetail($foreign);
        $foreignResult = app(ZnanjeImportService::class)->import($foreign);

        $this->assertDatabaseHas('product_category', [
            'product_id' => $croatianResult['product_id'],
            'category_id' => $croatianCategory,
        ]);
        $this->assertDatabaseMissing('product_category', [
            'product_id' => $croatianResult['product_id'],
            'category_id' => $legacyCategory,
        ]);
        $this->assertDatabaseHas('product_category', [
            'product_id' => $foreignResult['product_id'],
            'category_id' => $foreignCategory,
        ]);
        $this->assertDatabaseMissing('product_category', [
            'product_id' => $foreignResult['product_id'],
            'category_id' => $legacyCategory,
        ]);
    }

    public function test_it_maps_an_existing_source_publisher_and_its_publisher_category(): void
    {
        $publisherParent = $this->category('Nakladnici');
        $fallbackCategory = $this->category('Znanje', $publisherParent);
        $mappedPublisherCategory = $this->category('Mozaik knjiga', $publisherParent);
        $fallbackPublisherId = $this->publisher('Znanje');
        $mappedPublisherId = $this->publisher('Mozaik knjiga');
        app(ZnanjeImportSettings::class)->save([
            'publisher_parent_category_id' => $publisherParent,
            'publisher_category_id' => $fallbackCategory,
            'publisher_id' => $fallbackPublisherId,
            'map_source_publishers' => 1,
            'default_quantity' => 1,
        ]);
        $source = $this->checkedSource([
            'name' => 'Knjiga drugog nakladnika',
            'source_publisher' => '  MOZAIK KNJIGA ',
            'isbn' => '9789534444444',
        ]);
        $this->mockFreshDetail($source);

        $result = app(ZnanjeImportService::class)->import($source);

        $product = Product::query()->findOrFail($result['product_id']);
        $this->assertSame($mappedPublisherId, (int) $product->publisher_id);
        $this->assertDatabaseHas('product_category', [
            'product_id' => $product->id,
            'category_id' => $mappedPublisherCategory,
        ]);
        $this->assertDatabaseHas('product_category', [
            'product_id' => $product->id,
            'category_id' => $publisherParent,
        ]);
    }

    public function test_inspection_uses_the_detail_contract_and_refreshes_price_and_availability(): void
    {
        $source = $this->checkedSource([
            'external_id' => '528463',
            'remote_product_id' => 528463,
            'source_url' => 'https://znanje.hr/product/jedno-zlatno-ljeto/528463',
            'price_eur' => 20,
            'sale_price_eur' => 16,
            'availability' => 'in_stock',
            'checked_source_hash' => null,
            'check_status' => 'pending',
        ]);
        $this->mock(ZnanjeProductPageClient::class)
            ->shouldReceive('fetch')
            ->once()
            ->with($source->source_url)
            ->andReturn('<html>pouzdana Znanje stranica</html>');
        $this->mock(ZnanjeProductDetailParser::class)
            ->shouldReceive('parse')
            ->once()
            ->with('<html>pouzdana Znanje stranica</html>', $source->source_url)
            ->andReturn([
                'external_id' => '528463',
                'remote_product_id' => 528463,
                'source_url' => $source->source_url,
                'authors' => ['Detaljni Autor', 'Drugi Autor'],
                'isbn' => null,
                'ean' => null,
                'description' => 'Neizmijenjeni hrvatski opis.',
                'source_category' => 'Knjige',
                'source_categories' => ['Knjige'],
                'source_genres' => [],
                'images' => [],
                'price_eur' => 21.5,
                'sale_price_eur' => null,
                'availability' => 'out_of_stock',
            ]);

        $inspected = app(ZnanjeImportService::class)->inspect($source);

        $this->assertSame('new', $inspected->check_status);
        $this->assertSame('Detaljni Autor', $inspected->author);
        $this->assertSame('Neizmijenjeni hrvatski opis.', $inspected->description);
        $this->assertSame(21.5, (float) $inspected->price_eur);
        $this->assertNull($inspected->sale_price_eur);
        $this->assertSame('out_of_stock', $inspected->availability);
    }

    public function test_identifier_match_is_authoritative_over_a_different_title_author_match(): void
    {
        $matchingAuthorId = app(AuthorResolver::class)->resolveName('Ana Marić');
        $identifierProduct = $this->product([
            'author_id' => 0,
            'name' => 'Drugo izdanje',
            'isbn' => '9789531111111',
            'ean' => null,
        ]);
        $this->product([
            'author_id' => $matchingAuthorId,
            'name' => 'Isti naslov',
            'isbn' => '9789532222222',
            'ean' => null,
        ]);
        $source = $this->checkedSource([
            'name' => 'Isti naslov',
            'isbn' => '978-953-111-111-1',
            'ean' => null,
            'author' => 'Ana Marić',
        ]);

        $inspected = app(ZnanjeImportService::class)->inspect($source);

        $this->assertSame('matched', $inspected->check_status);
        $this->assertSame($identifierProduct->id, (int) $inspected->product_id);
    }

    public function test_normalized_title_and_first_author_match_only_when_identifiers_do_not_match(): void
    {
        $authorId = app(AuthorResolver::class)->resolveName('Ana Marić');
        $existing = $this->product([
            'author_id' => $authorId,
            'name' => 'Moja   knjiga',
            'isbn' => null,
            'ean' => null,
        ]);
        $source = $this->checkedSource([
            'name' => '  MOJA knjiga ',
            'isbn' => null,
            'ean' => null,
            'author' => 'Ana Marić, Drugi Autor',
        ]);

        $inspected = app(ZnanjeImportService::class)->inspect($source);

        $this->assertSame('matched', $inspected->check_status);
        $this->assertSame($existing->id, (int) $inspected->product_id);
    }

    public function test_surname_first_detail_author_is_canonicalized_without_creating_a_duplicate(): void
    {
        $this->configuredSettings();
        $authorId = app(AuthorResolver::class)->resolveName('Josephine Bataille');
        $existing = $this->product([
            'author_id' => $authorId,
            'name' => 'Knjiga koja već postoji',
            'isbn' => null,
            'ean' => null,
        ]);
        $matchingSource = $this->checkedSource([
            'name' => 'Knjiga koja već postoji',
            'isbn' => null,
            'ean' => null,
            'author' => 'Bataille, Josephine',
        ]);
        $this->mockFreshDetail($matchingSource, [
            'authors' => ['Bataille, Josephine'],
        ]);

        $matched = app(ZnanjeImportService::class)->import($matchingSource);

        $this->assertSame('skipped', $matched['action']);
        $this->assertSame($existing->id, $matched['product_id']);
        $this->assertSame('Josephine Bataille', $matchingSource->fresh()->author);

        $newSource = $this->checkedSource([
            'name' => 'Nova knjiga istog autora',
            'isbn' => null,
            'ean' => null,
            'author' => 'Bataille, Josephine',
        ]);
        $this->mockFreshDetail($newSource, [
            'authors' => ['Bataille, Josephine'],
        ]);

        $created = app(ZnanjeImportService::class)->import($newSource);
        $createdProduct = Product::query()->findOrFail($created['product_id']);

        $this->assertSame('created', $created['action']);
        $this->assertSame($authorId, (int) $createdProduct->author_id);
        $this->assertSame('Josephine Bataille', (string) optional($createdProduct->author)->title);
        $this->assertSame(1, DB::table('authors')->count());
    }

    public function test_skip_existing_preserves_catalog_data(): void
    {
        $publisherParent = $this->category('Nakladnici');
        $publisherCategory = $this->category('Znanje', $publisherParent);
        $publisherId = $this->publisher('Znanje');
        app(ZnanjeImportSettings::class)->save([
            'publisher_parent_category_id' => $publisherParent,
            'publisher_category_id' => $publisherCategory,
            'publisher_id' => $publisherId,
            'existing_action' => 'skip',
        ]);
        $product = $this->product([
            'name' => 'Postojeća knjiga',
            'isbn' => '9789533333333',
            'price' => 7.5,
            'quantity' => 9,
            'status' => 1,
        ]);
        $source = $this->checkedSource([
            'product_id' => $product->id,
            'name' => 'Postojeća knjiga',
            'isbn' => '9789533333333',
            'price_eur' => 99,
            'availability' => 'Nedostupno',
            'check_status' => 'matched',
        ]);
        $this->mockFreshDetail($source);

        $result = app(ZnanjeImportService::class)->import($source);

        $this->assertSame('skipped', $result['action']);
        $product->refresh();
        $this->assertSame(7.5, (float) $product->price);
        $this->assertSame(9, (int) $product->quantity);
        $this->assertSame(1, (int) $product->status);
    }

    public function test_existing_price_stock_update_revalidates_the_locked_feed_snapshot(): void
    {
        $settings = $this->configuredSettings([
            'existing_action' => 'price_stock',
            'default_quantity' => 3,
        ]);
        $product = $this->product([
            'name' => 'Postojeća utrka',
            'isbn' => '9789533333395',
            'price' => 7.5,
            'quantity' => 9,
        ]);
        $source = $this->checkedSource([
            'product_id' => $product->id,
            'name' => 'Postojeća utrka',
            'isbn' => '9789533333395',
            'price_eur' => 20,
            'check_status' => 'matched',
        ]);
        $this->mockFreshDetail($source);
        $newHash = hash('sha256', 'existing-feed-won-before-lock');
        $this->mock(ZnanjeImportSettings::class)
            ->shouldReceive('all')
            ->once()
            ->andReturnUsing(function () use ($source, $settings, $newHash) {
                ZnanjeImportProduct::query()->whereKey($source->id)->update([
                    'source_hash' => $newHash,
                    'checked_source_hash' => null,
                    'price_eur' => 99,
                    'availability' => 'out_of_stock',
                    'check_status' => 'pending',
                ]);

                return $settings;
            });

        try {
            app(ZnanjeImportService::class)->import($source);
            $this->fail('Promijenjeni feed mora prekinuti postojeći price/stock update.');
        } catch (ZnanjeRetryableException $exception) {
            $this->assertStringContainsString('promijenio se', $exception->getMessage());
        }

        $product->refresh();
        $this->assertSame(7.5, (float) $product->price);
        $this->assertSame(9, (int) $product->quantity);
        $source->refresh();
        $this->assertSame($newHash, $source->source_hash);
        $this->assertNull($source->checked_source_hash);
        $this->assertNull($source->imported_hash);
    }

    public function test_existing_price_stock_update_preserves_an_action_locked_special(): void
    {
        $this->configuredSettings([
            'existing_action' => 'price_stock',
            'default_quantity' => 3,
        ]);
        $product = $this->product([
            'name' => 'Zaključana akcija',
            'isbn' => '9789533333388',
            'price' => 8,
            'special' => 4.25,
            'special_lock' => 1,
            'quantity' => 9,
        ]);
        $source = $this->checkedSource([
            'product_id' => $product->id,
            'name' => 'Zaključana akcija',
            'isbn' => '9789533333388',
            'price_eur' => 20,
            'sale_price_eur' => 15,
            'check_status' => 'matched',
        ]);
        $this->mockFreshDetail($source);

        $result = app(ZnanjeImportService::class)->import($source);

        $this->assertSame('updated', $result['action']);
        $product->refresh();
        $this->assertSame(20.0, (float) $product->price);
        $this->assertSame(4.25, (float) $product->special);
        $this->assertSame(1, (int) $product->special_lock);
        $this->assertSame(3, (int) $product->quantity);
    }

    public function test_unavailable_new_book_is_not_imported_from_a_stale_feed_row(): void
    {
        $source = $this->checkedSource([
            'availability' => 'out_of_stock',
            'check_status' => 'new',
        ]);
        $this->mockFreshDetail($source);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('više nije dostupan za narudžbu');

        try {
            app(ZnanjeImportService::class)->import($source);
        } finally {
            $this->assertSame(0, Product::query()->count());
        }
    }

    public function test_stale_detail_inspection_cannot_overwrite_a_newer_feed_version(): void
    {
        $source = $this->checkedSource([
            'checked_source_hash' => null,
            'check_status' => 'pending',
        ]);
        $oldHash = (string) $source->source_hash;
        $newHash = hash('sha256', 'newer-feed-version');
        $html = '<html>stari detail odgovor</html>';
        $this->mock(ZnanjeProductPageClient::class)
            ->shouldReceive('fetch')->once()->with($source->source_url)->andReturn($html);
        $this->mock(ZnanjeProductDetailParser::class)
            ->shouldReceive('parse')
            ->once()
            ->with($html, $source->source_url)
            ->andReturnUsing(function () use ($source, $newHash) {
                ZnanjeImportProduct::query()->whereKey($source->id)->update([
                    'description' => 'Opis iz novijeg feeda.',
                    'availability' => 'out_of_stock',
                    'source_hash' => $newHash,
                    'checked_source_hash' => null,
                    'check_status' => 'pending',
                ]);

                return $this->detailPayload($source, [
                    'description' => 'Zastarjeli detail opis.',
                    'availability' => 'in_stock',
                ]);
            });

        try {
            app(ZnanjeImportService::class)->inspect($source, true);
            $this->fail('Stari detail rezultat mora izgubiti utrku s novim feedom.');
        } catch (ZnanjeRetryableException $exception) {
            $this->assertStringContainsString('promijenio se', $exception->getMessage());
        }

        $source->refresh();
        $this->assertNotSame($oldHash, $source->source_hash);
        $this->assertSame($newHash, $source->source_hash);
        $this->assertSame('Opis iz novijeg feeda.', $source->description);
        $this->assertSame('out_of_stock', $source->availability);
        $this->assertNull($source->checked_source_hash);
        $this->assertSame('pending', $source->check_status);
    }

    public function test_feed_change_after_inspection_aborts_creation_from_the_locked_row(): void
    {
        $settings = $this->configuredSettings();
        $source = $this->checkedSource(['isbn' => '9789535555555']);
        $this->mockFreshDetail($source);
        $newHash = hash('sha256', 'feed-won-before-lock');
        $this->mock(ZnanjeImportSettings::class)
            ->shouldReceive('all')
            ->once()
            ->andReturnUsing(function () use ($source, $settings, $newHash) {
                ZnanjeImportProduct::query()->whereKey($source->id)->update([
                    'source_hash' => $newHash,
                    'checked_source_hash' => null,
                    'availability' => 'out_of_stock',
                    'check_status' => 'pending',
                ]);

                return $settings;
            });

        try {
            app(ZnanjeImportService::class)->import($source);
            $this->fail('Promijenjeni feed mora prekinuti uvoz prije kreiranja proizvoda.');
        } catch (ZnanjeRetryableException $exception) {
            $this->assertStringContainsString('promijenio se', $exception->getMessage());
        }

        $this->assertSame(0, Product::query()->count());
        $source->refresh();
        $this->assertSame($newHash, $source->source_hash);
        $this->assertNull($source->checked_source_hash);
        $this->assertSame('out_of_stock', $source->availability);
    }

    public function test_new_product_calculation_uses_the_locked_source_values(): void
    {
        $settings = $this->configuredSettings(['markup_percent' => 10]);
        $source = $this->checkedSource([
            'isbn' => '9789536666666',
            'price_eur' => 10,
            'sale_price_eur' => null,
        ]);
        $this->mockFreshDetail($source);
        $this->mock(ZnanjeImportSettings::class)
            ->shouldReceive('all')
            ->once()
            ->andReturnUsing(function () use ($source, $settings) {
                // Simulira konkurentno obogaćivanje istog provjerenog snapshota.
                ZnanjeImportProduct::query()->whereKey($source->id)->update(['price_eur' => 30]);

                return $settings;
            });

        $result = app(ZnanjeImportService::class)->import($source);

        $product = Product::query()->findOrFail($result['product_id']);
        $this->assertSame(33.0, (float) $product->price);
    }

    public function test_conflict_detected_inside_transaction_is_persisted_after_rollback(): void
    {
        $settings = $this->configuredSettings();
        $source = $this->checkedSource(['isbn' => '9789537777777']);
        $this->mockFreshDetail($source);
        $this->mock(ZnanjeImportSettings::class)
            ->shouldReceive('all')
            ->once()
            ->andReturnUsing(function () use ($settings) {
                $this->product(['isbn' => '9789537777777', 'ean' => null]);
                $this->product(['isbn' => '9789537777777', 'ean' => null]);

                return $settings;
            });

        try {
            app(ZnanjeImportService::class)->import($source);
            $this->fail('Višestruko poklapanje mora zaustaviti uvoz.');
        } catch (\RuntimeException $exception) {
            $this->assertNotInstanceOf(ZnanjeRetryableException::class, $exception);
            $this->assertStringContainsString('odgovara na više Zuzi artikala', $exception->getMessage());
        }

        $this->assertSame(2, Product::query()->count());
        $source->refresh();
        $this->assertNull($source->product_id);
        $this->assertSame('conflict', $source->check_status);
        $this->assertStringContainsString('odgovara na više Zuzi artikala', $source->check_message);
    }

    private function checkedSource(array $overrides = []): ZnanjeImportProduct
    {
        $externalId = (string) ($overrides['external_id'] ?? random_int(100000, 999999));
        $remoteProductId = (int) ($overrides['remote_product_id']
            ?? (ctype_digit($externalId) ? $externalId : random_int(100000, 999999)));
        $hash = hash('sha256', 'znanje-source-' . Str::uuid());

        return ZnanjeImportProduct::query()->create(array_merge([
            'external_id' => $externalId,
            'remote_product_id' => $remoteProductId,
            'name' => 'Znanje knjiga',
            'description' => 'Opis knjige.',
            'source_category' => 'Knjige',
            'source_categories' => ['Knjige'],
            'source_publisher' => 'Znanje',
            'source_url' => 'https://znanje.hr/product/znanje-knjiga/' . $remoteProductId,
            'image_url' => null,
            'additional_image_urls' => [],
            'price_eur' => 10,
            'sale_price_eur' => null,
            'availability' => 'Dostupno',
            'isbn' => null,
            'ean' => null,
            'author' => 'Autor Knjige',
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

    private function mockFreshDetail(ZnanjeImportProduct $source, array $overrides = []): void
    {
        $html = '<html>pouzdana Znanje stranica ' . $source->external_id . '</html>';
        $this->mock(ZnanjeProductPageClient::class)
            ->shouldReceive('fetch')
            ->once()
            ->with($source->source_url)
            ->andReturn($html);
        $this->mock(ZnanjeProductDetailParser::class)
            ->shouldReceive('parse')
            ->once()
            ->with($html, $source->source_url)
            ->andReturn($this->detailPayload($source, $overrides));
    }

    private function detailPayload(ZnanjeImportProduct $source, array $overrides = []): array
    {
        return array_merge([
            'external_id' => (string) $source->external_id,
            'remote_product_id' => (int) $source->remote_product_id,
            'source_url' => $source->source_url,
            'authors' => $source->author ? [(string) $source->author] : [],
            'isbn' => $source->isbn,
            'ean' => $source->ean,
            'description' => $source->description,
            'source_category' => $source->source_category,
            'source_categories' => (array) $source->source_categories,
            'source_publisher' => $source->source_publisher,
            'source_genres' => (array) $source->source_genres,
            'images' => [],
            'price_eur' => $source->price_eur,
            'sale_price_eur' => $source->sale_price_eur,
            'availability' => $source->availability,
            'format' => $source->format,
            'pages' => $source->pages,
            'letter' => $source->letter,
            'binding' => $source->binding,
            'publication_year' => $source->publication_year,
            'language' => $source->language,
            'origin' => $source->origin,
        ], $overrides);
    }

    private function configuredSettings(array $overrides = []): array
    {
        $publisherParent = $this->category('Nakladnici');
        $publisherCategory = $this->category('Znanje', $publisherParent);
        $publisherId = $this->publisher('Znanje');
        app(ZnanjeImportSettings::class)->save(array_merge([
            'markup_percent' => 0,
            'publisher_parent_category_id' => $publisherParent,
            'publisher_category_id' => $publisherCategory,
            'publisher_id' => $publisherId,
            'map_source_publishers' => 0,
            'category_map' => [],
            'default_quantity' => 1,
            'activate_new_products' => 0,
            'existing_action' => 'skip',
        ], $overrides));

        return app(ZnanjeImportSettings::class)->all();
    }

    private function product(array $overrides): Product
    {
        $id = DB::table('products')->insertGetId(array_merge([
            'author_id' => 0,
            'name' => 'Proizvod ' . Str::uuid(),
            'sku' => (string) random_int(100000, 999999),
            'itemid' => random_int(100000, 999999),
            'isbn' => null,
            'ean' => null,
            'slug' => 'proizvod-' . Str::uuid(),
            'url' => '/',
            'price' => 10,
            'quantity' => 1,
            'tax_id' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return Product::query()->findOrFail($id);
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
