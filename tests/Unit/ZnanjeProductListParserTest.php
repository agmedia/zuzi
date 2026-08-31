<?php

namespace Tests\Unit;

use App\Services\Znanje\ZnanjeProductListParser;
use Tests\TestCase;

class ZnanjeProductListParserTest extends TestCase
{
    public function test_it_parses_available_book_metadata_and_unicode_product_urls(): void
    {
        $html = $this->listing(
            500,
            'Knjige',
            501485,
            "Nikkin dnevnik – Priče ne baš pametne gospođice Sveznalice",
            '/product/nikkin-dnevnik-–-price-ne-bas-pametne-gospodjice-sveznalice/501485',
            "Nikkin dnevnik – Priče ne baš pametne gospođice Sveznalice"
        );

        $parsed = app(ZnanjeProductListParser::class)->parse($html, 500, 1);
        $item = $parsed['items'][0];

        $this->assertSame(1, $parsed['total']);
        $this->assertSame('501485', $item['external_id']);
        $this->assertSame(501485, $item['feed_position']);
        $this->assertStringContainsString('%E2%80%93', $item['source_url']);
        $this->assertSame(['Knjige', 'Književnost', 'Humor'], $item['source_categories']);
        $this->assertSame(['Književnost', 'Humor'], $item['source_genres']);
        $this->assertSame('Nakladnik d.o.o.', $item['source_publisher']);
        $this->assertSame('Autor, Drugi Autor', $item['author']);
        $this->assertSame(15.9, $item['price_eur']);
        $this->assertNull($item['sale_price_eur']);
        $this->assertSame('Meki', $item['binding']);
        $this->assertSame('Hrvatski', $item['language']);
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $item['source_hash']);
    }

    public function test_it_rejects_an_unavailable_card_even_if_the_filter_claims_available(): void
    {
        $html = str_replace(
            '<button type="button">U košaricu</button>',
            '<button type="button" disabled>Nije dostupno</button>',
            $this->listing(505, 'Strane knjige', 10, 'Book', '/product/book/10', 'Book')
        );

        $this->expectException(\App\Services\Znanje\ZnanjeTerminalException::class);
        app(ZnanjeProductListParser::class)->parse($html, 505, 1);
    }

    public function test_it_treats_tracking_discount_as_amount_and_visible_del_as_regular_price(): void
    {
        $html = $this->listing(500, 'Knjige', 545334, 'Akcijska', '/product/akcijska/545334', 'Akcijska');
        $html = str_replace('15.90, 0.00', '25.20, 2.80', $html);
        $html = str_replace('15,90 €', '<del>28,00 €</del> 25,20 €', $html);

        $item = app(ZnanjeProductListParser::class)->parse($html, 500, 1)['items'][0];

        $this->assertSame(28.0, $item['price_eur']);
        $this->assertSame(25.2, $item['sale_price_eur']);
    }

    private function listing(
        int $rootId,
        string $root,
        int $id,
        string $name,
        string $url,
        string $eventName
    ): string {
        $onclick = "sendFullEventForClick('select_item', 0, '{$id}', '{$eventName}', 15.90, 0.00, "
            . "'Nakladnik d.o.o.', '{$root}', 'Književnost', 'Humor', '', '0.00%', "
            . "'Autor, Drugi Autor', '2026', 'Hrvatski', 'meki uvez', '', '', 1);";

        return '<html><body>'
            . '<select id="sorting"><option value="date|desc" selected>Novi</option></select>'
            . '<select id="numberOfProducts"><option value="84" selected>84</option></select>'
            . '<input id="showAvailableOnly" checked>'
            . '<span itemprop="numberOfItems">1</span>'
            . '<div class="grid-item"><div class="product-card">'
            . '<a class="product-thumb" href="' . $url . '" onclick="' . htmlspecialchars($onclick, ENT_QUOTES) . '">'
            . '<img src="https://znanje.hr/product-images/a.jpg"></a>'
            . '<p class="product-author">Autor, Drugi Autor</p>'
            . '<h3 class="product-title"><span>' . htmlspecialchars($name) . '</span></h3>'
            . '<h4 class="product-price"><p>15,90 €</p></h4>'
            . '<div class="product-buttons"><button type="button">U košaricu</button></div>'
            . '</div></div></body></html>';
    }
}
