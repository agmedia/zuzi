<?php

namespace App\Services\Delfi;

use App\Models\Back\Catalog\DelfiImportProduct;
use App\Models\Back\Catalog\Product\Product;

class DelfiImportedProductStockSynchronizer
{
    private DelfiImportSettings $settings;

    public function __construct(DelfiImportSettings $settings)
    {
        $this->settings = $settings;
    }

    public function sync(): array
    {
        $defaultQuantity = max(0, (int) $this->settings->all()['default_quantity']);
        $importedProductIds = DelfiImportProduct::query()
            ->select('product_id')
            ->whereNotNull('product_id')
            ->whereNotNull('imported_at')
            ->distinct();
        $availableProductIds = DelfiImportProduct::query()
            ->select('product_id')
            ->availableForImport()
            ->where('is_current', true)
            ->whereNotNull('product_id')
            ->whereNotNull('imported_at')
            ->distinct();

        $zeroed = Product::query()
            ->whereIn('id', clone $importedProductIds)
            ->whereNotIn('id', clone $availableProductIds)
            ->where('quantity', '!=', 0)
            ->update([
                'quantity' => 0,
                'updated_at' => now(),
            ]);

        $restored = 0;
        if ($defaultQuantity > 0) {
            $restored = Product::query()
                ->whereIn('id', $availableProductIds)
                ->where('quantity', 0)
                ->update([
                    'quantity' => $defaultQuantity,
                    'updated_at' => now(),
                ]);
        }

        return [
            'zeroed' => $zeroed,
            'restored' => $restored,
            'default_quantity' => $defaultQuantity,
        ];
    }
}
