<?php

namespace Tests\Unit;

use App\Services\Znanje\ZnanjeProductDetailParser;
use Tests\TestCase;

class ZnanjeProductDetailParserTest extends TestCase
{
    public function test_it_extracts_book_identity_taxonomy_specs_and_eur_price(): void
    {
        $parsed = app(ZnanjeProductDetailParser::class)->parse(
            $this->html(),
            'https://znanje.hr/product/jedno-zlatno-ljeto/528463'
        );

        $this->assertSame('528463', $parsed['external_id']);
        $this->assertSame('Jedno zlatno ljeto', $parsed['name']);
        $this->assertSame(['Carley Fortune'], $parsed['authors']);
        $this->assertSame('9789535304128', $parsed['isbn']);
        $this->assertSame('Znanje', $parsed['source_publisher']);
        $this->assertSame(['Knjige', 'Književnost'], $parsed['source_categories']);
        $this->assertSame('150 x 228 mm', $parsed['format']);
        $this->assertSame(380, $parsed['pages']);
        $this->assertSame('Meki', $parsed['binding']);
        $this->assertSame(2026, $parsed['publication_year']);
        $this->assertSame('Hrvatski', $parsed['language']);
        $this->assertSame(16.9, $parsed['price_eur']);
        $this->assertTrue($parsed['available']);
        $this->assertStringContainsString('Opis knjige', $parsed['description']);
    }

    public function test_it_keeps_preorders_available_and_reads_the_main_crossed_out_price(): void
    {
        $html = str_replace(
            [
                '<meta itemprop="price" content="16.90">',
                'https://schema.org/InStock',
                '<meta itemprop="url"',
            ],
            [
                '<meta itemprop="price" content="25.20"><span class="h2"><del>28,00 €</del> 25,20 €</span>',
                'https://schema.org/PreOrder',
                '<meta itemprop="url"',
            ],
            $this->html()
        );

        $parsed = app(ZnanjeProductDetailParser::class)->parse(
            $html,
            'https://znanje.hr/product/jedno-zlatno-ljeto/528463'
        );

        $this->assertTrue($parsed['available']);
        $this->assertSame('in_stock', $parsed['availability']);
        $this->assertSame(28.0, $parsed['price_eur']);
        $this->assertSame(25.2, $parsed['sale_price_eur']);
    }

    public function test_it_keeps_a_comma_form_author_as_one_canonical_name(): void
    {
        $html = str_replace(
            '<span itemprop="author"><span itemprop="name">Carley Fortune</span></span>',
            '<h2 class="product-author"><span>Bataille</span> , <span>Josephine</span></h2>'
                . '<span itemprop="author"><span itemprop="name">Bataille</span></span>'
                . '<span itemprop="author"><span itemprop="name">Josephine</span></span>',
            $this->html()
        );

        $parsed = app(ZnanjeProductDetailParser::class)->parse(
            $html,
            'https://znanje.hr/product/jedno-zlatno-ljeto/528463'
        );

        $this->assertSame(['Bataille, Josephine'], $parsed['authors']);
        $this->assertSame('Bataille, Josephine', $parsed['author']);
    }

    private function html(): string
    {
        return '<html><body><ul>'
            . '<li itemprop="itemListElement"><a href="https://znanje.hr/kategorija-proizvoda/knjige/500"><span itemprop="name">Knjige</span></a></li>'
            . '<li itemprop="itemListElement"><a href="https://znanje.hr/kategorija-proizvoda/knjizevnost/500010"><span itemprop="name">Književnost</span></a></li>'
            . '</ul><span itemscope itemtype="https://schema.org/Product https://schema.org/Book">'
            . '<h1 class="product-name">Jedno zlatno ljeto</h1>'
            . '<meta itemprop="url" content="https://znanje.hr/product/jedno-zlatno-ljeto/528463">'
            . '<span itemprop="author"><span itemprop="name">Carley Fortune</span></span>'
            . '<span itemprop="offers"><meta itemprop="price" content="16.90"><link itemprop="availability" href="https://schema.org/InStock"></span>'
            . '<meta itemprop="sku" content="528463"><meta itemprop="isbn" content="9789535304128">'
            . '<meta itemprop="gtin13" content="9789535304128"><meta itemprop="numberOfPages" content="380">'
            . '<meta itemprop="datePublished" content="2026"><meta itemprop="inLanguage" content="hr">'
            . '<span itemprop="publisher"><span itemprop="name">Znanje</span></span>'
            . '<div><span class="text-medium">FORMAT</span><br>150 x 228 mm</div>'
            . '<div><span class="text-medium">UVEZ</span><br>meki</div>'
            . '<div class="product-gallery"><img src="https://znanje.hr/product-images/a.jpg"></div>'
            . '<p itemprop="description">Opis knjige.<script>alert(1)</script></p>'
            . '</span></body></html>';
    }
}
