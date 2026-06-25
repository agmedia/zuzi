<?php

namespace App\Services\Pelion;

use App\Services\GiftVoucherService;
use App\Services\GiftWrapService;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PelionStockService
{
    public function validateCheckoutItems($cartItems, ?string $baseUrl = null, ?string $apiKey = null): array
    {
        $baseUrl = $this->baseUrl($baseUrl);
        $apiKey = $this->apiKey($apiKey);
        $requestedByProductId = $this->requestedProductsFromCart($cartItems);

        if ($requestedByProductId->isEmpty()) {
            return [
                'ok' => true,
                'message' => null,
                'checked' => [],
                'skipped' => [],
                'unavailable' => [],
                'zeroed_product_ids' => [],
            ];
        }

        $products = DB::table('products')
            ->select('id', 'name', 'sku', 'itemid', 'quantity', 'delivery_24h')
            ->whereIn('id', $requestedByProductId->keys()->all())
            ->get()
            ->keyBy('id');

        $checkItems = [];
        $skipped = [];
        $unavailable = [];

        foreach ($requestedByProductId as $productId => $requestedQuantity) {
            $product = $products->get($productId);

            if (! $product) {
                $unavailable[] = [
                    'id' => $productId,
                    'name' => 'Artikl #' . $productId,
                    'requested' => $requestedQuantity,
                    'available' => 0,
                    'reason' => 'missing_product',
                ];
                continue;
            }

            if ((int) $product->delivery_24h === 1) {
                $skipped[] = [
                    'id' => (int) $product->id,
                    'name' => $product->name,
                    'reason' => 'delivery_24h',
                ];
                continue;
            }

            $itemId = $this->normalizePelionItemId($product->itemid);

            if (! $itemId) {
                $skipped[] = [
                    'id' => (int) $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'requested' => $requestedQuantity,
                    'available' => 0,
                    'reason' => 'missing_itemid',
                ];
                continue;
            }

            $checkItems[$itemId] = [
                'product' => $product,
                'requested' => $requestedQuantity,
            ];
        }

        try {
            $stockByItemId = $this->stockQuantitiesForItemIds(array_keys($checkItems), $baseUrl, $apiKey);
        } catch (\Throwable $e) {
            return $this->skippedCheckoutCheck('pelion_unavailable', $e, $skipped);
        }

        $checked = [];

        foreach ($checkItems as $itemId => $item) {
            $product = $item['product'];
            $requestedQuantity = (int) $item['requested'];
            $availableQuantity = $this->normalizeProductStockQuantity($stockByItemId[$itemId] ?? 0);

            $checked[] = [
                'id' => (int) $product->id,
                'itemid' => $itemId,
                'name' => $product->name,
                'requested' => $requestedQuantity,
                'available' => $availableQuantity,
            ];

            if ($availableQuantity < $requestedQuantity) {
                $unavailable[] = [
                    'id' => (int) $product->id,
                    'itemid' => $itemId,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'requested' => $requestedQuantity,
                    'available' => $availableQuantity,
                    'reason' => $availableQuantity > 0 ? 'insufficient_quantity' : 'out_of_stock',
                ];
            }
        }

        $zeroedProductIds = count($unavailable)
            ? $this->zeroUnavailableProductQuantities($unavailable)
            : [];

        return [
            'ok' => count($unavailable) === 0,
            'message' => count($unavailable) ? $this->checkoutUnavailableMessage($unavailable) : null,
            'checked' => $checked,
            'skipped' => $skipped,
            'unavailable' => $unavailable,
            'zeroed_product_ids' => $zeroedProductIds,
        ];
    }

    public function stockQuantitiesForItemIds(array $itemIds, ?string $baseUrl = null, ?string $apiKey = null): array
    {
        $baseUrl = $this->baseUrl($baseUrl);
        $apiKey = $this->apiKey($apiKey);
        $stockByItemId = [];
        $itemIds = collect($itemIds)
            ->map(function ($itemId) {
                return $this->normalizePelionItemId($itemId);
            })
            ->filter()
            ->unique()
            ->values();

        if ($itemIds->isEmpty()) {
            return [];
        }

        $url = $baseUrl . '/stockList';
        $responses = Http::pool(function (Pool $pool) use ($itemIds, $url, $apiKey) {
            foreach ($itemIds as $itemId) {
                $pool->as((string) $itemId)
                    ->withHeaders($this->headers($apiKey))
                    ->withOptions($this->checkoutHttpOptions())
                    ->get($url, ['ItemId' => $itemId]);
            }
        });

        foreach ($itemIds as $itemId) {
            $response = $responses[(string) $itemId] ?? null;

            if (! $response instanceof Response || ! $response->successful()) {
                throw new RuntimeException('Pelion provjera zalihe nije uspjela za ItemID ' . $itemId . '.');
            }

            $stockByItemId[$itemId] = $this->sumStockQuantity($this->normalizeStockRows($response->json()));
        }

        return $stockByItemId;
    }

    public function stockSummaryFromRows(array $stockRows): array
    {
        $stockByItemId = [];
        $rowsQuantityGreaterThanZero = 0;
        $skippedInvalid = 0;

        foreach ($stockRows as $stockRow) {
            if (! is_array($stockRow)) {
                $skippedInvalid++;
                continue;
            }

            $itemId = $this->stockRowItemId($stockRow);

            if (! $itemId) {
                $skippedInvalid++;
                continue;
            }

            $quantity = $this->stockQuantityFromRow($stockRow);

            if ($quantity > 0) {
                $rowsQuantityGreaterThanZero++;
            }

            $stockByItemId[$itemId] = ($stockByItemId[$itemId] ?? 0) + $quantity;
        }

        return [
            'stock_rows_received' => count($stockRows),
            'stock_itemids_received' => count($stockByItemId),
            'stock_items_quantity_gt_0' => count(array_filter($stockByItemId, function (float $quantity) {
                return $quantity > 0;
            })),
            'stock_rows_quantity_gt_0' => $rowsQuantityGreaterThanZero,
            'skipped_invalid' => $skippedInvalid,
        ];
    }

    public function normalizeStockRows($stock): array
    {
        if (! is_array($stock)) {
            return [];
        }

        if (isset($stock['STOCKQUANTITY']) || isset($stock['ITEMID'])) {
            return [$stock];
        }

        foreach (['stock', 'Stock', 'STOCK', 'data', 'Data', 'items', 'Items'] as $key) {
            if (isset($stock[$key]) && is_array($stock[$key])) {
                return $this->normalizeStockRows($stock[$key]);
            }
        }

        return array_values(array_filter($stock, 'is_array'));
    }

    private function requestedProductsFromCart($cartItems): Collection
    {
        return collect($cartItems)
            ->filter(function ($item) {
                return ! GiftVoucherService::isGiftVoucherItem($item)
                    && ! GiftWrapService::isGiftWrapItem($item)
                    && is_numeric(data_get($item, 'id'));
            })
            ->groupBy(function ($item) {
                return (int) data_get($item, 'id');
            })
            ->map(function (Collection $items) {
                return (int) $items->sum(function ($item) {
                    return max(1, (int) data_get($item, 'quantity', 1));
                });
            });
    }

    private function checkoutUnavailableMessage(array $unavailable): string
    {
        $items = collect($unavailable)
            ->take(3)
            ->map(function (array $item) {
                return $item['name'] . ' (u košarici: ' . $item['requested'] . ', dostupno: ' . $item['available'] . ')';
            })
            ->implode('; ');

        return 'Nažalost, artikl nije dostupan: ' . $items . '. Molimo maknite taj artikl iz košarice prije završetka narudžbe.';
    }

    private function zeroUnavailableProductQuantities(array $unavailable): array
    {
        $productIds = collect($unavailable)
            ->filter(function (array $item) {
                return ($item['reason'] ?? null) === 'out_of_stock';
            })
            ->pluck('id')
            ->filter()
            ->map(function ($id) {
                return (int) $id;
            })
            ->unique()
            ->values()
            ->all();

        if (empty($productIds)) {
            return [];
        }

        DB::table('products')
            ->whereIn('id', $productIds)
            ->where(function ($query) {
                $query->whereNull('delivery_24h')
                    ->orWhere('delivery_24h', '!=', 1);
            })
            ->update([
                'quantity' => 0,
                'updated_at' => now(),
            ]);

        return $productIds;
    }

    private function skippedCheckoutCheck(string $reason, \Throwable $e, array $skipped = []): array
    {
        Log::warning('Pelion checkout stock check skipped', [
            'reason' => $reason,
            'message' => $e->getMessage(),
        ]);

        return [
            'ok' => true,
            'message' => null,
            'checked' => [],
            'skipped' => $skipped,
            'unavailable' => [],
            'zeroed_product_ids' => [],
            'stock_check_skipped' => true,
            'skip_reason' => $reason,
        ];
    }

    private function baseUrl(?string $baseUrl = null): string
    {
        return rtrim($baseUrl ?: config('services.pelion.base_url', 'https://zuzishop.pelionpro.com/api/v1'), '/');
    }

    private function apiKey(?string $apiKey = null): string
    {
        $apiKey = $apiKey ?: config('services.pelion.api_key');

        if (! $apiKey) {
            throw new RuntimeException('Nedostaje Pelion API ključ.');
        }

        return $apiKey;
    }

    private function headers(string $apiKey): array
    {
        return [
            'Content-Type' => 'application/json',
            'X-API-KEY' => $apiKey,
        ];
    }

    private function checkoutHttpOptions(): array
    {
        return [
            'connect_timeout' => max(1, (float) config('services.pelion.checkout_connect_timeout', 1)),
            'timeout' => max(1, (float) config('services.pelion.checkout_timeout', 2)),
        ];
    }

    private function stockQuantityFromRow(array $row): float
    {
        $quantity = str_replace(',', '.', trim((string) ($row['STOCKQUANTITY'] ?? $row['StockQuantity'] ?? $row['stockquantity'] ?? $row['quantity'] ?? $row['Quantity'] ?? 0)));

        return is_numeric($quantity) ? (float) $quantity : 0;
    }

    private function stockRowItemId(array $row): ?int
    {
        return $this->normalizePelionItemId($row['ITEMID'] ?? $row['ItemId'] ?? $row['itemid'] ?? $row['item_id'] ?? null);
    }

    private function normalizePelionItemId($value): ?int
    {
        $value = trim((string) $value);

        if ($value === '' || ! ctype_digit($value)) {
            return null;
        }

        $itemid = (int) $value;

        return $itemid > 0 ? $itemid : null;
    }

    private function normalizeProductStockQuantity($quantity): int
    {
        return max(0, (int) floor((float) $quantity));
    }

    private function sumStockQuantity(array $stockRows): float
    {
        return array_reduce($stockRows, function (float $total, array $row) {
            return $total + $this->stockQuantityFromRow($row);
        }, 0.0);
    }
}
