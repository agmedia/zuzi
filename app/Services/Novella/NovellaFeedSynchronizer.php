<?php

namespace App\Services\Novella;

use App\Models\Back\Catalog\NovellaImportProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class NovellaFeedSynchronizer
{
    private const STAGING_TABLE = 'novella_import_feed_rows';

    private const FEED_BASELINE_KEY = '_novella_feed';

    private NovellaProductApiClient $api;

    private NovellaProductListParser $parser;

    public function __construct(
        NovellaProductApiClient $api,
        NovellaProductListParser $parser
    ) {
        $this->api = $api;
        $this->parser = $parser;
    }

    /**
     * Download every public WooCommerce page before changing the live set.
     */
    public function refresh(): array
    {
        $this->deleteAbandonedStagingRows();

        $token = (string) Str::uuid();
        $seenAt = now();
        $previousCurrent = NovellaImportProduct::query()->where('is_current', true)->count();
        $perPage = max(1, min(100, (int) config('novella_import.per_page', 100)));
        $maxPages = max(1, (int) config('novella_import.max_pages', 100));
        $batchSize = max(10, min(500, (int) config('novella_import.sync_batch_size', 100)));
        $page = 1;
        $position = 0;
        $duplicates = 0;
        $skipped = 0;
        $seenIds = [];
        $batch = [];
        $snapshot = [];
        $expectedTotal = null;
        $expectedTotalPages = null;
        $lastRemoteId = null;

        try {
            while ($page <= $maxPages) {
                $parsed = $this->parser->parseCollection($this->api->fetchPage($page, $perPage));
                $items = (array) ($parsed['items'] ?? []);
                $pageTotal = (int) ($parsed['total'] ?? -1);
                $pageTotalPages = (int) ($parsed['total_pages'] ?? -1);
                if ($expectedTotal === null) {
                    $expectedTotal = $pageTotal;
                    $expectedTotalPages = $pageTotalPages;
                } elseif ($pageTotal !== $expectedTotal || $pageTotalPages !== $expectedTotalPages) {
                    throw new RuntimeException(
                        'Novella katalog promijenio se tijekom osvježavanja. Postojeći podaci nisu promijenjeni.'
                    );
                }

                foreach ($items as $item) {
                    if (! is_array($item)) {
                        $skipped++;
                        continue;
                    }

                    $position++;
                    $normalized = $this->normalizeItem($item, $position);
                    $remoteId = (int) $normalized['remote_product_id'];
                    if ($lastRemoteId !== null && $remoteId <= $lastRemoteId) {
                        throw new RuntimeException(
                            'Novella artikli nisu jedinstveni i stabilno sortirani između stranica.'
                        );
                    }
                    $lastRemoteId = $remoteId;
                    if (! $this->isImportable($normalized)) {
                        $skipped++;
                        continue;
                    }

                    $externalId = $normalized['external_id'];
                    if (isset($seenIds[$externalId])) {
                        throw new RuntimeException(
                            'Novella API sadrži ponovljeni artikl ' . $externalId . '.'
                        );
                    }
                    $seenIds[$externalId] = true;
                    $snapshot[] = $normalized;
                    $batch[] = $this->stagingRow($normalized, $token, $seenAt);

                    if (count($batch) >= $batchSize) {
                        $this->upsertStaging($batch);
                        $batch = [];
                    }
                }

                if (! $this->hasNextPage($parsed, $page, count($items), $perPage)) {
                    break;
                }
                $page++;
            }

            if ($page >= $maxPages && $this->hasNextPage($parsed ?? [], $page, count($items ?? []), $perPage)) {
                throw new RuntimeException('Novella API vratio je više stranica od dopuštenog sigurnosnog limita.');
            }

            if ($batch !== []) {
                $this->upsertStaging($batch);
            }

            $staged = count($seenIds);
            if ($staged < 1) {
                throw new RuntimeException(
                    'Novella API nije vratio nijednu valjanu knjigu. Postojeći podaci nisu promijenjeni.'
                );
            }
            if ($expectedTotal !== null && $staged !== $expectedTotal) {
                throw new RuntimeException(sprintf(
                    'Novella API najavio je %d, a sigurno je preuzeto %d knjiga. Postojeći podaci nisu promijenjeni.',
                    $expectedTotal,
                    $staged
                ));
            }
            $this->assertSaneBookCount($staged, $previousCurrent);
            $this->mergeStagingIntoLive($token, $seenAt, $batchSize);
            $snapshotWarning = null;
            try {
                $this->writeSnapshot($snapshot, $seenAt->toIso8601String());
            } catch (\Throwable $exception) {
                report($exception);
                $snapshotWarning = 'Podaci su osvježeni, ali lokalnu Novella snimku nije moguće spremiti.';
            }

            return [
                'staged' => $staged,
                'current' => NovellaImportProduct::query()->where('is_current', true)->count(),
                'retired' => NovellaImportProduct::query()->where('is_current', false)->count(),
                'skipped' => $skipped,
                'duplicates' => $duplicates,
                'pages' => $page,
                'path' => (string) config('novella_import.snapshot_path'),
                'snapshot_warning' => $snapshotWarning,
            ];
        } finally {
            $this->deleteStagingToken($token);
        }
    }

    private function normalizeItem(array $item, int $position): array
    {
        $categories = $this->stringList($item['source_categories'] ?? []);
        $genres = $this->stringList($item['source_genres'] ?? $categories);
        $sourceCategory = $this->singleLine($item['source_category'] ?? ($categories[0] ?? 'Knjige'));
        if ($sourceCategory !== '' && ! in_array($sourceCategory, $categories, true)) {
            array_unshift($categories, $sourceCategory);
        }

        $normalized = [
            'external_id' => trim((string) ($item['external_id'] ?? $item['remote_product_id'] ?? '')),
            'remote_product_id' => (int) ($item['remote_product_id'] ?? $item['external_id'] ?? 0) ?: null,
            'feed_position' => $position,
            'name' => $this->singleLine($item['name'] ?? ''),
            'description' => trim((string) ($item['description'] ?? '')) ?: null,
            'source_category' => $sourceCategory ?: null,
            'source_categories' => $categories,
            'source_publisher' => $this->singleLine($item['source_publisher'] ?? '') ?: null,
            'source_url' => trim((string) ($item['source_url'] ?? '')),
            'image_url' => trim((string) ($item['image_url'] ?? '')) ?: null,
            'additional_image_urls' => array_values(array_unique(array_filter(array_map(
                fn ($url) => trim((string) $url),
                (array) ($item['additional_image_urls'] ?? [])
            )))),
            'price_eur' => max(0, round((float) ($item['price_eur'] ?? 0), 4)),
            'sale_price_eur' => isset($item['sale_price_eur']) && (float) $item['sale_price_eur'] > 0
                ? round((float) $item['sale_price_eur'], 4)
                : null,
            'availability' => strtolower(trim((string) ($item['availability'] ?? ''))),
            'sku' => $this->singleLine($item['sku'] ?? '') ?: null,
            'isbn' => $this->identifier($item['isbn'] ?? null),
            'ean' => $this->identifier($item['ean'] ?? null),
            'author' => $this->singleLine($item['author'] ?? '') ?: null,
            'source_genres' => $genres,
            'genre' => $this->singleLine($item['genre'] ?? ($genres[0] ?? '')) ?: null,
        ];

        $hashPayload = $normalized;
        unset($hashPayload['feed_position']);
        $normalized['source_hash'] = $this->validHash($item['source_hash'] ?? null)
            ?: hash('sha256', (string) json_encode(
                $hashPayload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
            ));

        return $normalized;
    }

    private function stagingRow(array $item, string $token, $seenAt): array
    {
        return [
            'feed_token' => $token,
            'external_id' => $item['external_id'],
            'remote_product_id' => $item['remote_product_id'],
            'feed_position' => $item['feed_position'],
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
                'sku', 'isbn', 'ean', 'author', 'source_genres', 'genre',
                'source_hash', 'updated_at',
            ]
        );
    }

    private function mergeStagingIntoLive(string $token, $seenAt, int $batchSize): void
    {
        DB::transaction(function () use ($token, $seenAt, $batchSize) {
            $this->assertNoIdentifierConflicts($token);

            DB::table(self::STAGING_TABLE)
                ->where('feed_token', $token)
                ->orderBy('id')
                ->chunkById($batchSize, function ($rows) {
                    $existing = NovellaImportProduct::query()
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
                        $previousFeedBaseline = isset($detailPayload[self::FEED_BASELINE_KEY])
                            && is_array($detailPayload[self::FEED_BASELINE_KEY])
                                ? $detailPayload[self::FEED_BASELINE_KEY]
                                : null;
                        $detailPayload[self::FEED_BASELINE_KEY] = $feedBaseline;

                        $records[] = [
                            'external_id' => $row->external_id,
                            'remote_product_id' => $row->remote_product_id,
                            'feed_position' => $row->feed_position,
                            'name' => $row->name,
                            'description' => $this->mergeFeedValue(
                                $live,
                                'description',
                                $feedBaseline['description'],
                                $previousFeedBaseline
                            ),
                            'source_category' => $row->source_category,
                            'source_categories' => $row->source_categories,
                            'source_publisher' => $this->mergeFeedValue(
                                $live,
                                'source_publisher',
                                $feedBaseline['source_publisher'],
                                $previousFeedBaseline
                            ),
                            'source_url' => $row->source_url,
                            'image_url' => $this->mergeFeedValue(
                                $live,
                                'image_url',
                                $feedBaseline['image_url'],
                                $previousFeedBaseline
                            ),
                            'additional_image_urls' => $this->json($this->mergeFeedValue(
                                $live,
                                'additional_image_urls',
                                $feedBaseline['additional_image_urls'],
                                $previousFeedBaseline
                            )),
                            'price_eur' => $row->price_eur,
                            'sale_price_eur' => $row->sale_price_eur,
                            'availability' => $row->availability,
                            'sku' => $row->sku,
                            'isbn' => $this->mergeFeedValue(
                                $live,
                                'isbn',
                                $feedBaseline['isbn'],
                                $previousFeedBaseline
                            ),
                            'ean' => $this->mergeFeedValue(
                                $live,
                                'ean',
                                $feedBaseline['ean'],
                                $previousFeedBaseline
                            ),
                            'author' => $this->mergeFeedValue(
                                $live,
                                'author',
                                $feedBaseline['author'],
                                $previousFeedBaseline
                            ),
                            'source_genres' => $row->source_genres,
                            'genre' => $row->genre,
                            'detail_payload' => $this->json($detailPayload),
                            'source_hash' => $row->source_hash,
                            'feed_token' => $row->feed_token,
                            'is_current' => 0,
                            'last_seen_at' => $row->updated_at,
                            'created_at' => $row->created_at,
                            'updated_at' => $row->updated_at,
                        ];
                    }

                    NovellaImportProduct::query()->upsert(
                        $records,
                        ['external_id'],
                        [
                            // Feed-derived values change only while they still
                            // equal the previous feed baseline. Values enriched
                            // from the detail page remain intact until inspect()
                            // refreshes them after source_hash becomes pending.
                            'remote_product_id', 'feed_position', 'name', 'description',
                            'source_category', 'source_categories', 'source_url',
                            'source_publisher', 'image_url', 'additional_image_urls',
                            'price_eur', 'sale_price_eur', 'availability', 'sku', 'isbn',
                            'ean', 'author', 'detail_payload',
                            'source_hash', 'source_genres', 'genre',
                            'feed_token', 'last_seen_at', 'updated_at',
                        ]
                    );
                });

            NovellaImportProduct::query()
                ->where('is_current', true)
                ->where(function ($query) use ($token) {
                    $query->whereNull('feed_token')->orWhere('feed_token', '!=', $token);
                })
                ->update(['is_current' => false, 'updated_at' => $seenAt]);

            NovellaImportProduct::query()
                ->where('feed_token', $token)
                ->where('is_current', false)
                ->update(['is_current' => true, 'updated_at' => $seenAt]);
        }, 3);
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
        ];
    }

    /**
     * Replace a live value only when it has not been enriched since the last
     * feed refresh. Older rows without a baseline are treated conservatively
     * after inspection and as plain feed rows before inspection.
     *
     * @return mixed
     */
    private function mergeFeedValue(
        ?NovellaImportProduct $live,
        string $field,
        $incoming,
        ?array $previousFeedBaseline
    ) {
        if ($live === null) {
            return $incoming;
        }

        if ($previousFeedBaseline === null) {
            return $live->checked_at === null ? $incoming : $live->{$field};
        }

        $previous = $previousFeedBaseline[$field] ?? null;

        return $this->sameFeedValue($live->{$field}, $previous)
            ? $incoming
            : $live->{$field};
    }

    private function sameFeedValue($live, $previous): bool
    {
        if (is_array($live) || is_array($previous)) {
            return array_values((array) $live) === array_values((array) $previous);
        }

        return $live === $previous || (string) $live === (string) $previous;
    }

    private function decodedStringList($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        return array_values(array_filter(array_map(
            fn ($item) => trim((string) $item),
            (array) $value
        ), fn (string $item) => $item !== ''));
    }

    private function assertNoIdentifierConflicts(string $token): void
    {
        $duplicateRemoteId = DB::table(self::STAGING_TABLE)
            ->where('feed_token', $token)
            ->whereNotNull('remote_product_id')
            ->groupBy('remote_product_id')
            ->havingRaw('COUNT(*) > 1')
            ->value('remote_product_id');
        if ($duplicateRemoteId !== null) {
            throw new RuntimeException(
                'Novella API sadrži više artikala s istim ID-em ' . $duplicateRemoteId . '.'
            );
        }

        $conflict = DB::table(self::STAGING_TABLE . ' as staging')
            ->join('novella_import_products as live', 'live.remote_product_id', '=', 'staging.remote_product_id')
            ->where('staging.feed_token', $token)
            ->whereColumn('live.external_id', '!=', 'staging.external_id')
            ->first(['staging.external_id', 'staging.remote_product_id', 'live.external_id as live_external_id']);
        if ($conflict) {
            throw new RuntimeException(sprintf(
                'Novella ID %s pripada i artiklu %s i artiklu %s. Postojeći podaci nisu promijenjeni.',
                $conflict->remote_product_id,
                $conflict->live_external_id,
                $conflict->external_id
            ));
        }
    }

    private function hasNextPage(array $parsed, int $page, int $itemCount, int $perPage): bool
    {
        if (array_key_exists('has_more', $parsed)) {
            return (bool) $parsed['has_more'];
        }
        if (isset($parsed['next_page'])) {
            return (int) $parsed['next_page'] > $page;
        }
        if (isset($parsed['total_pages'])) {
            return $page < (int) $parsed['total_pages'];
        }

        return $itemCount >= $perPage;
    }

    private function isImportable(array $item): bool
    {
        $url = $item['source_url'];
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $allowedHosts = array_map('strtolower', (array) config('novella_import.allowed_product_hosts', []));

        return $item['external_id'] !== ''
            && (int) $item['remote_product_id'] > 0
            && $item['name'] !== ''
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && $scheme === 'https'
            && in_array($host, $allowedHosts, true)
            && (float) $item['price_eur'] > 0;
    }

    private function assertSaneBookCount(int $staged, int $previousCurrent): void
    {
        $minimumExpected = max(1, (int) config('novella_import.minimum_expected_books', 250));
        if ($previousCurrent === 0 && $staged < $minimumExpected) {
            throw new RuntimeException(sprintf(
                'Novella API vratio je samo %d knjiga; očekuje se najmanje %d. Postojeći podaci nisu promijenjeni.',
                $staged,
                $minimumExpected
            ));
        }
        if ($previousCurrent > 0) {
            $ratio = min(1, max(0.1, (float) config('novella_import.minimum_current_ratio', 0.70)));
            $minimum = (int) ceil($previousCurrent * $ratio);
            if ($staged < $minimum) {
                throw new RuntimeException(sprintf(
                    'Novella feed pao je s %d na %d knjiga. Sigurnosna provjera spriječila je masovno povlačenje artikala.',
                    $previousCurrent,
                    $staged
                ));
            }
        }
    }

    private function writeSnapshot(array $items, string $syncedAt): void
    {
        $path = (string) config('novella_import.snapshot_path');
        $metadataPath = (string) config('novella_import.metadata_path');
        if ($path === '' || $metadataPath === '') {
            return;
        }

        $payload = $this->json([
            'synced_at' => $syncedAt,
            'count' => count($items),
            'items' => $items,
        ]);
        $metadata = $this->json([
            'synced_at' => $syncedAt,
            'count' => count($items),
            'bytes' => strlen($payload),
            'sha256' => hash('sha256', $payload),
        ]);

        $this->atomicWrite($path, $payload);
        $this->atomicWrite($metadataPath, $metadata);
    }

    private function atomicWrite(string $path, string $contents): void
    {
        File::ensureDirectoryExists(dirname($path));
        $temporary = tempnam(dirname($path), '.novella-');
        if ($temporary === false) {
            throw new RuntimeException('Nije moguće pripremiti lokalnu Novella snimku.');
        }

        try {
            if (file_put_contents($temporary, $contents, LOCK_EX) === false
                || ! rename($temporary, $path)) {
                throw new RuntimeException('Nije moguće spremiti lokalnu Novella snimku.');
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
        DB::table(self::STAGING_TABLE)
            ->where('created_at', '<', now()->subDay())
            ->delete();
    }

    private function validHash($value): ?string
    {
        $value = strtolower(trim((string) $value));

        return preg_match('/\A[a-f0-9]{64}\z/D', $value) ? $value : null;
    }

    private function identifier($value): ?string
    {
        $identifier = strtoupper(preg_replace('/[^0-9X]/i', '', (string) $value) ?? '');

        return $identifier !== '' ? $identifier : null;
    }

    private function stringList($values): array
    {
        if (! is_array($values)) {
            $values = [$values];
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($value) => $this->singleLine($value),
            $values
        ))));
    }

    private function singleLine($value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', strip_tags((string) $value)) ?? '';

        return preg_replace('/\s+/u', ' ', trim($value)) ?? '';
    }

    private function json($value): string
    {
        $encoded = json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );
        if ($encoded === false) {
            throw new RuntimeException('Novella podaci sadrže neispravne znakove.');
        }

        return $encoded;
    }
}
