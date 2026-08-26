<?php

namespace App\Services\Novella;

use App\Models\Back\Catalog\Category;
use App\Models\Back\Catalog\Publisher;
use App\Models\Back\Settings\Settings;
use Illuminate\Support\Facades\Schema;

class NovellaImportSettings
{
    private const CODE = 'novella_import';

    public function all(): array
    {
        $stored = Schema::hasTable('settings')
            ? Settings::query()->where('code', self::CODE)->pluck('value', 'key')
            : collect();
        $existingAction = (string) $stored->get('existing_action', 'skip');

        return [
            // Kept for the shared import contract; Novella prices are already EUR.
            'exchange_rate' => 1.0,
            'markup_percent' => (float) $stored->get(
                'markup_percent',
                config('novella_import.markup_percent', 0)
            ),
            'publisher_parent_category_id' => (int) $stored->get(
                'publisher_parent_category_id',
                $this->defaultPublisherParentCategoryId()
            ),
            'publisher_category_id' => (int) $stored->get(
                'publisher_category_id',
                $this->defaultPublisherCategoryId()
            ),
            'publisher_id' => (int) $stored->get('publisher_id', $this->defaultPublisherId()),
            'map_source_publishers' => (bool) ((int) $stored->get('map_source_publishers', 0)),
            'category_map' => $this->decodeCategoryMap($stored->get('category_map', '{}')),
            // Alias keeps the shared Delfi-style settings UI reusable.
            'genre_category_map' => $this->decodeCategoryMap($stored->get('category_map', '{}')),
            'default_quantity' => (int) $stored->get(
                'default_quantity',
                config('novella_import.default_quantity', 1)
            ),
            'activate_new_products' => (bool) ((int) $stored->get('activate_new_products', 0)),
            'existing_action' => in_array($existingAction, ['skip', 'price_stock'], true)
                ? $existingAction
                : 'skip',
        ];
    }

    public function save(array $values): array
    {
        $scalarKeys = [
            'markup_percent',
            'publisher_parent_category_id',
            'publisher_category_id',
            'publisher_id',
            'map_source_publishers',
            'default_quantity',
            'activate_new_products',
            'existing_action',
        ];

        foreach ($scalarKeys as $key) {
            if (array_key_exists($key, $values)) {
                $this->store($key, (string) $values[$key], false);
            }
        }

        $mapKey = array_key_exists('category_map', $values)
            ? 'category_map'
            : (array_key_exists('genre_category_map', $values) ? 'genre_category_map' : null);
        if ($mapKey !== null) {
            $map = $this->sanitizeCategoryMap($values[$mapKey]);
            $this->store(
                'category_map',
                (string) json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                true
            );
        }

        return $this->all();
    }

    private function store(string $key, string $value, bool $json): void
    {
        Settings::query()->updateOrCreate(
            ['code' => self::CODE, 'key' => $key],
            ['value' => $value, 'json' => $json ? 1 : 0]
        );
    }

    private function decodeCategoryMap($value): array
    {
        if (is_array($value)) {
            return $this->sanitizeCategoryMap($value);
        }

        $decoded = json_decode((string) $value, true);

        return $this->sanitizeCategoryMap(is_array($decoded) ? $decoded : []);
    }

    private function sanitizeCategoryMap($map): array
    {
        if (! is_array($map)) {
            return [];
        }

        $normalized = [];
        foreach ($map as $sourceCategory => $categoryId) {
            $sourceCategory = trim((string) $sourceCategory);
            $categoryId = (int) $categoryId;
            if ($sourceCategory !== '' && $categoryId > 0) {
                $normalized[$sourceCategory] = $categoryId;
            }
        }
        ksort($normalized, SORT_NATURAL | SORT_FLAG_CASE);

        return $normalized;
    }

    private function defaultPublisherParentCategoryId(): int
    {
        if (! Schema::hasTable('categories')) {
            return 0;
        }

        return (int) (Category::query()
            ->where('parent_id', 0)
            ->whereRaw('LOWER(TRIM(title)) = ?', ['nakladnici'])
            ->value('id') ?: 0);
    }

    private function defaultPublisherCategoryId(): int
    {
        $parentId = $this->defaultPublisherParentCategoryId();
        if (! $parentId) {
            return 0;
        }

        return (int) (Category::query()
            ->where('parent_id', $parentId)
            ->whereRaw('LOWER(TRIM(title)) = ?', ['novella'])
            ->value('id') ?: 0);
    }

    private function defaultPublisherId(): int
    {
        if (! Schema::hasTable('publishers')) {
            return 0;
        }

        return (int) (Publisher::query()
            ->whereRaw('LOWER(TRIM(title)) = ?', ['novella'])
            ->value('id') ?: 0);
    }
}
