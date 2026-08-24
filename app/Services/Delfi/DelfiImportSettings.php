<?php

namespace App\Services\Delfi;

use App\Models\Back\Catalog\Category;
use App\Models\Back\Catalog\Publisher;
use App\Models\Back\Settings\Settings;
use Illuminate\Support\Facades\Schema;

class DelfiImportSettings
{
    private const CODE = 'delfi_import';

    public function all(): array
    {
        $stored = Schema::hasTable('settings')
            ? Settings::query()->where('code', self::CODE)->pluck('value', 'key')
            : collect();
        $existingAction = (string) $stored->get('existing_action', 'skip');

        return [
            'exchange_rate' => (float) $stored->get('exchange_rate', config('delfi_import.exchange_rate', 117.2)),
            'markup_percent' => (float) $stored->get('markup_percent', config('delfi_import.markup_percent', 0)),
            'publisher_parent_category_id' => (int) $stored->get(
                'publisher_parent_category_id',
                $this->defaultPublisherParentCategoryId()
            ),
            'publisher_category_id' => (int) $stored->get(
                'publisher_category_id',
                $this->defaultPublisherCategoryId()
            ),
            'publisher_id' => (int) $stored->get('publisher_id', $this->defaultPublisherId()),
            'map_source_publishers' => (bool) ((int) $stored->get('map_source_publishers', 1)),
            'genre_category_map' => $this->decodeGenreMap($stored->get('genre_category_map', '{}')),
            'default_quantity' => (int) $stored->get('default_quantity', config('delfi_import.default_quantity', 1)),
            'activate_new_products' => (bool) ((int) $stored->get('activate_new_products', 0)),
            'translate_descriptions' => (bool) ((int) $stored->get(
                'translate_descriptions',
                config('delfi_import.translate_descriptions', false) ? 1 : 0
            )),
            'existing_action' => in_array($existingAction, ['skip', 'price_stock'], true)
                ? $existingAction
                : 'skip',
        ];
    }

    public function save(array $values): array
    {
        $scalarKeys = [
            'exchange_rate',
            'markup_percent',
            'publisher_parent_category_id',
            'publisher_category_id',
            'publisher_id',
            'map_source_publishers',
            'default_quantity',
            'activate_new_products',
            'translate_descriptions',
            'existing_action',
        ];

        foreach ($scalarKeys as $key) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            $this->store($key, (string) $values[$key], false);
        }

        if (array_key_exists('genre_category_map', $values)) {
            $map = $this->sanitizeGenreMap($values['genre_category_map']);
            $this->store(
                'genre_category_map',
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

    private function decodeGenreMap($value): array
    {
        if (is_array($value)) {
            return $this->sanitizeGenreMap($value);
        }

        $decoded = json_decode((string) $value, true);

        return $this->sanitizeGenreMap(is_array($decoded) ? $decoded : []);
    }

    private function sanitizeGenreMap($map): array
    {
        if (! is_array($map)) {
            return [];
        }

        $normalized = [];
        foreach ($map as $sourceGenre => $categoryId) {
            $sourceGenre = trim((string) $sourceGenre);
            $categoryId = (int) $categoryId;

            if ($sourceGenre !== '' && $categoryId > 0) {
                $normalized[$sourceGenre] = $categoryId;
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
            ->whereRaw('LOWER(TRIM(title)) = ?', ['delfi'])
            ->value('id') ?: 0);
    }

    private function defaultPublisherId(): int
    {
        if (! Schema::hasTable('publishers')) {
            return 0;
        }

        return (int) (Publisher::query()
            ->whereRaw('LOWER(TRIM(title)) = ?', ['delfi'])
            ->value('id') ?: 0);
    }
}
