<?php

namespace App\Services\Novella;

class NovellaProductListParser
{
    use NormalizesNovellaData;

    public function parseCollection(array $payload): array
    {
        $items = $payload['items'] ?? null;
        $total = $this->nonNegativeInteger($payload['total'] ?? null);
        $totalPages = $this->nonNegativeInteger($payload['total_pages'] ?? null);
        $page = $this->positiveInteger($payload['page'] ?? null);
        $perPage = $this->positiveInteger($payload['per_page'] ?? null);

        if (! is_array($items) || ! $this->isList($items) || $total === null || $totalPages === null
            || $page === null || $perPage === null || $perPage > NovellaProductApiClient::MAX_PER_PAGE
            || count($items) > $perPage
            || $totalPages !== ($total === 0 ? 0 : (int) ceil($total / $perPage))) {
            throw new NovellaTerminalException('Novella odgovor nema ispravnu paginaciju artikala.');
        }

        $expectedCount = $page <= $totalPages
            ? min($perPage, $total - (($page - 1) * $perPage))
            : 0;
        if (count($items) !== $expectedCount) {
            throw new NovellaTerminalException('Novella odgovor nije usklađen s ukupnim brojem artikala.');
        }

        $parsed = [];
        $previousId = null;
        foreach ($items as $item) {
            if (! is_array($item)) {
                throw new NovellaTerminalException('Novella odgovor sadrži neispravan artikl.');
            }

            $product = $this->parseProduct($item);
            $remoteId = $product['remote_product_id'];
            if ($previousId !== null && $remoteId <= $previousId) {
                throw new NovellaTerminalException('Novella artikli nisu jedinstveni i sortirani po ID-u uzlazno.');
            }
            $previousId = $remoteId;
            $parsed[] = $product;
        }

        $hasMore = $page < $totalPages;

        return [
            'items' => $parsed,
            'total' => $total,
            'total_pages' => $totalPages,
            'page' => $page,
            'per_page' => $perPage,
            'next_page' => $hasMore ? $page + 1 : null,
            'has_more' => $hasMore,
        ];
    }

