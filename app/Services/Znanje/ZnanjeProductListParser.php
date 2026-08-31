<?php

namespace App\Services\Znanje;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

class ZnanjeProductListParser
{
    use NormalizesZnanjeData;

    public function parse(string $html, int $rootCategoryId, int $page = 1): array
    {
        $roots = ZnanjeProductListClient::roots();
        if (! isset($roots[$rootCategoryId]) || $page < 1) {
            throw new ZnanjeTerminalException('Znanje kategorija ili stranica nije ispravna.');
        }
        $html = str_replace("\0", '', $html);
        if (trim($html) === '') {
            throw new ZnanjeTerminalException('Znanje stranica kataloga je prazna.');
        }

        [$document, $xpath] = $this->document($html);
        $available = $xpath->query('//*[@id="showAvailableOnly" and @checked]');
        $sort = $xpath->query('//*[@id="sorting"]//option[@selected and @value="date|desc"]');
        $perPageOption = $xpath->query('//*[@id="numberOfProducts"]//option[@selected and @value="84"]');
        if ($available === false || $available->length !== 1
            || $sort === false || $sort->length !== 1
            || $perPageOption === false || $perPageOption->length !== 1) {
            throw new ZnanjeTerminalException(
                'Znanje katalog nije potvrdio filtre dostupnosti, veličine stranice i najnovijeg datuma.'
            );
        }

        $totalNode = $xpath->query('//*[@itemprop="numberOfItems"]')->item(0);
        $total = $this->positiveInteger(preg_replace('/[^0-9]/', '', $this->nodeText($totalNode)) ?? '');
        if ($total === null) {
            throw new ZnanjeTerminalException('Znanje katalog ne sadrži pouzdan ukupan broj proizvoda.');
        }

        $cards = $xpath->query(
            '//div[contains(concat(" ", normalize-space(@class), " "), " grid-item ")]'
            . '/div[contains(concat(" ", normalize-space(@class), " "), " product-card ")]'
        );
        if ($cards === false) {
            throw new ZnanjeTerminalException('Znanje proizvode nije moguće pročitati.');
        }
        $expectedCount = min(
            ZnanjeProductListClient::PER_PAGE,
            max(0, $total - (($page - 1) * ZnanjeProductListClient::PER_PAGE))
        );
        if ($expectedCount < 1 || $cards->length !== $expectedCount) {
            throw new ZnanjeTerminalException(sprintf(
                'Znanje stranica %d sadrži %d od očekivanih %d proizvoda.',
                $page,
                $cards->length,
                $expectedCount
            ));
        }

        $items = [];
        $seen = [];
        foreach ($cards as $card) {
            if (! $card instanceof DOMElement) {
                continue;
            }
            $item = $this->parseCard($xpath, $card, $roots[$rootCategoryId]['name']);
            if (isset($seen[$item['external_id']])) {
                throw new ZnanjeTerminalException(
                    'Znanje stranica sadrži ponovljeni artikl ' . $item['external_id'] . '.'
                );
            }
            $seen[$item['external_id']] = true;
            $items[] = $item;
        }

        $totalPages = (int) ceil($total / ZnanjeProductListClient::PER_PAGE);

        return [
            'items' => $items,
            'total' => $total,
            'total_pages' => $totalPages,
            'page' => $page,
            'per_page' => ZnanjeProductListClient::PER_PAGE,
            'has_more' => $page < $totalPages,
            'next_page' => $page < $totalPages ? $page + 1 : null,
            'root_category_id' => $rootCategoryId,
            'root_category' => $roots[$rootCategoryId]['name'],
        ];
    }

