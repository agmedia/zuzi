<?php

namespace App\Services\Znanje;

use Illuminate\Support\Str;

trait NormalizesZnanjeData
{
    protected function text($value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $text = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return preg_replace('/\s+/u', ' ', trim($text)) ?? '';
    }

    protected function description($value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $html = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/isu', '', (string) $value) ?? (string) $value;
        $html = preg_replace('/<(?:br\s*\/?|\/p|\/div|\/li|\/h[1-6])>/iu', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\t ]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s*\n\s*/u', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    protected function normalizedKey($value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', Str::ascii(mb_strtolower($this->text($value)))) ?? '';
    }

    protected function positiveInteger($value): ?int
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return preg_match('/\A[1-9][0-9]*\z/D', $value) === 1 ? (int) $value : null;
    }

    protected function identifier($value): ?string
    {
        $identifier = strtoupper(preg_replace('/[^0-9X]/i', '', (string) $value) ?? '');

        return $identifier !== '' ? $identifier : null;
    }

    protected function decimal($value): ?float
    {
        if (! is_scalar($value)) {
            return null;
        }
        $value = preg_replace('/[^0-9,.-]/u', '', trim((string) $value)) ?? '';
        if ($value === '') {
            return null;
        }
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = strrpos($value, ',') > strrpos($value, '.')
                ? str_replace(['.', ','], ['', '.'], $value)
                : str_replace(',', '', $value);
        } else {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? round((float) $value, 4) : null;
    }

    protected function binding($value): ?string
    {
        $binding = $this->text($value);
        $normalized = $this->normalizedKey($binding);
        if (in_array($normalized, ['mek', 'meki', 'mekiuvez', 'paperback', 'softcover'], true)) {
            return 'Meki';
        }
        if (in_array($normalized, ['tvrd', 'tvrdi', 'tvrdiuvez', 'hardback', 'hardcover'], true)) {
            return 'Tvrdi';
        }

        return $binding !== '' ? $binding : null;
    }

    protected function language($value): ?string
    {
        $language = $this->text($value);
        if ($language === '') {
            return null;
        }

        return [
            'hr' => 'Hrvatski', 'hrvatski' => 'Hrvatski',
            'en' => 'Engleski', 'engleski' => 'Engleski', 'english' => 'Engleski',
            'de' => 'Njemački', 'njemacki' => 'Njemački', 'german' => 'Njemački',
            'it' => 'Talijanski', 'talijanski' => 'Talijanski', 'italian' => 'Talijanski',
            'fr' => 'Francuski', 'francuski' => 'Francuski', 'french' => 'Francuski',
            'es' => 'Španjolski', 'spanjolski' => 'Španjolski', 'spanish' => 'Španjolski',
        ][$this->normalizedKey($language)] ?? $language;
    }

    protected function safeProductUrl($value): ?string
    {
        return $this->safeZnanjeUrl($value, '/product/', 'allowed_product_hosts');
    }

    protected function safeImageUrl($value): ?string
    {
        return $this->safeZnanjeUrl($value, '/product-images/', 'allowed_image_hosts');
    }

    private function safeZnanjeUrl($value, string $pathPrefix, string $hostConfig): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $url = trim((string) $value);
        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        } elseif (str_starts_with($url, '/')) {
            $url = 'https://znanje.hr' . $url;
        }
        $parts = parse_url($url);
        $hosts = array_map('strtolower', (array) config('znanje_import.' . $hostConfig, []));
        if (! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! in_array(strtolower((string) ($parts['host'] ?? '')), $hosts, true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
            || isset($parts['query']) || isset($parts['fragment'])
            || ! str_starts_with((string) ($parts['path'] ?? ''), $pathPrefix)) {
            return null;
        }

        // Znanje has a few canonical slugs with a literal Unicode dash. Encode
        // every path segment, while avoiding double encoding existing %XX data,
        // before asking FILTER_VALIDATE_URL to validate the final URL.
        $path = implode('/', array_map(
            static fn (string $segment): string => rawurlencode(rawurldecode($segment)),
            explode('/', (string) $parts['path'])
        ));
        $url = 'https://' . strtolower((string) $parts['host']) . $path;
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return $url;
    }
}