    public function parseProduct(array $product): array
    {
        $remoteId = $this->positiveInteger($product['id'] ?? null);
        $name = $this->text($product['name'] ?? null);
        $type = $this->text($product['type'] ?? null);
        $sourceUrl = $this->productUrl($product['permalink'] ?? null);
        if ($remoteId === null || $name === '' || $type !== 'simple' || $sourceUrl === null) {
            throw new NovellaTerminalException('Novella artiklu nedostaje ID, naziv, vrsta ili sigurna poveznica.');
        }

        $categories = $this->bookCategories($product['categories'] ?? null);
        if ($categories === []) {
            throw new NovellaTerminalException('Novella odgovor sadrži artikl izvan kategorije Knjige.');
        }
        $sourceCategories = array_values(array_unique(array_merge(
            ['Knjige'],
            array_column($categories, 'name')
        )));
        $sourceGenres = array_values(array_filter($sourceCategories, fn (string $category) => $category !== 'Knjige'));

        $prices = is_array($product['prices'] ?? null) ? $product['prices'] : [];
        $currency = strtoupper($this->text($prices['currency_code'] ?? null));
        $minorUnit = $this->minorUnit($prices['currency_minor_unit'] ?? null);
        if ($currency !== 'EUR' || $minorUnit === null) {
            throw new NovellaTerminalException('Novella artikl nema ispravnu EUR cijenu.');
        }
        $price = $this->minorPrice($prices['price'] ?? null, $minorUnit);
        $regularPrice = $this->minorPrice($prices['regular_price'] ?? null, $minorUnit);
        $rawSalePrice = $this->minorPrice($prices['sale_price'] ?? null, $minorUnit);
        $salePrice = ($product['on_sale'] ?? false) === true
            && $rawSalePrice !== null
            && $regularPrice !== null
            && $rawSalePrice < $regularPrice
                ? $rawSalePrice
                : null;

        $images = $this->images($product['images'] ?? null);
        $sku = $this->text($product['sku'] ?? null);
        $ean = $this->ean($sku);
        $isbn = $this->isbn($sku);
        $authors = $this->authors($product['tags'] ?? null);
        $publisher = $this->publisher($product['attributes'] ?? null);
        $description = $this->description($product['description'] ?? null);
        $shortDescription = $this->description($product['short_description'] ?? null);
        $isAvailable = ($product['is_in_stock'] ?? false) === true
            || ($product['is_on_backorder'] ?? false) === true;

        $result = [
            'external_id' => (string) $remoteId,
            'remote_product_id' => $remoteId,
            'name' => $name,
            'title' => $name,
            'description' => $description !== '' ? $description : $shortDescription,
            'short_description' => $shortDescription,
            'source_category' => 'Knjige',
            'category' => 'Knjige',
            'source_categories' => $sourceCategories,
            'source_genres' => $sourceGenres,
            'genre' => $sourceGenres[0] ?? null,
            'source_publisher' => $publisher,
            'publisher' => $publisher,
            'source_url' => $sourceUrl,
            'permalink' => $sourceUrl,
            'image_url' => $images[0] ?? null,
            'additional_image_urls' => array_slice($images, 1),
            'image' => $images[0] ?? null,
            'images' => $images,
            'price_eur' => $regularPrice ?? $price,
            'sale_price_eur' => $salePrice,
            'availability' => $isAvailable ? 'in_stock' : 'out_of_stock',
            'is_available' => $isAvailable,
            'sku' => $sku !== '' ? $sku : null,
            'isbn' => $isbn,
            'ean' => $ean,
            'author' => $authors !== [] ? implode(', ', $authors) : null,
            'authors' => $authors,
            'format' => null,
            'pages' => null,
            'letter' => null,
            'binding' => null,
            'publication_year' => null,
            'year' => null,
            'language' => null,
            'origin' => null,
            'translator' => null,
        ];

        $result['source_hash'] = hash('sha256', json_encode([
            'remote_product_id' => $result['remote_product_id'],
            'name' => $result['name'],
            'description' => $result['description'],
            'short_description' => $result['short_description'],
            'source_categories' => $result['source_categories'],
            'source_publisher' => $result['source_publisher'],
            'source_url' => $result['source_url'],
            'images' => $result['images'],
            'price_eur' => $result['price_eur'],
            'sale_price_eur' => $result['sale_price_eur'],
            'availability' => $result['availability'],
            'sku' => $result['sku'],
            'author' => $result['author'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));

        return $result;
    }

    private function bookCategories($values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $categories = [];
        foreach ($values as $category) {
            if (! is_array($category)) {
                continue;
            }

            $id = $this->positiveInteger($category['id'] ?? null);
            $name = $this->text($category['name'] ?? null);
            $link = $this->categoryUrl($category['link'] ?? null);
            if ($id !== null && $name !== '' && $link !== null) {
                $categories[$id] = ['id' => $id, 'name' => $name, 'link' => $link];
            }
        }

        return $categories;
    }

    private function images($values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $images = [];
        foreach ($values as $image) {
            if (! is_array($image)) {
                continue;
            }
            $url = $this->imageUrl($image['src'] ?? null);
            if ($url !== null) {
                $images[] = $url;
            }
        }

        return array_values(array_unique($images));
    }

    private function authors($values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $authors = [];
        foreach ($values as $tag) {
            if (! is_array($tag)) {
                continue;
            }

            $link = trim((string) ($tag['link'] ?? ''));
            $path = (string) parse_url($link, PHP_URL_PATH);
            $name = $this->text($tag['name'] ?? null);
            if ($name !== '' && str_starts_with($path, '/autor/')) {
                $authors[] = $name;
            }
        }

        return array_values(array_unique($authors));
    }

    private function publisher($values): ?string
    {
        if (! is_array($values)) {
            return null;
        }

        foreach ($values as $attribute) {
            if (! is_array($attribute)
                || ! in_array($this->normalizedKey($attribute['name'] ?? null), ['izdavac', 'nakladnik'], true)) {
                continue;
            }

            $publishers = [];
            foreach ((array) ($attribute['terms'] ?? []) as $term) {
                if (is_array($term)) {
                    $name = $this->text($term['name'] ?? null);
                    if ($name !== '') {
                        $publishers[] = $name;
                    }
                }
            }

            $publishers = array_values(array_unique($publishers));

            return $publishers !== [] ? implode(', ', $publishers) : null;
        }

        return null;
    }

    private function minorUnit($value): ?int
    {
        if (is_int($value) && $value >= 0 && $value <= 4) {
            return $value;
        }
        if (is_string($value) && preg_match('/\A[0-4]\z/D', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    private function minorPrice($value, int $minorUnit): ?float
    {
        if (is_int($value)) {
            $minor = $value;
        } elseif (is_string($value) && preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $value) === 1) {
            $minor = (int) $value;
        } else {
            return null;
        }

        return round($minor / (10 ** $minorUnit), $minorUnit);
    }

    private function nonNegativeInteger($value): ?int
    {
        if (is_int($value) && $value >= 0) {
            return $value;
        }
        if (is_string($value) && preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    private function isList(array $values): bool
    {
        return $values === [] || array_keys($values) === range(0, count($values) - 1);
    }
}
