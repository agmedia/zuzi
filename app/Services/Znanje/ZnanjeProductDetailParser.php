<?php

namespace App\Services\Znanje;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

class ZnanjeProductDetailParser
{
    use NormalizesZnanjeData;

    public function parse(string $html, string $url): array
    {
        $url = $this->safeProductUrl($url);
        $html = str_replace("\0", '', $html);
        if ($url === null || trim($html) === '') {
            throw new ZnanjeTerminalException('Znanje stranica artikla ili njezina adresa nije ispravna.');
        }
        $path = (string) parse_url($url, PHP_URL_PATH);
        if (preg_match('#/([1-9][0-9]*)/?\z#D', $path, $matches) !== 1) {
            throw new ZnanjeTerminalException('Znanje ID artikla nije moguće pročitati iz adrese.');
        }
        $remoteId = (int) $matches[1];

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML(
                '<?xml encoding="UTF-8">' . $html,
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (! $loaded) {
            throw new ZnanjeTerminalException('Znanje HTML artikla nije moguće pročitati.');
        }
        $xpath = new DOMXPath($document);
        $title = $this->nodeText($xpath->query(
            '//h1[contains(concat(" ", normalize-space(@class), " "), " product-name ")]'
        )->item(0));
        if ($title === '') {
            throw new ZnanjeTerminalException('Znanje HTML ne sadrži naziv artikla.');
        }

        $canonicalNode = $xpath->query('//meta[@itemprop="url"][@content]')->item(0);
        if ($canonicalNode instanceof DOMElement) {
            $canonical = $this->safeProductUrl($canonicalNode->getAttribute('content'));
            if ($canonical !== null
                && rtrim((string) parse_url($canonical, PHP_URL_PATH), '/') !== rtrim($path, '/')) {
                throw new ZnanjeTerminalException('Znanje stranica vratila je drugi artikl od zatraženog.');
            }
        }

        $specifications = $this->specifications($xpath);
        $categories = $this->categories($xpath, $title);
        if ($categories === [] || ! in_array($categories[0], ['Knjige', 'Strane knjige'], true)) {
            throw new ZnanjeTerminalException('Znanje artikl ne pripada podržanoj kategoriji knjiga.');
        }
        $authors = $this->authors($xpath);
        $images = $this->images($xpath);
        $sku = $this->metaContent($xpath, 'sku') ?: (string) $remoteId;
        $pageRemoteId = $this->positiveInteger($sku);
        if ($pageRemoteId !== null && $pageRemoteId !== $remoteId) {
            throw new ZnanjeTerminalException('Znanje stranica artikla sadrži proturječan ID.');
        }

        $isbn = $this->identifier($this->metaContent($xpath, 'isbn'));
        $ean = $this->identifier(
            $this->metaContent($xpath, 'gtin13')
                ?: $this->specification($specifications, ['barkod', 'ean'])
        );
        if ($isbn === null && $ean !== null && in_array(strlen($ean), [10, 13], true)) {
            $isbn = $ean;
        }
        [$price, $salePrice] = $this->prices($xpath);
        $availabilityNode = $xpath->query('//*[@itemprop="offers"]//*[@itemprop="availability"][@href]')->item(0);
        $availabilityUrl = $availabilityNode instanceof DOMElement
            ? strtolower($availabilityNode->getAttribute('href'))
            : '';
        $availabilityType = strtolower((string) basename((string) parse_url($availabilityUrl, PHP_URL_PATH)));
        $available = in_array($availabilityType, [
            'instock', 'preorder', 'backorder', 'limitedavailability', 'onlineonly',
        ], true);
        $descriptionNode = $xpath->query('//*[not(self::meta) and @itemprop="description"]')->item(0);
        $description = $descriptionNode instanceof DOMNode
            ? $this->description($this->innerHtml($descriptionNode))
            : '';
        $publisher = $this->publisher($xpath)
            ?: $this->specification($specifications, ['nakladnikproizvodac', 'nakladnik', 'izdavac']);
        $pages = $this->positiveInteger(
            $this->metaContent($xpath, 'numberOfPages')
                ?: preg_replace('/[^0-9]/', '', $this->specification($specifications, ['brojstranica']))
        );
        $yearText = $this->metaContent($xpath, 'datePublished')
            ?: $this->specification($specifications, ['godinaizdanja']);
        $year = preg_match('/\b(?:19|20)[0-9]{2}\b/', $yearText, $yearMatch) === 1
            ? (int) $yearMatch[0]
            : null;
        $language = $this->language(
            $this->specification($specifications, ['jezikizdanja', 'jezik'])
                ?: $this->metaContent($xpath, 'inLanguage')
        );
        $genres = array_values(array_slice($categories, 1));

        return [
            'external_id' => (string) $remoteId,
            'remote_product_id' => $remoteId,
            'name' => $title,
            'title' => $title,
            'source_url' => $url,
            'permalink' => $url,
            'sku' => $sku,
            'isbn' => $isbn,
            'ean' => $ean,
            'barcode' => $ean,
            'author' => $authors !== [] ? implode(', ', $authors) : null,
            'authors' => $authors,
            'translator' => ($translator = $this->specification($specifications, ['prevoditelj'])) !== ''
                ? $translator
                : null,
            'source_publisher' => $publisher !== '' ? $publisher : null,
            'publisher' => $publisher !== '' ? $publisher : null,
            'source_category' => $categories[0],
            'category' => $categories[0],
            'source_categories' => $categories,
            'source_genres' => $genres,
            'genre' => $genres[0] ?? null,
            'format' => ($format = $this->specification($specifications, ['format'])) !== '' ? $format : null,
            'pages' => $pages,
            'letter' => ($letter = $this->specification($specifications, ['pismo'])) !== '' ? $letter : null,
            'binding' => $this->binding($this->specification($specifications, ['uvez'])),
            'publication_year' => $year,
            'year' => $year,
            'language' => $language,
            'origin' => ($origin = $this->specification($specifications, [
                'zemljaporijekla', 'mjestopodrijetla', 'porijeklo',
            ])) !== '' ? $origin : null,
            'description' => $description,
            'image_url' => $images[0] ?? null,
            'additional_image_urls' => array_slice($images, 1),
            'image' => $images[0] ?? null,
            'images' => $images,
            'price_eur' => $price,
            'sale_price_eur' => $salePrice,
            'availability' => $available ? 'in_stock' : 'out_of_stock',
            'available' => $available,
            'detail_payload' => $specifications,
        ];
    }

    private function specifications(DOMXPath $xpath): array
    {
        $result = [];
        $nodes = $xpath->query('//span[contains(concat(" ", normalize-space(@class), " "), " text-medium ")]');
        if ($nodes === false) {
            return [];
        }
        foreach ($nodes as $labelNode) {
            if (! $labelNode instanceof DOMElement || ! $labelNode->parentNode instanceof DOMNode) {
                continue;
            }
            $label = $this->nodeText($labelNode);
            $key = $this->normalizedKey($label);
            $fullText = $this->nodeText($labelNode->parentNode);
            $value = trim(preg_replace('/^' . preg_quote($label, '/') . '\s*/iu', '', $fullText) ?? '');
            if ($key !== '' && $value !== '' && ! isset($result[$key])) {
                $result[$key] = $value;
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

    private function categories(DOMXPath $xpath, string $title): array
    {
        $result = [];
        $nodes = $xpath->query(
            '//li[@itemprop="itemListElement"]//a[contains(@href,"/kategorija-proizvoda/")]'
            . '//*[@itemprop="name"]'
        );
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                $name = $this->nodeText($node);
                if ($name !== '' && $name !== $title) {
                    $result[] = $name;
                }
            }
        }

        return array_values(array_unique($result));
    }

    private function authors(DOMXPath $xpath): array
    {
        $result = [];
        $nodes = $xpath->query(
            '//*[@itemprop="author" and not(ancestor::*[@itemprop="author"])]'
        );
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if (! $node instanceof DOMNode) {
                    continue;
                }
                $nameNodes = (new DOMXPath($node->ownerDocument))->query(
                    './/*[@itemprop="name"]',
                    $node
                );
                $names = [];
                if ($nameNodes !== false) {
                    foreach ($nameNodes as $nameNode) {
                        $name = $this->nodeText($nameNode);
                        if ($name !== '') {
                            $names[] = $name;
                        }
                    }
                }
                if ($names !== []) {
                    $result[] = implode(', ', array_values(array_unique($names)));
                }
            }
        }
        $displayAuthor = $this->nodeText(
            $xpath->query('//h2[contains(@class,"product-author")]')->item(0)
        );
        $displayAuthor = preg_replace('/\s*,\s*/u', ', ', $displayAuthor) ?? $displayAuthor;
        if (count($result) > 1
            && $displayAuthor !== ''
            && $this->normalizedKey($displayAuthor) === $this->normalizedKey(implode(', ', $result))) {
            // Znanje occasionally splits a surname-first author such as
            // "Bataille, Josephine" into two schema.org Person nodes even
            // though its visible canonical author value is one comma form.
            $result = [$displayAuthor];
        }
        if ($result === []) {
            if ($displayAuthor !== '') {
                $result[] = $displayAuthor;
            }
        }

        return array_values(array_unique($result));
    }

