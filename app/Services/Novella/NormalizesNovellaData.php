<?php

namespace App\Services\Novella;

use Illuminate\Support\Str;

trait NormalizesNovellaData
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

        $html = (string) $value;
        $html = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/isu', '', $html) ?? $html;
        $html = preg_replace('/<(?:br\s*\/?|\/p|\/div|\/li|\/h[1-6])>/iu', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\t ]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s*\n\s*/u', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    protected function normalizedKey($value): string
    {
        $ascii = Str::ascii(mb_strtolower($this->text($value)));

        return preg_replace('/[^a-z0-9]+/', '', $ascii) ?? '';
    }

    protected function positiveInteger($value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (! is_string($value) || preg_match('/\A[1-9][0-9]*\z/D', trim($value)) !== 1) {
            return null;
        }

        $validated = filter_var(trim($value), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX],
        ]);

        return $validated === false ? null : $validated;
    }

    protected function isbn($value): ?string
    {
        $isbn = strtoupper(preg_replace('/[^0-9X]/i', '', (string) $value) ?? '');

        if (strlen($isbn) === 13) {
            if (! str_starts_with($isbn, '978') && ! str_starts_with($isbn, '979')) {
                return null;
            }

            $sum = 0;
            for ($index = 0; $index < 12; $index++) {
                $sum += ((int) $isbn[$index]) * ($index % 2 === 0 ? 1 : 3);
            }

            return (10 - ($sum % 10)) % 10 === (int) $isbn[12] ? $isbn : null;
        }

        if (strlen($isbn) === 10) {
            $sum = 0;
            for ($index = 0; $index < 10; $index++) {
                if ($isbn[$index] === 'X' && $index !== 9) {
                    return null;
                }

                $sum += ($isbn[$index] === 'X' ? 10 : (int) $isbn[$index]) * (10 - $index);
            }

            return $sum % 11 === 0 ? $isbn : null;
        }

        return null;
    }

    protected function ean($value): ?string
    {
        $ean = preg_replace('/[^0-9]/', '', (string) $value) ?? '';
        $length = strlen($ean);
        if (! in_array($length, [8, 12, 13, 14], true)) {
            return null;
        }

        $sum = 0;
        $weight = 3;
        for ($index = $length - 2; $index >= 0; $index--) {
            $sum += ((int) $ean[$index]) * $weight;
            $weight = $weight === 3 ? 1 : 3;
        }

        return (10 - ($sum % 10)) % 10 === (int) $ean[$length - 1] ? $ean : null;
    }

    protected function productUrl($value): ?string
    {
        return $this->safeNovellaUrl($value, '/proizvod/');
    }

    protected function categoryUrl($value): ?string
    {
        return $this->safeNovellaUrl($value, '/kategorija-proizvoda/knjige');
    }

    protected function imageUrl($value): ?string
    {
        return $this->safeNovellaUrl($value, '/wp-content/uploads/');
    }

    protected function safeNovellaUrl($value, string $requiredPathPrefix): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $url = trim((string) $value);
        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        } elseif (str_starts_with($url, '/')) {
            $url = 'https://novella.hr' . $url;
        }

        $url = str_replace(' ', '%20', $url);
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts)
            || mb_strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! in_array(mb_strtolower((string) ($parts['host'] ?? '')), ['novella.hr', 'www.novella.hr'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || ! str_starts_with((string) ($parts['path'] ?? ''), $requiredPathPrefix)) {
            return null;
        }

        return $url;
    }

    protected function binding($value): ?string
    {
        $binding = $this->text($value);
        if ($binding === '') {
            return null;
        }

        $normalized = $this->normalizedKey($binding);
        if (in_array($normalized, ['mek', 'meki', 'mekiuvez', 'paperback', 'softback', 'softcover', 'bros'], true)) {
            return 'Meki';
        }
        if (in_array($normalized, ['tvrd', 'tvrdi', 'tvrdouvez', 'tvrdiuvez', 'hardback', 'hardcover'], true)) {
            return 'Tvrdi';
        }

        return $binding;
    }

    protected function language($value): ?string
    {
        $language = $this->text($value);
        if ($language === '') {
            return null;
        }

        return [
            'engleski' => 'Engleski',
            'english' => 'Engleski',
            'hrvatski' => 'Hrvatski',
            'croatian' => 'Hrvatski',
            'srpski' => 'Srpski',
            'serbian' => 'Srpski',
            'njemacki' => 'Njemački',
            'german' => 'Njemački',
            'francuski' => 'Francuski',
            'french' => 'Francuski',
            'talijanski' => 'Talijanski',
            'italian' => 'Talijanski',
            'spanjolski' => 'Španjolski',
            'spanish' => 'Španjolski',
        ][$this->normalizedKey($language)] ?? $language;
    }
}
