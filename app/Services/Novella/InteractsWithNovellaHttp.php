<?php

namespace App\Services\Novella;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use InvalidArgumentException;
use Throwable;

trait InteractsWithNovellaHttp
{
    /**
     * Resolve an API endpoint from configuration without allowing the
     * importer to become an arbitrary server-side HTTP client.
     */
    protected function configuredApiEndpoint(string $key): string
    {
        $url = trim((string) config('novella_import.' . $key, ''));
        $parts = parse_url($url);
        $allowedHosts = array_values(array_filter(array_map(
            static fn ($host): string => is_scalar($host) ? strtolower(trim((string) $host)) : '',
            (array) config('novella_import.allowed_product_hosts', [])
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
            || trim((string) ($parts['path'] ?? ''), '/') === '') {
            throw new InvalidArgumentException('Novella API adresa u postavkama nije sigurna ili ispravna.');
        }

        return $url;
    }

    protected function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        return $exception instanceof RequestException
            && $exception->response->status() >= 500;
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
        if ($timestamp === false) {
            return 2;
        }

        return min(max($timestamp - time(), 1), 120);
    }

    protected function isRetryableStatus(int $status): bool
    {
        return in_array($status, [403, 408, 425, 429], true) || $status >= 500;
    }
}
