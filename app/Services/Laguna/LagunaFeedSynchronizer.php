<?php

namespace App\Services\Laguna;

use App\Models\Back\Catalog\LagunaImportProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LagunaFeedSynchronizer
{
    private LagunaFeedService $feed;

    public function __construct(LagunaFeedService $feed)
    {
        $this->feed = $feed;
    }

    public function refresh(): array
    {
        $metadata = $this->feed->refreshCache();
        $counts = $this->syncFile($metadata['path']);

        return $metadata + $counts;
    }

    public function syncFile(string $path): array
    {
        $token = (string) Str::uuid();
        $seenAt = now();
        $batch = [];
        $staged = 0;
        $skipped = 0;
        $duplicates = 0;
        $selectedScores = [];
        $feedPosition = 0;

        DB::transaction(function () use (
            $path,
            $token,
            $seenAt,
            &$batch,
            &$staged,
            &$skipped,
            &$duplicates,
            &$selectedScores,
            &$feedPosition
        ) {
            foreach ($this->feed->iterate($path) as $item) {
                if (! $this->isAllowedCategory($item['source_category'])) {
                    continue;
                }

                $feedPosition++;

                if (! $this->isImportable($item)) {
                    $skipped++;
                    continue;
                }

                $externalId = (string) $item['external_id'];
                $score = $this->preferenceScore($item);
                if (array_key_exists($externalId, $selectedScores)) {
                    $duplicates++;
                    if ($score <= $selectedScores[$externalId]) {
                        continue;
                    }
                } else {
                    $staged++;
                }
                $selectedScores[$externalId] = $score;

                $batch[$externalId] = [
                    'external_id' => $item['external_id'],
                    'feed_position' => $feedPosition,
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'product_type' => $item['product_type'],
                    'source_category' => $item['source_category'],
                    'source_url' => $item['source_url'],
                    'image_url' => $item['image_url'],
                    'additional_image_urls' => json_encode($item['additional_image_urls'], JSON_UNESCAPED_SLASHES),
                    'price_rsd' => $item['price_rsd'],
                    'sale_price_rsd' => $item['sale_price_rsd'],
                    'availability' => $item['availability'],
                    'source_hash' => $item['source_hash'],
                    'feed_token' => $token,
                    'is_current' => 1,
                    'last_seen_at' => $seenAt,
                    'created_at' => $seenAt,
                    'updated_at' => $seenAt,
                ];

                if (count($batch) >= 100) {
                    $this->upsert(array_values($batch));
                    $batch = [];
                }
            }

            if ($batch !== []) {
                $this->upsert(array_values($batch));
                $batch = [];
            }

            LagunaImportProduct::query()
                ->where('feed_token', '!=', $token)
                ->update(['is_current' => false, 'updated_at' => $seenAt]);
        }, 3);

        return [
            'staged' => $staged,
            'current' => LagunaImportProduct::query()->where('is_current', true)->count(),
            'retired' => LagunaImportProduct::query()->where('is_current', false)->count(),
            'skipped' => $skipped,
            'duplicates' => $duplicates,
        ];
    }

    private function upsert(array $rows): int
    {
        LagunaImportProduct::query()->upsert(
            $rows,
            ['external_id'],
            [
                'feed_position', 'name', 'description', 'product_type', 'source_category', 'source_url',
                'image_url', 'additional_image_urls', 'price_rsd', 'sale_price_rsd',
                'availability', 'source_hash', 'feed_token', 'is_current',
                'last_seen_at', 'updated_at',
            ]
        );

        return count($rows);
    }

    private function isAllowedCategory(?string $category): bool
    {
        $allowed = array_map(function ($value) {
            return mb_strtolower(trim((string) $value));
        }, (array) config('laguna_import.allowed_source_categories', ['Knjige']));

        return in_array(mb_strtolower(trim((string) $category)), $allowed, true);
    }

    private function isImportable(array $item): bool
    {
        return trim((string) ($item['external_id'] ?? '')) !== ''
            && trim((string) ($item['name'] ?? '')) !== ''
            && trim((string) ($item['source_url'] ?? '')) !== ''
            && (float) ($item['price_rsd'] ?? 0) > 0;
    }

    private function preferenceScore(array $item): int
    {
        $nameAndUrl = mb_strtolower(
            (string) ($item['name'] ?? '') . ' ' . (string) ($item['source_url'] ?? '')
        );
        $isSignedCopy = Str::contains($nameAndUrl, ['potpisan primerak', 'potpisani-primerak']);
        $inStock = strtolower((string) ($item['availability'] ?? '')) === 'in stock';

        return ($isSignedCopy ? 0 : 100) + ($inStock ? 10 : 0);
    }
}
