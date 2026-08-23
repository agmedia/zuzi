<?php

namespace Tests\Unit;

use App\Services\Laguna\LagunaProductPageParser;
use Tests\TestCase;

class LagunaProductPageParserTest extends TestCase
{
    public function test_it_maps_all_supported_zuzi_book_fields_and_ignores_translator(): void
    {
        $html = '<html><head><script type="application/ld+json">' . json_encode([
            '@context' => 'https://schema.org',
            '@type' => ['Product', 'Book'],
            'name' => 'Test knjiga',
            'isbn' => '978-86-521-6434-9',
            'author' => [['@type' => 'Person', 'name' => 'Test Autor']],
            'genre' => 'Knjige za decu',
            'numberOfPages' => 344,
            'sku' => 'A335684',
        ], JSON_UNESCAPED_UNICODE) . '</script></head><body>'
            . $this->row('Format:', '13x20')
            . $this->row('Broj strana:', '344')
            . $this->row('Pismo:', 'Latinica')
            . $this->row('Povez:', 'Mek')
            . $this->row('Godina izdanja:', '21. avgust 2026.')
            . $this->row('ISBN:', '978-86-521-6434-9')
            . $this->row('Prevodilac:', 'Dijana Đelošević')
            . $this->row('Šifra proizvoda:', 'A335684')
            . '</body></html>';

        $parsed = app(LagunaProductPageParser::class)->parse($html);

        $this->assertSame('9788652164349', $parsed['isbn']);
        $this->assertSame('Test Autor', $parsed['author']);
        $this->assertSame('Knjige za decu', $parsed['genre']);
        $this->assertSame('13x20', $parsed['format']);
        $this->assertSame(344, $parsed['pages']);
        $this->assertSame('Latinica', $parsed['letter']);
        $this->assertSame('Meki', $parsed['binding']);
        $this->assertSame(2026, $parsed['publication_year']);
        $this->assertSame('A335684', $parsed['sku']);
        $this->assertArrayNotHasKey('translator', $parsed);
    }

    private function row(string $label, string $value): string
    {
        return '<div><span>' . $label . '</span><span>' . $value . '</span></div>';
    }
}
