<?php

namespace Tests\Unit;

use App\Services\Novella\NovellaCategoryApiClient;
use App\Services\Novella\NovellaCategoryParser;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class NovellaCategoryApiClientTest extends TestCase
{
    public function test_it_fetches_categories_and_parser_keeps_only_the_books_tree(): void
    {
        Http::fake(['*' => Http::response([
            $this->category(63, 'Knjige', 0, '/kategorija-proizvoda/knjige/'),
            $this->category(84, 'Kolekcije', 0, '/kategorija-proizvoda/kolekcije/'),
            $this->category(101, 'Književnost', 63, '/kategorija-proizvoda/knjige/knjizevnost/'),
            $this->category(44, 'Klasične priče', 84, '/kategorija-proizvoda/kolekcije/klasicne-price/'),
        ], 200, ['X-WP-Total' => '4', 'X-WP-TotalPages' => '1'])]);

        $raw = app(NovellaCategoryApiClient::class)->fetchPage(1, 100);
        $parsed = app(NovellaCategoryParser::class)->parseCollection($raw);

        $this->assertSame(['Knjige', 'Književnost'], array_column($parsed['items'], 'name'));
        Http::assertSent(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return strtok($request->url(), '?') === 'https://novella.hr/wp-json/wc/store/v1/products/categories'
                && $query === [
                    'per_page' => '100',
                    'page' => '1',
                    'orderby' => 'name',
                    'order' => 'asc',
                ];
        });
    }

    public function test_it_accepts_a_complete_short_first_page_when_store_api_omits_total_headers(): void
    {
        Http::fake(['*' => Http::response([
            $this->category(63, 'Knjige', 0, '/kategorija-proizvoda/knjige/'),
            $this->category(101, 'Književnost', 63, '/kategorija-proizvoda/knjige/knjizevnost/'),
        ])]);

        $raw = app(NovellaCategoryApiClient::class)->fetchPage();

        $this->assertSame(2, $raw['total']);
        $this->assertSame(1, $raw['total_pages']);
    }

    public function test_parser_uses_the_configured_books_root_category_id(): void
    {
        config()->set('novella_import.book_category_id', 777);
        $payload = [
            'items' => [
                $this->category(63, 'Stara korijenska kategorija', 0, '/kategorija-proizvoda/staro/'),
                $this->category(777, 'Knjige', 0, '/kategorija-proizvoda/knjige/'),
                $this->category(778, 'Književnost', 777, '/kategorija-proizvoda/knjige/knjizevnost/'),
            ],
            'total' => 3,
            'total_pages' => 1,
            'page' => 1,
            'per_page' => 100,
        ];

        $parsed = app(NovellaCategoryParser::class)->parseCollection($payload);

        $this->assertSame([777, 778], array_column($parsed['items'], 'id'));
        $this->assertSame(['Knjige', 'Književnost'], array_column($parsed['items'], 'name'));
    }

    public function test_it_uses_the_configured_categories_endpoint(): void
    {
        config()->set('novella_import.categories_api_url', 'https://www.novella.hr/custom/categories');
        Http::fake(['*' => Http::response([])]);

        app(NovellaCategoryApiClient::class)->fetchPage();

        Http::assertSent(fn ($request) => strtok($request->url(), '?') === 'https://www.novella.hr/custom/categories');
    }

    public function test_it_rejects_an_unsafe_categories_endpoint(): void
    {
        config()->set('novella_import.categories_api_url', 'http://novella.hr/wp-json/categories');
        Http::fake();

        $this->expectException(InvalidArgumentException::class);

        try {
            app(NovellaCategoryApiClient::class)->fetchPage();
        } finally {
            Http::assertNothingSent();
        }
    }

    private function category(int $id, string $name, int $parent, string $path): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'slug' => mb_strtolower(str_replace(' ', '-', $name)),
            'parent' => $parent,
            'count' => 10,
            'permalink' => 'https://novella.hr' . $path,
        ];
    }
}
