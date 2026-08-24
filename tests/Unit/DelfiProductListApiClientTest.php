<?php

namespace Tests\Unit;

use App\Services\Delfi\DelfiProductListApiClient;
use App\Services\Delfi\DelfiRetryableException;
use App\Services\Delfi\DelfiTerminalException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class DelfiProductListApiClientTest extends TestCase
{
    public function test_it_fetches_a_sorted_page_from_the_fixed_endpoint(): void
    {
        Http::fake(['*' => Http::response($this->page(2, [
            ['oldProductId' => 251927, 'category' => 'Knjiga'],
            ['oldProductId' => 251926, 'category' => 'Knjiga'],
        ]))]);

        $payload = app(DelfiProductListApiClient::class)->fetchPage(0, 2);

        $this->assertSame(251927, $payload['data']['data'][0]['oldProductId']);
        Http::assertSent(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return strtok($request->url(), '?') === 'https://delfi.rs/api/pc-frontend-api/product-list'
                && $query === [
                    'limit' => '2',
                    'skip' => '0',
                    'sort' => 'oldProductId_asc',
                    'category' => 'Knjiga,Strana knjiga',
                ]
                && $request->hasHeader('Accept', 'application/json');
        });
    }

    public function test_it_accepts_only_valid_offsets_and_at_most_100_records(): void
    {
        Http::fake();
        $client = app(DelfiProductListApiClient::class);

        foreach ([
            [-1, 100],
            [0, 0],
            [0, 101],
        ] as [$skip, $limit]) {
            try {
                $client->fetchPage($skip, $limit);
                $this->fail('Expected invalid bulk request arguments.');
            } catch (InvalidArgumentException) {
                // Expected.
            }
        }

        Http::assertNothingSent();
    }

    public function test_it_retries_server_failures(): void
    {
        Http::fakeSequence()
            ->push(['message' => 'busy'], 503)
            ->push($this->page(0, []), 200);

        app(DelfiProductListApiClient::class)->fetchPage(0, 100);

        Http::assertSentCount(2);
    }

    public function test_it_does_not_retry_rate_limits(): void
    {
        Http::fake(['*' => Http::response(['message' => 'slow down'], 429, ['Retry-After' => '11'])]);
        try {
            app(DelfiProductListApiClient::class)->fetchPage();
            $this->fail('Expected a retryable rate-limit error.');
        } catch (DelfiRetryableException $exception) {
            $this->assertSame(429, $exception->responseStatus());
            $this->assertSame(11, $exception->retryAfterSeconds());
        }

        Http::assertSentCount(1);
    }

    public function test_it_exposes_a_terminal_client_error(): void
    {
        Http::fake(['*' => Http::response(['message' => 'missing'], 404)]);

        $this->expectException(DelfiTerminalException::class);
        $this->expectExceptionMessage('HTTP 404');

        app(DelfiProductListApiClient::class)->fetchPage();
    }

    public function test_it_treats_invalid_or_incomplete_pagination_as_retryable(): void
    {
        foreach ([
            '<html>challenge</html>',
            ['data' => ['data' => []]],
            $this->page(10, []),
            $this->page(1, [
                ['oldProductId' => 2],
                ['oldProductId' => 1],
            ]),
        ] as $responsePayload) {
            Http::fake(['*' => Http::response($responsePayload)]);

            try {
                app(DelfiProductListApiClient::class)->fetchPage(0, 1);
                $this->fail('Expected a retryable invalid-response error.');
            } catch (DelfiRetryableException $exception) {
                $this->assertSame(503, $exception->responseStatus());
            }
        }
    }

    public function test_it_retries_an_exhausted_connection_failure(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('connection timed out');
        });

        try {
            app(DelfiProductListApiClient::class)->fetchPage();
            $this->fail('Expected a retryable connection exception.');
        } catch (DelfiRetryableException $exception) {
            $this->assertSame(503, $exception->responseStatus());
            $this->assertSame(2, $exception->retryAfterSeconds());
        }

        $this->assertSame(3, $attempts);
    }

    private function page(int $total, array $items): array
    {
        return [
            'data' => [
                'recordsTotal' => $total,
                'data' => $items,
                'metadata' => ['duration' => '10ms'],
            ],
        ];
    }
}
