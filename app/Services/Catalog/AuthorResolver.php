<?php

namespace App\Services\Catalog;

use App\Helpers\Helper;
use App\Models\Back\Catalog\Author;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Normalizer;

class AuthorResolver
{
    private const LOCK_SECONDS = 30;

    private const LOCK_WAIT_SECONDS = 10;

    private const MAX_NORMALIZED_KEY_LENGTH = 191;

    /**
     * Resolve the first author in an import value to one canonical author row.
     */
    public function resolve(?string $authors): int
    {
        $name = explode(',', (string) $authors, 2)[0];

        return $this->resolveName($name);
    }

    /**
     * Resolve one complete author name, including any significant commas.
     */
    public function resolveName(?string $name): int
    {
        $name = self::normalizeName((string) $name);
        if ($name === '') {
            return 0;
        }

        $normalizedKey = self::normalizedKey($name);
        $lockName = 'catalog-author:' . hash('sha256', $normalizedKey);

        return Cache::lock($lockName, self::LOCK_SECONDS)
            ->block(self::LOCK_WAIT_SECONDS, function () use ($name, $normalizedKey) {
                try {
                    return DB::transaction(function () use ($name, $normalizedKey) {
                        $existing = $this->findByNormalizedKey($normalizedKey);
                        if ($existing) {
                            return (int) $existing->id;
                        }

                        $slug = $this->uniqueSlug($name);
                        $author = Author::query()->create([
                            'letter' => Helper::resolveFirstLetter($name),
                            'title' => $name,
                            'normalized_title' => $normalizedKey,
                            'description' => null,
                            'meta_title' => $name,
                            'meta_description' => null,
                            'lang' => 'hr',
                            'sort_order' => 0,
                            'status' => 1,
                            'slug' => $slug,
                            'url' => config('settings.author_path') . '/' . $slug,
                        ]);

                        return (int) $author->id;
                    });
                } catch (QueryException $exception) {
                    if (! $this->isUniqueConstraintViolation($exception)) {
                        throw $exception;
                    }

                    // The unique normalized_title index is the final race
                    // safeguard. Re-query the winner after a competing insert.
                    return DB::transaction(function () use ($normalizedKey, $exception) {
                        $existing = $this->findByNormalizedKey($normalizedKey);
                        if (! $existing) {
                            throw $exception;
                        }

                        return (int) $existing->id;
                    });
                }
            });
    }

    /**
     * Normalize a single author name without transliterating or dropping punctuation.
     */
    public static function normalizeName(string $name): string
    {
        if (class_exists(Normalizer::class)) {
            $normalized = Normalizer::normalize($name, Normalizer::FORM_C);
            if (is_string($normalized)) {
                $name = $normalized;
            }
        }

        $collapsed = preg_replace('/[\p{Z}\s]+/u', ' ', $name);

        return trim(is_string($collapsed) ? $collapsed : $name);
    }

    /**
     * Build the persisted comparison key for one complete author name.
     */
    public static function normalizedKey(string $name): string
    {
        $normalized = mb_strtolower(self::normalizeName($name), 'UTF-8');
        if (mb_strlen($normalized, 'UTF-8') <= self::MAX_NORMALIZED_KEY_LENGTH) {
            return $normalized;
        }

        return mb_substr($normalized, 0, 126, 'UTF-8')
            . ':' . hash('sha256', $normalized);
    }

    private function findByNormalizedKey(string $normalizedKey): ?Author
    {
        return Author::query()
            ->where('normalized_title', $normalizedKey)
            ->orderBy('id')
            ->lockForUpdate()
            ->first();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'autor';
        $slug = $base;
        $counter = 2;

        while (Author::query()->where('slug', $slug)->lockForUpdate()->exists()) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        return $sqlState === '23505'
            || ($sqlState === '23000' && in_array($driverCode, [19, 1062], true));
    }
}
