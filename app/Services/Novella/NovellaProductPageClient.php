<?php

namespace App\Services\Novella;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class NovellaProductPageClient
{
    use InteractsWithNovellaHttp;
    use NormalizesNovellaData;

    private const MAX_ATTEMPTS = 3;
    private const RETRY_DELAY_MS = 400;
    private const MAX_HTML_BYTES = 5_000_000;

    public function fetch(string $url): string
    {
        $trustedUrl = $this->productUrl($url);
        if ($trustedUrl === null) {
            throw new InvalidArgumentException('Novella poveznica artikla nije dopuštena.');
        }

        try {
            $response = Http::accept('text/html,application/xhtml+xml')
                ->withOptions([
                    'connect_timeout' => 5,
                    'allow_redirects' => false,
                ])
                ->timeout(25)
                ->withHeaders(['User-Agent' => 'Zuzi-Novella-Importer/1.0'])
                ->retry(self::MAX_ATTEMPTS, self::RETRY_DELAY_MS, fn ($exception) => $this->shouldRetry($exception))
                ->get($trustedUrl);
        } catch (RequestException $exception) {
            $status = $exception->response->status();
            if ($this->isRetryableStatus($status)) {
                throw new NovellaRetryableException(
                    'Novella stranica artikla privremeno je nedostupna (HTTP ' . $status . ').',
                    $status === 429 ? 429 : 503,
                    $this->retryAfterSeconds($exception),
                    $exception
                );
            }

            throw new NovellaTerminalException('Novella stranica artikla vratila je HTTP ' . $status . '.', 0, $exception);
        } catch (ConnectionException $exception) {
            throw new NovellaRetryableException(
                'Povezivanje na Novella stranicu artikla privremeno nije uspjelo.',
                503,
                2,
                $exception
            );
        }

        if ($response->status() >= 300 && $response->status() < 400) {
            throw new NovellaTerminalException(
                'Novella stranica artikla vratila je nedopušteno preusmjeravanje (HTTP '
                . $response->status() . ').'
            );
        }

        $html = $response->body();
        $contentType = mb_strtolower((string) $response->header('Content-Type'));
        if ($html === '' || strlen($html) > self::MAX_HTML_BYTES
            || ($contentType !== '' && ! str_contains($contentType, 'text/html') && ! str_contains($contentType, 'application/xhtml+xml'))
            || ! preg_match('/<h1\b[^>]*class=["\'][^"\']*\bproduct_title\b/iu', $html)) {
            throw new NovellaRetryableException(
                'Novella je privremeno vratila neispravnu stranicu artikla.',
                503,
                3
            );
        }

        return $html;
    }
}
