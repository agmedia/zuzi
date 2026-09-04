<?php

namespace Tests\Feature;

use App\Http\Controllers\Back\Catalog\DelfiImportController;
use App\Models\Back\Catalog\NovellaImportProduct;
use App\Models\Back\Catalog\ZnanjeImportProduct;
use App\Services\Delfi\DelfiFeedSynchronizer;
use App\Services\Delfi\DelfiImportService;
use App\Services\Delfi\DelfiImportedProductStockSynchronizer;
use App\Services\Novella\NovellaFeedSynchronizer;
use App\Services\Novella\NovellaImportService;
use App\Services\Znanje\ZnanjeFeedSynchronizer;
use App\Services\Znanje\ZnanjeImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class RefreshAndInspectCatalogImportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_nightly_command_refreshes_and_inspects_all_three_sources(): void
    {
        $novella = $this->pendingNovella();
        $znanje = $this->pendingZnanje();

        $this->mock(NovellaFeedSynchronizer::class)
            ->shouldReceive('refresh')
            ->once()
            ->ordered()
            ->andReturn(['current' => 1]);
        $this->mock(NovellaImportService::class)
            ->shouldReceive('inspect')
            ->once()
            ->ordered()
            ->andReturnUsing(fn (NovellaImportProduct $source) => $this->markChecked($source));

        $this->mock(ZnanjeFeedSynchronizer::class)
            ->shouldReceive('refresh')
            ->once()
            ->ordered()
            ->andReturn(['current' => 1]);
        $this->mock(ZnanjeImportService::class)
            ->shouldReceive('inspect')
            ->once()
            ->ordered()
            ->andReturnUsing(fn (ZnanjeImportProduct $source) => $this->markChecked($source));

        $this->mock(DelfiFeedSynchronizer::class)
            ->shouldReceive('refresh')
            ->once()
            ->ordered()
            ->andReturn(['current' => 1]);
        $this->mock(DelfiImportedProductStockSynchronizer::class)
            ->shouldReceive('sync')
            ->once()
            ->ordered()
            ->andReturn(['zeroed' => 1, 'restored' => 2, 'default_quantity' => 5]);
        $delfiImport = $this->mock(DelfiImportService::class);
        $this->mock(DelfiImportController::class)
            ->shouldReceive('bulkInspect')
            ->once()
            ->ordered()
            ->with(Mockery::type(Request::class), $delfiImport)
            ->andReturn(response()->json([
                'success' => true,
                'processed' => 1,
                'failed' => 0,
                'remaining' => 0,
                'done' => true,
                'incomplete' => false,
            ]));

        $this->artisan('imports:refresh-and-inspect', [
            'source' => 'all',
            '--max-seconds' => 60,
            '--delay-ms' => 0,
        ])->assertExitCode(0);

        $this->assertSame('new', $novella->fresh()->check_status);
        $this->assertSame('new', $znanje->fresh()->check_status);
    }

    public function test_nightly_command_rejects_unknown_source(): void
    {
        $this->artisan('imports:refresh-and-inspect', ['source' => 'laguna'])
            ->expectsOutput('Izvor mora biti all, delfi, znanje ili novella.')
            ->assertExitCode(2);
    }

    private function pendingNovella(): NovellaImportProduct
    {
        return NovellaImportProduct::query()->create($this->pendingAttributes('novella'));
    }

    private function pendingZnanje(): ZnanjeImportProduct
    {
        return ZnanjeImportProduct::query()->create($this->pendingAttributes('znanje'));
    }

    private function pendingAttributes(string $source): array
    {
        return [
            'external_id' => strtoupper($source) . '-NIGHTLY-1',
            'name' => 'Noćna provjera ' . $source,
            'source_category' => 'Knjige',
            'source_url' => 'https://example.com/' . $source,
            'source_hash' => hash('sha256', $source . '-current'),
            'checked_source_hash' => hash('sha256', $source . '-old'),
            'feed_token' => (string) Str::uuid(),
            'is_current' => true,
            'check_status' => 'pending',
        ];
    }

    private function markChecked($source)
    {
        $source->update([
            'checked_source_hash' => $source->source_hash,
            'check_status' => 'new',
            'checked_at' => now(),
        ]);

        return $source->fresh();
    }
}
