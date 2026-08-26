<?php

namespace Tests\Feature;

use App\Models\Back\Catalog\NovellaImportProduct;
use App\Services\Novella\NovellaFeedSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class NovellaFeedSynchronizerTest extends TestCase
{
    use RefreshDatabase;

    private string $snapshotPath;

    private string $metadataPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->snapshotPath = storage_path('framework/testing/novella-products.json');
        $this->metadataPath = storage_path('framework/testing/novella-products.meta.json');
        File::delete([$this->snapshotPath, $this->metadataPath]);
        config([
            'novella_import.snapshot_path' => $this->snapshotPath,
            'novella_import.metadata_path' => $this->metadataPath,
            'novella_import.minimum_expected_books' => 1,
            'novella_import.minimum_current_ratio' => 0.1,
        ]);
    }

    protected function tearDown(): void
    {
        File::delete([$this->snapshotPath, $this->metadataPath]);

        parent::tearDown();
    }

    public function test_refresh_is_incremental_atomic_and_keeps_feed_categories(): void
    {
        $first = $this->book(22905, 'Agentica', '1890');
        $second = $this->book(22906, 'Druga knjiga', '1490');
        $changed = $this->book(22905, 'Agentica', '1990');

        Http::fake([
            'https://novella.hr/wp-json/wc/store/v1/products*' => Http::sequence()
                ->push([$first, $second], 200, [
                    'X-WP-Total' => '2',
                    'X-WP-TotalPages' => '1',
                ])
                ->push([$changed], 200, [
                    'X-WP-Total' => '1',
                    'X-WP-TotalPages' => '1',
                ]),
        ]);

        $firstResult = app(NovellaFeedSynchronizer::class)->refresh();
        $this->assertSame(2, $firstResult['current']);
        $source = NovellaImportProduct::query()->where('external_id', '22905')->firstOrFail();
        $firstHash = $source->source_hash;
        $this->assertSame(['Knjige', 'Književnost'], $source->source_categories);
        $this->assertSame(['Književnost'], $source->source_genres);
        $this->assertSame('9789534002261', $source->isbn);
        $this->assertSame('Katherine Center', $source->author);
        $this->assertSame(18.9, $source->price_eur);
        $this->assertSame('Opis knjige.', $source->detail_payload['_novella_feed']['description']);
        $this->assertFileExists($this->snapshotPath);
        $source->update([
            'description' => 'Potpuni opis sa stranice.',
            'source_publisher' => 'Detaljni nakladnik',
            'author' => 'Detaljni autor',
            'image_url' => 'https://novella.hr/wp-content/uploads/detail.jpg',
            'isbn' => '9789534002999',
            'ean' => '9789534002999',
        ]);

        $secondResult = app(NovellaFeedSynchronizer::class)->refresh();
        $this->assertSame(1, $secondResult['current']);
        $this->assertSame(1, $secondResult['retired']);
        $source->refresh();
        $this->assertTrue($source->is_current);
        $this->assertSame(19.9, $source->price_eur);
        $this->assertNotSame($firstHash, $source->source_hash);
        $this->assertSame('Potpuni opis sa stranice.', $source->description);
        $this->assertSame('Detaljni nakladnik', $source->source_publisher);
        $this->assertSame('Detaljni autor', $source->author);
        $this->assertSame('https://novella.hr/wp-content/uploads/detail.jpg', $source->image_url);
        $this->assertSame('9789534002999', $source->isbn);
        $this->assertFalse(
            NovellaImportProduct::query()->where('external_id', '22906')->firstOrFail()->is_current
        );
    }

    public function test_refresh_applies_changed_feed_values_without_overwriting_detail_enrichment(): void
    {
        $first = $this->book(22905, 'Agentica', '1890');
        $changed = $this->book(22905, 'Agentica', '1990');
        $changed['sku'] = '9789534002995';
        $changed['description'] = '<p>Novi opis iz feeda.</p>';
        $changed['images'] = [[
            'src' => 'https://novella.hr/wp-content/uploads/new-feed.jpg',
        ]];
        $changed['tags'] = [[
            'name' => 'Novi autor iz feeda',
            'link' => 'https://novella.hr/autor/novi-autor-iz-feeda/',
        ]];
        $changed['attributes'] = [[
            'name' => 'Izdavač',
            'terms' => [['name' => 'Novi feed izdavač']],
        ]];

        Http::fake([
            'https://novella.hr/wp-json/wc/store/v1/products*' => Http::sequence()
                ->push([$first], 200, [
                    'X-WP-Total' => '1',
                    'X-WP-TotalPages' => '1',
                ])
                ->push([$changed], 200, [
                    'X-WP-Total' => '1',
                    'X-WP-TotalPages' => '1',
                ]),
        ]);

        app(NovellaFeedSynchronizer::class)->refresh();
        $source = NovellaImportProduct::query()->where('external_id', '22905')->firstOrFail();
        $source->update([
            'description' => 'Potpuni opis sa stranice.',
            'source_publisher' => 'Detaljni nakladnik',
            'author' => 'Detaljni autor',
            'image_url' => 'https://novella.hr/wp-content/uploads/detail.jpg',
            'additional_image_urls' => ['https://novella.hr/wp-content/uploads/detail-2.jpg'],
            'isbn' => '9789534002777',
            'ean' => '9789534002777',
            'checked_source_hash' => $source->source_hash,
            'checked_at' => now(),
            'check_status' => 'new',
        ]);

        app(NovellaFeedSynchronizer::class)->refresh();
        $source->refresh();

        $this->assertSame('9789534002995', $source->sku);
        $this->assertSame('Potpuni opis sa stranice.', $source->description);
        $this->assertSame('Detaljni nakladnik', $source->source_publisher);
        $this->assertSame('Detaljni autor', $source->author);
        $this->assertSame('https://novella.hr/wp-content/uploads/detail.jpg', $source->image_url);
        $this->assertSame(
            ['https://novella.hr/wp-content/uploads/detail-2.jpg'],
            $source->additional_image_urls
        );
        $this->assertSame('9789534002777', $source->isbn);
        $this->assertSame('9789534002777', $source->ean);
        $this->assertSame('pending', $source->ui_status);
        $this->assertSame('Novi opis iz feeda.', $source->detail_payload['_novella_feed']['description']);
        $this->assertSame('Novi feed izdavač', $source->detail_payload['_novella_feed']['source_publisher']);
        $this->assertSame('Novi autor iz feeda', $source->detail_payload['_novella_feed']['author']);
        $this->assertSame('9789534002995', $source->detail_payload['_novella_feed']['isbn']);
        $this->assertSame('9789534002995', $source->detail_payload['_novella_feed']['ean']);
        $this->assertSame(
            'https://novella.hr/wp-content/uploads/new-feed.jpg',
            $source->detail_payload['_novella_feed']['image_url']
        );
    }

    public function test_refresh_aborts_if_total_changes_between_pages(): void
    {
        $existing = $this->existingSource();
        config(['novella_import.per_page' => 1]);
        Http::fake([
            'https://novella.hr/wp-json/wc/store/v1/products*' => Http::sequence()
                ->push([$this->book(22905, 'Agentica', '1890')], 200, [
                    'X-WP-Total' => '2',
                    'X-WP-TotalPages' => '2',
                ])
                ->push([$this->book(22906, 'Druga knjiga', '1490')], 200, [
                    'X-WP-Total' => '3',
                    'X-WP-TotalPages' => '3',
                ]),
        ]);

        try {
            app(NovellaFeedSynchronizer::class)->refresh();
            $this->fail('Expected an unstable feed to abort.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('promijenio se tijekom osvježavanja', $exception->getMessage());
        }

        $existing->refresh();
        $this->assertTrue($existing->is_current);
        $this->assertSame('old-hash', $existing->source_hash);
        $this->assertSame(1, NovellaImportProduct::query()->count());
    }

    public function test_refresh_aborts_on_cross_page_duplicate_without_retiring_live_rows(): void
    {
        $existing = $this->existingSource();
        config(['novella_import.per_page' => 1]);
        $duplicate = $this->book(22905, 'Agentica', '1890');
        Http::fake([
            'https://novella.hr/wp-json/wc/store/v1/products*' => Http::sequence()
                ->push([$duplicate], 200, [
                    'X-WP-Total' => '2',
                    'X-WP-TotalPages' => '2',
                ])
                ->push([$duplicate], 200, [
                    'X-WP-Total' => '2',
                    'X-WP-TotalPages' => '2',
                ]),
        ]);

        try {
            app(NovellaFeedSynchronizer::class)->refresh();
            $this->fail('Expected duplicate cross-page IDs to abort.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('nisu jedinstveni', $exception->getMessage());
        }

        $existing->refresh();
        $this->assertTrue($existing->is_current);
        $this->assertSame('old-hash', $existing->source_hash);
        $this->assertSame(1, NovellaImportProduct::query()->count());
    }

    private function existingSource(): NovellaImportProduct
    {
        return NovellaImportProduct::query()->create([
            'external_id' => 'old-1',
            'remote_product_id' => 100,
            'name' => 'Postojeća knjiga',
            'source_url' => 'https://novella.hr/proizvod/postojeca-knjiga/',
            'price_eur' => 10,
            'source_hash' => 'old-hash',
            'feed_token' => (string) Str::uuid(),
            'is_current' => true,
            'last_seen_at' => now(),
        ]);
    }

    private function book(int $id, string $name, string $price): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'type' => 'simple',
            'permalink' => 'https://novella.hr/proizvod/' . Str::slug($name) . '/',
            'sku' => '9789534002261',
            'description' => '<p>Opis knjige.</p>',
            'short_description' => '<p>Kratko.</p>',
            'on_sale' => false,
            'prices' => [
                'price' => $price,
                'regular_price' => $price,
                'sale_price' => $price,
                'currency_code' => 'EUR',
                'currency_minor_unit' => 2,
            ],
            'images' => [[
                'src' => 'https://novella.hr/wp-content/uploads/' . $id . '.jpg',
            ]],
            'categories' => [
                [
                    'id' => 63,
                    'name' => 'Knjige',
                    'link' => 'https://novella.hr/kategorija-proizvoda/knjige/',
                ],
                [
                    'id' => 101,
                    'name' => 'Književnost',
                    'link' => 'https://novella.hr/kategorija-proizvoda/knjige/knjizevnost/',
                ],
            ],
            'tags' => [[
                'name' => 'Katherine Center',
                'link' => 'https://novella.hr/autor/katherine-center/',
            ]],
            'attributes' => [[
                'name' => 'Izdavač',
                'terms' => [['name' => 'Novella']],
            ]],
            'is_in_stock' => true,
            'is_on_backorder' => false,
        ];
    }
}
