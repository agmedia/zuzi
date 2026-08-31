<?php

namespace App\Services\Znanje;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use InvalidArgumentException;
use Throwable;

trait InteractsWithZnanjeHttp
{
    private static float $lastZnanjeRequestAt = 0.0;

    protected function trustedUrl(string $url, string $requiredPathPrefix): string
    {
        $url = trim($url);
        $parts = parse_url($url);
        $allowedHosts = array_values(array_filter(array_map(
            static fn ($host): string => is_scalar($host) ? strtolower(trim((string) $host)) : '',
            (array) config('znanje_import.allowed_product_hosts', [])
        )));

        if ($url === ''
            || filter_var($url, FILTER_VALIDATE_URL) === false
            || ! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! in_array(strtolower((string) ($parts['host'] ?? '')), $allowedHosts, true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || ! str_starts_with((string) ($parts['path'] ?? ''), $requiredPathPrefix)) {
            throw new InvalidArgumentException('Znanje adresa nije sigurna ili ispravna.');
        }

        return $url;
    }

    protected function throttleZnanjeRequest(): void
    {
        $delayMs = max(0, min(5000, (int) config('znanje_import.request_delay_ms', 350)));
        if ($delayMs === 0) {
            self::$lastZnanjeRequestAt = microtime(true);

            return;
        }

        $remaining = ($delayMs / 1000) - (microtime(true) - self::$lastZnanjeRequestAt);
        if ($remaining > 0) {
            usleep((int) ceil($remaining * 1000000));
        }
        self::$lastZnanjeRequestAt = microtime(true);
    }

    protected function shouldRetryZnanje(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        return $exception instanceof RequestException
            && $exception->response->status() >= 500;
    }

    protected function isRetryableZnanjeStatus(int $status): bool
    {
        return in_array($status, [403, 408, 425, 429], true) || $status >= 500;
    }

    protected function retryAfterSeconds(RequestException $exception): int
    {
        $value = trim((string) $exception->response->header('Retry-After'));
        if ($value === '') {
            return 2;
        }
        if (ctype_digit($value)) {
            return min(max((int) $value, 1), 120);
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? 2 : min(max($timestamp - time(), 1), 120);
    }
}
