<?php

namespace App\Http\Controllers\Back\Settings;

use App\Helpers\Csv;
use App\Http\Controllers\Controller;
use App\Models\Back\Settings\Api\PlavaKrava;
use App\Models\Back\Settings\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ApiController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        return view('back.settings.api.index');
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function pelion()
    {
        return view('back.settings.pelion.index', [
            'pelionBaseUrl' => config('services.pelion.base_url'),
        ]);
    }


    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function import(Request $request)
    {
        $data = $this->validateTarget($request);

        $targetClass = $this->resolveTargetClass($data);

        if ($targetClass) {
            $ok = $targetClass->process($data);

            if ($ok) {
                return response()->json(['success' => $ok]);
            }
        }

        return response()->json(['error' => 'Whoops.!! Pokušajte ponovo ili kontaktirajte administratora!']);
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public function upload(Request $request)
    {
        $request->validate([
            'target' => 'required',
            'method' => 'required',
            'file' => 'file|mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/excel'
        ]);

        $targetClass = $this->resolveTargetClass($request->toArray());

        if ($targetClass) {
            $path = $targetClass->upload($request);

            if ($path) {
                $excel = new Csv($path, 'Xlsx');

                $ok = $targetClass->process($request->toArray(), $excel->csv->toArray());

                if ($ok) {
                    return response()->json(['success' => $ok]);
                }
            }
        }


        return response()->json(['success' => $request->toArray()]);
    }


    /**
     * @param Request $request
     * @param string  $type
     *
     * @return mixed
     */
    public function validateTarget(Request $request, string $type = 'import')
    {
        $request->validate([
            'data.target' => 'required',
            'data.method' => 'required'
        ]);

        $data = $request->input('data');

        $request->merge([
            'data' => [
                'target' => $data['target'],
                'method' => $data['method'],
                'type' => $type
            ]
        ]);

        return $request->input('data');
    }


    /**
     * @param array $data
     *
     * @return mixed
     */
    private function resolveTargetClass(array $data)
    {
        $class = null;

        if (isset($data['target'])) {
            if ($data['target'] == 'plava-krava') {
                $class = new PlavaKrava();
            }
        }

        return $class;
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function pelionTest(Request $request)
    {
        $data = $request->validate([
            'action' => 'required|string|in:item-list,item-list-attrs,item-by-id,group-items,group-active,item-type,item-groups,stock-list,stock-by-item,sync-item-index,sync-product-isbns,sync-product-quantities,sync-product-shelves,stock-by-isbn',
            'item_id' => 'nullable|integer|min:1',
            'item_group_id' => 'nullable|integer|min:1',
            'item_type' => 'nullable|string|in:T,K,U',
            'isbn' => 'nullable|string|max:32',
            'base_url' => 'nullable|string|url',
            'api_key' => 'nullable|string',
        ]);

        $apiKey = ($data['api_key'] ?? null) ?: config('services.pelion.api_key');

        if (!$apiKey) {
            return response()->json([
                'error' => 'Nedostaje API ključ. Unesite ga u formu ili postavite PELION_API_KEY u .env (config/services.php -> pelion).'
            ], 422);
        }

        $baseUrl = rtrim(($data['base_url'] ?? null) ?: config('services.pelion.base_url', 'https://zuzishop.pelionpro.com/api/v1'), '/');

        if ($data['action'] === 'sync-item-index') {
            return $this->syncPelionItemIndex($baseUrl, $apiKey);
        }

        if ($data['action'] === 'sync-product-isbns') {
            return $this->syncProductIsbnsFromPelion($baseUrl, $apiKey);
        }

        if ($data['action'] === 'sync-product-quantities') {
            return $this->syncProductQuantitiesFromPelion($baseUrl, $apiKey);
        }

        if ($data['action'] === 'sync-product-shelves') {
            return $this->syncProductShelvesFromPelion($baseUrl, $apiKey);
        }

        if ($data['action'] === 'stock-by-isbn') {
            return $this->pelionStockByIsbn($data, $baseUrl, $apiKey);
        }

        $query = [];
        $path = '/itemList';

        switch ($data['action']) {
            case 'item-list':
                break;
            case 'item-list-attrs':
                $query['ItemAttributes'] = 'D';
                break;
            case 'item-by-id':
                if (empty($data['item_id'])) {
                    return response()->json(['error' => 'Za ovaj poziv treba ItemId.'], 422);
                }
                $query['ItemId'] = $data['item_id'];
                break;
            case 'group-items':
                if (empty($data['item_group_id'])) {
                    return response()->json(['error' => 'Za ovaj poziv treba ItemGroupId.'], 422);
                }
                $query['ItemGroupId'] = $data['item_group_id'];
                break;
            case 'group-active':
                if (empty($data['item_group_id'])) {
                    return response()->json(['error' => 'Za ovaj poziv treba ItemGroupId.'], 422);
                }
                $query['ItemGroupId'] = $data['item_group_id'];
                $query['ItemActive'] = 'D';
                break;
            case 'item-type':
                if (empty($data['item_type'])) {
                    return response()->json(['error' => 'Za ovaj poziv treba ItemType (T, K ili U).'], 422);
                }
                $query['ItemType'] = $data['item_type'];
                break;
            case 'item-groups':
                $path = '/itemGroupList';
                break;
            case 'stock-list':
                $path = '/stockList';
                break;
            case 'stock-by-item':
                if (empty($data['item_id'])) {
                    return response()->json(['error' => 'Za ovaj poziv treba ItemId.'], 422);
                }
                $path = '/stockList';
                $query['ItemId'] = $data['item_id'];
                break;
        }

        $url = $baseUrl . $path;

        try {
            $response = Http::timeout(20)
                            ->withHeaders([
                                'Content-Type' => 'application/json',
                                'X-API-KEY' => $apiKey,
                            ])
                            ->get($url, $query);

            return response()->json([
                'success' => true,
                'status' => $response->status(),
                'url' => $response->effectiveUri() ? (string) $response->effectiveUri() : $url,
                'body' => $response->json() !== null ? $response->json() : $response->body(),
            ], $response->successful() ? 200 : 422);
        } catch (\Throwable $e) {
            Log::error('Pelion API test failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Greška kod poziva Pelion API-ja: ' . $e->getMessage()
            ], 500);
        }
    }

    private function syncPelionItemIndex(string $baseUrl, string $apiKey)
    {
        if (! Schema::hasTable('pelion_items')) {
            return response()->json([
                'error' => 'Nedostaje tablica pelion_items. Pokrenite migracije prije sinkronizacije.'
            ], 422);
        }

        $url = $baseUrl . '/itemList';

        try {
            $response = Http::timeout(120)
                            ->withHeaders($this->pelionHeaders($apiKey))
                            ->get($url);

            if (! $response->successful()) {
                return response()->json([
                    'success' => false,
                    'status' => $response->status(),
                    'url' => $response->effectiveUri() ? (string) $response->effectiveUri() : $url,
                    'body' => $response->json() !== null ? $response->json() : $response->body(),
                ], 422);
            }

            $items = $response->json();

            if (! is_array($items)) {
                return response()->json([
                    'error' => 'Pelion itemList nije vratio očekivani JSON niz.'
                ], 422);
            }

            $syncedAt = now();
            $rows = [];
            $synced = 0;

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $barcode = $this->normalizeBarcode($item['ITEMBARCODE'] ?? null);
                $itemId = (int) trim((string) ($item['ITEMID'] ?? ''));

                if (! $barcode || ! $itemId) {
                    continue;
                }

                $rows[] = [
                    'item_id' => $itemId,
                    'item_barcode' => $barcode,
                    'item_code' => trim((string) ($item['ITEMCODE'] ?? '')) ?: null,
                    'item_name' => trim((string) ($item['ITEMNAME'] ?? '')) ?: null,
                    'item_group_id' => trim((string) ($item['ITEMGROUPID'] ?? '')) ?: null,
                    'item_active' => trim((string) ($item['ITEMACTIVE'] ?? '')) ?: null,
                    'item_type' => trim((string) ($item['ITEMTYPE'] ?? '')) ?: null,
                    'item_price' => is_numeric($item['ITEMPRICE'] ?? null) ? (float) $item['ITEMPRICE'] : null,
                    'synced_at' => $syncedAt,
                    'created_at' => $syncedAt,
                    'updated_at' => $syncedAt,
                ];

                if (count($rows) >= 1000) {
                    $synced += $this->upsertPelionItems($rows);
                    $rows = [];
                }
            }

            if ($rows) {
                $synced += $this->upsertPelionItems($rows);
            }

            return response()->json([
                'success' => true,
                'status' => $response->status(),
                'url' => $response->effectiveUri() ? (string) $response->effectiveUri() : $url,
                'body' => [
                    'synced' => $synced,
                    'message' => 'Pelion barcode index je osvježen.',
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Pelion item index sync failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Greška kod osvježavanja Pelion barcode indexa: ' . $e->getMessage()
            ], 500);
        }
    }

    private function pelionStockByIsbn(array $data, string $baseUrl, string $apiKey)
    {
        if (! Schema::hasTable('pelion_items')) {
            return response()->json([
                'error' => 'Nedostaje tablica pelion_items. Pokrenite migracije prije dohvaćanja zalihe po ISBN-u.'
            ], 422);
        }

        $isbn = $this->normalizeBarcode($data['isbn'] ?? null);

        if (! $isbn) {
            return response()->json(['error' => 'Za ovaj poziv treba ISBN / ITEMBARCODE.'], 422);
        }

        $pelionItems = DB::table('pelion_items')
                         ->where('item_barcode', $isbn)
                         ->orderBy('item_id')
                         ->get();

        if ($pelionItems->isEmpty()) {
            return response()->json([
                'error' => 'ISBN nije pronađen u lokalnom Pelion barcode indexu. Pokrenite "Osvježi Pelion barcode index" pa pokušajte ponovo.',
                'body' => [
                    'isbn' => $isbn,
                ],
            ], 404);
        }

        $localProduct = $this->findLocalProductByIsbn($isbn);
        $url = $baseUrl . '/stockList';

        try {
            $stockRows = [];
            $stockUrls = [];
            $stockStatuses = [];

            foreach ($pelionItems as $pelionItem) {
                $response = Http::timeout(20)
                                ->withHeaders($this->pelionHeaders($apiKey))
                                ->get($url, ['ItemId' => $pelionItem->item_id]);

                $stockStatuses[] = $response->status();
                $stockUrls[] = $response->effectiveUri() ? (string) $response->effectiveUri() : $url . '?ItemId=' . $pelionItem->item_id;
                $stockRows = array_merge($stockRows, $this->normalizeStockRows($response->json()));
            }

            $ok = collect($stockStatuses)->every(fn ($status) => $status >= 200 && $status < 300);

            return response()->json([
                'success' => true,
                'status' => $ok ? 200 : 422,
                'url' => count($stockUrls) === 1 ? $stockUrls[0] : $url,
                'body' => [
                    'isbn' => $isbn,
                    'quantity' => $this->sumStockQuantity($stockRows),
                    'local_product' => $localProduct,
                    'pelion_items' => $pelionItems->map(function ($item) {
                        return [
                            'ITEMID' => $item->item_id,
                            'ITEMBARCODE' => $item->item_barcode,
                            'ITEMCODE' => $item->item_code,
                            'ITEMNAME' => $item->item_name,
                        ];
                    })->values(),
                    'stock' => $stockRows,
                    'urls' => $stockUrls,
                ],
            ], $ok ? 200 : 422);
        } catch (\Throwable $e) {
            Log::error('Pelion stock by ISBN failed', [
                'isbn' => $isbn,
                'item_ids' => $pelionItems->pluck('item_id')->all(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Greška kod dohvata Pelion zalihe po ISBN-u: ' . $e->getMessage()
            ], 500);
        }
    }

    private function syncProductIsbnsFromPelion(string $baseUrl, string $apiKey)
    {
        if (
            ! Schema::hasTable('products') ||
            ! Schema::hasColumn('products', 'sku') ||
            ! Schema::hasColumn('products', 'isbn') ||
            ! Schema::hasColumn('products', 'itemid')
        ) {
            return response()->json([
                'error' => 'Nedostaje tablica products ili stupci products.sku / products.isbn / products.itemid.'
            ], 422);
        }

        $url = $baseUrl . '/itemList';

        try {
            $response = Http::timeout(120)
                            ->withHeaders($this->pelionHeaders($apiKey))
                            ->get($url);

            if (! $response->successful()) {
                return response()->json([
                    'success' => false,
                    'status' => $response->status(),
                    'url' => $response->effectiveUri() ? (string) $response->effectiveUri() : $url,
                    'body' => $response->json() !== null ? $response->json() : $response->body(),
                ], 422);
            }

            $items = $response->json();

            if (! is_array($items)) {
                return response()->json([
                    'error' => 'Pelion itemList nije vratio očekivani JSON niz.'
                ], 422);
            }

            $skuItems = [];
            $conflicts = [];
            $skippedInvalid = 0;

            foreach ($items as $item) {
                if (! is_array($item)) {
                    $skippedInvalid++;
                    continue;
                }

                $sku = $this->normalizePelionSku($item['ITEMCODE'] ?? null);
                $isbn = $this->normalizeBarcode($item['ITEMBARCODE'] ?? null);
                $itemid = $this->normalizePelionItemId($item['ITEMID'] ?? null);

                if (! $sku || ! $isbn || ! $itemid) {
                    $skippedInvalid++;
                    continue;
                }

                $pair = [
                    'isbn' => $isbn,
                    'itemid' => $itemid,
                ];
                $pairKey = $isbn . ':' . $itemid;

                if (isset($conflicts[$sku])) {
                    $conflicts[$sku][$pairKey] = $pair;
                    continue;
                }

                if (
                    isset($skuItems[$sku]) &&
                    ($skuItems[$sku]['isbn'] !== $isbn || $skuItems[$sku]['itemid'] !== $itemid)
                ) {
                    $existing = $skuItems[$sku];
                    $conflicts[$sku] = [
                        $existing['isbn'] . ':' . $existing['itemid'] => $existing,
                        $pairKey => $pair,
                    ];
                    unset($skuItems[$sku]);
                    continue;
                }

                $skuItems[$sku] = $pair;
            }

            $now = now();
            $updated = 0;
            $unchanged = 0;
            $missingProducts = 0;
            $examples = [
                'updated' => [],
                'missing_products' => [],
                'conflicts' => [],
            ];

            foreach (array_chunk($skuItems, 500, true) as $chunk) {
                $products = DB::table('products')
                              ->select('id', 'sku', 'isbn', 'itemid')
                              ->whereIn('sku', array_keys($chunk))
                              ->get()
                              ->keyBy('sku');

                foreach ($chunk as $sku => $pelionItem) {
                    $isbn = $pelionItem['isbn'];
                    $itemid = $pelionItem['itemid'];
                    $product = $products->get($sku);

                    if (! $product) {
                        $missingProducts++;

                        if (count($examples['missing_products']) < 10) {
                            $examples['missing_products'][] = [
                                'sku' => $sku,
                                'isbn' => $isbn,
                                'itemid' => $itemid,
                            ];
                        }

                        continue;
                    }

                    if (
                        $this->normalizeBarcode($product->isbn ?? null) === $isbn &&
                        (int) ($product->itemid ?? 0) === $itemid
                    ) {
                        $unchanged++;
                        continue;
                    }

                    DB::table('products')
                      ->where('id', $product->id)
                      ->update([
                          'isbn' => $isbn,
                          'itemid' => $itemid,
                          'updated_at' => $now,
                      ]);

                    $updated++;

                    if (count($examples['updated']) < 10) {
                        $examples['updated'][] = [
                            'id' => $product->id,
                            'sku' => $sku,
                            'old_isbn' => $product->isbn,
                            'new_isbn' => $isbn,
                            'old_itemid' => $product->itemid,
                            'new_itemid' => $itemid,
                        ];
                    }
                }
            }

            foreach (array_slice($conflicts, 0, 10, true) as $sku => $pelionItems) {
                $examples['conflicts'][] = [
                    'sku' => $sku,
                    'items' => array_values($pelionItems),
                ];
            }

            return response()->json([
                'success' => true,
                'status' => $response->status(),
                'url' => $response->effectiveUri() ? (string) $response->effectiveUri() : $url,
                'body' => [
                    'message' => 'Pelion ISBN i ItemID sinkronizacija je završena.',
                    'items_received' => count($items),
                    'valid_sku_item_pairs' => count($skuItems),
                    'updated' => $updated,
                    'unchanged' => $unchanged,
                    'missing_products' => $missingProducts,
                    'skipped_invalid' => $skippedInvalid,
                    'skipped_conflicts' => count($conflicts),
                    'mapping' => [
                        'ITEMCODE' => 'products.sku',
                        'ITEMBARCODE' => 'products.isbn',
                        'ITEMID' => 'products.itemid',
                    ],
                    'examples' => $examples,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Pelion product ISBN sync failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Greška kod Pelion ISBN sinkronizacije: ' . $e->getMessage()
            ], 500);
        }
    }

    private function syncProductQuantitiesFromPelion(string $baseUrl, string $apiKey)
    {
        if (
            ! Schema::hasTable('products') ||
            ! Schema::hasColumn('products', 'itemid') ||
            ! Schema::hasColumn('products', 'quantity')
        ) {
            return response()->json([
                'error' => 'Nedostaje tablica products ili stupci products.itemid / products.quantity.'
            ], 422);
        }

        $url = $baseUrl . '/stockList';

        try {
            $response = Http::timeout(120)
                            ->withHeaders($this->pelionHeaders($apiKey))
                            ->get($url);

            if (! $response->successful()) {
                return response()->json([
                    'success' => false,
                    'status' => $response->status(),
                    'url' => $response->effectiveUri() ? (string) $response->effectiveUri() : $url,
                    'body' => $response->json() !== null ? $response->json() : $response->body(),
                ], 422);
            }

            $stockRows = $this->normalizeStockRows($response->json());

            if (! $stockRows) {
                return response()->json([
                    'error' => 'Pelion stockList nije vratio očekivani JSON niz zaliha.'
                ], 422);
            }

            $stockByItemId = [];
            $skippedInvalid = 0;

            foreach ($stockRows as $stockRow) {
                if (! is_array($stockRow)) {
                    $skippedInvalid++;
                    continue;
                }

                $itemId = $this->normalizePelionItemId($stockRow['ITEMID'] ?? $stockRow['ItemId'] ?? $stockRow['itemid'] ?? $stockRow['item_id'] ?? null);

                if (! $itemId) {
                    $skippedInvalid++;
                    continue;
                }

                $stockByItemId[$itemId] = ($stockByItemId[$itemId] ?? 0) + $this->stockQuantityFromRow($stockRow);
            }

            if (! $stockByItemId) {
                return response()->json([
                    'error' => 'Pelion stockList nije vratio nijedan red s valjanim ITEMID.'
                ], 422);
            }

            $now = now();
            $updated = 0;
            $unchanged = 0;
            $matchedProducts = 0;
            $quantityGreaterThanZero = 0;
            $missingItemidProducts = 0;
            $notInPelionProducts = 0;
            $matchedStockItemIds = [];
            $examples = [
                'updated' => [],
                'missing_itemid' => [],
                'not_in_pelion' => [],
            ];

            DB::table('products')
              ->select('id', 'name', 'sku', 'itemid', 'quantity')
              ->chunkById(500, function ($products) use (
                  $stockByItemId,
                  $now,
                  &$updated,
                  &$unchanged,
                  &$matchedProducts,
                  &$quantityGreaterThanZero,
                  &$missingItemidProducts,
                  &$notInPelionProducts,
                  &$matchedStockItemIds,
                  &$examples
              ) {
                foreach ($products as $product) {
                    $itemId = $this->normalizePelionItemId($product->itemid);
                    $quantity = 0;

                    if ($itemId && array_key_exists($itemId, $stockByItemId)) {
                        $quantity = $this->normalizeProductStockQuantity($stockByItemId[$itemId]);
                        $matchedProducts++;
                        $matchedStockItemIds[$itemId] = true;
                    } elseif (! $itemId) {
                        $missingItemidProducts++;

                        if (count($examples['missing_itemid']) < 10) {
                            $examples['missing_itemid'][] = [
                                'id' => $product->id,
                                'sku' => $product->sku,
                                'name' => $product->name,
                                'old_quantity' => (int) $product->quantity,
                                'new_quantity' => 0,
                            ];
                        }
                    } else {
                        $notInPelionProducts++;

                        if (count($examples['not_in_pelion']) < 10) {
                            $examples['not_in_pelion'][] = [
                                'id' => $product->id,
                                'sku' => $product->sku,
                                'name' => $product->name,
                                'itemid' => $itemId,
                                'old_quantity' => (int) $product->quantity,
                                'new_quantity' => 0,
                            ];
                        }
                    }

                    if ($quantity > 0) {
                        $quantityGreaterThanZero++;
                    }

                    if ((int) $product->quantity === $quantity) {
                        $unchanged++;
                        continue;
                    }

                    DB::table('products')
                      ->where('id', $product->id)
                      ->update([
                          'quantity' => $quantity,
                          'updated_at' => $now,
                      ]);

                    $updated++;

                    if (count($examples['updated']) < 10) {
                        $examples['updated'][] = [
                            'id' => $product->id,
                            'sku' => $product->sku,
                            'name' => $product->name,
                            'itemid' => $itemId,
                            'old_quantity' => (int) $product->quantity,
                            'new_quantity' => $quantity,
                        ];
                    }
                }
              });

            $pelionItemidsWithoutProduct = count(array_diff_key($stockByItemId, $matchedStockItemIds));

            return response()->json([
                'success' => true,
                'status' => $response->status(),
                'url' => $response->effectiveUri() ? (string) $response->effectiveUri() : $url,
                'body' => [
                    'message' => 'Pelion količine su updejtane prema products.itemid.',
                    'stock_rows_received' => count($stockRows),
                    'stock_itemids_received' => count($stockByItemId),
                    'matched_products' => $matchedProducts,
                    'updated' => $updated,
                    'unchanged' => $unchanged,
                    'quantity_gt_zero' => $quantityGreaterThanZero,
                    'missing_itemid_products' => $missingItemidProducts,
                    'not_in_pelion_products' => $notInPelionProducts,
                    'pelion_itemids_without_product' => $pelionItemidsWithoutProduct,
                    'skipped_invalid' => $skippedInvalid,
                    'mapping' => [
                        'ITEMID' => 'products.itemid',
                        'STOCKQUANTITY' => 'products.quantity',
                    ],
                    'examples' => $examples,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Pelion product quantity sync failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Greška kod Pelion sinkronizacije količina: ' . $e->getMessage()
            ], 500);
        }
    }

    private function syncProductShelvesFromPelion(string $baseUrl, string $apiKey)
    {
        if (
            ! Schema::hasTable('products') ||
            ! Schema::hasColumn('products', 'itemid') ||
            ! Schema::hasColumn('products', 'polica')
        ) {
            return response()->json([
                'error' => 'Nedostaje tablica products ili stupci products.itemid / products.polica.'
            ], 422);
        }

        $groupsUrl = $baseUrl . '/itemGroupList';
        $itemsUrl = $baseUrl . '/itemList';

        try {
            $groupsResponse = Http::timeout(60)
                                  ->withHeaders($this->pelionHeaders($apiKey))
                                  ->get($groupsUrl);

            if (! $groupsResponse->successful()) {
                return response()->json([
                    'success' => false,
                    'status' => $groupsResponse->status(),
                    'url' => $groupsResponse->effectiveUri() ? (string) $groupsResponse->effectiveUri() : $groupsUrl,
                    'body' => $groupsResponse->json() !== null ? $groupsResponse->json() : $groupsResponse->body(),
                ], 422);
            }

            $groups = $groupsResponse->json();

            if (! is_array($groups)) {
                return response()->json([
                    'error' => 'Pelion itemGroupList nije vratio očekivani JSON niz.'
                ], 422);
            }

            $groupShelves = [];
            $skippedInvalidGroups = 0;
            $skippedEmptyShelves = 0;

            foreach ($groups as $group) {
                if (! is_array($group)) {
                    $skippedInvalidGroups++;
                    continue;
                }

                $groupId = $this->normalizePelionGroupId($group['ITEMGROUPID'] ?? null);
                $shelf = $this->normalizePelionShelf($group['ITEMGROUPNAME'] ?? null);

                if (! $groupId) {
                    $skippedInvalidGroups++;
                    continue;
                }

                if (! $shelf) {
                    $skippedEmptyShelves++;
                    continue;
                }

                $groupShelves[$groupId] = $shelf;
            }

            if (! $groupShelves) {
                return response()->json([
                    'error' => 'Pelion itemGroupList nije vratio nijednu valjanu policu za upis.'
                ], 422);
            }

            $itemsResponse = Http::timeout(120)
                                 ->withHeaders($this->pelionHeaders($apiKey))
                                 ->get($itemsUrl);

            if (! $itemsResponse->successful()) {
                return response()->json([
                    'success' => false,
                    'status' => $itemsResponse->status(),
                    'url' => $itemsResponse->effectiveUri() ? (string) $itemsResponse->effectiveUri() : $itemsUrl,
                    'body' => $itemsResponse->json() !== null ? $itemsResponse->json() : $itemsResponse->body(),
                ], 422);
            }

            $items = $itemsResponse->json();

            if (! is_array($items)) {
                return response()->json([
                    'error' => 'Pelion itemList nije vratio očekivani JSON niz.'
                ], 422);
            }

            $shelvesByItemId = [];
            $itemExamples = [];
            $skippedInvalidItems = 0;
            $skippedMissingGroup = 0;
            $skippedDuplicateConflicts = 0;

            foreach ($items as $item) {
                if (! is_array($item)) {
                    $skippedInvalidItems++;
                    continue;
                }

                $itemId = $this->normalizePelionItemId($item['ITEMID'] ?? null);
                $groupId = $this->normalizePelionGroupId($item['ITEMGROUPID'] ?? null);

                if (! $itemId || ! $groupId) {
                    $skippedInvalidItems++;
                    continue;
                }

                if (! array_key_exists($groupId, $groupShelves)) {
                    $skippedMissingGroup++;
                    continue;
                }

                $shelf = $groupShelves[$groupId];

                if (isset($shelvesByItemId[$itemId]) && $shelvesByItemId[$itemId]['polica'] !== $shelf) {
                    unset($shelvesByItemId[$itemId]);
                    $skippedDuplicateConflicts++;
                    continue;
                }

                $shelvesByItemId[$itemId] = [
                    'polica' => $shelf,
                    'item_group_id' => $groupId,
                    'item_code' => trim((string) ($item['ITEMCODE'] ?? '')) ?: null,
                    'item_name' => trim((string) ($item['ITEMNAME'] ?? '')) ?: null,
                ];

                if (count($itemExamples) < 10) {
                    $itemExamples[] = [
                        'itemid' => $itemId,
                        'item_group_id' => $groupId,
                        'polica' => $shelf,
                        'item_code' => $shelvesByItemId[$itemId]['item_code'],
                        'item_name' => $shelvesByItemId[$itemId]['item_name'],
                    ];
                }
            }

            if (! $shelvesByItemId) {
                return response()->json([
                    'error' => 'Pelion itemList nije vratio nijedan artikl s valjanom policom.'
                ], 422);
            }

            $now = now();
            $updated = 0;
            $unchanged = 0;
            $matchedProducts = 0;
            $missingProducts = 0;
            $examples = [
                'updated' => [],
                'missing_products' => [],
                'candidate_items' => $itemExamples,
            ];

            foreach (array_chunk($shelvesByItemId, 500, true) as $chunk) {
                $products = DB::table('products')
                              ->select('id', 'name', 'sku', 'itemid', 'polica')
                              ->whereIn('itemid', array_keys($chunk))
                              ->get()
                              ->keyBy(function ($product) {
                                  return (int) $product->itemid;
                              });

                foreach ($chunk as $itemId => $pelionItem) {
                    $product = $products->get($itemId);
                    $shelf = $pelionItem['polica'];

                    if (! $product) {
                        $missingProducts++;

                        if (count($examples['missing_products']) < 10) {
                            $examples['missing_products'][] = [
                                'itemid' => $itemId,
                                'item_group_id' => $pelionItem['item_group_id'],
                                'polica' => $shelf,
                                'item_code' => $pelionItem['item_code'],
                                'item_name' => $pelionItem['item_name'],
                            ];
                        }

                        continue;
                    }

                    $matchedProducts++;

                    if ($this->normalizePelionShelf($product->polica ?? null) === $shelf) {
                        $unchanged++;
                        continue;
                    }

                    DB::table('products')
                      ->where('id', $product->id)
                      ->update([
                          'polica' => $shelf,
                          'updated_at' => $now,
                      ]);

                    $updated++;

                    if (count($examples['updated']) < 10) {
                        $examples['updated'][] = [
                            'id' => $product->id,
                            'sku' => $product->sku,
                            'name' => $product->name,
                            'itemid' => $itemId,
                            'old_polica' => $product->polica,
                            'new_polica' => $shelf,
                            'item_group_id' => $pelionItem['item_group_id'],
                        ];
                    }
                }
            }

            return response()->json([
                'success' => true,
                'status' => $itemsResponse->status(),
                'url' => $itemsResponse->effectiveUri() ? (string) $itemsResponse->effectiveUri() : $itemsUrl,
                'body' => [
                    'message' => 'Pelion police su upisane prema ITEMGROUPNAME.',
                    'groups_received' => count($groups),
                    'valid_group_shelves' => count($groupShelves),
                    'items_received' => count($items),
                    'candidate_items' => count($shelvesByItemId),
                    'matched_products' => $matchedProducts,
                    'updated' => $updated,
                    'unchanged' => $unchanged,
                    'missing_products' => $missingProducts,
                    'skipped_invalid_groups' => $skippedInvalidGroups,
                    'skipped_empty_shelves' => $skippedEmptyShelves,
                    'skipped_invalid_items' => $skippedInvalidItems,
                    'skipped_missing_group' => $skippedMissingGroup,
                    'skipped_duplicate_conflicts' => $skippedDuplicateConflicts,
                    'mapping' => [
                        'ITEMID' => 'products.itemid',
                        'ITEMGROUPID' => 'itemGroupList.ITEMGROUPID',
                        'ITEMGROUPNAME' => 'products.polica',
                    ],
                    'examples' => $examples,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Pelion product shelf sync failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Greška kod Pelion sinkronizacije polica: ' . $e->getMessage()
            ], 500);
        }
    }

    private function pelionHeaders(string $apiKey): array
    {
        return [
            'Content-Type' => 'application/json',
            'X-API-KEY' => $apiKey,
        ];
    }

    private function upsertPelionItems(array $rows): int
    {
        DB::table('pelion_items')->upsert(
            $rows,
            ['item_id'],
            ['item_barcode', 'item_code', 'item_name', 'item_group_id', 'item_active', 'item_type', 'item_price', 'synced_at', 'updated_at']
        );

        return count($rows);
    }

    private function normalizeBarcode($value): ?string
    {
        $value = strtoupper(trim((string) $value));

        if ($value === '') {
            return null;
        }

        $value = str_replace(['ISBN-13', 'ISBN13', 'ISBN-10', 'ISBN10', 'ISBN', ':'], '', $value);
        $value = preg_replace('/[^0-9X]/', '', $value);

        return $value ?: null;
    }

    private function normalizePelionSku($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
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

    private function normalizePelionGroupId($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizePelionShelf($value): ?string
    {
        $value = preg_replace('/\s+/', ' ', trim((string) $value));

        return $value === '' ? null : $value;
    }

    private function findLocalProductByIsbn(string $isbn)
    {
        if (! Schema::hasTable('products')) {
            return null;
        }

        return DB::table('products')
                 ->select('id', 'name', 'sku', 'isbn', 'itemid', 'quantity', 'status')
                 ->where('isbn', $isbn)
                 ->first();
    }

    private function normalizeStockRows($stock): array
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

    private function stockQuantityFromRow(array $row): float
    {
        $quantity = str_replace(',', '.', trim((string) ($row['STOCKQUANTITY'] ?? $row['StockQuantity'] ?? $row['stockquantity'] ?? $row['quantity'] ?? $row['Quantity'] ?? 0)));

        return is_numeric($quantity) ? (float) $quantity : 0;
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
