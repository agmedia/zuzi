<?php

namespace App\Services\Novella;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class NovellaCategoryApiClient
{
    use InteractsWithNovellaHttp;

    public const MAX_PER_PAGE = 100;

    private const MAX_ATTEMPTS = 3;
    private const RETRY_DELAY_MS = 400;

    public function fetchPage(int $page = 1, int $perPage = self::MAX_PER_PAGE): array
    {
        if ($page < 1 || $perPage < 1 || $perPage > self::MAX_PER_PAGE) {
            throw new InvalidArgumentException('Novella paginacija kategorija nije ispravna.');
        }
        $endpoint = $this->configuredApiEndpoint('categories_api_url');

        try {
            $response = Http::acceptJson()
                ->withOptions(['connect_timeout' => 5])
                ->timeout(20)
                ->withHeaders(['User-Agent' => 'Zuzi-Novella-Importer/1.0'])
                ->retry(self::MAX_ATTEMPTS, self::RETRY_DELAY_MS, fn ($exception) => $this->shouldRetry($exception))
                ->get($endpoint, [
                    'per_page' => $perPage,
                    'page' => $page,
                    'orderby' => 'name',
                    'order' => 'asc',
                ]);
        } catch (RequestException $exception) {
            $status = $exception->response->status();
            if ($this->isRetryableStatus($status)) {
                throw new NovellaRetryableException(
                    'Novella API kategorija privremeno je nedostupan (HTTP ' . $status . ').',
                    $status === 429 ? 429 : 503,
                    $this->retryAfterSeconds($exception),
                    $exception
                );
            }

            throw new NovellaTerminalException('Novella API kategorija vratio je HTTP ' . $status . '.', 0, $exception);
        } catch (ConnectionException $exception) {
            throw new NovellaRetryableException(
                'Povezivanje na Novella API kategorija privremeno nije uspjelo.',
                503,
                2,
                $exception
            );
        }

        $items = $response->json();
        $total = $this->headerInteger($response->header('X-WP-Total'));
        $totalPages = $this->headerInteger($response->header('X-WP-TotalPages'));
        if (is_array($items) && $this->isList($items) && $page === 1 && count($items) < $perPage
            && $total === null && $totalPages === null) {
            // Unlike the products endpoint, Woo Store API installations can
            // omit pagination headers for categories. A short first page is
            // still a complete, unambiguous taxonomy response.
            $total = count($items);
            $totalPages = $total === 0 ? 0 : 1;
        }
        if (! is_array($items) || ! $this->isList($items) || count($items) > $perPage
            || $total === null || $totalPages === null
            || $totalPages !== ($total === 0 ? 0 : (int) ceil($total / $perPage))) {
            throw new NovellaRetryableException('Novella API kategorija vratio je neispravan odgovor.', 503, 3);
        }

        $expected = $page <= $totalPages ? min($perPage, $total - (($page - 1) * $perPage)) : 0;
        if (count($items) !== $expected) {
            throw new NovellaRetryableException('Novella API kategorija vratio je nepotpun odgovor.', 503, 3);
        }
        foreach ($items as $item) {
            if (! is_array($item)) {
                throw new NovellaRetryableException('Novella API kategorija vratio je neispravan odgovor.', 503, 3);
            }
        }

        return [
            'items' => $items,
            'total' => $total,
            'total_pages' => $totalPages,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    private function headerInteger($value): ?int
    {
        $value = trim((string) $value);

        return preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $value) === 1 ? (int) $value : null;
    }

    private function isList(array $values): bool
    {
        return $values === [] || array_keys($values) === range(0, count($values) - 1);
    }
}
