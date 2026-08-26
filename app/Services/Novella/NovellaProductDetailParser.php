<?php

namespace App\Services\Novella;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

class NovellaProductDetailParser
{
    use NormalizesNovellaData;

    public function parse(string $html): array
    {
        if (trim($html) === '') {
            throw new NovellaTerminalException('Novella stranica artikla je prazna.');
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (! $loaded) {
            throw new NovellaTerminalException('Novella HTML artikla nije moguće pročitati.');
        }

        $xpath = new DOMXPath($document);
        $title = $this->nodeText($xpath->query('//h1[contains(concat(" ", normalize-space(@class), " "), " product_title ")]')->item(0));
        if ($title === '') {
            throw new NovellaTerminalException('Novella HTML ne sadrži podatke artikla.');
        }

        $specifications = $this->specifications($xpath);
        $tracking = $this->trackingData($xpath);
        $schema = $this->schemaData($xpath);
        $barcodeValue = $this->specification($specifications, ['barkod', 'barcode']);
        $isbn = $this->isbn($this->specification($specifications, ['isbn']))
            ?: $this->isbn($barcodeValue)
            ?: $this->isbn($tracking['sku'] ?? null);
        $ean = $this->ean($barcodeValue) ?: $this->ean($tracking['sku'] ?? null);
        $author = $this->specification($specifications, ['autor', 'autorica', 'pisac'])
            ?: $this->text($tracking['item_brand'] ?? null);
        $authors = $author !== ''
            ? array_values(array_unique(array_filter(array_map([$this, 'text'], preg_split('/\s*[,;]\s*/u', $author) ?: []))))
            : [];
        $categories = $schema['source_categories'] ?? [];
        $images = array_values(array_unique(array_merge(
            $this->galleryImages($xpath),
            (array) ($schema['images'] ?? [])
        )));

        $pages = $this->numberFromText($this->specification($specifications, [
            'brojstranica', 'stranica', 'pages',
        ]));
        $year = $this->year($this->specification($specifications, [
            'godinaizdanja', 'godina', 'publicationyear',
        ]));
        $description = $this->pageDescription($xpath);
        $publisher = $this->specification($specifications, ['izdavac', 'nakladnik']);
        $sourceUrl = $this->productUrl($schema['source_url'] ?? null);
        $sourceGenres = array_values(array_filter($categories, fn (string $value) => $value !== 'Knjige'));

        return [
            'external_id' => isset($tracking['internal_id']) && $this->positiveInteger($tracking['internal_id']) !== null
                ? (string) $this->positiveInteger($tracking['internal_id'])
                : null,
            'remote_product_id' => $this->positiveInteger($tracking['internal_id'] ?? null),
            'name' => $title,
            'title' => $title,
            'source_url' => $sourceUrl,
            'permalink' => $sourceUrl,
            'sku' => ($sku = $this->text($tracking['sku'] ?? ($schema['sku'] ?? null))) !== '' ? $sku : null,
            'isbn' => $isbn,
            'ean' => $ean,
            'barcode' => $ean,
            'author' => $authors !== [] ? implode(', ', $authors) : null,
            'authors' => $authors,
            'translator' => ($translator = $this->specification($specifications, ['prevoditelj', 'prevodilac'])) !== ''
                ? $translator
                : null,
            'source_publisher' => $publisher !== '' ? $publisher : null,
            'publisher' => $publisher !== '' ? $publisher : null,
            'source_category' => $categories[0] ?? null,
            'category' => $categories[0] ?? null,
            'source_categories' => $categories,
            'source_genres' => $sourceGenres,
            'genre' => $sourceGenres[0] ?? null,
            'format' => ($format = $this->specification($specifications, ['formatknjige', 'format', 'dimenzije'])) !== ''
                ? $format
                : null,
            'pages' => $pages,
            'letter' => ($letter = $this->specification($specifications, ['pismo'])) !== '' ? $letter : null,
            'binding' => $this->binding($this->specification($specifications, ['uvez', 'povez', 'binding'])),
            'publication_year' => $year,
            'year' => $year,
            'language' => $this->language($this->specification($specifications, [
                'jezikizdanja', 'jezik', 'language',
            ])),
            'origin' => ($origin = $this->specification($specifications, [
                'mjestoizdanja', 'zemljaporijekla', 'porijeklo',
            ])) !== '' ? $origin : null,
            'description' => $description,
            'image_url' => $images[0] ?? null,
            'additional_image_urls' => array_slice($images, 1),
            'image' => $images[0] ?? null,
            'images' => $images,
            'detail_payload' => $specifications,
        ];
    }

    private function specifications(DOMXPath $xpath): array
    {
        $result = [];
        $nodes = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " ms_book_details ")]');
        if ($nodes === false) {
            return [];
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $spans = $xpath->query('./span', $node);
            if ($spans === false || $spans->length < 2) {
                continue;
            }
            $label = $this->normalizedKey($this->nodeText($spans->item(0)));
            $value = $this->nodeText($spans->item($spans->length - 1));
            if ($label !== '' && $value !== '') {
                $result[$label] = $value;
            }
        }

