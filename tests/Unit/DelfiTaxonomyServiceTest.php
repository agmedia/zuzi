<?php

namespace Tests\Unit;

use App\Services\Delfi\DelfiTaxonomyService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DelfiTaxonomyServiceTest extends TestCase
{
    public function test_it_returns_unique_genres_only_for_both_book_categories(): void
    {
        Cache::forget('delfi-import-book-genres-by-category');
        Http::fake([
            'https://delfi.rs/api/pc-frontend-api/get-filters-data' => Http::response([
                'data' => ['genresByCategories' => [
                    ['category' => 'Knjiga', 'genres' => [
                        ['genreName' => 'Drama'],
                        ['genreName' => 'Fantastika'],
                    ]],
                    ['category' => 'Strana knjiga', 'genres' => [
                        ['genreName' => 'Fantasy'],
                        ['genreName' => 'Drama'],
                    ]],
                    ['category' => 'Gift', 'genres' => [['genreName' => 'Pokloni']]],
                ]],
            ], 200),
        ]);

        $service = app(DelfiTaxonomyService::class);

        $this->assertSame([
            'Knjiga' => ['Drama', 'Fantastika'],
            'Strana knjiga' => ['Drama', 'Fantasy'],
        ], $service->bookGenresByCategory());
        $this->assertSame(
            ['Drama', 'Fantastika', 'Fantasy'],
            $service->bookGenres()
        );
        Http::assertSentCount(1);
    }
}
