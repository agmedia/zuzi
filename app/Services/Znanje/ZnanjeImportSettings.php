<?php

namespace App\Services\Znanje;

use App\Models\Back\Catalog\Category;
use App\Models\Back\Catalog\Publisher;
use App\Models\Back\Settings\Settings;
use Illuminate\Support\Facades\Schema;

class ZnanjeImportSettings
{
    private const CODE = 'znanje_import';

    public function all(): array
    {
        $stored = Schema::hasTable('settings')
            ? Settings::query()
                ->where('code', self::CODE)
                ->orderBy('updated_at')
                ->orderBy('id')
                ->pluck('value', 'key')
            : collect();
        $existingAction = (string) $stored->get('existing_action', 'skip');

        return [
            // Znanje cijene već su izražene u eurima.
            'exchange_rate' => 1.0,
            'markup_percent' => (float) $stored->get(
                'markup_percent',
                config('znanje_import.markup_percent', 0)
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
            // Ako postoji istoimeni Zuzi nakladnik i njegova podkategorija,
            // koristi ih; u suprotnom sigurno ostaje zadani Znanje fallback.
            'map_source_publishers' => (bool) ((int) $stored->get('map_source_publishers', 1)),
            'category_map' => $this->decodeCategoryMap($stored->get('category_map', '{}')),
            // Alias zadržava kompatibilnost sa zajedničkim sučeljem importa.
            'genre_category_map' => $this->decodeCategoryMap($stored->get('category_map', '{}')),
            'default_quantity' => max(0, (int) $stored->get(
                'default_quantity',
                config('znanje_import.default_quantity', 1)
            )),
            'activate_new_products' => (bool) ((int) $stored->get('activate_new_products', 0)),
            'existing_action' => in_array($existingAction, ['skip', 'price_stock'], true)
                ? $existingAction
                : 'skip',
        ];
    }

    public function save(array $values): array
    {
        foreach ([
            'markup_percent',
            'publisher_parent_category_id',
            'publisher_category_id',
            'publisher_id',
            'map_source_publishers',
            'default_quantity',
            'activate_new_products',
            'existing_action',
        ] as $key) {
            if (array_key_exists($key, $values)) {
                $this->store($key, (string) $values[$key], false);
            }
        }

        $mapKey = array_key_exists('category_map', $values)
            ? 'category_map'
            : (array_key_exists('genre_category_map', $values) ? 'genre_category_map' : null);
        if ($mapKey !== null) {
            $this->store(
                'category_map',
                (string) json_encode(
                    $this->sanitizeCategoryMap($values[$mapKey]),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
                true
            );
        }

        return $this->all();
    }

    private function store(string $key, string $value, bool $json): void
    {
        $query = Settings::query()->where('code', self::CODE)->where('key', $key);
        $attributes = ['value' => $value, 'json' => $json ? 1 : 0];

        if ($query->exists()) {
            $query->update($attributes);

            return;
        }

        Settings::query()->create(['code' => self::CODE, 'key' => $key] + $attributes);
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
        foreach ($map as $sourceKey => $categoryId) {
            $sourceKey = trim((string) $sourceKey);
            $categoryId = (int) $categoryId;
            if ($sourceKey !== '' && $categoryId > 0) {
                $normalized[$sourceKey] = $categoryId;
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
            ->whereRaw('LOWER(TRIM(title)) = ?', ['znanje'])
            ->value('id') ?: 0);
    }

    private function defaultPublisherId(): int
    {
        if (! Schema::hasTable('publishers')) {
            return 0;
        }

        return (int) (Publisher::query()
            ->whereRaw('LOWER(TRIM(title)) = ?', ['znanje'])
            ->value('id') ?: 0);
    }
}
