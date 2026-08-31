<?php

namespace Tests\Feature;

use App\Models\Back\Catalog\ZnanjeImportProduct;
use App\Services\Znanje\ZnanjeFeedSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ZnanjeFeedSynchronizerTest extends TestCase
{
    use RefreshDatabase;

    private string $snapshotPath;

    private string $metadataPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->snapshotPath = storage_path('framework/testing/znanje-products.json');
        $this->metadataPath = storage_path('framework/testing/znanje-products.meta.json');
        File::delete([$this->snapshotPath, $this->metadataPath]);
        config([
            'cache.default' => 'array',
            'znanje_import.snapshot_path' => $this->snapshotPath,
            'znanje_import.metadata_path' => $this->metadataPath,
            'znanje_import.minimum_expected_books' => 1,
            'znanje_import.minimum_current_ratio' => 0.1,
            'znanje_import.request_delay_ms' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        File::delete([$this->snapshotPath, $this->metadataPath]);
        parent::tearDown();
    }

    public function test_a_second_refresh_cannot_race_the_active_sync(): void
    {
        $lock = Cache::lock('znanje-import-feed-sync', 60);
        $this->assertTrue($lock->get());

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('već je u tijeku');
            app(ZnanjeFeedSynchronizer::class)->refresh();
        } finally {
            $lock->release();
        }
    }

    public function test_batch_session_is_resumable_and_finalization_is_idempotent(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push($this->listing('Knjige', 100, 'Prva', 10), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Strane knjige', 200, 'Second', 20), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Knjige', 100, 'Prva', 10), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Strane knjige', 200, 'Second', 20), 200, ['Content-Type' => 'text/html'])]);
        $synchronizer = app(ZnanjeFeedSynchronizer::class);

        $started = $synchronizer->start();
        $this->assertSame('crawling', $started['phase']);
        $this->assertSame(0, $started['processed_pages']);
        $this->assertSame(0, $started['total_pages']);
        $this->assertSame('Knjige', $started['root']);
        $this->assertSame(1, $started['current_page']);

        $firstStep = $synchronizer->step($started['token'], 1);
        $this->assertSame(1, $firstStep['processed_pages']);
        $this->assertSame(1, $firstStep['total_pages']);
        $this->assertSame(1, $firstStep['staged']);
        $this->assertSame('Strane knjige', $firstStep['root']);
        $this->assertFalse($firstStep['ready_to_finalize']);
        $this->assertSame($firstStep, $synchronizer->status($started['token']));
        $resumed = $synchronizer->start();
        $this->assertSame($started['token'], $resumed['token']);
        $this->assertSame(1, $resumed['processed_pages']);
        $this->assertSame(0, ZnanjeImportProduct::query()->count());

        $secondStep = $synchronizer->step($started['token'], 1);
        $this->assertSame('validating', $secondStep['phase']);
        $this->assertSame(2, $secondStep['processed_pages']);
        $this->assertSame(2, $secondStep['total_pages']);
        $this->assertSame(2, $secondStep['staged']);
        $this->assertFalse($secondStep['ready_to_finalize']);
        $this->assertNull($secondStep['root']);
        $this->assertNull($secondStep['current_page']);

        $firstValidation = $synchronizer->step($started['token'], 1);
        $this->assertSame('validating', $firstValidation['phase']);
        $this->assertFalse($firstValidation['ready_to_finalize']);
        Http::assertSentCount(3);

        $secondValidation = $synchronizer->step($started['token'], 1);
        $this->assertSame('validation_complete', $secondValidation['phase']);
        $this->assertFalse($secondValidation['ready_to_finalize']);
        Http::assertSentCount(4);

        // No remote request is made while the next short step promotes the
        // session to the locally-finalizable phase.
        $ready = $synchronizer->step($started['token'], 1);
        $this->assertSame('ready_to_finalize', $ready['phase']);
        $this->assertTrue($ready['ready_to_finalize']);
        Http::assertSentCount(4);

        $finalizingState = Cache::get('znanje-import-feed-state:' . $started['token']);
        $finalizingState['phase'] = 'finalizing';
        Cache::put('znanje-import-feed-state:' . $started['token'], $finalizingState, 3600);
        $resumedFinalize = $synchronizer->step($started['token'], 1);
        $this->assertSame('finalizing', $resumedFinalize['phase']);
        $this->assertTrue($resumedFinalize['ready_to_finalize']);

        $result = $synchronizer->finalize($started['token']);
        Http::assertSentCount(4);
        $this->assertSame(2, $result['current']);
        $this->assertSame(0, $result['retired_now']);
        $completed = $synchronizer->status($started['token']);
        $this->assertTrue($completed['completed']);
        $this->assertSame('complete', $completed['phase']);
        $completedStep = $synchronizer->step($started['token'], 1);
        $this->assertTrue($completedStep['completed']);
        $this->assertSame($result, $completedStep['result']);
        $this->assertSame($result, $synchronizer->finalize($started['token']));
        $this->assertDatabaseCount('znanje_import_feed_rows', 0);
    }

    public function test_step_lock_always_outlives_the_bounded_remote_work(): void
    {
        config([
            'znanje_import.feed_request_timeout' => 999,
            'znanje_import.feed_request_attempts' => 999,
            'znanje_import.feed_retry_delay_ms' => 999999,
            'znanje_import.feed_step_lock_seconds' => 1,
            'znanje_import.request_delay_ms' => 999999,
        ]);
        $synchronizer = app(ZnanjeFeedSynchronizer::class);
        $method = new \ReflectionMethod($synchronizer, 'stepLockSeconds');
        $method->setAccessible(true);

        // Hard caps yield at most 45 s of requests, 2 s of retry delays and
        // 5 s of throttling; the mutex includes another 30 s safety margin.
        $this->assertGreaterThanOrEqual(82, $method->invoke($synchronizer, 1));
    }

    public function test_refresh_is_incremental_atomic_and_writes_a_normalized_snapshot(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push($this->listing('Knjige', 100, 'Prva', 10.90), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Strane knjige', 200, 'Second', 20.90), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Knjige', 100, 'Prva', 10.90), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Strane knjige', 200, 'Second', 20.90), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Knjige', 100, 'Prva', 11.90), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Strane knjige', 201, 'Third', 21.90), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Knjige', 100, 'Prva', 11.90), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Strane knjige', 201, 'Third', 21.90), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Knjige', 100, 'Prva', 12.90), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Strane knjige', 201, 'Third', 21.90), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Knjige', 100, 'Prva', 12.90), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Strane knjige', 201, 'Third', 21.90), 200, ['Content-Type' => 'text/html'])]);

        $first = app(ZnanjeFeedSynchronizer::class)->refresh();
        $this->assertSame(2, $first['current']);
        $source = ZnanjeImportProduct::query()->where('external_id', '100')->firstOrFail();
        $sourceId = $source->id;
        $this->assertSame(100, $source->feed_position);
        $this->assertSame(['Knjige', 'Književnost'], $source->source_categories);
        $this->assertSame(['Književnost'], $source->source_genres);
        $this->assertSame('Nakladnik', $source->detail_payload['_znanje_feed']['source_publisher']);
        $this->assertFileExists($this->snapshotPath);
        $snapshot = json_decode((string) file_get_contents($this->snapshotPath), true);
        $this->assertSame(2, $snapshot['count']);
        $this->assertSame(['Knjige' => 1, 'Strane knjige' => 1], $snapshot['root_totals']);
        $metadata = json_decode((string) file_get_contents($this->metadataPath), true);
        $this->assertSame(filesize($this->snapshotPath), $metadata['bytes']);
        $this->assertSame(hash_file('sha256', $this->snapshotPath), $metadata['sha256']);
        $source->update([
            'format' => '150 x 228 mm',
            'pages' => 380,
            'author' => 'Detaljni autor',
            'checked_at' => now(),
        ]);

        $second = app(ZnanjeFeedSynchronizer::class)->refresh();
        $this->assertSame(3, $second['current']);
        $this->assertSame(0, $second['retired']);
        $this->assertSame(0, $second['retired_now']);
        $source->refresh();
        $this->assertSame($sourceId, $source->id);
        $this->assertSame(11.9, $source->price_eur);
        $this->assertTrue($source->is_current);
        $this->assertSame('150 x 228 mm', $source->format);
        $this->assertSame(380, $source->pages);
        $this->assertSame('Detaljni autor', $source->author);
        $this->assertTrue(
            ZnanjeImportProduct::query()->where('external_id', '200')->firstOrFail()->is_current
        );
        $this->assertTrue(
            ZnanjeImportProduct::query()->where('external_id', '201')->firstOrFail()->is_current
        );

        $third = app(ZnanjeFeedSynchronizer::class)->refresh();
        $this->assertSame(2, $third['current']);
        $this->assertSame(1, $third['retired']);
        $this->assertSame(1, $third['retired_now']);
        $this->assertFalse(
            ZnanjeImportProduct::query()->where('external_id', '200')->firstOrFail()->is_current
        );
    }

    public function test_single_root_session_persists_scope_and_uses_partial_sanity(): void
    {
        config(['znanje_import.minimum_expected_books' => 20000]);
        Http::fake(['*' => Http::sequence()
            ->push($this->listing('Strane knjige', 5001, 'Foreign', 20), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Strane knjige', 5001, 'Foreign', 20), 200, ['Content-Type' => 'text/html'])]);
        $synchronizer = app(ZnanjeFeedSynchronizer::class);

        $progress = $synchronizer->start(505);
        $this->assertSame(505, $progress['selected_root_id']);
        $this->assertSame('Strane knjige (engleske)', $progress['selected_root_label']);
        $this->assertSame('Strane knjige', $progress['root']);
        do {
            $progress = $synchronizer->step($progress['token'], 10);
        } while (! $progress['ready_to_finalize']);
        $result = $synchronizer->finalize($progress['token']);

        $this->assertSame(505, $result['selected_root_id']);
        $this->assertSame(1, $result['current']);
        $this->assertSame(['Knjige' => 0, 'Strane knjige' => 1], $result['root_totals']);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/strane-knjige/505'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/knjige/500'));
    }

    public function test_partial_refresh_retires_only_its_root_and_snapshots_all_current_books(): void
    {
        Http::fake(['*' => Http::sequence()
            // Initial complete catalogue.
            ->push($this->listing('Knjige', 100, 'Prva', 10), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Strane knjige', 200, 'Foreign', 20), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Knjige', 100, 'Prva', 10), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Strane knjige', 200, 'Foreign', 20), 200, ['Content-Type' => 'text/html'])
            // First selected-root miss keeps 100 current.
            ->push($this->listing('Knjige', 101, 'Nova', 11), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Knjige', 101, 'Nova', 11), 200, ['Content-Type' => 'text/html'])
            // Second selected-root miss retires 100, never foreign 200.
            ->push($this->listing('Knjige', 101, 'Nova', 12), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Knjige', 101, 'Nova', 12), 200, ['Content-Type' => 'text/html'])]);
        $synchronizer = app(ZnanjeFeedSynchronizer::class);

        $synchronizer->refresh();
        $firstPartial = $synchronizer->refresh(500);
        $this->assertSame(3, $firstPartial['current']);
        $this->assertTrue(ZnanjeImportProduct::query()->where('external_id', '100')->firstOrFail()->is_current);
        $this->assertTrue(ZnanjeImportProduct::query()->where('external_id', '200')->firstOrFail()->is_current);
        $firstSnapshot = json_decode((string) file_get_contents($this->snapshotPath), true);
        $this->assertSame(3, $firstSnapshot['count']);
        $this->assertSame(['Knjige' => 2, 'Strane knjige' => 1], $firstSnapshot['root_totals']);

        $secondPartial = $synchronizer->refresh(500);

        $this->assertSame(500, $secondPartial['selected_root_id']);
        $this->assertSame(2, $secondPartial['current']);
        $this->assertSame(1, $secondPartial['retired_now']);
        $this->assertFalse(ZnanjeImportProduct::query()->where('external_id', '100')->firstOrFail()->is_current);
        $this->assertTrue(ZnanjeImportProduct::query()->where('external_id', '200')->firstOrFail()->is_current);
        $snapshot = json_decode((string) file_get_contents($this->snapshotPath), true);
        $metadata = json_decode((string) file_get_contents($this->metadataPath), true);
        $this->assertSame(2, $snapshot['count']);
        $this->assertCount(2, $snapshot['items']);
        $this->assertSame(['Knjige' => 1, 'Strane knjige' => 1], $snapshot['root_totals']);
        $this->assertSame(2, $metadata['count']);
        $this->assertSame($snapshot['root_totals'], $metadata['root_totals']);
    }

    public function test_cross_root_duplicate_is_deduplicated_without_retiring_a_first_miss(): void
    {
        $existing = ZnanjeImportProduct::query()->create([
            'external_id' => '999',
            'remote_product_id' => 999,
            'feed_position' => 999,
            'name' => 'Postojeća',
            'source_category' => 'Knjige',
            'source_url' => 'https://znanje.hr/product/postojeca/999',
            'price_eur' => 9,
            'availability' => 'in_stock',
            'source_hash' => hash('sha256', 'old'),
            'feed_token' => (string) Str::uuid(),
            'is_current' => true,
        ]);
        Http::fake(['*' => Http::sequence()
            ->push($this->listing('Knjige', 100, 'Prva', 10), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Strane knjige', 100, 'Duplicate', 20), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Knjige', 100, 'Prva', 10), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Strane knjige', 100, 'Duplicate', 20), 200, ['Content-Type' => 'text/html'])]);

        $result = app(ZnanjeFeedSynchronizer::class)->refresh();

        $existing->refresh();
        $this->assertTrue($existing->is_current);
        $this->assertSame(1, $result['duplicates']);
        $this->assertSame(1, $result['staged']);
        $this->assertSame(1, $result['reconciliation_gap']);
        $this->assertSame(2, ZnanjeImportProduct::query()->count());
        $this->assertDatabaseCount('znanje_import_feed_rows', 0);
    }

    public function test_small_total_and_first_page_drift_is_reconciled_before_activation(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push($this->listing('Knjige', 100, 'Prva', 10), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Strane knjige', 200, 'Second', 20), 200, ['Content-Type' => 'text/html'])
            ->push($this->listingMany('Knjige', [
                [101, 'Nova tijekom uvoza', 11],
                [100, 'Prva', 10],
            ]), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Strane knjige', 200, 'Second', 20), 200, ['Content-Type' => 'text/html'])]);

        $result = app(ZnanjeFeedSynchronizer::class)->refresh();

        $this->assertSame(3, $result['staged']);
        $this->assertSame(3, $result['current']);
        $this->assertSame(0, $result['reconciliation_gap']);
        $this->assertSame(1, $result['drift']['Knjige']['delta']);
        $this->assertSame(2, $result['root_totals']['Knjige']);
        $this->assertDatabaseHas('znanje_import_products', [
            'external_id' => '101',
            'is_current' => true,
        ]);
    }

    public function test_one_invalid_price_is_skipped_without_aborting_the_selected_root_refresh(): void
    {
        $listing = $this->listingMany('Knjige', [
            [303701, 'Valjana knjiga', 19.90],
            [303702, 'Knjiga bez cijene', 0],
        ]);
        Http::fake(['*' => Http::sequence()
            ->push($listing, 200, ['Content-Type' => 'text/html'])
            ->push($listing, 200, ['Content-Type' => 'text/html'])]);

        $result = app(ZnanjeFeedSynchronizer::class)->refresh(500);

        $this->assertSame(1, $result['staged']);
        $this->assertSame(1, $result['current']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(0, $result['reconciliation_gap']);
        $this->assertDatabaseHas('znanje_import_products', ['external_id' => '303701']);
        $this->assertDatabaseMissing('znanje_import_products', ['external_id' => '303702']);
    }

    public function test_remote_identity_conflict_rolls_back_without_retiring_live_rows(): void
    {
        $existing = ZnanjeImportProduct::query()->create([
            'external_id' => 'legacy-100',
            'remote_product_id' => 100,
            'feed_position' => 100,
            'name' => 'Postojeća',
            'source_category' => 'Knjige',
            'source_url' => 'https://znanje.hr/product/postojeca/100',
            'price_eur' => 9,
            'availability' => 'in_stock',
            'source_hash' => hash('sha256', 'old'),
            'feed_token' => (string) Str::uuid(),
            'is_current' => true,
        ]);
        Http::fake(['*' => Http::sequence()
            ->push($this->listing('Knjige', 100, 'Prva', 10), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Strane knjige', 200, 'Second', 20), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Knjige', 100, 'Prva', 10), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Strane knjige', 200, 'Second', 20), 200, ['Content-Type' => 'text/html'])]);

        try {
            app(ZnanjeFeedSynchronizer::class)->refresh();
            $this->fail('Expected identity conflict.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Znanje ID 100', $exception->getMessage());
        }

        $existing->refresh();
        $this->assertTrue($existing->is_current);
        $this->assertSame('Postojeća', $existing->name);
        $this->assertSame(1, ZnanjeImportProduct::query()->count());
        $this->assertDatabaseCount('znanje_import_feed_rows', 0);
    }

    public function test_reappearance_resets_the_two_consecutive_miss_guard(): void
    {
        Http::fake(['*' => Http::sequence()
            // Initial feed: both titles are present.
            ->push($this->listing('Knjige', 100, 'Prva', 10), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Strane knjige', 200, 'Second', 20), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Knjige', 100, 'Prva', 10), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Strane knjige', 200, 'Second', 20), 200, ['Content-Type' => 'text/html'])
            // First miss: 200 remains current.
            ->push($this->listing('Knjige', 100, 'Prva', 10), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Strane knjige', 201, 'Third', 21), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Knjige', 100, 'Prva', 10), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Strane knjige', 201, 'Third', 21), 200, ['Content-Type' => 'text/html'])
            // Reappearance resets its feed token.
            ->push($this->listing('Knjige', 100, 'Prva', 10), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Strane knjige', 200, 'Second', 20), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Knjige', 100, 'Prva', 10), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Strane knjige', 200, 'Second', 20), 200, ['Content-Type' => 'text/html'])
            // A new first miss must keep it current again.
            ->push($this->listing('Knjige', 100, 'Prva', 10), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Strane knjige', 201, 'Third', 21), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Knjige', 100, 'Prva', 10), 200, ['Content-Type' => 'text/html'])
            ->push($this->listing('Strane knjige', 201, 'Third', 21), 200, ['Content-Type' => 'text/html'])]);

        $synchronizer = app(ZnanjeFeedSynchronizer::class);
        $synchronizer->refresh();
        $synchronizer->refresh();
        $this->assertTrue(ZnanjeImportProduct::query()->where('external_id', '200')->firstOrFail()->is_current);
        $synchronizer->refresh();
        $this->assertTrue(ZnanjeImportProduct::query()->where('external_id', '200')->firstOrFail()->is_current);
        $fourth = $synchronizer->refresh();

        $this->assertSame(0, $fourth['retired_now']);
        $this->assertTrue(ZnanjeImportProduct::query()->where('external_id', '200')->firstOrFail()->is_current);
    }

    private function listing(string $root, int $id, string $name, float $price): string
    {
        return $this->listingMany($root, [[$id, $name, $price]]);
    }

    private function listingMany(string $root, array $items): string
    {
        $category = $root === 'Knjige' ? 'Književnost' : 'Fiction';
        $cards = '';
        foreach ($items as [$id, $name, $price]) {
            $event = "sendFullEventForClick('select_item', 0, '{$id}', '{$name}', "
                . number_format($price, 2, '.', '')
                . ", 0.00, 'Nakladnik', '{$root}', '{$category}', '', '', '0.00%', "
                . "'Autor', '2026', 'Hrvatski', 'meki uvez', '', '', 1);";
            $cards .= '<div class="grid-item"><div class="product-card">'
                . '<a class="product-thumb" href="/product/' . Str::slug($name) . '/' . $id . '" onclick="'
                . htmlspecialchars($event, ENT_QUOTES) . '">'
                . '<img src="https://znanje.hr/product-images/' . $id . '.jpg"></a>'
                . '<p class="product-author">Autor</p><h3 class="product-title">' . htmlspecialchars($name) . '</h3>'
                . '<h4 class="product-price">' . number_format($price, 2, ',', '.') . ' €</h4>'
                . '<div class="product-buttons"><button type="button">U košaricu</button></div>'
                . '</div></div>';
        }

        return '<html><body>'
            . '<select id="sorting"><option value="date|desc" selected>Novi</option></select>'
            . '<select id="numberOfProducts"><option value="84" selected>84</option></select>'
            . '<input id="showAvailableOnly" checked>'
            . '<span itemprop="numberOfItems">' . count($items) . '</span>'
            . $cards . '</body></html>';
    }
}
