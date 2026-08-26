<?php

namespace Tests\Unit;

use App\Services\Novella\NovellaProductPageClient;
use App\Services\Novella\NovellaRetryableException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class NovellaProductPageClientTest extends TestCase
{
    public function test_it_fetches_only_a_trusted_product_page_without_following_redirects(): void
    {
        Http::fake(['*' => Http::response(
            '<html><h1 class="product_title entry-title">Agentica</h1></html>',
            200,
            ['Content-Type' => 'text/html; charset=UTF-8']
        )]);

        $html = app(NovellaProductPageClient::class)->fetch('https://novella.hr/proizvod/agentica/');

        $this->assertStringContainsString('Agentica', $html);
        Http::assertSent(fn ($request) => $request->url() === 'https://novella.hr/proizvod/agentica/');
    }

    public function test_it_rejects_untrusted_urls_before_requesting_them(): void
    {
        Http::fake();

        foreach ([
            'https://evil.test/proizvod/agentica/',
            'https://novella.hr@evil.test/proizvod/agentica/',
            'http://novella.hr/proizvod/agentica/',
            'https://novella.hr/proizvod/agentica/?redirect=evil',
        ] as $url) {
            try {
                app(NovellaProductPageClient::class)->fetch($url);
                $this->fail('Expected unsafe URL rejection.');
            } catch (InvalidArgumentException) {
                // Expected.
            }
        }

        Http::assertNothingSent();
    }

    public function test_it_retries_server_errors(): void
    {
        Http::fakeSequence()
            ->push('busy', 503)
            ->push('<h1 class="product_title">Agentica</h1>', 200, ['Content-Type' => 'text/html']);

        app(NovellaProductPageClient::class)->fetch('https://novella.hr/proizvod/agentica/');
        Http::assertSentCount(2);
    }

    public function test_it_treats_challenge_html_as_retryable(): void
    {
        Http::fake(['*' => Http::response('<html>Cloudflare challenge</html>', 200, [
            'Content-Type' => 'text/html',
        ])]);
        $this->expectException(NovellaRetryableException::class);
        app(NovellaProductPageClient::class)->fetch('https://novella.hr/proizvod/agentica/');
    }
}
