<?php

namespace App\Services\Delfi;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class DelfiTaxonomyService
{
    private const ENDPOINT = 'https://delfi.rs/api/pc-frontend-api/get-filters-data';
    private const CACHE_KEY = 'delfi-import-book-genres-by-category';

    public function bookGenres(): array
    {
        $genres = [];
        foreach ($this->bookGenresByCategory() as $categoryGenres) {
            foreach ($categoryGenres as $genre) {
                $genres[$genre] = true;
            }
        }

        $genres = array_keys($genres);
        natcasesort($genres);

        return array_values($genres);
    }

    public function bookGenresByCategory(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
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
                $categoryName = is_array($category) ? trim((string) ($category['category'] ?? '')) : '';
                if (! in_array($categoryName, ['Knjiga', 'Strana knjiga'], true)) {
                    continue;
                }
                foreach ((array) ($category['genres'] ?? []) as $genre) {
                    $name = trim((string) (is_array($genre) ? ($genre['genreName'] ?? '') : $genre));
                    if ($name !== '') {
                        $genres[$categoryName][$name] = true;
                    }
                }
            }

            foreach ($genres as $category => $categoryGenres) {
                $categoryGenres = array_keys($categoryGenres);
                natcasesort($categoryGenres);
                $genres[$category] = array_values($categoryGenres);
            }
            if ($genres !== []) {
                Cache::put(self::CACHE_KEY, $genres, now()->addDay());
            }

            return $genres;
        } catch (\Throwable) {
            // A temporary Delfi outage must not hide the taxonomy for a full day.
            return [];
        }
    }
}
