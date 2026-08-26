<?php

namespace Tests\Unit;

use App\Services\Novella\NovellaProductApiClient;
use App\Services\Novella\NovellaRetryableException;
use App\Services\Novella\NovellaTerminalException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class NovellaProductApiClientTest extends TestCase
{
    public function test_it_fetches_only_simple_books_in_stable_id_order(): void
    {
        Http::fake(['*' => Http::response([
            ['id' => 2156],
            ['id' => 2159],
        ], 200, ['X-WP-Total' => '2', 'X-WP-TotalPages' => '1'])]);

        $page = app(NovellaProductApiClient::class)->fetchPage(1, 2);

        $this->assertSame(2, $page['total']);
        $this->assertSame(2156, $page['items'][0]['id']);
        Http::assertSent(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return strtok($request->url(), '?') === 'https://novella.hr/wp-json/wc/store/v1/products'
                && $query === [
                    'category' => '63',
                    'type' => 'simple',
                    'per_page' => '2',
                    'page' => '1',
                    'orderby' => 'id',
                    'order' => 'asc',
                ];
        });
    }

    public function test_it_uses_the_configured_endpoint_and_book_category(): void
    {
        config()->set('novella_import.products_api_url', 'https://www.novella.hr/custom/products');
        config()->set('novella_import.book_category_id', 777);
        Http::fake(['*' => Http::response([], 200, [
            'X-WP-Total' => '0',
            'X-WP-TotalPages' => '0',
        ])]);

        app(NovellaProductApiClient::class)->fetchPage();

        Http::assertSent(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return strtok($request->url(), '?') === 'https://www.novella.hr/custom/products'
                && $query['category'] === '777'
                && $query['type'] === 'simple';
        });
    }

    public function test_it_rejects_a_configured_product_type_other_than_simple(): void
    {
        config()->set('novella_import.product_type', 'subscription');
        Http::fake();

        $this->expectException(InvalidArgumentException::class);

        try {
            app(NovellaProductApiClient::class)->fetchPage();
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_it_rejects_an_endpoint_outside_the_allowed_hosts(): void
    {
        config()->set('novella_import.products_api_url', 'https://example.test/products');
        Http::fake();

        $this->expectException(InvalidArgumentException::class);

        try {
            app(NovellaProductApiClient::class)->fetchPage();
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_it_rejects_invalid_pagination_without_a_request(): void
    {
        Http::fake();

        foreach ([[0, 100], [1, 0], [1, 101]] as [$page, $perPage]) {
            try {
                app(NovellaProductApiClient::class)->fetchPage($page, $perPage);
                $this->fail('Expected invalid pagination.');
            } catch (InvalidArgumentException) {
                // Expected.
            }
        }

        Http::assertNothingSent();
    }

    public function test_it_retries_server_errors(): void
    {
        Http::fakeSequence()
            ->push(['message' => 'busy'], 503)
            ->push([], 200, ['X-WP-Total' => '0', 'X-WP-TotalPages' => '0']);

        app(NovellaProductApiClient::class)->fetchPage();
        Http::assertSentCount(2);
    }

    public function test_it_propagates_rate_limits_without_immediate_retries(): void
    {
        Http::fake(['*' => Http::response(['message' => 'slow'], 429, ['Retry-After' => '17'])]);
        try {
            app(NovellaProductApiClient::class)->fetchPage();
            $this->fail('Expected retryable rate limit.');
        } catch (NovellaRetryableException $exception) {
            $this->assertSame(429, $exception->responseStatus());
            $this->assertSame(17, $exception->retryAfterSeconds());
        }
        Http::assertSentCount(1);
    }

    public function test_it_exposes_terminal_client_errors(): void
    {
        Http::fake(['*' => Http::response(['message' => 'missing'], 404)]);
        try {
            app(NovellaProductApiClient::class)->fetchPage();
            $this->fail('Expected terminal error.');
        } catch (NovellaTerminalException $exception) {
            $this->assertStringContainsString('HTTP 404', $exception->getMessage());
        }
    }

    public function test_it_treats_invalid_envelopes_as_retryable(): void
    {
        Http::fake(['*' => Http::response([], 200, [
            'X-WP-Total' => '100',
            'X-WP-TotalPages' => '1',
        ])]);
        $this->expectException(NovellaRetryableException::class);
        app(NovellaProductApiClient::class)->fetchPage();
    }
}
