<?php

namespace Tests\Unit;

use App\Services\Delfi\DelfiProductDetailParser;
use Tests\TestCase;

class DelfiProductDetailParserTest extends TestCase
{
    public function test_it_maps_the_delfi_overview_payload_to_zuzi_book_fields(): void
    {
        $payload = [
            'data' => [
                'product' => [
                    '_id' => '6a87ee201e2e7104ded45e44',
                    'oldProductId' => 251908,
                    'navId' => 'A335680',
                    'barcode' => '978-86-521-6212-3',
                    'publisher' => 'Laguna',
                    'authors' => [
                        ['authorName' => 'Geri Penton'],
                        ['authorName' => 'Doti Saton'],
                    ],
                    'genres' => [['genreName' => 'Knjige za decu']],
                    'subgenres' => [['subgenreName' => 'Fantastika']],
                    'attributes' => [
                        ['k' => 'numberOfPages', 'v' => '298'],
                        ['k' => 'format', 'v' => '13x20 cm'],
                    ],
                    'alphabets' => ['cyrillic'],
                    'cover' => 'Mek',
                    'releaseDate' => '21. avgust 2026.',
                    'defaultReleaseDate' => '2026-08-21T00:00:00.000Z',
                    'category' => 'Knjiga',
                    'description' => '<p><em>Nisu ve&scaron;tice.</em></p><p>Časna reč!</p>',
                    'images' => [
                        'xxl' => '/_img/artikli/Knjiga/251908/org/nevestice.png',
                        'm' => '/_img/artikli/Knjiga/251908/s/nevestice.png',
                    ],
                    'imgGallery' => [[
                        'originalImg' => '/_img/artikli/Knjiga/251908/galerija/original-nevestice.png',
                    ]],
                ],
            ],
        ];

        $parsed = app(DelfiProductDetailParser::class)->parse($payload);

        $this->assertSame('6a87ee201e2e7104ded45e44', $parsed['external_id']);
        $this->assertSame(251908, $parsed['remote_product_id']);
        $this->assertSame('9788652162123', $parsed['isbn']);
        $this->assertSame('9788652162123', $parsed['ean']);
        $this->assertSame('A335680', $parsed['nav_id']);
        $this->assertSame('A335680', $parsed['sku']);
        $this->assertSame('Geri Penton, Doti Saton', $parsed['author']);
        $this->assertSame(['Geri Penton', 'Doti Saton'], $parsed['authors']);
        $this->assertSame('Laguna', $parsed['publisher']);
        $this->assertSame(['Knjige za decu', 'Fantastika'], $parsed['source_genres']);
        $this->assertSame('13x20 cm', $parsed['format']);
        $this->assertSame(298, $parsed['pages']);
        $this->assertSame('Ćirilica', $parsed['letter']);
        $this->assertSame('Meki', $parsed['binding']);
        $this->assertSame(2026, $parsed['publication_year']);
        $this->assertSame("Nisu veštice.\nČasna reč!", $parsed['description']);
        $this->assertSame('Srpski', $parsed['language']);
        $this->assertSame('Beograd', $parsed['origin']);
        $this->assertSame(
            'https://delfi.rs/_img/artikli/Knjiga/251908/org/nevestice.png',
            $parsed['image']
        );
        $this->assertSame([
            'https://delfi.rs/_img/artikli/Knjiga/251908/org/nevestice.png',
            'https://delfi.rs/_img/artikli/Knjiga/251908/galerija/original-nevestice.png',
        ], $parsed['images']);
    }

    public function test_it_uses_foreign_book_attributes_and_normalizes_paperback(): void
    {
        $parsed = app(DelfiProductDetailParser::class)->parse([
            'oldProductId' => 251917,
            'barcode' => '9781911746287',
            'category' => 'Strana knjiga',
            'cover' => 'Paperback',
            'releaseDate' => '2025.',
            'attributes' => [
                ['k' => 'languageOfEdition', 'v' => 'English'],
                ['k' => 'importedFrom', 'v' => 'Velika Britanija'],
            ],
        ]);

        $this->assertSame('Meki', $parsed['binding']);
        $this->assertSame('Engleski', $parsed['language']);
        $this->assertSame('Velika Britanija', $parsed['origin']);
        $this->assertSame(2025, $parsed['publication_year']);
    }

    public function test_it_keeps_a_non_isbn_barcode_only_as_ean(): void
    {
        $parsed = app(DelfiProductDetailParser::class)->parse([
            'oldProductId' => 251999,
            'barcode' => '1234567890128',
            'category' => 'Knjiga',
        ]);

        $this->assertNull($parsed['isbn']);
        $this->assertSame('1234567890128', $parsed['ean']);
    }
}
