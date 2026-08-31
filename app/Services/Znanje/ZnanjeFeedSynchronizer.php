<?php

namespace App\Services\Znanje;

use App\Models\Back\Catalog\ZnanjeImportProduct;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class ZnanjeFeedSynchronizer
{
    private const STAGING_TABLE = 'znanje_import_feed_rows';

    private const FEED_BASELINE_KEY = '_znanje_feed';

    private const SYNC_LOCK = 'znanje-import-feed-sync';

    private const ACTIVE_SESSION_KEY = 'znanje-import-feed-active-session';

    private const STATE_PREFIX = 'znanje-import-feed-state:';

    private const STEP_LOCK_PREFIX = 'znanje-import-feed-step:';

    private const ROOT_SUCCESS_TOKEN_PREFIX = 'znanje-import-feed-root-token:';

    private ZnanjeProductListClient $client;

    private ZnanjeProductListParser $parser;

    public function __construct(ZnanjeProductListClient $client, ZnanjeProductListParser $parser)
    {
        $this->client = $client;
        $this->parser = $parser;
    }

    public function sync(?int $rootCategoryId = null): array
    {
        return $this->refresh($rootCategoryId);
    }

    public function refresh(?int $rootCategoryId = null): array
    {
        $session = $this->start($rootCategoryId);
        $token = $session['token'];
        try {
            do {
                $session = $this->step($token, 10);
            } while (! $session['ready_to_finalize']);

            return $this->finalize($token);
        } catch (\Throwable $exception) {
            $this->cancel($token, false);
            throw $exception;
        }
    }

    public function start(?int $rootCategoryId = null): array
    {
        $selectedRoots = $this->rootsFor($rootCategoryId);
        $resumable = $this->resumableActiveProgress();
        if ($resumable !== null) {
            return $resumable;
        }

        $token = (string) Str::uuid();
        $ttl = $this->sessionTtl();
        $lock = Cache::lock(self::SYNC_LOCK, $ttl, $token);
        if (! $lock->get()) {
            $resumable = $this->resumableActiveProgress();
            if ($resumable !== null) {
                return $resumable;
            }
            throw new RuntimeException('Osvježavanje Znanje kataloga već je u tijeku.');
        }

        try {
            $this->deleteAbandonedStagingRows();
            $now = now()->toIso8601String();
            $selectedRootNames = array_column($selectedRoots, 'name');
            $state = [
                'token' => $token,
                'selected_root_id' => $rootCategoryId,
                'selected_root_label' => $this->rootLabel($rootCategoryId),
                'phase' => 'crawling',
                'root_index' => 0,
                'next_page' => 1,
                'target_pages' => null,
                'root_pages' => [],
                'root_totals' => [],
                'finalize_root_index' => 0,
                'drift' => [],
                'processed_pages' => 0,
                'skipped' => 0,
                'skipped_external_ids' => [],
                'duplicates' => 0,
                // Sanity and retirement comparisons must use the same scope
                // that is being refreshed. The global current count remains
                // represented by the final snapshot/result.
                'previous_current' => ZnanjeImportProduct::query()
                    ->where('is_current', true)
                    ->whereIn('source_category', $selectedRootNames)
                    ->count(),
                'created_at' => $now,
                'updated_at' => $now,
                'error' => null,
                'message' => 'Znanje katalog je pripremljen za preuzimanje.',
                'result' => null,
            ];
            // Persist resumable state before publishing its owner token. This
            // closes the tiny start-response race where a second request could
            // otherwise observe an owner without state and discard the session.
            if (! Cache::put($this->stateKey($token), $state, $ttl)) {
                throw new RuntimeException('Stanje Znanje osvježavanja nije moguće spremiti.');
            }
        } catch (\Throwable $exception) {
            Cache::forget($this->stateKey($token));
            $lock->release();
            throw $exception;
        }
        if (! Cache::add(self::ACTIVE_SESSION_KEY, $token, $ttl)) {
            Cache::forget($this->stateKey($token));
            $lock->release();
            $resumable = $this->resumableActiveProgress();
            if ($resumable !== null) {
                return $resumable;
            }
            throw new RuntimeException('Osvježavanje Znanje kataloga već je u tijeku.');
        }

        try {
            return $this->progress($state);
        } catch (\Throwable $exception) {
            if (Cache::get(self::ACTIVE_SESSION_KEY) === $token) {
                Cache::forget(self::ACTIVE_SESSION_KEY);
            }
            Cache::forget($this->stateKey($token));
            $lock->release();
            throw $exception;
        }
    }

    public function step(string $token, int $maxPages = 3): array
    {
        $this->assertToken($token);
        $mutex = Cache::lock(
            self::STEP_LOCK_PREFIX . $token,
            $this->stepLockSeconds($maxPages)
        );
        if (! $mutex->get()) {
            throw new RuntimeException('Drugi korak ovog Znanje osvježavanja još je u tijeku.');
        }

        try {
            $cachedState = Cache::get($this->stateKey($token));
            if (is_array($cachedState)
                && ($cachedState['token'] ?? null) === $token
                && ($cachedState['phase'] ?? null) === 'complete') {
                return $this->progress($cachedState);
            }
            $state = $this->activeState($token);
            if ($state['phase'] === 'validation_complete') {
                // Deliberately consume a separate, network-free request before
                // the controller starts the local merge. The last remote page
                // and the DB finalization can therefore never share a gateway
                // timeout budget.
                $state['phase'] = 'ready_to_finalize';
                $state['message'] = 'Završna provjera je gotova; aktiviram novi katalog.';
                $state['error'] = null;
                $this->saveState($state);

                return $this->progress($state);
            }
            if ($state['phase'] === 'validating') {
                $this->validateNextRoot($state);

                return $this->progress($state);
            }
            if ($state['phase'] !== 'crawling') {
                return $this->progress($state);
            }
            $limit = max(1, min(10, $maxPages));
            $roots = $this->rootsForState($state);
            $rootIds = array_keys($roots);
            $maxConfiguredPages = max(1, (int) config('znanje_import.max_pages_per_category', 500));

            for ($processed = 0; $processed < $limit && $state['phase'] === 'crawling'; $processed++) {
                $rootIndex = (int) $state['root_index'];
                if (! isset($rootIds[$rootIndex])) {
                    $state['phase'] = 'validating';
                    $state['message'] = 'Sve stranice su preuzete; provjeravam završno stanje izvora.';
                    $this->saveState($state);
                    break;
                }
                $rootId = (int) $rootIds[$rootIndex];
                $root = $roots[$rootId];
                $page = max(1, (int) $state['next_page']);
                if ($page > $maxConfiguredPages) {
                    throw new RuntimeException(
                        'Znanje kategorija ' . $root['name'] . ' ima više stranica od sigurnosnog limita.'
                    );
                }

                $parsed = $this->parser->parse(
                    $this->client->fetchPage($rootId, $page),
                    $rootId,
                    $page
                );
                $this->recordParserSkips($state, $parsed);
                $pageTotal = (int) ($parsed['total'] ?? -1);
                $pageTotalPages = (int) ($parsed['total_pages'] ?? -1);
                if ($pageTotal < 1 || $pageTotalPages < $page || $pageTotalPages > $maxConfiguredPages) {
                    throw new RuntimeException(
                        'Znanje katalog vratio je neispravnu paginaciju. Postojeći podaci nisu promijenjeni.'
                    );
                }

                $rootName = $root['name'];
                if (! isset($state['drift'][$rootName])) {
                    $state['drift'][$rootName] = [
                        'initial_total' => $pageTotal,
                        'minimum_total' => $pageTotal,
                        'maximum_total' => $pageTotal,
                        'final_total' => null,
                        'delta' => null,
                    ];
                }
                $state['drift'][$rootName]['minimum_total'] = min(
                    $state['drift'][$rootName]['minimum_total'],
                    $pageTotal
                );
                $state['drift'][$rootName]['maximum_total'] = max(
                    $state['drift'][$rootName]['maximum_total'],
                    $pageTotal
                );
                $state['root_pages'][$rootName] = $pageTotalPages;

                $pageResult = $this->stagePageItems(
                    (array) ($parsed['items'] ?? []),
                    $rootName,
                    $token,
                    true
                );
                $state['skipped'] += $pageResult['skipped'];
                $state['duplicates'] += $pageResult['duplicates'];
                $state['processed_pages']++;
                $state['target_pages'] = $pageTotalPages;

                if ($page >= $pageTotalPages) {
                    $state['root_index'] = $rootIndex + 1;
                    $state['next_page'] = 1;
                    $state['target_pages'] = null;
                    if (! isset($rootIds[$rootIndex + 1])) {
                        $state['phase'] = 'validating';
                        $state['message'] = 'Sve stranice su preuzete; provjeravam završno stanje izvora.';
                    } else {
                        $state['message'] = 'Kategorija ' . $rootName . ' je preuzeta.';
                    }
                } else {
                    $state['next_page'] = $page + 1;
                    $state['message'] = sprintf(
                        'Preuzimam %s: stranica %d od %d.',
                        $rootName,
                        $state['next_page'],
                        $pageTotalPages
                    );
                }
                $state['error'] = null;
                $this->saveState($state);
            }

            return $this->progress($state);
        } catch (ZnanjeRetryableException $exception) {
            if (isset($state) && is_array($state)) {
                $state['error'] = $exception->getMessage();
                $state['message'] = 'Izvor je privremeno nedostupan; isti korak možete ponoviti.';
                $this->saveState($state);
            }
            throw $exception;
        } catch (\Throwable $exception) {
            if (isset($state) && is_array($state)) {
                $this->failSession($state, $exception->getMessage());
            }
            throw $exception;
        } finally {
            $mutex->release();
        }
    }

    public function status(string $token): array
    {
        $this->assertToken($token);
        $state = Cache::get($this->stateKey($token));
        if (! is_array($state) || ($state['token'] ?? null) !== $token) {
            throw new RuntimeException('Znanje sesija osvježavanja više ne postoji.');
        }

        return $this->progress($state);
    }

    public function finalize(string $token): array
    {
        $this->assertToken($token);
        $mutex = Cache::lock(self::STEP_LOCK_PREFIX . $token, 600);
        if (! $mutex->get()) {
            throw new RuntimeException('Drugi korak ovog Znanje osvježavanja još je u tijeku.');
        }

        try {
            $cachedState = Cache::get($this->stateKey($token));
            if (is_array($cachedState)
                && ($cachedState['phase'] ?? null) === 'complete'
                && is_array($cachedState['result'] ?? null)) {
                return $cachedState['result'];
            }
            $state = $this->activeState($token);
            if (! in_array($state['phase'], ['ready_to_finalize', 'finalizing'], true)) {
                throw new RuntimeException('Znanje stranice još nisu u cijelosti preuzete.');
            }
            $state['phase'] = 'finalizing';
            $state['message'] = 'Provjeravam završno stanje i aktiviram novi katalog.';
            $state['error'] = null;
            $this->saveState($state);

            $roots = $this->rootsForState($state);
            $rootTotals = (array) ($state['root_totals'] ?? []);
            foreach ($roots as $root) {
                $rootName = $root['name'];
                if (! isset($rootTotals[$rootName])
                    || (int) $rootTotals[$rootName] < 1
                    || ! isset($state['drift'][$rootName]['final_total'])) {
                    throw new RuntimeException(
                        'Znanje završna provjera nije dovršena za sve kategorije.'
                    );
                }
            }

            $staged = DB::table(self::STAGING_TABLE)->where('feed_token', $token)->count();
            $announced = array_sum($rootTotals);
            // Cards explicitly rejected by the parser (for example, one
            // product without a usable EUR price) still belong to the remote
            // announced total. Count those known skips for reconciliation so
            // one bad product cannot abort an otherwise valid refresh.
            $knownParserSkips = count((array) ($state['skipped_external_ids'] ?? []));
            $reconciliationGap = abs(($staged + $knownParserSkips) - $announced);
            $allowedDrift = $this->allowedCatalogDrift($announced);
            foreach ($state['drift'] as $rootName => &$rootDrift) {
                $rootAllowedDrift = $this->allowedCatalogDrift((int) $rootDrift['final_total']);
                $rootDrift['allowed_drift'] = $rootAllowedDrift;
                if (($rootDrift['maximum_total'] - $rootDrift['minimum_total']) > $rootAllowedDrift) {
                    throw new RuntimeException(sprintf(
                        'Znanje kategorija %s promijenila se s %d na %d artikala tijekom uvoza (dopušteno %d). Postojeći podaci nisu promijenjeni.',
                        $rootName,
                        $rootDrift['minimum_total'],
                        $rootDrift['maximum_total'],
                        $rootAllowedDrift
                    ));
                }
            }
            unset($rootDrift);
            if ($staged < 1 || $reconciliationGap > $allowedDrift) {
                throw new RuntimeException(sprintf(
                    'Znanje je završno najavio %d, a sigurno je preuzeto %d jedinstvenih knjiga (dopušteno odstupanje %d). Postojeći podaci nisu promijenjeni.',
                    $announced,
                    $staged,
                    $allowedDrift
                ));
            }
            $this->assertSaneBookCount(
                $staged,
                (int) $state['previous_current'],
                ($state['selected_root_id'] ?? null) !== null
            );
            $seenAt = now();
            $retiredNow = $this->mergeStagingIntoLive(
                $token,
                $seenAt,
                max(10, min(500, (int) config('znanje_import.sync_batch_size', 100))),
                array_column($roots, 'name')
            );

            $current = ZnanjeImportProduct::query()->where('is_current', true)->count();
            $snapshotRootTotals = $this->currentRootTotals();

            $snapshotWarning = null;
            try {
                $this->writeSnapshotFromCurrent(
                    $current,
                    $seenAt->toIso8601String(),
                    $snapshotRootTotals
                );
            } catch (\Throwable $exception) {
                report($exception);
                $snapshotWarning = 'Podaci su osvježeni, ali lokalnu Znanje snimku nije moguće spremiti.';
            }
            $result = [
                'staged' => $staged,
                'current' => $current,
                'retired' => ZnanjeImportProduct::query()->where('is_current', false)->count(),
                'retired_now' => $retiredNow,
                'skipped' => (int) $state['skipped'],
                'duplicates' => (int) $state['duplicates'],
                'pages' => (int) $state['processed_pages'] + count($roots),
                'root_totals' => $snapshotRootTotals,
                'drift' => $state['drift'],
                'reconciliation_gap' => $reconciliationGap,
                'allowed_drift' => $allowedDrift,
                'path' => (string) config('znanje_import.snapshot_path'),
                'snapshot_warning' => $snapshotWarning,
                'selected_root_id' => $state['selected_root_id'],
                'selected_root_label' => $state['selected_root_label'],
            ];

            $state['phase'] = 'complete';
            $state['result'] = $result;
            $state['root_totals'] = $snapshotRootTotals;
            $state['message'] = 'Znanje katalog je uspješno osvježen.';
            $state['error'] = null;
            $this->saveState($state, false);
            $this->deleteStagingToken($token);
            $this->releaseSession($token);

            return $result;
        } catch (ZnanjeRetryableException $exception) {
            if (isset($state) && is_array($state)) {
                $state['phase'] = 'ready_to_finalize';
                $state['error'] = $exception->getMessage();
                $state['message'] = 'Završna provjera privremeno nije uspjela; možete je ponoviti.';
                $this->saveState($state);
            }
            throw $exception;
        } catch (RuntimeException $exception) {
            if (isset($state) && is_array($state)) {
                $this->failSession($state, $exception->getMessage());
            }
            throw $exception;
        } catch (\Throwable $exception) {
            if (isset($state) && is_array($state)) {
                $state['phase'] = 'ready_to_finalize';
                $state['error'] = $exception->getMessage();
                $state['message'] = 'Završna obrada nije uspjela; pokušajte ponovno.';
                $this->saveState($state);
            }
            throw $exception;
        } finally {
            $mutex->release();
        }
    }

    public function cancel(string $token, bool $remember = true): void
    {
        if (! $this->validToken($token)) {
            return;
        }
        $state = Cache::get($this->stateKey($token));
        $this->deleteStagingToken($token);
        if ($remember && is_array($state)) {
            $state['phase'] = 'cancelled';
            $state['message'] = 'Znanje osvježavanje je prekinuto.';
            $state['error'] = null;
            $state['updated_at'] = now()->toIso8601String();
            Cache::put($this->stateKey($token), $state, 600);
        } else {
            Cache::forget($this->stateKey($token));
        }
        $this->releaseSession($token);
    }

    private function validateNextRoot(array &$state): void
    {
        $roots = $this->rootsForState($state);
        $rootIds = array_keys($roots);
        $rootIndex = max(0, (int) ($state['finalize_root_index'] ?? 0));
        if (! isset($rootIds[$rootIndex])) {
            $state['phase'] = 'validation_complete';
            $state['message'] = 'Završna mrežna provjera je dovršena.';
            $state['error'] = null;
            $this->saveState($state);

            return;
        }

        $rootId = (int) $rootIds[$rootIndex];
        $root = $roots[$rootId];
        $maxPages = max(1, (int) config('znanje_import.max_pages_per_category', 500));
        $parsed = $this->parser->parse(
            $this->client->fetchPage($rootId, 1),
            $rootId,
            1
        );
        $this->recordParserSkips($state, $parsed);
        $finalTotal = (int) ($parsed['total'] ?? -1);
        $finalPages = (int) ($parsed['total_pages'] ?? -1);
        if ($finalTotal < 1 || $finalPages < 1 || $finalPages > $maxPages) {
            throw new RuntimeException(
                'Znanje završna provjera vratila je neispravnu paginaciju. Postojeći podaci nisu promijenjeni.'
            );
        }

        $rootName = $root['name'];
        if (! isset($state['drift'][$rootName])
            || ! isset($state['drift'][$rootName]['initial_total'])) {
            throw new RuntimeException(
                'Znanje završna provjera nema početne podatke za kategoriju ' . $rootName . '.'
            );
        }
        $state['root_totals'][$rootName] = $finalTotal;
        $state['drift'][$rootName]['minimum_total'] = min(
            (int) $state['drift'][$rootName]['minimum_total'],
            $finalTotal
        );
        $state['drift'][$rootName]['maximum_total'] = max(
            (int) $state['drift'][$rootName]['maximum_total'],
            $finalTotal
        );
        $state['drift'][$rootName]['final_total'] = $finalTotal;
        $state['drift'][$rootName]['delta'] = $finalTotal
            - (int) $state['drift'][$rootName]['initial_total'];
        $pageResult = $this->stagePageItems(
            (array) ($parsed['items'] ?? []),
            $rootName,
            (string) $state['token'],
            false
        );
        $state['skipped'] += $pageResult['skipped'];
        $state['finalize_root_index'] = $rootIndex + 1;
        if (! isset($rootIds[$rootIndex + 1])) {
            $state['phase'] = 'validation_complete';
            $state['message'] = 'Završna mrežna provjera je dovršena.';
        } else {
            $state['message'] = sprintf(
                'Završno provjeravam kategorije: %d od %d.',
                $state['finalize_root_index'],
                count($rootIds)
            );
        }
        $state['error'] = null;
        $this->saveState($state);
    }

    private function stagePageItems(
        array $items,
        string $rootName,
        string $token,
        bool $countDuplicates
    ): array {
        $skipped = 0;
        $rows = [];
        $remoteIds = [];
        foreach ($items as $item) {
            if (! is_array($item) || ! $this->isImportable($item, $rootName)) {
                $skipped++;
                continue;
            }
            $externalId = (string) $item['external_id'];
            $remoteId = (int) $item['remote_product_id'];
            if (isset($rows[$externalId]) || isset($remoteIds[$remoteId])) {
                throw new RuntimeException(
                    'Znanje katalog sadrži proturječan ili ponovljeni ID unutar iste stranice.'
                );
            }
            $rows[$externalId] = $item;
            $remoteIds[$remoteId] = $externalId;
        }
        if ($rows === []) {
            return ['skipped' => $skipped, 'duplicates' => 0];
        }

        $existing = DB::table(self::STAGING_TABLE)
            ->where('feed_token', $token)
            ->where(function ($query) use ($rows, $remoteIds) {
                $query->whereIn('external_id', array_keys($rows))
                    ->orWhereIn('remote_product_id', array_keys($remoteIds));
            })
            ->get(['external_id', 'remote_product_id']);
        $existingByExternal = [];
        $existingByRemote = [];
        foreach ($existing as $row) {
            $existingByExternal[(string) $row->external_id] = (int) $row->remote_product_id;
            $existingByRemote[(int) $row->remote_product_id] = (string) $row->external_id;
        }

        $duplicates = 0;
        $seenAt = now();
        $stagingRows = [];
        foreach ($rows as $externalId => $item) {
            $remoteId = (int) $item['remote_product_id'];
            if ((isset($existingByExternal[$externalId])
                    && $existingByExternal[$externalId] !== $remoteId)
                || (isset($existingByRemote[$remoteId])
                    && $existingByRemote[$remoteId] !== (string) $externalId)) {
                throw new RuntimeException(
                    'Znanje katalog sadrži proturječan odnos vanjskog ID-a i ID-a artikla.'
                );
            }
            if ($countDuplicates && isset($existingByExternal[$externalId])) {
                $duplicates++;
            }
            $stagingRows[] = $this->stagingRow($item, $token, $seenAt);
        }
        $this->upsertStaging($stagingRows);

        return ['skipped' => $skipped, 'duplicates' => $duplicates];
    }

    private function recordParserSkips(array &$state, array $parsed): void
    {
        $knownIds = array_fill_keys(
            array_map('strval', (array) ($state['skipped_external_ids'] ?? [])),
            true
        );

        foreach ((array) ($parsed['skipped']['items'] ?? []) as $skippedItem) {
            if (! is_array($skippedItem)) {
                continue;
            }
            $externalId = trim((string) ($skippedItem['external_id'] ?? ''));
            if ($externalId === '' || isset($knownIds[$externalId])) {
                continue;
            }
            $knownIds[$externalId] = true;
            $state['skipped'] = (int) ($state['skipped'] ?? 0) + 1;
        }

        $state['skipped_external_ids'] = array_keys($knownIds);
    }

    private function progress(array $state): array
    {
        $roots = array_values($this->rootsForState($state));
        $rootIndex = (int) ($state['root_index'] ?? 0);
        $phase = (string) ($state['phase'] ?? 'error');
        $result = is_array($state['result'] ?? null) ? $state['result'] : null;
        $staged = $phase === 'complete' && $result !== null
            ? (int) ($result['staged'] ?? 0)
            : DB::table(self::STAGING_TABLE)
                ->where('feed_token', (string) $state['token'])
                ->count();

        return [
            'token' => (string) $state['token'],
            'selected_root_id' => $state['selected_root_id'] ?? null,
            'selected_root_label' => (string) ($state['selected_root_label'] ?? $this->rootLabel(null)),
            'phase' => $phase,
            'processed_pages' => (int) ($state['processed_pages'] ?? 0),
            'total_pages' => array_sum(array_map('intval', (array) ($state['root_pages'] ?? []))),
            'staged' => $staged,
            'root' => $roots[$rootIndex]['name'] ?? null,
            'current_page' => isset($roots[$rootIndex]) ? (int) ($state['next_page'] ?? 1) : null,
            // A request may be disconnected while finalize is still running.
            // Returning finalizing as ready lets the next idempotent request
            // call finalize again instead of looping through no-op steps.
            'ready_to_finalize' => in_array($phase, ['ready_to_finalize', 'finalizing'], true),
            'completed' => $phase === 'complete',
            'error' => $state['error'] ?? null,
            'message' => (string) ($state['message'] ?? ''),
            'updated_at' => $state['updated_at'] ?? null,
            'result' => $result,
        ];
    }

    private function activeState(string $token): array
    {
        if (Cache::get(self::ACTIVE_SESSION_KEY) !== $token) {
            throw new RuntimeException('Ova Znanje sesija više nije aktivna.');
        }
        $state = Cache::get($this->stateKey($token));
        if (! is_array($state) || ($state['token'] ?? null) !== $token) {
            throw new RuntimeException('Znanje sesija osvježavanja više ne postoji.');
        }
        if (in_array($state['phase'] ?? null, ['error', 'cancelled'], true)) {
            throw new RuntimeException((string) ($state['error'] ?: $state['message']));
        }

        return $state;
    }

    private function resumableActiveProgress(): ?array
    {
        $activeToken = Cache::get(self::ACTIVE_SESSION_KEY);
        if ($activeToken === null) {
            return null;
        }
        if (! is_string($activeToken) || ! $this->validToken($activeToken)) {
            if (Cache::get(self::ACTIVE_SESSION_KEY) === $activeToken) {
                Cache::forget(self::ACTIVE_SESSION_KEY);
            }

            return null;
        }
        $state = Cache::get($this->stateKey($activeToken));
        if (is_array($state)
            && ($state['token'] ?? null) === $activeToken
            && in_array($state['phase'] ?? null, [
                'crawling', 'validating', 'validation_complete',
                'ready_to_finalize', 'finalizing',
            ], true)) {
            // Refresh the independently expiring owner marker. The global
            // lock may expire between HTTP requests, while this compare-token
            // marker still prevents a second crawl from being created.
            Cache::put(self::ACTIVE_SESSION_KEY, $activeToken, $this->sessionTtl());

            return $this->progress($state);
        }

        // An owner without usable state cannot be resumed. Release only that
        // exact owner, so a concurrent session can never be cleared here.
        if (Cache::get(self::ACTIVE_SESSION_KEY) === $activeToken) {
            Cache::forget(self::ACTIVE_SESSION_KEY);
            try {
                Cache::restoreLock(self::SYNC_LOCK, $activeToken)->release();
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return null;
    }

    private function saveState(array &$state, bool $renewSession = true): void
    {
        $token = (string) ($state['token'] ?? '');
        $this->assertToken($token);
        if ($renewSession && Cache::get(self::ACTIVE_SESSION_KEY) !== $token) {
            throw new RuntimeException('Ova Znanje sesija više nije aktivna.');
        }
        $state['updated_at'] = now()->toIso8601String();
        if (! Cache::put($this->stateKey($token), $state, $this->sessionTtl())) {
            throw new RuntimeException('Stanje Znanje osvježavanja nije moguće spremiti.');
        }
        if ($renewSession) {
            Cache::put(self::ACTIVE_SESSION_KEY, $token, $this->sessionTtl());
        }
    }

    private function failSession(array $state, string $message): void
    {
        $token = (string) $state['token'];
        $state['phase'] = 'error';
        $state['error'] = $message;
        $state['message'] = 'Znanje osvježavanje je prekinuto; postojeći katalog nije promijenjen.';
        try {
            $this->saveState($state, false);
        } finally {
            $this->deleteStagingToken($token);
            $this->releaseSession($token);
        }
    }

    private function releaseSession(string $token): void
    {
        if (Cache::get(self::ACTIVE_SESSION_KEY) === $token) {
            Cache::forget(self::ACTIVE_SESSION_KEY);
        }
        try {
            Cache::restoreLock(self::SYNC_LOCK, $token)->release();
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function stateKey(string $token): string
    {
        return self::STATE_PREFIX . $token;
    }

    private function rootSuccessTokenKey(string $rootName): string
    {
        return self::ROOT_SUCCESS_TOKEN_PREFIX . hash('sha256', $rootName);
    }

    private function rootsFor(?int $rootCategoryId): array
    {
        $roots = ZnanjeProductListClient::roots();
        if ($rootCategoryId === null) {
            return $roots;
        }
        if (! isset($roots[$rootCategoryId])) {
            throw new RuntimeException('Znanje import podržava samo korijenske kategorije 500 i 505.');
        }

        return [$rootCategoryId => $roots[$rootCategoryId]];
    }

    private function rootsForState(array $state): array
    {
        $rootCategoryId = array_key_exists('selected_root_id', $state)
            && $state['selected_root_id'] !== null
                ? (int) $state['selected_root_id']
                : null;

        return $this->rootsFor($rootCategoryId);
    }

    private function rootLabel(?int $rootCategoryId): string
    {
        if ($rootCategoryId === null) {
            return 'Sve dostupne knjige';
        }

        if ($rootCategoryId === 505) {
            return 'Strane knjige (engleske)';
        }

        return (string) $this->rootsFor($rootCategoryId)[$rootCategoryId]['name'];
    }

    private function currentRootTotals(): array
    {
        $counts = ZnanjeImportProduct::query()
            ->where('is_current', true)
            ->whereIn('source_category', array_column(ZnanjeProductListClient::roots(), 'name'))
            ->select('source_category')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('source_category')
            ->pluck('aggregate', 'source_category');

        $totals = [];
        foreach (ZnanjeProductListClient::roots() as $root) {
            $totals[$root['name']] = (int) ($counts[$root['name']] ?? 0);
        }

        return $totals;
    }

    private function sessionTtl(): int
    {
        return max(900, min(86400, (int) config('znanje_import.sync_session_seconds', 14400)));
    }

    private function stepLockSeconds(int $maxPages): int
    {
        $pages = max(1, min(10, $maxPages));
        $worstCaseSeconds = $pages * ZnanjeProductListClient::maximumRequestDurationSeconds();

        return max(
            $worstCaseSeconds + 30,
            max(60, min(900, (int) config('znanje_import.feed_step_lock_seconds', 120)))
        );
    }

    private function assertToken(string $token): void
    {
        if (! $this->validToken($token)) {
            throw new RuntimeException('Znanje token osvježavanja nije ispravan.');
        }
    }

    private function validToken(string $token): bool
    {
        return preg_match(
            '/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/Di',
            $token
        ) === 1;
    }

    private function stagingRow(array $item, string $token, $seenAt): array
    {
        return [
            'feed_token' => $token,
            'external_id' => $item['external_id'],
            'remote_product_id' => $item['remote_product_id'],
            'feed_position' => $item['remote_product_id'],
            'name' => $item['name'],
            'description' => $item['description'],
            'source_category' => $item['source_category'],
            'source_categories' => $this->json($item['source_categories']),
            'source_publisher' => $item['source_publisher'],
            'source_url' => $item['source_url'],
            'image_url' => $item['image_url'],
            'additional_image_urls' => $this->json($item['additional_image_urls']),
            'price_eur' => $item['price_eur'],
            'sale_price_eur' => $item['sale_price_eur'],
            'availability' => $item['availability'],
            'sku' => $item['sku'],
            'isbn' => $item['isbn'],
            'ean' => $item['ean'],
            'author' => $item['author'],
            'source_genres' => $this->json($item['source_genres']),
            'genre' => $item['genre'],
            'format' => $item['format'],
            'pages' => $item['pages'],
            'letter' => $item['letter'],
            'binding' => $item['binding'],
            'publication_year' => $item['publication_year'],
            'language' => $item['language'],
            'origin' => $item['origin'],
            'source_hash' => $item['source_hash'],
            'created_at' => $seenAt,
            'updated_at' => $seenAt,
        ];
    }

    private function upsertStaging(array $rows): void
    {
        DB::table(self::STAGING_TABLE)->upsert(
            $rows,
            ['feed_token', 'external_id'],
            [
                'remote_product_id', 'feed_position', 'name', 'description', 'source_category',
                'source_categories', 'source_publisher', 'source_url', 'image_url',
                'additional_image_urls', 'price_eur', 'sale_price_eur', 'availability',
                'sku', 'isbn', 'ean', 'author', 'source_genres', 'genre', 'format', 'pages',
                'letter', 'binding', 'publication_year', 'language', 'origin', 'source_hash',
                'updated_at',
            ]
        );
    }

    private function mergeStagingIntoLive(
        string $token,
        $seenAt,
        int $batchSize,
        array $selectedRootNames
    ): int
    {
        $alreadyMerged = ZnanjeImportProduct::query()
            ->where('feed_token', $token)
            ->where('is_current', true)
            ->exists();
        $previousTokens = [];
        if (! $alreadyMerged) {
            // Partial refreshes give each root its own successful token. Keep
            // the two-consecutive-miss guard independent per root so a later
            // partial or full refresh compares like with like.
            foreach ($selectedRootNames as $rootName) {
                $previous = Cache::get($this->rootSuccessTokenKey($rootName));
                $previousTokens[$rootName] = is_string($previous) && $this->validToken($previous)
                    ? $previous
                    : null;
            }
        }

        $retired = DB::transaction(function () use (
            $token,
            $seenAt,
            $batchSize,
            $selectedRootNames,
            $previousTokens,
            $alreadyMerged
        ) {
            $this->assertNoIdentifierConflicts($token);
            DB::table(self::STAGING_TABLE)
                ->where('feed_token', $token)
                ->orderBy('id')
                ->chunkById($batchSize, function ($rows) {
                    $existing = ZnanjeImportProduct::query()
                        ->whereIn('external_id', $rows->pluck('external_id')->all())
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('external_id');
                    $records = [];
                    foreach ($rows as $row) {
                        $live = $existing->get($row->external_id);
                        $feedBaseline = $this->feedBaseline($row);
                        $detailPayload = $live && is_array($live->detail_payload)
                            ? $live->detail_payload
                            : [];
                        $previousBaseline = isset($detailPayload[self::FEED_BASELINE_KEY])
                            && is_array($detailPayload[self::FEED_BASELINE_KEY])
                                ? $detailPayload[self::FEED_BASELINE_KEY]
                                : null;
                        $detailPayload[self::FEED_BASELINE_KEY] = $feedBaseline;

                        $records[] = [
                            'external_id' => $row->external_id,
                            'remote_product_id' => $row->remote_product_id,
                            'feed_position' => $row->remote_product_id,
                            'name' => $row->name,
                            'description' => $this->mergeFeedValue($live, 'description', $feedBaseline['description'], $previousBaseline),
                            'source_category' => $row->source_category,
                            'source_categories' => $row->source_categories,
                            'source_publisher' => $this->mergeFeedValue($live, 'source_publisher', $feedBaseline['source_publisher'], $previousBaseline),
                            'source_url' => $row->source_url,
                            'image_url' => $this->mergeFeedValue($live, 'image_url', $feedBaseline['image_url'], $previousBaseline),
                            'additional_image_urls' => $this->json($this->mergeFeedValue($live, 'additional_image_urls', $feedBaseline['additional_image_urls'], $previousBaseline)),
                            'price_eur' => $row->price_eur,
                            'sale_price_eur' => $row->sale_price_eur,
                            'availability' => $row->availability,
                            'sku' => $row->sku,
                            'isbn' => $this->mergeFeedValue($live, 'isbn', $feedBaseline['isbn'], $previousBaseline),
                            'ean' => $this->mergeFeedValue($live, 'ean', $feedBaseline['ean'], $previousBaseline),
                            'author' => $this->mergeFeedValue($live, 'author', $feedBaseline['author'], $previousBaseline),
                            'source_genres' => $row->source_genres,
                            'genre' => $row->genre,
                            'format' => $this->mergeFeedValue($live, 'format', $feedBaseline['format'], $previousBaseline),
                            'pages' => $this->mergeFeedValue($live, 'pages', $feedBaseline['pages'], $previousBaseline),
                            'letter' => $this->mergeFeedValue($live, 'letter', $feedBaseline['letter'], $previousBaseline),
                            'binding' => $this->mergeFeedValue($live, 'binding', $feedBaseline['binding'], $previousBaseline),
                            'publication_year' => $this->mergeFeedValue($live, 'publication_year', $feedBaseline['publication_year'], $previousBaseline),
                            'language' => $this->mergeFeedValue($live, 'language', $feedBaseline['language'], $previousBaseline),
                            'origin' => $this->mergeFeedValue($live, 'origin', $feedBaseline['origin'], $previousBaseline),
                            'detail_payload' => $this->json($detailPayload),
                            'source_hash' => $row->source_hash,
                            'feed_token' => $row->feed_token,
                            'is_current' => 0,
                            'last_seen_at' => $row->updated_at,
                            'created_at' => $row->created_at,
                            'updated_at' => $row->updated_at,
                        ];
                    }
                    ZnanjeImportProduct::query()->upsert(
                        $records,
                        ['external_id'],
                        [
                            'remote_product_id', 'feed_position', 'name', 'description',
                            'source_category', 'source_categories', 'source_publisher', 'source_url',
                            'image_url', 'additional_image_urls', 'price_eur', 'sale_price_eur',
                            'availability', 'sku', 'isbn', 'ean', 'author', 'source_genres', 'genre',
                            'format', 'pages', 'letter', 'binding', 'publication_year', 'language',
                            'origin', 'detail_payload', 'source_hash', 'feed_token', 'last_seen_at',
                            'updated_at',
                        ]
                    );
                });

            $retired = 0;
            if (! $alreadyMerged) {
                foreach ($selectedRootNames as $rootName) {
                    $previousToken = $previousTokens[$rootName] ?? null;
                    if ($previousToken === null) {
                        continue;
                    }
                    // A product must be absent from two consecutive successful
                    // refreshes of its own root before it is retired. Unselected
                    // roots are deliberately invisible to this query.
                    $retired += ZnanjeImportProduct::query()
                        ->where('is_current', true)
                        ->where('source_category', $rootName)
                        ->where(function ($query) use ($token) {
                            $query->whereNull('feed_token')->orWhere('feed_token', '!=', $token);
                        })
                        ->where(function ($query) use ($previousToken) {
                            $query->whereNull('feed_token')->orWhere('feed_token', '!=', $previousToken);
                        })
                        ->update(['is_current' => false, 'updated_at' => $seenAt]);
                }
            }
            ZnanjeImportProduct::query()
                ->where('feed_token', $token)
                ->where('is_current', false)
                ->update(['is_current' => true, 'updated_at' => $seenAt]);

            return $retired;
        }, 3);

        // Persist the successful baseline separately per root. If this marker
        // is ever flushed, one refresh becomes conservatively non-retiring
        // rather than guessing a token and risking a false retirement.
        foreach ($selectedRootNames as $rootName) {
            Cache::forever($this->rootSuccessTokenKey($rootName), $token);
        }

        return $retired;
    }

    private function feedBaseline(object $row): array
    {
        return [
            'description' => $row->description,
            'source_publisher' => $row->source_publisher,
            'image_url' => $row->image_url,
            'additional_image_urls' => $this->decodedStringList($row->additional_image_urls),
            'isbn' => $row->isbn,
            'ean' => $row->ean,
            'author' => $row->author,
            'format' => $row->format,
            'pages' => $row->pages,
            'letter' => $row->letter,
            'binding' => $row->binding,
            'publication_year' => $row->publication_year,
            'language' => $row->language,
            'origin' => $row->origin,
        ];
    }

    private function mergeFeedValue(?ZnanjeImportProduct $live, string $field, $incoming, ?array $previous)
    {
        if ($live === null) {
            return $incoming;
        }
        if ($previous === null) {
            return $live->checked_at === null ? $incoming : $live->{$field};
        }
        $old = $previous[$field] ?? null;
        if (is_array($live->{$field}) || is_array($old)) {
            return array_values((array) $live->{$field}) === array_values((array) $old)
                ? $incoming
                : $live->{$field};
        }

        return $live->{$field} === $old || (string) $live->{$field} === (string) $old
            ? $incoming
            : $live->{$field};
    }

    private function assertNoIdentifierConflicts(string $token): void
    {
        $duplicate = DB::table(self::STAGING_TABLE)
            ->where('feed_token', $token)
            ->whereNotNull('remote_product_id')
            ->groupBy('remote_product_id')
            ->havingRaw('COUNT(*) > 1')
            ->value('remote_product_id');
        if ($duplicate !== null) {
            throw new RuntimeException('Znanje katalog sadrži više artikala s istim ID-em ' . $duplicate . '.');
        }
        $conflict = DB::table(self::STAGING_TABLE . ' as staging')
            ->join('znanje_import_products as live', 'live.remote_product_id', '=', 'staging.remote_product_id')
            ->where('staging.feed_token', $token)
            ->whereColumn('live.external_id', '!=', 'staging.external_id')
            ->first(['staging.external_id', 'staging.remote_product_id', 'live.external_id as live_external_id']);
        if ($conflict) {
            throw new RuntimeException(sprintf(
                'Znanje ID %s pripada i artiklu %s i artiklu %s. Postojeći podaci nisu promijenjeni.',
                $conflict->remote_product_id,
                $conflict->live_external_id,
                $conflict->external_id
            ));
        }
    }

    private function isImportable(array $item, string $rootName): bool
    {
        $url = trim((string) ($item['source_url'] ?? ''));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return trim((string) ($item['external_id'] ?? '')) !== ''
            && (int) ($item['remote_product_id'] ?? 0) > 0
            && trim((string) ($item['name'] ?? '')) !== ''
            && ($item['source_category'] ?? null) === $rootName
            && in_array($rootName, ['Knjige', 'Strane knjige'], true)
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
            && in_array($host, array_map('strtolower', (array) config('znanje_import.allowed_product_hosts', [])), true)
            && (float) ($item['price_eur'] ?? 0) > 0
            && ($item['availability'] ?? null) === 'in_stock';
    }

    private function assertSaneBookCount(
        int $staged,
        int $previousCurrent,
        bool $partial
    ): void
    {
        $minimum = max(1, (int) config('znanje_import.minimum_expected_books', 20000));
        // The configured global floor is intentionally not applied to one
        // selected root. Its remote announced total and reconciliation check
        // still protect a first partial refresh; subsequent ones also use the
        // selected root's own current-count ratio below.
        if (! $partial && $previousCurrent === 0 && $staged < $minimum) {
            throw new RuntimeException(sprintf(
                'Znanje katalog sadrži samo %d knjiga; očekuje se najmanje %d. Postojeći podaci nisu promijenjeni.',
                $staged,
                $minimum
            ));
        }
        if ($previousCurrent > 0) {
            $ratio = min(1, max(0.1, (float) config('znanje_import.minimum_current_ratio', 0.75)));
            $required = (int) ceil($previousCurrent * $ratio);
            if ($staged < $required) {
                throw new RuntimeException(sprintf(
                    'Znanje feed pao je s %d na %d knjiga. Sigurnosna provjera spriječila je masovno povlačenje artikala.',
                    $previousCurrent,
                    $staged
                ));
            }
        }
    }

    private function allowedCatalogDrift(int $announced): int
    {
        return max(
            max(1, (int) config('znanje_import.maximum_catalog_drift_items', 20)),
            (int) ceil(
                $announced * min(
                    0.05,
                    max(0.0001, (float) config('znanje_import.maximum_catalog_drift_ratio', 0.005))
                )
            )
        );
    }

    private function writeSnapshotFromCurrent(
        int $expectedCount,
        string $syncedAt,
        array $rootTotals
    ): void
    {
        $path = (string) config('znanje_import.snapshot_path');
        $metadataPath = (string) config('znanje_import.metadata_path');
        if ($path === '' || $metadataPath === '') {
            return;
        }
        File::ensureDirectoryExists(dirname($path));
        File::ensureDirectoryExists(dirname($metadataPath));
        $temporary = tempnam(dirname($path), '.znanje-snapshot-');
        if ($temporary === false) {
            throw new RuntimeException('Nije moguće pripremiti lokalnu Znanje snimku.');
        }

        $handle = fopen($temporary, 'wb');
        if ($handle === false) {
            @unlink($temporary);
            throw new RuntimeException('Nije moguće otvoriti lokalnu Znanje snimku.');
        }
        $hash = hash_init('sha256');
        $bytes = 0;
        $writtenCount = 0;
        $first = true;
        $writeFailure = null;
        try {
            $prefix = '{"synced_at":' . $this->json($syncedAt)
                . ',"count":' . $expectedCount
                . ',"root_totals":' . $this->json($rootTotals)
                . ',"items":[';
            $this->writeSnapshotChunk($handle, $hash, $bytes, $prefix);

            // A partial staging set contains only one root. The durable
            // snapshot must always remain a complete view of every current
            // Znanje book, including untouched roots.
            DB::table('znanje_import_products')
                ->where('is_current', true)
                ->orderBy('id')
                ->chunkById(500, function ($rows) use (
                    $handle,
                    $hash,
                    &$bytes,
                    &$writtenCount,
                    &$first
                ) {
                    foreach ($rows as $row) {
                        $json = $this->json($this->snapshotItem($row));
                        $this->writeSnapshotChunk(
                            $handle,
                            $hash,
                            $bytes,
                            ($first ? '' : ',') . $json
                        );
                        $first = false;
                        $writtenCount++;
                    }
                });
            $this->writeSnapshotChunk($handle, $hash, $bytes, ']}');
            if ($writtenCount !== $expectedCount) {
                throw new RuntimeException('Lokalna Znanje snimka nije sadržavala sve pripremljene knjige.');
            }
            if (! fflush($handle)) {
                throw new RuntimeException('Lokalnu Znanje snimku nije moguće dovršiti.');
            }
        } catch (\Throwable $exception) {
            $writeFailure = $exception;
        } finally {
            fclose($handle);
        }
        if ($writeFailure !== null) {
            @unlink($temporary);
            throw $writeFailure;
        }

        try {
            if (! rename($temporary, $path)) {
                throw new RuntimeException('Nije moguće promovirati lokalnu Znanje snimku.');
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }

        $this->atomicWrite($metadataPath, $this->json([
            'synced_at' => $syncedAt,
            'count' => $writtenCount,
            'root_totals' => $rootTotals,
            'bytes' => $bytes,
            'sha256' => hash_final($hash),
        ]));
    }

    /** @param resource $handle */
    private function writeSnapshotChunk($handle, $hash, int &$bytes, string $contents): void
    {
        $length = strlen($contents);
        $offset = 0;
        while ($offset < $length) {
            $written = fwrite($handle, substr($contents, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Lokalnu Znanje snimku nije moguće zapisati.');
            }
            $offset += $written;
        }
        hash_update($hash, $contents);
        $bytes += $length;
    }

    private function snapshotItem(object $row): array
    {
        return [
            'external_id' => (string) $row->external_id,
            'remote_product_id' => $row->remote_product_id !== null ? (int) $row->remote_product_id : null,
            'feed_position' => $row->remote_product_id !== null ? (int) $row->remote_product_id : null,
            'name' => $row->name,
            'description' => $row->description,
            'source_category' => $row->source_category,
            'source_categories' => $this->decodedStringList($row->source_categories),
            'source_publisher' => $row->source_publisher,
            'source_url' => $row->source_url,
            'image_url' => $row->image_url,
            'additional_image_urls' => $this->decodedStringList($row->additional_image_urls),
            'price_eur' => (float) $row->price_eur,
            'sale_price_eur' => $row->sale_price_eur !== null ? (float) $row->sale_price_eur : null,
            'availability' => $row->availability,
            'sku' => $row->sku,
            'isbn' => $row->isbn,
            'ean' => $row->ean,
            'author' => $row->author,
            'source_genres' => $this->decodedStringList($row->source_genres),
            'genre' => $row->genre,
            'format' => $row->format,
            'pages' => $row->pages !== null ? (int) $row->pages : null,
            'letter' => $row->letter,
            'binding' => $row->binding,
            'publication_year' => $row->publication_year !== null ? (int) $row->publication_year : null,
            'language' => $row->language,
            'origin' => $row->origin,
            'source_hash' => $row->source_hash,
        ];
    }

    private function atomicWrite(string $path, string $contents): void
    {
        File::ensureDirectoryExists(dirname($path));
        $temporary = tempnam(dirname($path), '.znanje-');
        if ($temporary === false) {
            throw new RuntimeException('Nije moguće pripremiti lokalnu Znanje snimku.');
        }
        try {
            if (file_put_contents($temporary, $contents, LOCK_EX) === false
                || ! rename($temporary, $path)) {
                throw new RuntimeException('Nije moguće spremiti lokalnu Znanje snimku.');
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    private function deleteStagingToken(string $token): void
    {
        try {
            DB::table(self::STAGING_TABLE)->where('feed_token', $token)->delete();
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function deleteAbandonedStagingRows(): void
    {
        DB::table(self::STAGING_TABLE)->where('created_at', '<', now()->subDay())->delete();
    }

    private function decodedStringList($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        return array_values(array_filter(array_map(fn ($item) => trim((string) $item), (array) $value)));
    }

    private function json($value): string
    {
        $encoded = json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );
        if ($encoded === false) {
            throw new RuntimeException('Znanje podaci sadrže neispravne znakove.');
        }

        return $encoded;
    }
}
