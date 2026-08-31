<?php

namespace Tests\Unit;

use App\Services\Znanje\ZnanjeProductListClient;
use App\Services\Znanje\ZnanjeRetryableException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class ZnanjeProductListClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['znanje_import.request_delay_ms' => 0]);
    }

    public function test_it_fetches_only_the_exact_public_book_roots_with_stable_filters(): void
    {
        Http::fake(['*' => Http::response($this->validHtml(), 200, ['Content-Type' => 'text/html'])]);

        app(ZnanjeProductListClient::class)->fetchPage(500, 2);

        Http::assertSent(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return strtok($request->url(), '?') === 'https://znanje.hr/kategorija-proizvoda/knjige/500'
                && $query === [
                    'sort' => 'date',
                    'order' => 'desc',
                    'perPage' => '84',
                    'available' => 'true',
                    'pageNumber' => '1',
                ];
        });
    }

    public function test_it_rejects_unknown_roots_and_untrusted_catalog_hosts(): void
    {
        Http::fake();
        foreach ([499, 506] as $root) {
            try {
                app(ZnanjeProductListClient::class)->fetchPage($root);
                $this->fail('Expected unsupported root rejection.');
            } catch (InvalidArgumentException) {
                // Expected.
            }
        }
        config(['znanje_import.catalog_url' => 'https://evil.test/{slug}/{id}']);
        try {
            app(ZnanjeProductListClient::class)->fetchPage(500);
            $this->fail('Expected unsafe host rejection.');
        } catch (InvalidArgumentException) {
            // Expected.
        }
        Http::assertNothingSent();
    }

    public function test_it_exposes_rate_limits_as_retryable_without_hiding_retry_after(): void
    {
        Http::fake(['*' => Http::response('slow', 429, ['Retry-After' => '19'])]);

        try {
            app(ZnanjeProductListClient::class)->fetchPage(500);
            $this->fail('Expected rate limit.');
        } catch (ZnanjeRetryableException $exception) {
            $this->assertSame(429, $exception->responseStatus());
            $this->assertSame(19, $exception->retryAfterSeconds());
        }
        Http::assertSentCount(1);
    }

    public function test_it_hard_caps_feed_retries_even_when_environment_values_are_unsafe(): void
    {
        config([
            'znanje_import.feed_request_attempts' => 99,
            'znanje_import.feed_request_timeout' => 999,
            'znanje_import.feed_connect_timeout' => 999,
            'znanje_import.feed_retry_delay_ms' => 0,
        ]);
        Http::fake(['*' => Http::sequence()
            ->push('temporary', 503)
            ->push('temporary', 503)
            ->push('temporary', 503)]);

        try {
            app(ZnanjeProductListClient::class)->fetchPage(500);
            $this->fail('Expected exhausted retryable feed request.');
        } catch (ZnanjeRetryableException $exception) {
            $this->assertSame(503, $exception->responseStatus());
        }

        Http::assertSentCount(3);
    }

    private function validHtml(): string
    {
        return '<html><input id="showAvailableOnly" checked>'
            . '<span itemprop="numberOfItems">1</span><div class="product-card"></div></html>';
    }
}
