<?php

namespace App\Services\Novella;

class NovellaCategoryParser
{
    use NormalizesNovellaData;

    public function parseCollection(array $payload): array
    {
        $rawItems = $payload['items'] ?? null;
        $total = $this->nonNegativeInteger($payload['total'] ?? null);
        $totalPages = $this->nonNegativeInteger($payload['total_pages'] ?? null);
        $page = $this->positiveInteger($payload['page'] ?? null);
        $perPage = $this->positiveInteger($payload['per_page'] ?? null);
        if (! is_array($rawItems) || ! $this->isList($rawItems) || $total === null || $totalPages === null
            || $page === null || $perPage === null || $perPage > NovellaCategoryApiClient::MAX_PER_PAGE) {
            throw new NovellaTerminalException('Novella odgovor kategorija nema ispravnu paginaciju.');
        }

        $all = [];
        foreach ($rawItems as $item) {
            if (! is_array($item)) {
                throw new NovellaTerminalException('Novella odgovor sadrži neispravnu kategoriju.');
            }
            $parsed = $this->parseCategory($item);
            if (isset($all[$parsed['id']])) {
                throw new NovellaTerminalException('Novella odgovor sadrži ponovljeni ID kategorije.');
            }
            $all[$parsed['id']] = $parsed;
        }

        $bookCategoryId = NovellaProductApiClient::bookCategoryId();
        $items = [];
        foreach ($all as $id => $category) {
            if ($this->belongsToBooks($id, $all, $bookCategoryId)) {
                $items[] = $category;
            }
        }

        usort($items, function (array $left, array $right) use ($bookCategoryId): int {
            if ($left['id'] === $bookCategoryId) {
                return -1;
            }
            if ($right['id'] === $bookCategoryId) {
                return 1;
            }

            return strnatcasecmp($left['name'], $right['name']);
        });

        return [
            'items' => $items,
            'book_categories' => $items,
            'total' => $total,
            'total_pages' => $totalPages,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public function parseCategory(array $category): array
    {
        $id = $this->positiveInteger($category['id'] ?? null);
        $name = $this->text($category['name'] ?? null);
        $slug = $this->text($category['slug'] ?? null);
        $parent = $this->nonNegativeInteger($category['parent'] ?? null);
        $count = $this->nonNegativeInteger($category['count'] ?? null);
        if ($id === null || $name === '' || $slug === '' || $parent === null || $count === null) {
            throw new NovellaTerminalException('Novella kategoriji nedostaju obavezni podaci.');
        }

        $link = $this->safeCategoryLink($category['permalink'] ?? null);

        return [
            'id' => $id,
            'name' => $name,
            'slug' => $slug,
            'parent_id' => $parent,
            'count' => $count,
            'source_url' => $link,
        ];
    }

    private function belongsToBooks(int $id, array $categories, int $bookCategoryId): bool
    {
        $visited = [];
        while ($id > 0) {
            if ($id === $bookCategoryId) {
                return true;
            }
            if (isset($visited[$id]) || ! isset($categories[$id])) {
                return false;
            }

            $visited[$id] = true;
            $id = $categories[$id]['parent_id'];
        }

        return false;
    }

    private function safeCategoryLink($value): ?string
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
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
            || isset($parts['query']) || isset($parts['fragment'])
            || ! str_starts_with((string) ($parts['path'] ?? ''), '/kategorija-proizvoda/')) {
            return null;
        }

        return $url;
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
