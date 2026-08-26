<?php

namespace App\Services\Novella;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class NovellaProductApiClient
{
    use InteractsWithNovellaHttp;

    public const BOOK_CATEGORY_ID = 63;
    public const MAX_PER_PAGE = 100;

    private const MAX_ATTEMPTS = 3;
    private const RETRY_DELAY_MS = 400;

    /**
     * Fetch one stable, oldest-first page of simple products in Knjige.
     */
    public function fetchPage(int $page = 1, int $perPage = self::MAX_PER_PAGE): array
    {
        $this->validatePagination($page, $perPage);
        $endpoint = $this->configuredApiEndpoint('products_api_url');
        $bookCategoryId = self::bookCategoryId();
        $productType = $this->configuredProductType();

        try {
            $response = Http::acceptJson()
                ->withOptions(['connect_timeout' => 5])
                ->timeout(30)
                ->withHeaders(['User-Agent' => 'Zuzi-Novella-Importer/1.0'])
                ->retry(self::MAX_ATTEMPTS, self::RETRY_DELAY_MS, fn ($exception) => $this->shouldRetry($exception))
                ->get($endpoint, [
                    'category' => $bookCategoryId,
                    'type' => $productType,
                    'per_page' => $perPage,
                    'page' => $page,
                    'orderby' => 'id',
                    'order' => 'asc',
                ]);
        } catch (RequestException $exception) {
            $status = $exception->response->status();
            if ($this->isRetryableStatus($status)) {
                throw new NovellaRetryableException(
                    $status === 429
                        ? 'Novella je privremeno ograničila broj zahtjeva.'
                        : 'Novella API je privremeno nedostupan (HTTP ' . $status . ').',
                    $status === 429 ? 429 : 503,
                    $this->retryAfterSeconds($exception),
                    $exception
                );
            }

            throw new NovellaTerminalException('Novella API vratio je HTTP ' . $status . '.', 0, $exception);
        } catch (ConnectionException $exception) {
            throw new NovellaRetryableException(
                'Povezivanje na Novella API privremeno nije uspjelo.',
                503,
                2,
                $exception
            );
        }

        $items = $response->json();
        $total = $this->nonNegativeHeader($response->header('X-WP-Total'));
        $totalPages = $this->nonNegativeHeader($response->header('X-WP-TotalPages'));
        if (! is_array($items) || ! $this->isList($items)
            || $total === null || $totalPages === null
            || ! $this->validEnvelope($items, $total, $totalPages, $page, $perPage)) {
            throw new NovellaRetryableException(
                'Novella API je privremeno vratio nepotpun ili neispravan odgovor.',
                503,
                3
            );
        }

        return [
            'items' => $items,
            'total' => $total,
            'total_pages' => $totalPages,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    private function validatePagination(int $page, int $perPage): void
    {
        if ($page < 1) {
            throw new InvalidArgumentException('Novella stranica mora biti pozitivan cijeli broj.');
        }
        if ($perPage < 1 || $perPage > self::MAX_PER_PAGE) {
            throw new InvalidArgumentException('Novella stranica mora sadržavati između 1 i 100 artikala.');
        }
    }

    public static function bookCategoryId(): int
    {
        $value = config('novella_import.book_category_id', self::BOOK_CATEGORY_ID);
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (is_string($value) && preg_match('/\A[1-9][0-9]*\z/D', $value) === 1) {
            return (int) $value;
        }

        throw new InvalidArgumentException('Novella ID kategorije knjiga u postavkama nije ispravan.');
    }

    private function configuredProductType(): string
    {
        $value = strtolower(trim((string) config('novella_import.product_type', 'simple')));
        if ($value !== 'simple') {
            throw new InvalidArgumentException('Novella import podržava samo jednostavne proizvode (simple).');
        }

        return $value;
    }

    private function validEnvelope(array $items, int $total, int $totalPages, int $page, int $perPage): bool
    {
        if (count($items) > $perPage || $totalPages !== ($total === 0 ? 0 : (int) ceil($total / $perPage))) {
            return false;
        }

        $expected = $page <= $totalPages
            ? min($perPage, $total - (($page - 1) * $perPage))
            : 0;
        if (count($items) !== $expected) {
            return false;
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                return false;
            }
        }

        return true;
    }

    private function nonNegativeHeader($value): ?int
    {
        $value = trim((string) $value);

        return preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $value) === 1 ? (int) $value : null;
    }

    private function isList(array $values): bool
    {
        return $values === [] || array_keys($values) === range(0, count($values) - 1);
    }
}
