<?php

namespace Tests\Unit;

use App\Services\Znanje\ZnanjeProductPageClient;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class ZnanjeProductPageClientTest extends TestCase
{
    public function test_it_fetches_only_trusted_product_pages_without_redirects(): void
    {
        config(['znanje_import.request_delay_ms' => 0]);
        Http::fake(['*' => Http::response(
            '<span itemtype="https://schema.org/Product"><h1 class="product-name">Knjiga</h1></span>',
            200,
            ['Content-Type' => 'text/html']
        )]);

        app(ZnanjeProductPageClient::class)->fetch('https://znanje.hr/product/knjiga/123');
        Http::assertSent(fn ($request) => $request->url() === 'https://znanje.hr/product/knjiga/123');
    }

    public function test_it_rejects_ajax_external_and_query_urls_before_requesting(): void
    {
        Http::fake();
        foreach ([
            'https://znanje.hr/ajax/product/123',
            'https://evil.test/product/knjiga/123',
            'http://znanje.hr/product/knjiga/123',
            'https://znanje.hr/product/knjiga/123?next=evil',
        ] as $url) {
            try {
                app(ZnanjeProductPageClient::class)->fetch($url);
                $this->fail('Expected unsafe URL rejection.');
            } catch (InvalidArgumentException) {
                // Expected.
            }
        }
        Http::assertNothingSent();
    }

    public function test_it_encodes_a_literal_unicode_product_slug_before_requesting(): void
    {
        config(['znanje_import.request_delay_ms' => 0]);
        Http::fake(['*' => Http::response(
            '<span itemtype="https://schema.org/Product"><h1 class="product-name">Knjiga</h1></span>',
            200,
            ['Content-Type' => 'text/html']
        )]);

        app(ZnanjeProductPageClient::class)->fetch('https://znanje.hr/product/knjiga–test/123');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'knjiga%E2%80%93test/123'));
    }
}
