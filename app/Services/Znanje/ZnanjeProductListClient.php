<?php

namespace App\Services\Znanje;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class ZnanjeProductListClient
{
    use InteractsWithZnanjeHttp;

    public const PER_PAGE = 84;

    private const ROOTS = [
        500 => ['name' => 'Knjige', 'slug' => 'knjige'],
        505 => ['name' => 'Strane knjige', 'slug' => 'strane-knjige'],
    ];

    public function fetchPage(int $rootCategoryId, int $page = 1): string
    {
        if ($page < 1) {
            throw new InvalidArgumentException('Znanje stranica mora biti pozitivan cijeli broj.');
        }
        $root = $this->configuredRoot($rootCategoryId);
        $perPage = (int) config('znanje_import.per_page', self::PER_PAGE);
        if ($perPage !== self::PER_PAGE
            || config('znanje_import.sort', 'date') !== 'date'
            || config('znanje_import.order', 'desc') !== 'desc'
            || config('znanje_import.available_only', true) !== true) {
            throw new InvalidArgumentException(
                'Znanje listing mora koristiti 84 dostupna proizvoda sortirana od najnovijeg.'
            );
        }

        $template = trim((string) config('znanje_import.catalog_url', ''));
        if (! str_contains($template, '{slug}') || ! str_contains($template, '{id}')) {
            throw new InvalidArgumentException('Znanje adresa kataloga nema obavezna polja kategorije.');
        }
        $url = str_replace(
            ['{slug}', '{id}'],
            [$root['slug'], (string) $rootCategoryId],
            $template
        );
        $url = $this->trustedUrl($url, '/kategorija-proizvoda/' . $root['slug'] . '/' . $rootCategoryId);
        $this->throttleZnanjeRequest();
        // Keep one listing fetch safely below the usual reverse-proxy timeout.
        // Retries are bounded too; the resumable synchronizer will repeat the
        // same page on a later AJAX request if all attempts fail.
        $timeout = self::requestTimeoutSeconds();
        $connectTimeout = max(1, min(5, $timeout, (int) config('znanje_import.feed_connect_timeout', 5)));
        $attempts = self::requestAttempts();
        $retryDelay = self::retryDelayMilliseconds();

        try {
            $response = Http::accept('text/html,application/xhtml+xml')
                ->withOptions([
                    'allow_redirects' => false,
                    'connect_timeout' => $connectTimeout,
                ])
                ->timeout($timeout)
                ->withHeaders(['User-Agent' => 'Zuzi-Znanje-Importer/1.0'])
                ->retry($attempts, $retryDelay, fn ($exception) => $this->shouldRetryZnanje($exception))
                ->get($url, [
                    'sort' => 'date',
                    'order' => 'desc',
                    'perPage' => self::PER_PAGE,
                    'available' => 'true',
                    // Znanje koristi nultu bazu u javnoj paginaciji.
                    'pageNumber' => $page - 1,
                ]);
        } catch (RequestException $exception) {
            $status = $exception->response->status();
            if ($this->isRetryableZnanjeStatus($status)) {
                throw new ZnanjeRetryableException(
                    $status === 429
                        ? 'Znanje je privremeno ograničilo broj zahtjeva.'
                        : 'Znanje katalog je privremeno nedostupan (HTTP ' . $status . ').',
                    $status === 429 ? 429 : 503,
                    $this->retryAfterSeconds($exception),
                    $exception
                );
            }

            throw new ZnanjeTerminalException('Znanje katalog vratio je HTTP ' . $status . '.', 0, $exception);
        } catch (ConnectionException $exception) {
            throw new ZnanjeRetryableException(
                'Povezivanje na Znanje katalog privremeno nije uspjelo.',
                503,
                3,
                $exception
            );
        }

        $html = str_replace("\0", '', $response->body());
        $contentType = strtolower((string) $response->header('Content-Type'));
        $maxBytes = max(1024, (int) config('znanje_import.max_html_bytes', 4 * 1024 * 1024));
        if ($html === '' || strlen($html) > $maxBytes
            || ($contentType !== ''
                && ! str_contains($contentType, 'text/html')
                && ! str_contains($contentType, 'application/xhtml+xml'))
            || ! str_contains($html, 'id="showAvailableOnly"')
            || ! str_contains($html, 'itemprop="numberOfItems"')
            || ! str_contains($html, 'product-card')) {
            throw new ZnanjeRetryableException(
                'Znanje je privremeno vratilo nepotpunu stranicu kataloga.',
                503,
                3
            );
        }

        return $html;
    }

    public static function roots(): array
    {
        return self::ROOTS;
    }

    public static function maximumRequestDurationSeconds(): int
    {
        $attempts = self::requestAttempts();

        return (self::requestTimeoutSeconds() * $attempts)
            + (int) ceil((max(0, $attempts - 1) * self::retryDelayMilliseconds()) / 1000)
            + (int) ceil(
                max(0, min(5000, (int) config('znanje_import.request_delay_ms', 350))) / 1000
            );
    }

    private static function requestTimeoutSeconds(): int
    {
        return max(5, min(15, (int) config('znanje_import.feed_request_timeout', 15)));
    }

    private static function requestAttempts(): int
    {
        return max(1, min(3, (int) config('znanje_import.feed_request_attempts', 3)));
    }

    private static function retryDelayMilliseconds(): int
    {
        return max(0, min(1000, (int) config('znanje_import.feed_retry_delay_ms', 600)));
    }

    private function configuredRoot(int $id): array
    {
        if (! isset(self::ROOTS[$id])) {
            throw new InvalidArgumentException('Znanje import podržava samo korijenske kategorije 500 i 505.');
        }
        $configured = (array) config('znanje_import.root_categories', []);
        $root = isset($configured[$id]) && is_array($configured[$id]) ? $configured[$id] : null;
        if ($root === null
            || trim((string) ($root['name'] ?? '')) !== self::ROOTS[$id]['name']
            || trim((string) ($root['slug'] ?? '')) !== self::ROOTS[$id]['slug']) {
            throw new InvalidArgumentException('Znanje korijenske kategorije u postavkama nisu ispravne.');
        }

        return self::ROOTS[$id];
    }
}
