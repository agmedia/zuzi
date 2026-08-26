<?php

namespace Tests\Unit;

use App\Services\Novella\NovellaProductListParser;
use App\Services\Novella\NovellaTerminalException;
use Tests\TestCase;

class NovellaProductListParserTest extends TestCase
{
    public function test_it_maps_a_woo_book_and_excludes_collection_categories(): void
    {
        $parsed = app(NovellaProductListParser::class)->parseCollection([
            'items' => [$this->book(22905)],
            'total' => 1,
            'total_pages' => 1,
            'page' => 1,
            'per_page' => 100,
        ]);
        $item = $parsed['items'][0];

        $this->assertFalse($parsed['has_more']);
        $this->assertNull($parsed['next_page']);
        $this->assertSame('22905', $item['external_id']);
        $this->assertSame('Agentica', $item['name']);
        $this->assertSame(['Knjige', 'Književnost'], $item['source_categories']);
        $this->assertSame(['Književnost'], $item['source_genres']);
        $this->assertNotContains('Kolekcije', $item['source_categories']);
        $this->assertSame('Katherine Center', $item['author']);
        $this->assertSame('Novella', $item['source_publisher']);
        $this->assertSame('9789534002261', $item['isbn']);
        $this->assertSame(18.9, $item['price_eur']);
        $this->assertNull($item['sale_price_eur']);
        $this->assertSame('in_stock', $item['availability']);
        $this->assertSame('https://novella.hr/wp-content/uploads/agentica.jpg', $item['image_url']);
        $this->assertSame([], $item['additional_image_urls']);
        $this->assertStringNotContainsString('alert', $item['description']);
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $item['source_hash']);
    }

    public function test_it_maps_a_real_sale_from_minor_eur_units(): void
    {
        $book = $this->book(22906);
        $book['on_sale'] = true;
        $book['prices']['price'] = '1701';
        $book['prices']['sale_price'] = '1701';
        $book['prices']['regular_price'] = '1890';

        $item = app(NovellaProductListParser::class)->parseProduct($book);

        $this->assertSame(18.9, $item['price_eur']);
        $this->assertSame(17.01, $item['sale_price_eur']);
    }

    public function test_it_accepts_a_live_shaped_book_child_when_woo_omits_root_category_63(): void
    {
        $book = $this->book(12871);
        $book['categories'] = [
            [
                'id' => 94,
                'name' => 'Protagonisti Drugoga svjetskog rata',
                'link' => 'https://novella.hr/kategorija-proizvoda/knjige/protagonisti-drugoga-svjetskog-rata-knjige/',
            ],
            [
                'id' => 93,
                'name' => 'Protagonisti Drugoga svjetskog rata',
                'link' => 'https://novella.hr/kategorija-proizvoda/kolekcije/protagonisti-drugog-svjetskog-rata/',
            ],
        ];

        $item = app(NovellaProductListParser::class)->parseProduct($book);

        $this->assertSame('Knjige', $item['source_category']);
        $this->assertSame([
            'Knjige',
            'Protagonisti Drugoga svjetskog rata',
        ], $item['source_categories']);
    }

    public function test_it_rejects_non_books_unsafe_urls_and_duplicate_ordering(): void
    {
        $parser = app(NovellaProductListParser::class);
        foreach ([
            array_replace($this->book(1), ['permalink' => 'https://evil.test/proizvod/a/']),
            array_replace($this->book(1), ['categories' => [[
                'id' => 84,
                'name' => 'Kolekcije',
                'link' => 'https://novella.hr/kategorija-proizvoda/kolekcije/',
            ]]]),
        ] as $book) {
            try {
                $parser->parseProduct($book);
                $this->fail('Expected terminal malformed product.');
            } catch (NovellaTerminalException) {
                // Expected.
            }
        }

        $this->expectException(NovellaTerminalException::class);
        $parser->parseCollection([
            'items' => [$this->book(2), $this->book(2)],
            'total' => 2,
            'total_pages' => 1,
            'page' => 1,
            'per_page' => 2,
        ]);
    }

    private function book(int $id): array
    {
        return [
            'id' => $id,
            'name' => 'Agentica',
            'type' => 'simple',
            'permalink' => 'https://novella.hr/proizvod/agentica/',
            'sku' => '9789534002261',
            'description' => '<p>Siguran opis.</p><script>alert(1)</script>',
            'short_description' => '<p>Kratko.</p>',
            'on_sale' => false,
            'prices' => [
                'price' => '1890',
                'regular_price' => '1890',
                'sale_price' => '1890',
                'currency_code' => 'EUR',
                'currency_minor_unit' => 2,
            ],
            'images' => [
                ['src' => 'https://novella.hr/wp-content/uploads/agentica.jpg'],
                ['src' => 'https://evil.test/cover.jpg'],
            ],
            'categories' => [
                ['id' => 63, 'name' => 'Knjige', 'link' => 'https://novella.hr/kategorija-proizvoda/knjige/'],
                ['id' => 101, 'name' => 'Književnost', 'link' => 'https://novella.hr/kategorija-proizvoda/knjige/knjizevnost/'],
                ['id' => 84, 'name' => 'Kolekcije', 'link' => 'https://novella.hr/kategorija-proizvoda/kolekcije/'],
            ],
            'tags' => [[
                'name' => 'Katherine Center',
                'link' => 'https://novella.hr/autor/katherine-center/',
            ]],
            'attributes' => [[
                'name' => 'Izdavač',
                'terms' => [['name' => 'Novella']],
            ]],
            'is_in_stock' => true,
            'is_on_backorder' => false,
        ];
    }
}
