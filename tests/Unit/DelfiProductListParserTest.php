<?php

namespace Tests\Unit;

use App\Services\Delfi\DelfiProductListParser;
use App\Services\Delfi\DelfiTerminalException;
use Tests\TestCase;

class DelfiProductListParserTest extends TestCase
{
    public function test_it_maps_a_live_shaped_domestic_book_page_and_pagination(): void
    {
        $payload = $this->page(3, [
            $this->book(251926, 'Vuk u gaćama', [
                '_id' => '6a8c06d11e2e7104dee0d9d7',
                'navId' => 'A335858',
                'barcode' => '9788682640608',
                'publisher' => 'Propolis Books',
                'authors' => [
                    ['authorName' => 'Vilfrid Lupan'],
                    ['authorName' => 'Majana Itojz'],
                ],
                'genres' => [['genreName' => 'Knjige za decu']],
                'subgenres' => [['subgenreName' => 'Stripovi i grafičke novele']],
                'attributes' => [
                    ['k' => 'numberOfPages', 'v' => '36'],
                    ['k' => 'format', 'v' => '22x29 cm'],
                ],
                'alphabets' => ['cyrillic'],
                'cover' => 'Tvrd',
                'releaseDate' => '2026.',
                'description' => null,
                'metaData' => ['description' => '<b>Duhovita</b> slikovnica &amp; strip.'],
                'priceList' => ['fullPrice' => 990, 'regularDiscountPrice' => 891],
                'isAvailable' => true,
                'status' => true,
                'quantity' => '7',
                'images' => [
                    'xxl' => '/_img/artikli/Knjiga/251927/org/vuk.png',
                    'm' => '/_img/artikli/Knjiga/251927/s/vuk.png',
                ],
                'updatedAtForApi' => '2026-08-24T11:45:41.639Z',
            ]),
            $this->book(251927, 'Matilda'),
        ]);

        $parsed = app(DelfiProductListParser::class)->parsePage($payload, 0, 2);
        $item = $parsed['items'][0];

        $this->assertSame(3, $parsed['total']);
        $this->assertSame(2, $parsed['next_skip']);
        $this->assertTrue($parsed['has_more']);
        $this->assertSame('6a8c06d11e2e7104dee0d9d7', $item['external_id']);
        $this->assertSame(251926, $item['remote_product_id']);
        $this->assertSame('A335858', $item['nav_id']);
        $this->assertSame('Vuk u gaćama', $item['name']);
        $this->assertSame('9788682640608', $item['isbn']);
        $this->assertSame('9788682640608', $item['ean']);
        $this->assertSame('Vilfrid Lupan, Majana Itojz', $item['author']);
        $this->assertSame('Propolis Books', $item['source_publisher']);
        $this->assertSame('Knjiga', $item['source_category']);
        $this->assertSame(['Knjige za decu', 'Stripovi i grafičke novele'], $item['source_genres']);
        $this->assertSame('22x29 cm', $item['format']);
        $this->assertSame(36, $item['pages']);
        $this->assertSame('Ćirilica', $item['letter']);
        $this->assertSame('Tvrdi', $item['binding']);
        $this->assertSame(2026, $item['publication_year']);
        $this->assertSame('', $item['description']);
        $this->assertSame('Duhovita slikovnica & strip.', $item['meta_description']);
        $this->assertSame(990.0, $item['price_rsd']);
        $this->assertSame(891.0, $item['sale_price_rsd']);
        $this->assertSame('in_stock', $item['availability']);
        $this->assertTrue($item['is_available']);
        $this->assertSame(7, $item['quantity']);
        $this->assertSame('https://delfi.rs/_img/artikli/Knjiga/251927/org/vuk.png', $item['image_url']);
    }

