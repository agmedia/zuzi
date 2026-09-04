<?php

namespace App\Console\Commands;

use App\Http\Controllers\Back\Catalog\DelfiImportController;
use App\Models\Back\Catalog\DelfiImportProduct;
use App\Models\Back\Catalog\NovellaImportProduct;
use App\Models\Back\Catalog\ZnanjeImportProduct;
use App\Services\Delfi\DelfiFeedSynchronizer;
use App\Services\Delfi\DelfiImportService;
use App\Services\Novella\NovellaFeedSynchronizer;
use App\Services\Novella\NovellaImportService;
use App\Services\Novella\NovellaRetryableException;
use App\Services\Novella\NovellaTerminalException;
use App\Services\Znanje\ZnanjeFeedSynchronizer;
use App\Services\Znanje\ZnanjeImportService;
use App\Services\Znanje\ZnanjeRetryableException;
use App\Services\Znanje\ZnanjeTerminalException;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class RefreshAndInspectCatalogImports extends Command
{
    protected $signature = 'imports:refresh-and-inspect
        {source=all : all, delfi, znanje or novella}
        {--max-seconds=7200 : Maximum inspection time per source}
        {--batch=50 : Number of Novella or Znanje records loaded per batch}
        {--delay-ms=250 : Pause between upstream inspection requests}';

    protected $description = 'Refresh and inspect Delfi, Znanje and Novella catalog feeds.';

    public function handle(): int
    {
        $requestedSource = strtolower(trim((string) $this->argument('source')));
        $sources = $requestedSource === 'all'
            ? ['novella', 'znanje', 'delfi']
            : [$requestedSource];
        if (array_diff($sources, ['delfi', 'znanje', 'novella']) !== []) {
            $this->error('Izvor mora biti all, delfi, znanje ili novella.');

            return self::INVALID;
        }

        $maxSeconds = max(60, min(21600, (int) $this->option('max-seconds')));
        $batchSize = max(1, min(100, (int) $this->option('batch')));
        $delayMilliseconds = max(0, min(5000, (int) $this->option('delay-ms')));
        $failed = false;

        foreach ($sources as $source) {
            $this->info(sprintf('Pokrećem noćno osvježavanje i provjeru: %s.', ucfirst($source)));

            try {
                if ($source === 'delfi') {
                    $result = $this->runDelfi($maxSeconds, $delayMilliseconds);
                } elseif ($source === 'znanje') {
                    $result = $this->runZnanje($maxSeconds, $batchSize, $delayMilliseconds);
                } else {
                    $result = $this->runNovella($maxSeconds, $batchSize, $delayMilliseconds);
                }

                $this->line(sprintf(
                    '%s: provjereno %d, grešaka %d, preostalo %d%s.',
                    ucfirst($source),
                    $result['processed'],
                    $result['failed'],
                    $result['remaining'],
                    $result['completed'] ? '' : ' (nastavlja se u sljedećem pokretanju)'
                ));
                Log::info('Noćno osvježavanje kataloga je završilo obradu izvora.', [
                    'source' => $source,
                    'result' => $result,
                ]);
            } catch (Throwable $exception) {
                $failed = true;
                report($exception);
                $this->error(ucfirst($source) . ': ' . $exception->getMessage());
                Log::error('Noćno osvježavanje kataloga nije uspjelo.', [
                    'source' => $source,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function runNovella(int $maxSeconds, int $batchSize, int $delayMilliseconds): array
    {
        $lock = Cache::lock('novella-import-refresh', $maxSeconds + 600);
        if (! $lock->get()) {
            throw new RuntimeException('Osvježavanje Novella feeda već je u tijeku.');
        }

        try {
            app(NovellaFeedSynchronizer::class)->refresh();
            Cache::forget('novella-import-source-genre-counts');
        } finally {
            optional($lock)->release();
        }

        return $this->inspectIndividually(
            NovellaImportProduct::class,
            app(NovellaImportService::class),
            'novella-import-source-',
            $maxSeconds,
            $batchSize,
            $delayMilliseconds
        );
    }

    private function runZnanje(int $maxSeconds, int $batchSize, int $delayMilliseconds): array
    {
        app(ZnanjeFeedSynchronizer::class)->refresh();
        Cache::forget('znanje-import-source-genre-counts-by-category');
        Cache::forget('znanje-import-source-category-counts');

        return $this->inspectIndividually(
            ZnanjeImportProduct::class,
            app(ZnanjeImportService::class),
            'znanje-import-source-',
            $maxSeconds,
            $batchSize,
            $delayMilliseconds
        );
    }

    private function runDelfi(int $maxSeconds, int $delayMilliseconds): array
    {
        $refreshLock = Cache::lock('delfi-import-refresh', $maxSeconds + 1800);
        if (! $refreshLock->get()) {
            throw new RuntimeException('Osvježavanje Delfi feeda već je u tijeku.');
        }

        try {
            app(DelfiFeedSynchronizer::class)->refresh();
        } finally {
            optional($refreshLock)->release();
        }

        $startedAt = microtime(true);
        $processed = 0;
        $failed = 0;
        $remaining = 0;
        $completed = false;
        $controller = app(DelfiImportController::class);
        $importService = app(DelfiImportService::class);

        do {
            $request = Request::create('/admin/catalog/delfi-import/inspect-bulk', 'POST', [
                'limit' => 100,
            ]);
            $response = $controller->bulkInspect($request, $importService);
            $payload = (array) $response->getData(true);
            if ($response->getStatusCode() >= 400) {
                throw new RuntimeException((string) ($payload['message'] ?? 'Delfi bulk provjera nije uspjela.'));
            }

            $processed += max(0, (int) ($payload['processed'] ?? 0));
            $failed += max(0, (int) ($payload['failed'] ?? 0));
            $remaining = max(0, (int) ($payload['remaining'] ?? 0));
            $completed = ! empty($payload['done']) && empty($payload['incomplete']);
            if (! empty($payload['done'])) {
                break;
            }

            $this->pause($delayMilliseconds);
        } while ((microtime(true) - $startedAt) < $maxSeconds);

        return compact('processed', 'failed', 'remaining', 'completed');
    }

    /**
     * @param class-string<NovellaImportProduct|ZnanjeImportProduct> $modelClass
     * @param NovellaImportService|ZnanjeImportService $importService
     */
    private function inspectIndividually(
        string $modelClass,
        $importService,
        string $lockPrefix,
        int $maxSeconds,
        int $batchSize,
        int $delayMilliseconds
    ): array {
        $startedAt = microtime(true);
        $processed = 0;
        $failed = 0;

        while ((microtime(true) - $startedAt) < $maxSeconds) {
            $sources = $this->pendingQuery($modelClass)->orderBy('id')->limit($batchSize)->get();
            if ($sources->isEmpty()) {
                break;
            }

            $batchProcessed = 0;
            foreach ($sources as $source) {
                if ((microtime(true) - $startedAt) >= $maxSeconds) {
                    break 2;
                }

                $lock = Cache::lock($lockPrefix . $source->id, 120);
                if (! $lock->get()) {
                    continue;
                }

                try {
                    $importService->inspect($source, false);
                    $processed++;
                    $batchProcessed++;
                } catch (NovellaTerminalException|ZnanjeTerminalException $exception) {
                    $processed++;
                    $failed++;
                    $batchProcessed++;
                    report($exception);
                } catch (NovellaRetryableException|ZnanjeRetryableException $exception) {
                    report($exception);

                    return $this->inspectionResult($modelClass, $processed, $failed, false);
                } finally {
                    optional($lock)->release();
                }

                $this->pause($delayMilliseconds);
            }

            if ($batchProcessed === 0) {
                break;
            }
        }

        return $this->inspectionResult($modelClass, $processed, $failed);
    }

    private function inspectionResult(
        string $modelClass,
        int $processed,
        int $failed,
        ?bool $completed = null
    ): array {
        $remaining = $this->pendingQuery($modelClass)->count();

        return [
            'processed' => $processed,
            'failed' => $failed,
            'remaining' => $remaining,
            'completed' => $completed ?? $remaining === 0,
        ];
    }

    private function pendingQuery(string $modelClass): Builder
    {
        return $modelClass::query()
            ->where('is_current', true)
            ->where(function (Builder $query) {
                $query->whereNull('checked_source_hash')
                    ->orWhereColumn('checked_source_hash', '!=', 'source_hash');
            });
    }

    private function pause(int $milliseconds): void
    {
        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }
}
