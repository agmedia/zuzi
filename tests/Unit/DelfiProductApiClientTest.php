<?php

namespace Tests\Unit;

use App\Services\Delfi\DelfiProductApiClient;
use App\Services\Delfi\DelfiRetryableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class DelfiProductApiClientTest extends TestCase
{
    public function test_it_fetches_an_overview_only_from_the_fixed_delfi_endpoint(): void
    {
        Http::fake([
            'https://delfi.rs/api/pc-frontend-api/overview/251908' => Http::response([
                'data' => ['product' => ['oldProductId' => 251908]],
            ]),
        ]);

        $payload = app(DelfiProductApiClient::class)->fetch('251908');

        $this->assertSame(251908, $payload['data']['product']['oldProductId']);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://delfi.rs/api/pc-frontend-api/overview/251908'
                && $request->hasHeader('Accept', 'application/json');
        });
    }

    public function test_it_rejects_non_numeric_ids_before_sending_a_request(): void
    {
        Http::fake();

        try {
            app(DelfiProductApiClient::class)->fetch('251908/../../evil.test');
            $this->fail('Expected an invalid product ID exception.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('pozitivan cijeli broj', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_it_retries_server_errors(): void
    {
        Http::fakeSequence()
            ->push(['message' => 'busy'], 503)
            ->push(['message' => 'still busy'], 502)
            ->push(['data' => ['product' => ['oldProductId' => 251908]]], 200);

        $payload = app(DelfiProductApiClient::class)->fetch(251908);

        $this->assertSame(251908, $payload['data']['product']['oldProductId']);
        Http::assertSentCount(3);
    }

    public function test_it_exposes_an_exhausted_rate_limit_as_a_typed_retryable_error(): void
    {
        Http::fake([
            '*' => Http::response(['message' => 'slow down'], 429, ['Retry-After' => '9']),
        ]);

        try {
            app(DelfiProductApiClient::class)->fetch(251908);
            $this->fail('Expected a retryable Delfi exception.');
        } catch (DelfiRetryableException $exception) {
            $this->assertSame(429, $exception->responseStatus());
            $this->assertSame(9, $exception->retryAfterSeconds());
        }

        Http::assertSentCount(1);
    }

    public function test_it_does_not_retry_a_terminal_client_error(): void
    {
        Http::fake(['*' => Http::response(['message' => 'missing'], 404)]);

        try {
            app(DelfiProductApiClient::class)->fetch(251908);
            $this->fail('Expected a terminal Delfi API exception.');
        } catch (RuntimeException $exception) {
            $this->assertNotInstanceOf(DelfiRetryableException::class, $exception);
            $this->assertStringContainsString('HTTP 404', $exception->getMessage());
        }

        Http::assertSentCount(1);
    }

    public function test_it_treats_an_invalid_json_response_as_retryable(): void
    {
        Http::fake(['*' => Http::response('<html>Cloudflare challenge</html>', 200)]);

        try {
            app(DelfiProductApiClient::class)->fetch(251908);
            $this->fail('Expected a retryable invalid-response exception.');
        } catch (DelfiRetryableException $exception) {
            $this->assertSame(503, $exception->responseStatus());
        }

        Http::assertSentCount(1);
    }

    public function test_it_exposes_an_exhausted_connection_failure_as_retryable_service_unavailable(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('connection timed out');
        });

        try {
            app(DelfiProductApiClient::class)->fetch(251908);
            $this->fail('Expected a retryable connection exception.');
        } catch (DelfiRetryableException $exception) {
            $this->assertSame(503, $exception->responseStatus());
            $this->assertSame(2, $exception->retryAfterSeconds());
        }

        $this->assertSame(3, $attempts);
    }
}
