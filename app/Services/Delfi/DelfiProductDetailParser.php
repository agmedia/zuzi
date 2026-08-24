<?php

namespace App\Services\Delfi;

class DelfiProductDetailParser
{
    public function parse(array $payload): array
    {
        $product = $this->product($payload);
        $attributes = $this->attributes($product['attributes'] ?? []);
        $barcode = $this->ean($product['barcode'] ?? null);
        $isbn = $this->isbn($this->attribute($attributes, ['isbn']))
            ?: $this->isbn($product['barcode'] ?? null);
        $authors = $this->namedValues($product['authors'] ?? [], ['authorName', 'name', 'title']);
        $genres = array_values(array_unique(array_merge(
            $this->namedValues($product['genres'] ?? [], ['genreName', 'name', 'title']),
            $this->namedValues($product['subgenres'] ?? [], ['subgenreName', 'genreName', 'name', 'title'])
        )));
        $images = $this->images($product);

        return [
            'external_id' => $this->text($product['_id'] ?? null) ?: null,
            'remote_product_id' => $this->positiveInteger($product['oldProductId'] ?? null),
            'isbn' => $isbn,
            'ean' => $barcode,
            'nav_id' => $this->text($product['navId'] ?? null) ?: null,
            'sku' => $this->text($product['navId'] ?? null) ?: null,
            'author' => $authors !== [] ? implode(', ', $authors) : null,
            'authors' => $authors,
            'publisher' => $this->text($product['publisher'] ?? null) ?: null,
            'genre' => $genres[0] ?? null,
            'source_genres' => $genres,
            'format' => $this->attribute($attributes, ['format', 'dimensions']) ?: null,
            'pages' => $this->positiveInteger($this->attribute($attributes, [
                'numberofpages', 'pages', 'pagenumber',
            ])),
            'letter' => $this->letter($product['alphabets'] ?? ($this->attribute($attributes, ['alphabet', 'letter']))),
            'binding' => $this->binding($product['cover'] ?? ($this->attribute($attributes, ['cover', 'binding']))),
            'publication_year' => $this->publicationYear($product, $attributes),
            'year' => $this->publicationYear($product, $attributes),
            'description' => $this->description($product['description'] ?? ($product['shortDescription'] ?? '')),
            'images' => $images,
            'image' => $images[0] ?? null,
            'language' => $this->language($product, $attributes),
            'origin' => $this->origin($product, $attributes),
        ];
    }

    private function product(array $payload): array
    {
        if (isset($payload['data']['product']) && is_array($payload['data']['product'])) {
            $product = $payload['data']['product'];
        } elseif (isset($payload['product']) && is_array($payload['product'])) {
            $product = $payload['product'];
        } else {
            $product = $payload;
        }

        if ($product === [] || (! isset($product['_id']) && ! isset($product['oldProductId']) && ! isset($product['barcode']))) {
            throw new DelfiTerminalException('Delfi odgovor ne sadrži podatke artikla.');
        }

        return $product;
    }

    private function attributes($values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $attributes = [];
        foreach ($values as $key => $attribute) {
            if (is_array($attribute) && array_key_exists('k', $attribute)) {
                $name = $this->attributeKey($attribute['k']);
                $value = $this->text($attribute['v'] ?? null);
            } elseif (is_string($key)) {
                $name = $this->attributeKey($key);
                $value = $this->text($attribute);
            } else {
                continue;
            }

            if ($name !== '' && $value !== '') {
                $attributes[$name] = $value;
            }
        }

        return $attributes;
    }

    private function attribute(array $attributes, array $keys): string
    {
        foreach ($keys as $key) {
            $key = $this->attributeKey($key);
            if (isset($attributes[$key]) && $attributes[$key] !== '') {
                return $attributes[$key];
            }
        }

        return '';
    }

    private function attributeKey($value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', mb_strtolower($this->text($value))) ?? '';
    }

    private function namedValues($values, array $keys): array
    {
        if (! is_array($values)) {
            return [];
        }

        $result = [];
        foreach ($values as $value) {
            if (is_array($value)) {
                $name = '';
                foreach ($keys as $key) {
                    $name = $this->text($value[$key] ?? null);
                    if ($name !== '') {
                        break;
                    }
                }
            } else {
                $name = $this->text($value);
            }

            if ($name !== '') {
                $result[] = $name;
            }
        }

        return array_values(array_unique($result));
    }

    private function ean($value): ?string
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

