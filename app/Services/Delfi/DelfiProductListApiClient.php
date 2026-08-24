<?php

namespace App\Services\Delfi;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

class DelfiProductListApiClient
{
    public const CATEGORIES = ['Knjiga', 'Strana knjiga'];
    public const MAX_LIMIT = 100;

    private const ENDPOINT = 'https://delfi.rs/api/pc-frontend-api/product-list';
    private const COMBINED_CATEGORY = 'Knjiga,Strana knjiga';
    private const MAX_ATTEMPTS = 3;
    private const RETRY_DELAY_MS = 400;

    /**
     * Fetch one page from the public Delfi product-list endpoint.
     *
     * Both exact book categories are fetched as one ascending stream. New
     * products receive higher oldProductId values, so adding them does not
     * shift the offsets of pages that were already processed.
     */
    public function fetchPage(int $skip = 0, int $limit = self::MAX_LIMIT): array
    {
        $this->validateRequest($skip, $limit);

        try {
            $response = Http::acceptJson()
                ->withOptions(['connect_timeout' => 5])
                ->timeout(30)
                ->withHeaders(['User-Agent' => 'Zuzi-Delfi-Importer/1.0'])
                ->retry(self::MAX_ATTEMPTS, self::RETRY_DELAY_MS, function (Throwable $exception) {
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }

                    if (! $exception instanceof RequestException) {
                        return false;
                    }

                    $status = $exception->response->status();

                    // Do not make an upstream rate limit worse with immediate retries.
                    return $status >= 500;
                })
                ->get(self::ENDPOINT, [
                    'limit' => $limit,
                    'skip' => $skip,
                    'sort' => 'oldProductId_asc',
                    'category' => self::COMBINED_CATEGORY,
                ]);
        } catch (RequestException $exception) {
            $status = $exception->response->status();
            if (in_array($status, [403, 408, 425, 429], true) || $status >= 500) {
                throw new DelfiRetryableException(
                    $status === 429
                        ? 'Delfi je privremeno ograničio bulk provjeru. Provjera će se moći nastaviti.'
                        : 'Delfi bulk API je privremeno nedostupan (HTTP ' . $status . ').',
                    $status === 429 ? 429 : 503,
                    $this->retryAfterSeconds($exception),
                    $exception
                );
            }

            throw new DelfiTerminalException('Delfi bulk API vratio je HTTP ' . $status . '.', 0, $exception);
        } catch (ConnectionException $exception) {
            throw new DelfiRetryableException(
                'Povezivanje na Delfi bulk API privremeno nije uspjelo.',
                503,
                2,
                $exception
            );
        }

        $decoded = $response->json();
        if (! is_array($decoded) || ! $this->hasValidEnvelope($decoded, $skip, $limit)) {
            throw new DelfiRetryableException(
                'Delfi bulk API je privremeno vratio nepotpun ili neispravan odgovor.',
                503,
                3
            );
        }

        return $decoded;
    }

    private function validateRequest(int $skip, int $limit): void
    {
        if ($skip < 0) {
            throw new InvalidArgumentException('Delfi bulk pomak ne može biti negativan.');
        }

        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw new InvalidArgumentException('Delfi bulk stranica mora sadržavati između 1 i 100 artikala.');
        }
    }

    private function hasValidEnvelope(array $payload, int $skip, int $limit): bool
    {
        $data = $payload['data'] ?? null;
        if (! is_array($data)
            || ! $this->isNonNegativeInteger($data['recordsTotal'] ?? null)
            || ! isset($data['data'])
            || ! is_array($data['data'])
            || ! $this->isList($data['data'])
            || count($data['data']) > $limit) {
            return false;
        }

        foreach ($data['data'] as $item) {
            if (! is_array($item)) {
                return false;
            }
        }

        $total = (int) $data['recordsTotal'];
        $received = count($data['data']);

        // A non-final page must be complete. Treat a temporarily incomplete
        // upstream response as retryable so the resume cursor is not advanced.
        if ($skip < $total && $received < min($limit, $total - $skip)) {
            return false;
        }

        return true;
    }

    private function isNonNegativeInteger($value): bool
    {
        if (is_int($value)) {
            return $value >= 0;
        }

        return is_string($value) && preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $value) === 1;
    }

    private function isList(array $values): bool
    {
        if ($values === []) {
            return true;
        }

        return array_keys($values) === range(0, count($values) - 1);
    }

    private function retryAfterSeconds(RequestException $exception): int
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
}