        return $result;
    }

    private function specification(array $specifications, array $keys): string
    {
        foreach ($keys as $key) {
            $key = $this->normalizedKey($key);
            if (isset($specifications[$key]) && $specifications[$key] !== '') {
                return $specifications[$key];
            }
        }

        return '';
    }

    private function trackingData(DOMXPath $xpath): array
    {
        $nodes = $xpath->query('//form[contains(concat(" ", normalize-space(@class), " "), " cart ")]//input[@name="gtm4wp_product_data"]');
        $node = $nodes === false ? null : $nodes->item(0);
        if (! $node instanceof DOMElement) {
            return [];
        }

        $decoded = json_decode($node->getAttribute('value'), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function schemaData(DOMXPath $xpath): array
    {
        $result = ['source_categories' => [], 'images' => []];
        $nodes = $xpath->query('//script[contains(translate(@type, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "application/ld+json")]');
        if ($nodes === false) {
            return $result;
        }

        foreach ($nodes as $node) {
            $decoded = json_decode((string) $node->textContent, true);
            if (! is_array($decoded)) {
                continue;
            }

            foreach ($this->schemaNodes($decoded) as $schemaNode) {
                $types = (array) ($schemaNode['@type'] ?? []);
                if (in_array('Product', $types, true)) {
                    $url = $this->productUrl($schemaNode['url'] ?? null);
                    if ($url !== null) {
                        $result['source_url'] = $url;
                    }
                    if (! isset($result['sku'])) {
                        $result['sku'] = $this->text($schemaNode['sku'] ?? null);
                    }
                    foreach ((array) ($schemaNode['image'] ?? []) as $image) {
                        $imageUrl = $this->imageUrl(is_array($image) ? ($image['url'] ?? ($image['contentUrl'] ?? null)) : $image);
                        if ($imageUrl !== null) {
                            $result['images'][] = $imageUrl;
                        }
                    }
                }
                if (in_array('BreadcrumbList', $types, true)) {
                    foreach ((array) ($schemaNode['itemListElement'] ?? []) as $element) {
                        if (! is_array($element)) {
                            continue;
                        }
                        $item = is_array($element['item'] ?? null) ? $element['item'] : [];
                        $link = $this->categoryUrl($item['@id'] ?? ($item['url'] ?? null));
                        $name = $this->text($item['name'] ?? ($element['name'] ?? null));
                        if ($link !== null && $name !== '') {
                            $result['source_categories'][] = $name;
                        }
                    }
                }
            }
        }

        $result['source_categories'] = array_values(array_unique($result['source_categories']));
        $result['images'] = array_values(array_unique($result['images']));

        return $result;
    }

    private function schemaNodes(array $decoded): array
    {
        if (isset($decoded['@graph']) && is_array($decoded['@graph'])) {
            return array_values(array_filter($decoded['@graph'], 'is_array'));
        }

        if ($decoded !== [] && array_keys($decoded) === range(0, count($decoded) - 1)) {
            return array_values(array_filter($decoded, 'is_array'));
        }

        return [$decoded];
    }

    private function galleryImages(DOMXPath $xpath): array
    {
        $result = [];
        $nodes = $xpath->query('//figure[contains(concat(" ", normalize-space(@class), " "), " woocommerce-product-gallery__image ")]//a[@href]');
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if ($node instanceof DOMElement && ($url = $this->imageUrl($node->getAttribute('href'))) !== null) {
                    $result[] = $url;
                }
            }
        }

        if ($result === []) {
            $nodes = $xpath->query('//meta[@property="og:image"][@content]');
            $node = $nodes === false ? null : $nodes->item(0);
            if ($node instanceof DOMElement && ($url = $this->imageUrl($node->getAttribute('content'))) !== null) {
                $result[] = $url;
            }
        }

        return array_values(array_unique($result));
    }

    private function pageDescription(DOMXPath $xpath): string
    {
        $nodes = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " elementor-tab-content ") and @data-tab="1"]');
        if ($nodes === false) {
            return '';
        }

        foreach ($nodes as $node) {
            $html = $this->innerHtml($node);
            $description = $this->description($html);
            if ($description !== '') {
                return $description;
            }
        }

        return '';
    }

    private function innerHtml(DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument?->saveHTML($child) ?: '';
        }

        return $html;
    }

    private function nodeText(?DOMNode $node): string
    {
        return $node === null ? '' : $this->text($node->textContent);
    }

    private function numberFromText(string $value): ?int
    {
        return preg_match('/\b[1-9][0-9]{0,5}\b/', $value, $matches) === 1 ? (int) $matches[0] : null;
    }

    private function year(string $value): ?int
    {
        return preg_match('/\b(?:19|20)\d{2}\b/', $value, $matches) === 1 ? (int) $matches[0] : null;
    }
}
