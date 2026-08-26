<?php

namespace Tests\Unit;

use App\Services\Novella\NovellaProductDetailParser;
use App\Services\Novella\NovellaTerminalException;
use Tests\TestCase;

class NovellaProductDetailParserTest extends TestCase
{
    public function test_it_extracts_specs_author_categories_description_and_safe_images(): void
    {
        $html = <<<'HTML'
<!doctype html><html><head>
<meta property="og:image" content="https://novella.hr/wp-content/uploads/fallback.jpg">
</head><body>
<h1 class="product_title entry-title">Agentica</h1>
<form class="cart"><input type="hidden" name="gtm4wp_product_data" value="{&quot;internal_id&quot;:22905,&quot;sku&quot;:&quot;9789534002261&quot;,&quot;item_brand&quot;:&quot;Katherine Center&quot;}"></form>
<div class="ms_book_details"><span><b>Jezik izvornika:</b></span><span>engleski</span></div>
<div class="ms_book_details"><span><b>Broj stranica:</b></span><span>356</span></div>
<div class="ms_book_details"><span><b>Prevoditelj:</b></span><span>Ira Martinović</span></div>
<div class="ms_book_details"><span><b>Uvez:</b></span><span>meki uvez</span></div>
<div class="ms_book_details"><span><b>Godina izdanja:</b></span><span>2026.</span></div>
<div class="ms_book_details"><span><b>Format knjige:</b></span><span>135x205</span></div>
<div class="ms_book_details"><span><b>ISBN:</b></span><span>9789534002261</span></div>
<div class="ms_book_details"><span><b>Barkod:</b></span><span>9789534002261</span></div>
<figure class="woocommerce-product-gallery__image"><a href="https://novella.hr/wp-content/uploads/agentica.jpg">cover</a></figure>
<figure class="woocommerce-product-gallery__image"><a href="https://evil.test/tracker.jpg">bad</a></figure>
<div class="elementor-tab-content" data-tab="1"><h5>Ona mu čuva leđa</h5><p>Siguran &amp; potpun opis.</p><script>alert(1)</script></div>
<script type="application/ld+json">{"@graph":[{"@type":"BreadcrumbList","itemListElement":[{"item":{"name":"Knjige","@id":"https://novella.hr/kategorija-proizvoda/knjige/"}},{"item":{"name":"Književnost","@id":"https://novella.hr/kategorija-proizvoda/knjige/knjizevnost/"}},{"item":{"name":"Kolekcije","@id":"https://novella.hr/kategorija-proizvoda/kolekcije/"}}]},{"@type":"Product","url":"https://novella.hr/proizvod/agentica/","sku":"9789534002261","image":"https://novella.hr/wp-content/uploads/schema.jpg"}]}</script>
</body></html>
HTML;

        $parsed = app(NovellaProductDetailParser::class)->parse($html);

        $this->assertSame(22905, $parsed['remote_product_id']);
        $this->assertSame('Agentica', $parsed['name']);
        $this->assertSame('Katherine Center', $parsed['author']);
        $this->assertSame('Ira Martinović', $parsed['translator']);
        $this->assertNull($parsed['language']);
        $this->assertSame('engleski', $parsed['detail_payload']['jezikizvornika']);
        $this->assertSame(356, $parsed['pages']);
        $this->assertSame('Meki', $parsed['binding']);
        $this->assertSame(2026, $parsed['publication_year']);
        $this->assertSame('135x205', $parsed['format']);
        $this->assertSame('9789534002261', $parsed['isbn']);
        $this->assertSame('9789534002261', $parsed['ean']);
        $this->assertSame(['Knjige', 'Književnost'], $parsed['source_categories']);
        $this->assertSame(['Književnost'], $parsed['source_genres']);
        $this->assertSame("Ona mu čuva leđa\nSiguran & potpun opis.", $parsed['description']);
        $this->assertStringNotContainsString('alert', $parsed['description']);
        $this->assertSame([
            'https://novella.hr/wp-content/uploads/agentica.jpg',
            'https://novella.hr/wp-content/uploads/schema.jpg',
        ], $parsed['images']);
    }

    public function test_it_rejects_html_that_is_not_a_product_page(): void
    {
        $this->expectException(NovellaTerminalException::class);
        app(NovellaProductDetailParser::class)->parse('<html><h1>Challenge</h1></html>');
    }
}
