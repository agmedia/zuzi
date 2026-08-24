<?php

namespace App\Services\Delfi;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

class DelfiProductApiClient
{
    private const ENDPOINT = 'https://delfi.rs/api/pc-frontend-api/overview/';
    private const MAX_ATTEMPTS = 3;
    private const RETRY_DELAY_MS = 400;

    /**
     * Fetch the public Delfi overview payload for a numeric product ID.
     *
     * @param  int|string  $numericProductId
     */
    public function fetch($numericProductId): array
    {
        $productId = $this->validatedProductId($numericProductId);

        try {
            $response = Http::acceptJson()
                ->withOptions(['connect_timeout' => 5])
                ->timeout(20)
                ->withHeaders(['User-Agent' => 'Zuzi-Delfi-Importer/1.0'])
                ->retry(self::MAX_ATTEMPTS, self::RETRY_DELAY_MS, function (Throwable $exception) {
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }

                    if (! $exception instanceof RequestException) {
                        return false;
                    }

                    $status = $exception->response->status();

                    // A rate limit must be propagated immediately so a bulk
                    // inspection does not make it worse with rapid retries.
                    return $status >= 500;
                })
                ->get(self::ENDPOINT . $productId);
        } catch (RequestException $exception) {
            $status = $exception->response->status();
            if (in_array($status, [403, 408, 425, 429], true) || $status >= 500) {
                throw new DelfiRetryableException(
                    $status === 429
                        ? 'Delfi je privremeno ograničio broj provjera. Provjera će se moći nastaviti.'
                        : 'Delfi API je privremeno nedostupan (HTTP ' . $status . ').',
                    $status === 429 ? 429 : 503,
                    $this->retryAfterSeconds($exception),
                    $exception
                );
            }

            throw new DelfiTerminalException('Delfi API vratio je HTTP ' . $status . '.', 0, $exception);
        } catch (ConnectionException $exception) {
            throw new DelfiRetryableException(
                'Povezivanje na Delfi API privremeno nije uspjelo.',
                503,
                2,
                $exception
            );
        }

        $decoded = $response->json();
        if (! is_array($decoded)) {
            throw new DelfiRetryableException(
                'Delfi API je privremeno vratio neispravan odgovor. Provjera se može nastaviti kasnije.',
                503,
                3
            );
        }

        return $decoded;
    }

    /**
     * Alias that makes the endpoint represented by fetch() explicit at call sites.
     *
     * @param  int|string  $numericProductId
     */
    public function fetchOverview($numericProductId): array
    {
        return $this->fetch($numericProductId);
    }

    /**
     * @param  int|string  $value
     */
    private function validatedProductId($value): int
    {
        if (is_int($value)) {
            if ($value < 1) {
                throw new InvalidArgumentException('Delfi ID artikla mora biti pozitivan cijeli broj.');
            }

            return $value;
        }

        if (! is_string($value) || ! preg_match('/\A[1-9][0-9]*\z/D', $value)) {
            throw new InvalidArgumentException('Delfi ID artikla mora biti pozitivan cijeli broj.');
        }

        $validated = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX],
        ]);

        if ($validated === false) {
            throw new InvalidArgumentException('Delfi ID artikla je izvan podržanog raspona.');
        }

        return $validated;
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
