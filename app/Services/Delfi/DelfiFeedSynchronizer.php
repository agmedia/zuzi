<?php

namespace App\Services\Delfi;

use App\Models\Back\Catalog\DelfiImportProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DelfiFeedSynchronizer
{
    private const STAGING_TABLE = 'delfi_import_feed_rows';

    private const LIVE_TABLE = 'delfi_import_products';

    private DelfiFeedService $feed;

    public function __construct(DelfiFeedService $feed)
    {
        $this->feed = $feed;
    }

    public function refresh(): array
    {
        $download = $this->feed->download();

        if (($download['not_modified'] ?? false)
            && DelfiImportProduct::query()->where('is_current', true)->exists()) {
            return array_merge($download, $this->databaseCounts(), [
                'staged' => (int) ($download['staged'] ?? $download['current'] ?? 0),
                'skipped' => 0,
                'skipped_category' => 0,
                'duplicates' => 0,
            ]);
        }

        try {
            $counts = $this->syncFile((string) $download['path'], true);

            if ($download['temporary'] ?? false) {
                $download = $this->feed->promote($download, $counts);
            } else {
                $this->feed->recordSyncMetadata($counts);
            }

            return array_merge($download, $counts);
        } catch (\Throwable $exception) {
            $this->feed->discard($download);
            throw $exception;
        }
    }

    public function syncFile(string $path, bool $enforceSanity = false): array
    {
        $this->deleteAbandonedStagingRows();
        $previousCurrent = DelfiImportProduct::query()->where('is_current', true)->count();
        $token = (string) Str::uuid();
        $seenAt = now();
        $batch = [];
        $seenExternalIds = [];
        $staged = 0;
        $skipped = 0;
        $skippedCategory = 0;
        $duplicates = 0;
        $feedPosition = 0;
        $total = 0;
        $batchSize = max(10, min(500, (int) config('delfi_import.sync_batch_size', 100)));

        try {
            foreach ($this->feed->iterate($path) as $item) {
                $total++;
                $feedPosition++;

                if (! $this->isAllowedCategory($item['source_category'] ?? null)) {
                    $skippedCategory++;
                    continue;
                }

                if (! $this->isImportable($item)) {
                    $skipped++;
                    continue;
                }

                $externalId = (string) $item['external_id'];
                if (isset($seenExternalIds[$externalId])) {
                    $duplicates++;
                    continue;
                }
                $seenExternalIds[$externalId] = true;
                $staged++;

                $batch[] = [
                    'feed_token' => $token,
                    'external_id' => $externalId,
                    'remote_product_id' => $item['remote_product_id'],
                    'feed_position' => $feedPosition,
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'source_category' => $item['source_category'],
                    'source_publisher' => $item['source_publisher'],
                    'source_url' => $item['source_url'],
                    'image_url' => $item['image_url'],
                    'additional_image_urls' => json_encode(
                        $item['additional_image_urls'],
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    ),
                    'price_rsd' => $item['price_rsd'],
                    'sale_price_rsd' => $item['sale_price_rsd'],
                    'availability' => $item['availability'],
                    'author' => $item['author'],
                    'source_hash' => $item['source_hash'],
                    'created_at' => $seenAt,
                    'updated_at' => $seenAt,
                ];

                if (count($batch) >= $batchSize) {
                    $this->upsertStaging($batch);
                    $batch = [];
                }
            }

            if ($batch !== []) {
                $this->upsertStaging($batch);
            }

            if ($staged < 1) {
                throw new RuntimeException('Delfi feed ne sadrži nijednu valjanu knjigu. Postojeći podaci nisu promijenjeni.');
            }

            if ($enforceSanity) {
                $this->assertSaneBookCount($staged, $previousCurrent);
            }

            $this->mergeStagingIntoLive($token, $seenAt, $batchSize);

            return array_merge($this->databaseCounts(), [
                'total' => $total,
                'staged' => $staged,
                'skipped' => $skipped,
                'skipped_category' => $skippedCategory,
                'duplicates' => $duplicates,
            ]);
        } finally {
            $this->deleteStagingToken($token);
        }
    }

    private function upsertStaging(array $rows): void
    {
        DB::table(self::STAGING_TABLE)->upsert(
            $rows,
            ['feed_token', 'external_id'],
            [
                'remote_product_id',
                'feed_position',
                'name',
                'description',
                'source_category',
                'source_publisher',
                'source_url',
                'image_url',
                'additional_image_urls',
                'price_rsd',
                'sale_price_rsd',
                'availability',
                'author',
                'source_hash',
                'updated_at',
            ]
        );
    }

    private function mergeStagingIntoLive(string $token, $seenAt, int $batchSize): void
    {
        DB::transaction(function () use ($token, $seenAt, $batchSize) {
            $this->assertNoIdentifierConflicts($token);

            if (DB::connection()->getDriverName() === 'mysql') {
                $this->mergeStagingWithMySql($token);
            } else {
                $this->mergeStagingPortably($token, $batchSize);
            }

            DelfiImportProduct::query()
                ->where('is_current', true)
                ->where(function ($query) use ($token) {
                    $query->whereNull('feed_token')->orWhere('feed_token', '!=', $token);
                })
                ->update(['is_current' => false, 'updated_at' => $seenAt]);

            DelfiImportProduct::query()
                ->where('feed_token', $token)
                ->where('is_current', false)
                ->update(['is_current' => true, 'updated_at' => $seenAt]);
        }, 3);
    }

    private function mergeStagingWithMySql(string $token): void
    {
        DB::statement(
            'INSERT INTO `delfi_import_products` ('
            . '`external_id`, `remote_product_id`, `feed_position`, `name`, `description`, '
            . '`source_category`, `source_publisher`, `source_url`, `image_url`, `additional_image_urls`, '
            . '`price_rsd`, `sale_price_rsd`, `availability`, `author`, `source_hash`, `feed_token`, '
            . '`is_current`, `last_seen_at`, `created_at`, `updated_at`'
            . ') SELECT '
            . '`external_id`, `remote_product_id`, `feed_position`, `name`, `description`, '
            . '`source_category`, `source_publisher`, `source_url`, `image_url`, `additional_image_urls`, '
            . '`price_rsd`, `sale_price_rsd`, `availability`, `author`, `source_hash`, `feed_token`, '
            . '0, `updated_at`, `created_at`, `updated_at` '
            . 'FROM `delfi_import_feed_rows` WHERE `feed_token` = ? '
            . 'ON DUPLICATE KEY UPDATE '
            . '`remote_product_id` = VALUES(`remote_product_id`), '
            . '`feed_position` = VALUES(`feed_position`), '
            . '`name` = VALUES(`name`), '
            . '`source_category` = VALUES(`source_category`), '
            . '`source_url` = VALUES(`source_url`), '
            . '`price_rsd` = VALUES(`price_rsd`), '
            . '`sale_price_rsd` = VALUES(`sale_price_rsd`), '
            . '`availability` = VALUES(`availability`), '
            . '`source_hash` = VALUES(`source_hash`), '
            . '`feed_token` = VALUES(`feed_token`), '
            . '`last_seen_at` = VALUES(`last_seen_at`), '
            . '`updated_at` = VALUES(`updated_at`)',
            [$token]
        );
    }

    private function mergeStagingPortably(string $token, int $batchSize): void
    {
        DB::table(self::STAGING_TABLE)
            ->where('feed_token', $token)
            ->orderBy('id')
            ->chunkById($batchSize, function ($rows) {
                $records = [];
                foreach ($rows as $row) {
                    $records[] = [
                        'external_id' => $row->external_id,
                        'remote_product_id' => $row->remote_product_id,
                        'feed_position' => $row->feed_position,
                        'name' => $row->name,
                        'description' => $row->description,
                        'source_category' => $row->source_category,
                        'source_publisher' => $row->source_publisher,
                        'source_url' => $row->source_url,
                        'image_url' => $row->image_url,
                        'additional_image_urls' => $row->additional_image_urls,
                        'price_rsd' => $row->price_rsd,
                        'sale_price_rsd' => $row->sale_price_rsd,
                        'availability' => $row->availability,
                        'author' => $row->author,
                        'source_hash' => $row->source_hash,
                        'feed_token' => $row->feed_token,
                        'is_current' => 0,
                        'last_seen_at' => $row->updated_at,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ];
                }

                DelfiImportProduct::query()->upsert(
                    $records,
                    ['external_id'],
                    [
                        'remote_product_id',
                        'feed_position',
                        'name',
                        'source_category',
                        'source_url',
                        'price_rsd',
                        'sale_price_rsd',
                        'availability',
                        'source_hash',
                        'feed_token',
                        'last_seen_at',
                        'updated_at',
                    ]
                );
            });
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
                'Delfi feed sadrži više artikala s istim internim ID-em ' . $duplicateRemoteId . '.'
            );
        }

        $conflict = DB::table(self::STAGING_TABLE . ' as staging')
            ->join(self::LIVE_TABLE . ' as live', 'live.remote_product_id', '=', 'staging.remote_product_id')
            ->where('staging.feed_token', $token)
            ->whereColumn('live.external_id', '!=', 'staging.external_id')
            ->first(['staging.external_id', 'staging.remote_product_id', 'live.external_id as live_external_id']);

        if ($conflict) {
            throw new RuntimeException(sprintf(
                'Delfi ID %s pripada i artiklu %s i artiklu %s. Postojeći podaci nisu promijenjeni.',
                $conflict->remote_product_id,
                $conflict->live_external_id,
                $conflict->external_id
            ));
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
        // A hard-killed PHP process cannot reach the token cleanup in finally.
        // These rows are never promoted without their token, so they are safe to purge.
        DB::table(self::STAGING_TABLE)
            ->where('created_at', '<', now()->subDay())
            ->delete();
    }

    private function isAllowedCategory(?string $category): bool
    {
        return in_array(
            trim((string) $category),
            array_values((array) config('delfi_import.allowed_source_categories', ['Knjiga', 'Strana knjiga'])),
            true
        );
    }

    private function isImportable(array $item): bool
    {
        $url = trim((string) ($item['source_url'] ?? ''));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $allowedHosts = array_map('strtolower', (array) config('delfi_import.allowed_product_hosts', ['delfi.rs']));

        return trim((string) ($item['external_id'] ?? '')) !== ''
            && (int) ($item['remote_product_id'] ?? 0) > 0
            && trim((string) ($item['name'] ?? '')) !== ''
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && $scheme === 'https'
            && in_array($host, $allowedHosts, true)
            && (float) ($item['price_rsd'] ?? 0) > 0;
    }

    private function databaseCounts(): array
    {
        return [
            'current' => DelfiImportProduct::query()->where('is_current', true)->count(),
            'retired' => DelfiImportProduct::query()->where('is_current', false)->count(),
        ];
    }

    private function assertSaneBookCount(int $staged, int $previousCurrent): void
    {
        $minimumExpected = max(1, (int) config('delfi_import.minimum_expected_books', 100000));
        if ($previousCurrent === 0 && $staged < $minimumExpected) {
            throw new \RuntimeException(sprintf(
                'Delfi feed sadrži samo %d knjiga; očekuje se najmanje %d. Postojeći podaci nisu promijenjeni.',
                $staged,
                $minimumExpected
            ));
        }

        if ($previousCurrent > 0) {
            $ratio = min(1, max(0.1, (float) config('delfi_import.minimum_current_ratio', 0.80)));
            $minimum = (int) ceil($previousCurrent * $ratio);
            if ($staged < $minimum) {
                throw new \RuntimeException(sprintf(
                    'Delfi feed pao je s %d na %d knjiga. Sigurnosna provjera spriječila je masovno povlačenje artikala.',
                    $previousCurrent,
                    $staged
                ));
            }
        }
    }
}