    private function isbn($value): ?string
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
                $digit = $isbn[$index] === 'X' ? 10 : (int) $isbn[$index];
                if ($isbn[$index] === 'X' && $index !== 9) {
                    return null;
                }
                $sum += $digit * (10 - $index);
            }

            return $sum % 11 === 0 ? $isbn : null;
        }

        return null;
    }

    private function positiveInteger($value): ?int
    {
        if (! preg_match('/\d+/', (string) $value, $matches)) {
            return null;
        }

        $number = (int) $matches[0];

        return $number > 0 ? $number : null;
    }

    private function letter($alphabets): ?string
    {
        $values = is_array($alphabets) ? $alphabets : [$alphabets];
        $mapped = [];

        foreach ($values as $alphabet) {
            if (is_array($alphabet)) {
                $alphabet = $alphabet['name'] ?? ($alphabet['title'] ?? '');
            }

            $value = $this->text($alphabet);
            $normalized = mb_strtolower($value);
            if (in_array($normalized, ['cyrillic', 'cirilica', 'ćirilica'], true)) {
                $mapped[] = 'Ćirilica';
            } elseif (in_array($normalized, ['latin', 'latinic', 'latinica'], true)) {
                $mapped[] = 'Latinica';
            } elseif ($value !== '') {
                $mapped[] = $value;
            }
        }

        $mapped = array_values(array_unique($mapped));

        return $mapped !== [] ? implode(', ', $mapped) : null;
    }

    private function binding($value): ?string
    {
        $binding = $this->text($value);
        if ($binding === '') {
            return null;
        }

        $normalized = mb_strtolower($binding);
        if (in_array($normalized, [
            'mek', 'meki', 'mek povez', 'meki povez', 'paperback', 'softback', 'softcover', 'broš', 'bros',
        ], true)) {
            return 'Meki';
        }

        if (in_array($normalized, [
            'tvrd', 'tvrdi', 'tvrd povez', 'tvrdi povez', 'hardback', 'hardcover',
        ], true)) {
            return 'Tvrdi';
        }

        return $binding;
    }

    private function publicationYear(array $product, array $attributes): ?int
    {
        $values = [
            $product['releaseDate'] ?? null,
            $this->attribute($attributes, ['publicationyear', 'yearofpublication', 'year']),
            $product['defaultReleaseDate'] ?? null,
        ];

        foreach ($values as $value) {
            if (preg_match('/\b(?:19|20)\d{2}\b/', (string) $value, $matches)) {
                return (int) $matches[0];
            }
        }

        return null;
    }

    private function description($value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $html = preg_replace('/<(?:br\s*\/?|\/p|\/div|\/li)>/iu', "\n", (string) $value) ?? (string) $value;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\t ]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s*\n\s*/u', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function images(array $product): array
    {
        $paths = [];
        $sizes = $product['images'] ?? [];
        if (is_array($sizes)) {
            foreach (['xxl', 'xl', 'l', 'm', 's', 'fb'] as $size) {
                if (! empty($sizes[$size])) {
                    $paths[] = $sizes[$size];
                    break;
                }
            }
        }

        foreach ((array) ($product['imgGallery'] ?? []) as $image) {
            if (! is_array($image)) {
                continue;
            }

            foreach (['originalImg', 'fullScreenImg', 'thumbnailImg'] as $key) {
                if (! empty($image[$key])) {
                    $paths[] = $image[$key];
                    break;
                }
            }
        }

        $result = [];
        foreach ($paths as $path) {
            $url = $this->imageUrl($path);
            if ($url !== null) {
                $result[] = $url;
            }
        }

        return array_values(array_unique($result));
    }

    private function imageUrl($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '//')) {
            $value = 'https:' . $value;
        } elseif (str_starts_with($value, '/')) {
            $value = 'https://delfi.rs' . $value;
        }

        $value = str_replace(' ', '%20', $value);
        $scheme = mb_strtolower((string) parse_url($value, PHP_URL_SCHEME));
        $host = mb_strtolower((string) parse_url($value, PHP_URL_HOST));
        $allowedHosts = array_map('mb_strtolower', (array) config('delfi_import.allowed_image_hosts', [
            'delfi.rs',
            'www.delfi.rs',
        ]));

        return $scheme === 'https'
            && in_array($host, $allowedHosts, true)
            && filter_var($value, FILTER_VALIDATE_URL) !== false
                ? $value
                : null;
    }

    private function language(array $product, array $attributes): ?string
    {
        $value = $this->attribute($attributes, ['languageofedition', 'language', 'jezikizdanja', 'jezik']);
        if ($value !== '') {
            $normalized = mb_strtolower($value);

            return [
                'english' => 'Engleski',
                'engleski' => 'Engleski',
                'serbian' => 'Srpski',
                'srpski' => 'Srpski',
                'croatian' => 'Hrvatski',
                'hrvatski' => 'Hrvatski',
            ][$normalized] ?? $value;
        }

        $category = mb_strtolower($this->text($product['category'] ?? null));
        if ($category === 'knjiga') {
            return 'Srpski';
        }

        if ($category === 'strana knjiga') {
            return 'Engleski';
        }

        return null;
    }

    private function origin(array $product, array $attributes): ?string
    {
        $origin = $this->attribute($attributes, ['madein', 'importedfrom', 'placeofpublication', 'origin']);
        if ($origin !== '') {
            return $origin;
        }

        return mb_strtolower($this->text($product['category'] ?? null)) === 'knjiga' ? 'Beograd' : null;
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