    private function images(DOMXPath $xpath): array
    {
        $result = [];
        $nodes = $xpath->query('//div[contains(@class,"product-gallery")]//img[@src]');
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                if ($node instanceof DOMElement
                    && ($url = $this->safeImageUrl($node->getAttribute('src'))) !== null) {
                    $result[] = $url;
                }
            }
        }

        return array_values(array_unique($result));
    }

    private function publisher(DOMXPath $xpath): string
    {
        return $this->nodeText($xpath->query('//*[@itemprop="publisher"]//*[@itemprop="name"]')->item(0));
    }

    private function prices(DOMXPath $xpath): array
    {
        $current = $this->decimal($this->metaContent($xpath, 'price'));
        $regularCandidates = [];
        // The crossed-out price in the main h2 block is the regular price.
        // h6 can contain an unrelated legal "lowest price in 30 days" value.
        $nodes = $xpath->query(
            '//span[contains(concat(" ", normalize-space(@class), " "), " h2 ")]//del'
        );
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                preg_match_all('/[0-9][0-9.,\s]*\s*€/u', $this->nodeText($node), $matches);
                foreach ($matches[0] ?? [] as $match) {
                    $value = $this->decimal($match);
                    if ($value !== null && $value > 0) {
                        $regularCandidates[] = $value;
                    }
                }
            }
        }
        $regular = $regularCandidates !== [] ? max($regularCandidates) : null;
        if ($current !== null && $current > 0 && $regular !== null && $regular > $current) {
            return [$regular, $current];
        }

        return [$current, null];
    }

    private function metaContent(DOMXPath $xpath, string $itemprop): string
    {
        $node = $xpath->query('//*[@itemprop="' . $itemprop . '"][@content]')->item(0);

        return $node instanceof DOMElement ? $this->text($node->getAttribute('content')) : '';
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
}
