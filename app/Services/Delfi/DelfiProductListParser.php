<?php

namespace App\Services\Delfi;

use InvalidArgumentException;

class DelfiProductListParser
{
    private DelfiProductDetailParser $detailParser;

    public function __construct(DelfiProductDetailParser $detailParser)
    {
        $this->detailParser = $detailParser;
    }

    /**
     * Normalize a validated product-list page and calculate its next offset.
     */
    public function parsePage(
        array $payload,
        int $skip = 0,
        int $limit = DelfiProductListApiClient::MAX_LIMIT
    ): array {
        $this->validatePageArguments($skip, $limit);

        $data = $payload['data'] ?? null;
        if (! is_array($data)
            || ! $this->isNonNegativeInteger($data['recordsTotal'] ?? null)
            || ! isset($data['data'])
            || ! is_array($data['data'])) {
            throw new DelfiTerminalException('Delfi bulk odgovor nema ispravnu paginaciju.');
        }

        $records = $data['data'];
        if (($records !== [] && array_keys($records) !== range(0, count($records) - 1))
            || count($records) > $limit) {
            throw new DelfiTerminalException('Delfi bulk odgovor sadrži neispravnu stranicu artikala.');
        }

        $total = (int) $data['recordsTotal'];
        $expectedCount = $skip < $total ? min($limit, $total - $skip) : 0;
        if (count($records) !== $expectedCount) {
            throw new DelfiTerminalException('Delfi bulk odgovor nije usklađen s ukupnim brojem artikala.');
        }

        $items = [];
        $rawCount = count($records);
        $seenRemoteIds = [];
        $previousRemoteId = null;

        foreach ($records as $record) {
            if (! is_array($record)) {
                throw new DelfiTerminalException('Delfi bulk odgovor sadrži neispravan artikl.');
            }

            $item = $this->parseItem($record);
            $remoteId = $item['remote_product_id'];
            if (isset($seenRemoteIds[$remoteId])) {
                throw new DelfiTerminalException('Delfi bulk odgovor sadrži ponovljeni ID artikla.');
            }
            if ($previousRemoteId !== null && $remoteId <= $previousRemoteId) {
                throw new DelfiTerminalException('Delfi bulk artikli nisu sortirani od najstarijeg prema najnovijem.');
            }

            $seenRemoteIds[$remoteId] = true;
            $previousRemoteId = $remoteId;
            $items[] = $item;
        }

        // Cursor progress is based on raw API records, not optional enrichment
        // fields. A legacy book without a title must never stall this page.
        $nextSkip = $skip + $rawCount;
        $hasMore = $nextSkip < $total;

        return [
            'items' => $items,
            'total' => $total,
            'skip' => $skip,
            'limit' => $limit,
            'next_skip' => $hasMore ? $nextSkip : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Map one list item to fields understood by the Delfi staging importer.
     */
    public function parseItem(array $product): array
    {
        $category = $this->text($product['category'] ?? null);
        if (! in_array($category, DelfiProductListApiClient::CATEGORIES, true)) {
            throw new DelfiTerminalException('Delfi bulk odgovor sadrži artikl iz nedopuštene kategorije.');
        }
        $name = $this->text($product['title'] ?? ($product['metaTitle'] ?? null));
        $details = $this->detailParser->parse($product);
        $remoteId = $details['remote_product_id'] ?? null;
        if (! is_int($remoteId) || $remoteId < 1) {
            throw new DelfiTerminalException('Delfi bulk artiklu nedostaje numerički ID.');
        }
        $externalId = trim((string) ($details['external_id'] ?? ''));

        $images = array_values((array) ($details['images'] ?? []));
        $fullPrice = $this->price($product['priceList']['fullPrice'] ?? null);
        $discountPrice = $this->price($product['priceList']['regularDiscountPrice'] ?? null);
        $salePrice = $fullPrice !== null
            && $discountPrice !== null
            && $discountPrice > 0
            && $discountPrice < $fullPrice
                ? $discountPrice
                : null;
        $isAvailable = ($product['isAvailable'] ?? false) === true
            && ($product['status'] ?? true) !== false;

        return [
            'external_id' => $externalId !== '' ? $externalId : null,
            'remote_product_id' => $remoteId,
            'nav_id' => $details['nav_id'] ?? null,
            'sku' => $details['sku'] ?? null,
            'name' => $name !== '' ? $name : null,
            'title' => $name !== '' ? $name : null,
            'isbn' => $details['isbn'] ?? null,
            'ean' => $details['ean'] ?? null,
            'author' => $details['author'] ?? null,
            'authors' => array_values((array) ($details['authors'] ?? [])),
            'source_publisher' => $details['publisher'] ?? null,
            'publisher' => $details['publisher'] ?? null,
            'source_category' => $category,
            'category' => $category,
            'genre' => $details['genre'] ?? null,
            'source_genres' => array_values((array) ($details['source_genres'] ?? [])),
            'format' => $details['format'] ?? null,
            'pages' => $details['pages'] ?? null,
            'letter' => $details['letter'] ?? null,
            'binding' => $details['binding'] ?? null,
            'publication_year' => $details['publication_year'] ?? null,
            'year' => $details['year'] ?? null,
            'description' => $details['description'] ?? '',
            'meta_description' => $this->description($product['metaData']['description'] ?? null),
            'image_url' => $images[0] ?? null,
            'additional_image_urls' => array_slice($images, 1),
            'image' => $images[0] ?? null,
            'images' => $images,
            'language' => $details['language'] ?? null,
            'origin' => $details['origin'] ?? null,
            'price_rsd' => $fullPrice,
            'sale_price_rsd' => $salePrice,
            'availability' => $isAvailable ? 'in_stock' : 'out_of_stock',
            'is_available' => $isAvailable,
            'quantity' => $this->quantity($product['quantity'] ?? null),
            'updated_at_for_api' => $this->text($product['updatedAtForApi'] ?? null) ?: null,
        ];
    }

    private function validatePageArguments(int $skip, int $limit): void
    {
        if ($skip < 0 || $limit < 1 || $limit > DelfiProductListApiClient::MAX_LIMIT) {
            throw new InvalidArgumentException('Delfi bulk paginacija nije ispravna.');
        }
    }

    private function price($value): ?float
    {
        if (! is_int($value) && ! is_float($value) && ! (is_string($value) && is_numeric($value))) {
            return null;
        }

        $price = (float) $value;

        return is_finite($price) && $price >= 0 ? round($price, 4) : null;
    }

    private function quantity($value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }

        if (! is_string($value) || preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $value) !== 1) {
            return null;
        }

        return (int) $value;
    }

    private function isNonNegativeInteger($value): bool
    {
        return (is_int($value) && $value >= 0)
            || (is_string($value) && preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $value) === 1);
    }

    private function description($value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $text = preg_replace('/<(?:br\s*\/?|\/p|\/div|\/li)>/iu', "\n", (string) $value) ?? (string) $value;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';

        return $text;
    }

    private function text($value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $value = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return preg_replace('/\s+/u', ' ', trim($value)) ?? '';
    }
}
