<?php

namespace App\Services\Delfi;

use RuntimeException;
use SimpleXMLElement;

class DelfiFeedNormalizer
{
    public function normalizeItemXml(string $xml): array
    {
        $previousErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $item = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        if (! $item || $errors !== []) {
            throw new RuntimeException('Nije moguće pročitati artikl iz Delfi feeda.');
        }

        $google = $item->children('http://base.google.com/ns/1.0');
        $externalId = trim((string) $google->id);
        $name = $this->singleLine((string) ($item->title ?: $google->title));
        $sourceUrl = trim((string) ($item->link ?: $google->link));
        $imageUrl = $this->imageUrl((string) $google->image_link);
        $sourceCategory = $this->singleLine((string) $item->category);
        $sourcePublisher = $this->singleLine((string) $item->brand);
        $author = $this->singleLine((string) $item->authors);

        $additionalImages = [];
        foreach ($google->additional_image_link as $image) {
            $url = $this->imageUrl((string) $image);
            if ($url !== null) {
                $additionalImages[] = $url;
            }
        }

        $normalized = [
            'external_id' => $externalId,
            'remote_product_id' => $this->remoteProductId($sourceUrl),
            'name' => $name,
            'description' => $this->description((string) ($item->description ?: $google->description)),
            'source_category' => $sourceCategory,
            'source_publisher' => $sourcePublisher ?: null,
            'source_url' => $sourceUrl,
            'image_url' => $imageUrl,
            'additional_image_urls' => array_values(array_unique($additionalImages)),
            'price_rsd' => $this->parsePrice((string) $google->price),
            'sale_price_rsd' => $this->parseNullablePrice((string) $google->sale_price),
            'availability' => strtolower(trim((string) $google->availability)),
            'author' => $author ?: null,
        ];

        $encoded = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new RuntimeException('Delfi artikl sadrži neispravne znakove.');
        }

        $normalized['source_hash'] = hash('sha256', $encoded);

        return $normalized;
    }

    private function parseNullablePrice(string $value): ?float
    {
        $price = $this->parsePrice($value);

        return $price > 0 ? $price : null;
    }

    private function parsePrice(string $value): float
    {
        if (! preg_match('/[0-9]+(?:[.,][0-9]+)?/', str_replace(' ', '', $value), $matches)) {
            return 0.0;
        }

        $number = str_replace(',', '.', $matches[0]);
        $parsed = (float) $number;

        // Some manually entered Serbian prices use a dot as the thousands
        // separator (for example 1.199 RSD), while the feed also contains a
        // handful of real three-decimal prices. Values below 100 RSD cannot be
        // realistic book prices here, so only that unambiguous form is scaled.
        if (preg_match('/^[0-9]{1,2}\.[0-9]{3}$/', $number) && $parsed < 100) {
            $parsed *= 1000;
        }

        return max(0, round($parsed, 4));
    }

    private function remoteProductId(string $url): ?int
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || ! preg_match('~/([0-9]+)(?:-|/|$)~', $path, $matches)) {
            return null;
        }

        $id = (int) $matches[1];

        return $id > 0 ? $id : null;
    }

    private function imageUrl(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '//')) {
            $value = 'https:' . $value;
        } elseif (str_starts_with($value, '/')) {
            $value = 'https://delfi.rs' . $value;
        }

        // Delfi paths contain unescaped spaces (for example "Strana knjiga").
        $value = str_replace(' ', '%20', $value);
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($value, PHP_URL_HOST));
        $allowedHosts = array_map('strtolower', (array) config('delfi_import.allowed_image_hosts', [
            'delfi.rs',
            'www.delfi.rs',
        ]));

        if ($scheme !== 'https'
            || ! in_array($host, $allowedHosts, true)
            || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return $value;
    }

    private function singleLine(string $value): string
    {
        $value = $this->decodeEntities(strip_tags($value));
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';

        return preg_replace('/\s+/u', ' ', trim($value)) ?? '';
    }

    private function description(string $value): string
    {
        $value = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $value) ?? $value;
        $value = preg_replace('/<\s*\/\s*p\s*>/i', "\n\n", $value) ?? $value;
        $value = $this->decodeEntities(strip_tags($value));
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]+/u', ' ', $value) ?? '';
        $value = preg_replace('/[ \t]+/u', ' ', $value) ?? '';
        $value = preg_replace('/ *\n */u', "\n", $value) ?? '';
        $value = preg_replace('/\n{3,}/u', "\n\n", $value) ?? '';

        return trim($value);
    }

    private function decodeEntities(string $value): string
    {
        $value = preg_replace(
            '/(?<!&)(nbsp|amp|quot|apos|ndash|mdash|lsquo|rsquo|ldquo|rdquo|hellip);/iu',
            '&$1;',
            $value
        ) ?? $value;

        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
