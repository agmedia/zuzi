<?php

namespace App\Services\Laguna;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

class LagunaProductPageParser
{
    public function parse(string $html): array
    {
        if (trim($html) === '') {
            throw new RuntimeException('Laguna stranica artikla je prazna.');
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previousErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $loaded = $document->loadHTML($html, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        if (! $loaded) {
            throw new RuntimeException('Laguna stranicu artikla nije moguće pročitati.');
        }

        $xpath = new DOMXPath($document);
        $structured = $this->structuredProduct($xpath);
        $specifications = $this->specifications($xpath);
        $isbn = $this->normalizeIsbn($structured['isbn'] ?? ($specifications['isbn'] ?? ''));
        $year = $this->year($specifications['godina izdanja'] ?? '');

        return [
            'isbn' => $isbn,
            'author' => $this->authors($structured['author'] ?? null),
            'genre' => $this->text($structured['genre'] ?? ''),
            'format' => $this->text($specifications['format'] ?? ''),
            'pages' => $this->positiveInteger($structured['numberOfPages'] ?? ($specifications['broj strana'] ?? null)),
            'letter' => $this->text($specifications['pismo'] ?? ''),
            'binding' => $this->normalizeBinding($specifications['povez'] ?? ''),
            'publication_year' => $year,
            'sku' => $this->text($structured['sku'] ?? ($specifications['šifra proizvoda'] ?? '')),
            'description' => $this->text($structured['description'] ?? ''),
        ];
    }

    private function structuredProduct(DOMXPath $xpath): array
    {
        $scripts = $xpath->query('//script[@type="application/ld+json"]');
        if (! $scripts) {
            return [];
        }

        foreach ($scripts as $script) {
            $decoded = json_decode(trim((string) $script->textContent), true);
            if (! is_array($decoded)) {
                continue;
            }

            foreach ($this->jsonLdNodes($decoded) as $node) {
                $types = (array) ($node['@type'] ?? []);
                if (array_intersect($types, ['Product', 'Book']) !== []) {
                    return $node;
                }
            }
        }

        return [];
    }

    private function jsonLdNodes(array $decoded): array
    {
        if (isset($decoded['@graph']) && is_array($decoded['@graph'])) {
            return $decoded['@graph'];
        }

        if (array_keys($decoded) === range(0, count($decoded) - 1)) {
            return $decoded;
        }

        return [$decoded];
    }

    private function specifications(DOMXPath $xpath): array
    {
        $values = [];
        $containers = $xpath->query('//div[count(*[self::span or self::div or self::dt or self::dd]) >= 2]');

        if ($containers) {
            foreach ($containers as $container) {
                $children = [];
                foreach ($container->childNodes as $child) {
                    if ($child instanceof DOMElement) {
                        $children[] = $child;
                    }
                }

                if (count($children) < 2) {
                    continue;
                }

                $label = $this->label($children[0]->textContent);
                if (! in_array($label, [
                    'format', 'broj strana', 'pismo', 'povez', 'godina izdanja',
                    'isbn', 'šifra proizvoda',
                ], true)) {
                    continue;
                }

                $value = $this->text($children[1]->textContent);
                if ($value !== '') {
                    $values[$label] = $value;
                }
            }
        }

        $terms = $xpath->query('//dt');
        if ($terms) {
            foreach ($terms as $term) {
                $label = $this->label($term->textContent);
                $valueNode = $term->nextSibling;
                while ($valueNode && ! ($valueNode instanceof DOMElement)) {
                    $valueNode = $valueNode->nextSibling;
                }
                if ($valueNode && $valueNode->nodeName === 'dd' && $label !== '') {
                    $values[$label] = $this->text($valueNode->textContent);
                }
            }
        }

        return $values;
    }

    private function authors($value): string
    {
        $names = [];
        foreach ((array) $value as $author) {
            if (is_array($author)) {
                $name = $this->text($author['name'] ?? '');
            } else {
                $name = $this->text($author);
            }

            if ($name !== '') {
                $names[] = $name;
            }
        }

        return implode(', ', array_unique($names));
    }

    private function label(string $value): string
    {
        return mb_strtolower(rtrim($this->text($value), ':'));
    }

    private function text($value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $value = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return preg_replace('/\s+/u', ' ', trim($value)) ?? '';
    }

    private function normalizeIsbn($value): ?string
    {
        $isbn = strtoupper(preg_replace('/[^0-9X]/i', '', (string) $value) ?? '');

        return in_array(strlen($isbn), [10, 13], true) ? $isbn : null;
    }

    private function positiveInteger($value): ?int
    {
        if (! preg_match('/\d+/', (string) $value, $matches)) {
            return null;
        }

        $number = (int) $matches[0];

        return $number > 0 ? $number : null;
    }

    private function year(string $value): ?int
    {
        if (! preg_match('/\b(19|20)\d{2}\b/', $value, $matches)) {
            return null;
        }

        return (int) $matches[0];
    }

    private function normalizeBinding(string $value): string
    {
        $binding = $this->text($value);
        $normalized = mb_strtolower($binding);

        if ($normalized === 'mek' || $normalized === 'meki povez') {
            return 'Meki';
        }

        if ($normalized === 'tvrd' || $normalized === 'tvrdi povez') {
            return 'Tvrdi';
        }

        return $binding;
    }
}
