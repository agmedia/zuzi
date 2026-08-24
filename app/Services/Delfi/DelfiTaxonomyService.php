<?php

namespace App\Services\Delfi;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class DelfiTaxonomyService
{
    private const ENDPOINT = 'https://delfi.rs/api/pc-frontend-api/get-filters-data';

    public function bookGenres(): array
    {
        $cacheKey = 'delfi-import-book-genres';
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        try {
            $response = Http::acceptJson()
                ->withOptions(['connect_timeout' => 5])
                ->timeout(15)
                ->withHeaders(['User-Agent' => 'Zuzi-Delfi-Importer/1.0'])
                ->get(self::ENDPOINT);
            if (! $response->successful()) {
                return [];
            }

            $genres = [];
            foreach ((array) $response->json('data.genresByCategories') as $category) {
                if (! is_array($category)
                    || ! in_array($category['category'] ?? null, ['Knjiga', 'Strana knjiga'], true)) {
                    continue;
                }
                foreach ((array) ($category['genres'] ?? []) as $genre) {
                    $name = trim((string) (is_array($genre) ? ($genre['genreName'] ?? '') : $genre));
                    if ($name !== '') {
                        $genres[$name] = true;
                    }
                }
            }
            $genres = array_keys($genres);
            natcasesort($genres);
            $genres = array_values($genres);
            if ($genres !== []) {
                Cache::put($cacheKey, $genres, now()->addDay());
            }

            return $genres;
        } catch (\Throwable) {
            // A temporary Delfi outage must not hide the taxonomy for a full day.
            return [];
        }
    }
}
