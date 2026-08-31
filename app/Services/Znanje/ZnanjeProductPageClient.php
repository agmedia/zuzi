<?php

namespace App\Services\Znanje;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class ZnanjeProductPageClient
{
    use InteractsWithZnanjeHttp;
    use NormalizesZnanjeData;

    public function fetch(string $url): string
    {
        $url = $this->safeProductUrl($url);
        if ($url === null) {
            throw new InvalidArgumentException('Znanje adresa artikla nije ispravna.');
        }
        $url = $this->trustedUrl($url, '/product/');
        $path = (string) parse_url($url, PHP_URL_PATH);
        if (preg_match('#\A/product/[^/]+/[1-9][0-9]*/?\z#D', $path) !== 1) {
            throw new InvalidArgumentException('Znanje adresa artikla nije ispravna.');
        }
        $this->throttleZnanjeRequest();

        try {
            $response = Http::accept('text/html,application/xhtml+xml')
                ->withOptions([
                    'allow_redirects' => false,
                    'connect_timeout' => max(1, (int) config('znanje_import.connect_timeout', 10)),
                ])
                ->timeout(max(10, (int) config('znanje_import.request_timeout', 90)))
                ->withHeaders(['User-Agent' => 'Zuzi-Znanje-Importer/1.0'])
                ->retry(3, 600, fn ($exception) => $this->shouldRetryZnanje($exception))
                ->get($url);
        } catch (RequestException $exception) {
            $status = $exception->response->status();
            if ($this->isRetryableZnanjeStatus($status)) {
                throw new ZnanjeRetryableException(
                    $status === 429
                        ? 'Znanje je privremeno ograničilo broj zahtjeva.'
                        : 'Znanje stranica artikla privremeno je nedostupna (HTTP ' . $status . ').',
                    $status === 429 ? 429 : 503,
                    $this->retryAfterSeconds($exception),
                    $exception
                );
            }

            throw new ZnanjeTerminalException('Znanje stranica artikla vratila je HTTP ' . $status . '.', 0, $exception);
        } catch (ConnectionException $exception) {
            throw new ZnanjeRetryableException(
                'Povezivanje na Znanje stranicu artikla privremeno nije uspjelo.',
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
            || ! preg_match('/itemtype=["\'][^"\']*schema\.org\/(?:Product|Book)/iu', $html)
            || ! str_contains($html, 'product-name')) {
            throw new ZnanjeRetryableException(
                'Znanje je privremeno vratilo neispravnu stranicu artikla.',
                503,
                3
            );
        }

        return $html;
    }
}