    private function parseCard(DOMXPath $xpath, DOMElement $card, string $rootName): array
    {
        $link = $xpath->query(
            './/a[contains(concat(" ", normalize-space(@class), " "), " product-thumb ")][@href]',
            $card
        )->item(0);
        $url = $link instanceof DOMElement ? $this->safeProductUrl($link->getAttribute('href')) : null;
        $path = $url !== null ? (string) parse_url($url, PHP_URL_PATH) : '';
        if ($url === null || preg_match('#/([1-9][0-9]*)/?\z#D', $path, $matches) !== 1) {
            throw new ZnanjeTerminalException('Znanje kartica sadrži neispravnu adresu artikla.');
        }
        $remoteId = (int) $matches[1];
        $name = $this->nodeText($xpath->query(
            './/h3[contains(concat(" ", normalize-space(@class), " "), " product-title ")]',
            $card
        )->item(0));
        $author = $this->nodeText($xpath->query(
            './/p[contains(concat(" ", normalize-space(@class), " "), " product-author ")]',
            $card
        )->item(0));
        $imageNode = $xpath->query('.//a[contains(@class,"product-thumb")]//img', $card)->item(0);
        $image = null;
        if ($imageNode instanceof DOMElement) {
            $image = $this->safeImageUrl(
                $imageNode->getAttribute('src') ?: $imageNode->getAttribute('data-src')
            );
        }
        $button = $xpath->query('.//div[contains(@class,"product-buttons")]//button', $card)->item(0);
        if (! $button instanceof DOMElement || $button->hasAttribute('disabled')) {
            throw new ZnanjeTerminalException(
                'Znanje je u dostupni listing uključio nedostupan artikl ' . $remoteId . '.'
            );
        }

        $metadata = $link instanceof DOMElement
            ? $this->eventMetadata($link->getAttribute('onclick'))
            : [];
        if ($metadata !== []) {
            // Tracking removes punctuation such as apostrophes, so compare a
            // normalized title while keeping the canonical URL/ID authoritative.
            if ((int) ($metadata[2] ?? 0) !== $remoteId
                || $this->normalizedKey($metadata[3] ?? '') !== $this->normalizedKey($name)) {
                throw new ZnanjeTerminalException(
                    'Znanje kartica artikla ' . $remoteId . ' sadrži proturječne identifikatore.'
                );
            }
            $author = $author !== '' ? $author : $this->text($metadata[12] ?? '');
        }
        if ($name === '') {
            throw new ZnanjeTerminalException('Znanje kartica artikla nema naziv.');
        }

        $categories = array_values(array_unique(array_filter(array_map(
            fn ($value) => $this->text($value),
            array_merge([$rootName], array_slice($metadata, 7, 4))
        ))));
        if (($categories[0] ?? null) !== $rootName) {
            array_unshift($categories, $rootName);
            $categories = array_values(array_unique($categories));
        }
        if (isset($metadata[7]) && $this->text($metadata[7]) !== ''
            && $this->text($metadata[7]) !== $rootName) {
            throw new ZnanjeTerminalException(
                'Znanje artikl ' . $remoteId . ' ne pripada očekivanoj korijenskoj kategoriji.'
            );
        }
        $genres = array_values(array_filter($categories, fn (string $value) => $value !== $rootName));
        [$regularPrice, $salePrice] = $this->prices($xpath, $card, $metadata);
        if ($regularPrice === null || $regularPrice <= 0) {
            throw new ZnanjeTerminalException('Znanje artikl ' . $remoteId . ' nema valjanu EUR cijenu.');
        }

        $year = $this->positiveInteger($metadata[13] ?? null);
        $year = $year !== null && $year >= 1900 && $year <= 2100 ? $year : null;
        $item = [
            'external_id' => (string) $remoteId,
            'remote_product_id' => $remoteId,
            'feed_position' => $remoteId,
            'name' => $name,
            'description' => null,
            'source_category' => $rootName,
            'source_categories' => $categories,
            'source_publisher' => ($publisher = $this->text($metadata[6] ?? '')) !== '' ? $publisher : null,
            'source_url' => $url,
            'image_url' => $image,
            'additional_image_urls' => [],
            'price_eur' => $regularPrice,
            'sale_price_eur' => $salePrice,
            'availability' => 'in_stock',
            'sku' => (string) $remoteId,
            'isbn' => null,
            'ean' => null,
            'author' => $author !== '' ? $author : null,
            'source_genres' => $genres,
            'genre' => $genres[0] ?? null,
            'format' => null,
            'pages' => null,
            'letter' => null,
            'binding' => $this->binding($metadata[15] ?? null),
            'publication_year' => $year,
            'language' => $this->language($metadata[14] ?? null),
            'origin' => null,
        ];
        $item['source_hash'] = hash('sha256', (string) json_encode(
            $item,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        ));

        return $item;
    }

    private function prices(DOMXPath $xpath, DOMElement $card, array $metadata): array
    {
        $text = $this->nodeText($xpath->query(
            './/h4[contains(concat(" ", normalize-space(@class), " "), " product-price ")]',
            $card
        )->item(0));
        preg_match_all('/[0-9][0-9.,\s]*\s*€/u', $text, $matches);
        $visible = array_values(array_filter(array_map(
            fn ($value) => $this->decimal($value),
            $matches[0] ?? []
        ), fn ($value) => $value !== null && $value > 0));
        if (count($visible) >= 2 && max($visible) !== min($visible)) {
            return [max($visible), min($visible)];
        }

        // Tracking exposes the current price and the absolute discount, not a
        // regular/sale pair. The visible <del> price above remains authoritative.
        $eventCurrent = $this->decimal($metadata[4] ?? null);
        $eventDiscount = $this->decimal($metadata[5] ?? null);
        if ($eventCurrent !== null && $eventCurrent > 0
            && $eventDiscount !== null && $eventDiscount > 0) {
            return [round($eventCurrent + $eventDiscount, 4), $eventCurrent];
        }

        return [$visible[0] ?? ($eventCurrent > 0 ? $eventCurrent : null), null];
    }

    private function eventMetadata(string $javascript): array
    {
        $needle = 'sendFullEventForClick(';
        $start = strpos($javascript, $needle);
        if ($start === false) {
            return [];
        }
        $start += strlen($needle);
        $arguments = [];
        $current = '';
        $quote = null;
        $escaped = false;
        $length = strlen($javascript);
        for ($index = $start; $index < $length; $index++) {
            $character = $javascript[$index];
            if ($quote !== null) {
                if ($escaped) {
                    $current .= [
                        'n' => "\n", 'r' => "\r", 't' => "\t",
                    ][$character] ?? $character;
                    $escaped = false;
                } elseif ($character === '\\') {
                    $escaped = true;
                } elseif ($character === $quote) {
                    $quote = null;
                } else {
                    $current .= $character;
                }
                continue;
            }
            if ($character === "'" || $character === '"') {
                $quote = $character;
            } elseif ($character === ',') {
                $arguments[] = trim($current);
                $current = '';
            } elseif ($character === ')') {
                $arguments[] = trim($current);
                break;
            } else {
                $current .= $character;
            }
        }

        return count($arguments) >= 19 && ($arguments[0] ?? '') === 'select_item'
            ? $arguments
            : [];
    }

    private function document(string $html): array
    {
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
            throw new ZnanjeTerminalException('Znanje HTML kataloga nije moguće pročitati.');
        }

        return [$document, new DOMXPath($document)];
    }

    private function nodeText(?DOMNode $node): string
    {
        return $node === null ? '' : $this->text($node->textContent);
    }
}