    public function test_it_maps_foreign_book_fields_and_finishes_the_last_page(): void
    {
        $product = $this->book(251936, 'The Coven', [
            'category' => 'Strana knjiga',
            'barcode' => '9781804994924',
            'attributes' => [
                ['k' => 'numberOfPages', 'v' => '320'],
                ['k' => 'format', 'v' => '19.7x2x12.7 cm'],
                ['k' => 'importedFrom', 'v' => 'Velika Britanija'],
            ],
            'cover' => 'Paperback',
            'releaseDate' => '2025.',
            'isAvailable' => false,
            'priceList' => ['fullPrice' => 1799, 'regularDiscountPrice' => 1799],
        ]);

        $parsed = app(DelfiProductListParser::class)->parsePage(
            $this->page(101, [$product]),
            100,
            100
        );
        $item = $parsed['items'][0];

        $this->assertFalse($parsed['has_more']);
        $this->assertNull($parsed['next_skip']);
        $this->assertSame('Engleski', $item['language']);
        $this->assertSame('Velika Britanija', $item['origin']);
        $this->assertSame('Meki', $item['binding']);
        $this->assertSame('out_of_stock', $item['availability']);
        $this->assertNull($item['sale_price_rsd']);
    }

    public function test_it_keeps_legacy_books_without_titles_and_advances_by_raw_page_size(): void
    {
        $parsed = app(DelfiProductListParser::class)->parsePage($this->page(3, [
            $this->book(79355, '', ['metaTitle' => '']),
            $this->book(79356, 'Sljedeća knjiga'),
        ]), 0, 2);

        $this->assertCount(2, $parsed['items']);
        $this->assertNull($parsed['items'][0]['name']);
        $this->assertNull($parsed['items'][0]['title']);
        $this->assertSame(2, $parsed['next_skip']);
        $this->assertTrue($parsed['has_more']);
    }

    public function test_it_accepts_both_exact_book_categories_but_rejects_other_products(): void
    {
        $parser = app(DelfiProductListParser::class);

        $mixed = $parser->parsePage($this->page(2, [
            $this->book(8, 'Domestic'),
            $this->book(9, 'Foreign', ['category' => 'Strana knjiga']),
        ]), 0, 2);
        $this->assertSame(['Knjiga', 'Strana knjiga'], array_column($mixed['items'], 'source_category'));

        try {
            $parser->parsePage($this->page(1, [
                $this->book(10, 'Igračka', ['category' => 'Igračke']),
            ]), 0, 1);
            $this->fail('Expected a terminal category error.');
        } catch (DelfiTerminalException $exception) {
            $this->assertStringContainsString('kategorij', $exception->getMessage());
        }
    }

    public function test_it_rejects_unsorted_or_duplicate_product_ids(): void
    {
        $parser = app(DelfiProductListParser::class);

        foreach ([
            [$this->book(11, 'Eleven'), $this->book(10, 'Ten')],
            [$this->book(10, 'Ten'), $this->book(10, 'Ten again')],
        ] as $products) {
            try {
                $parser->parsePage($this->page(2, $products), 0, 2);
                $this->fail('Expected invalid bulk ordering.');
            } catch (DelfiTerminalException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function test_it_rejects_inconsistent_pagination_and_missing_numeric_identity(): void
    {
        $parser = app(DelfiProductListParser::class);

        foreach ([
            $this->page(2, [$this->book(10, 'Only one')]),
            $this->page(1, [[
                'category' => 'Knjiga',
                'title' => 'No ID',
            ]]),
        ] as $payload) {
            try {
                $parser->parsePage($payload, 0, 2);
                $this->fail('Expected a terminal malformed-page error.');
            } catch (DelfiTerminalException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function test_external_id_is_optional_when_numeric_identity_is_present(): void
    {
        $parsed = app(DelfiProductListParser::class)->parsePage($this->page(1, [[
            'oldProductId' => 10,
            'category' => 'Knjiga',
            'title' => 'No external ID',
        ]]), 0, 1);

        $this->assertNull($parsed['items'][0]['external_id']);
        $this->assertSame(10, $parsed['items'][0]['remote_product_id']);
    }

    private function page(int $total, array $items): array
    {
        return ['data' => ['recordsTotal' => $total, 'data' => $items]];
    }

    private function book(int $id, string $title, array $overrides = []): array
    {
        return array_replace([
            '_id' => 'external-' . $id,
            'oldProductId' => $id,
            'title' => $title,
            'category' => 'Knjiga',
            'authors' => [],
            'genres' => [],
            'subgenres' => [],
            'attributes' => [],
            'images' => [],
            'isAvailable' => true,
            'status' => true,
        ], $overrides);
    }
}
