<?php

namespace App\Services\Laguna;

use App\Models\Back\Catalog\Category;
use App\Models\Back\Catalog\Publisher;
use App\Models\Back\Settings\Settings;

class LagunaImportSettings
{
    private const CODE = 'laguna_import';

    public function all(): array
    {
        $stored = Settings::query()
            ->where('code', self::CODE)
            ->pluck('value', 'key');
        $existingAction = (string) $stored->get('existing_action', 'skip');

        return [
            'exchange_rate' => (float) $stored->get('exchange_rate', config('laguna_import.exchange_rate', 117.2)),
            'markup_percent' => (float) $stored->get('markup_percent', config('laguna_import.markup_percent', 0)),
            'publisher_parent_category_id' => (int) $stored->get(
                'publisher_parent_category_id',
                $this->defaultPublisherParentCategoryId()
            ),
            'publisher_category_id' => (int) $stored->get(
                'publisher_category_id',
                $this->defaultPublisherCategoryId()
            ),
            'publisher_id' => (int) $stored->get('publisher_id', $this->defaultPublisherId()),
            'default_quantity' => (int) $stored->get('default_quantity', config('laguna_import.default_quantity', 1)),
            'activate_new_products' => (bool) ((int) $stored->get('activate_new_products', 0)),
            'existing_action' => in_array($existingAction, ['skip', 'price_stock'], true)
                ? $existingAction
                : 'skip',
        ];
    }

    public function save(array $values): array
    {
        foreach ($values as $key => $value) {
            Settings::query()->updateOrCreate(
                ['code' => self::CODE, 'key' => $key],
                ['value' => (string) $value, 'json' => 0]
            );
        }

        return $this->all();
    }

    private function defaultPublisherParentCategoryId(): int
    {
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
            ->whereRaw('LOWER(TRIM(title)) = ?', ['laguna'])
            ->value('id') ?: 0);
    }

    private function defaultPublisherId(): int
    {
        return (int) (Publisher::query()
            ->whereRaw('LOWER(TRIM(title)) = ?', ['laguna'])
            ->value('id') ?: 0);
    }
}
