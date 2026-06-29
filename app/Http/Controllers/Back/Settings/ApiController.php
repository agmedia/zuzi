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
use Illuminate\Support\Str;

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
            'action' => 'required|string|in:item-list,item-list-attrs,item-by-id,group-items,group-active,item-type,item-groups,stock-list,stock-by-item,sync-item-index,sync-product-isbns,sync-product-quantities,sync-product-shelves,stock-by-isbn,scan-name-mismatches,apply-name-mismatches',
            'item_id' => 'nullable|integer|min:1',
            'item_group_id' => 'nullable|integer|min:1',
            'item_type' => 'nullable|string|in:T,K,U',
            'isbn' => 'nullable|string|max:32',
            'base_url' => 'nullable|string|url',
            'api_key' => 'nullable|string',
            'min_score' => 'nullable|integer|min:50|max:100',
            'limit' => 'nullable|integer|min:1|max:500',
            'matches' => 'nullable|array|max:500',
            'matches.*.product_id' => 'required_with:matches|integer|min:1',
            'matches.*.item_id' => 'required_with:matches|integer|min:1',
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

        if ($data['action'] === 'scan-name-mismatches') {
            return $this->scanPelionNameMismatches(
                $baseUrl,
                $apiKey,
                (int) ($data['min_score'] ?? 88),
                (int) ($data['limit'] ?? 100)
            );
        }

        if ($data['action'] === 'apply-name-mismatches') {
            return $this->applyPelionNameMismatches($data, $baseUrl, $apiKey);
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

            $json = $response->json();
            $body = $json !== null ? $json : $response->body();
            $summary = null;

            if ($path === '/stockList' && $json !== null) {
                $summary = $this->stockSummaryFromRows($this->normalizeStockRows($json));
            }

            return response()->json([
                'success' => true,
                'status' => $response->status(),
                'url' => $response->effectiveUri() ? (string) $response->effectiveUri() : $url,
                'summary' => $summary,
                'body' => $body,
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
            ! Schema::hasColumn('products', 'quantity') ||
            ! Schema::hasColumn('products', 'delivery_24h')
        ) {
            return response()->json([
                'error' => 'Nedostaje tablica products ili stupci products.itemid / products.quantity / products.delivery_24h.'
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
            $stockSummary = $this->stockSummaryFromRows($stockRows);

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
            $skippedDelivery24hProducts = 0;
            $matchedStockItemIds = [];
            $examples = [
                'updated' => [],
                'missing_itemid' => [],
                'not_in_pelion' => [],
                'skipped_delivery_24h' => [],
            ];

            DB::table('products')
              ->select('id', 'name', 'sku', 'itemid', 'quantity', 'delivery_24h')
              ->chunkById(500, function ($products) use (
                  $stockByItemId,
                  $now,
                  &$updated,
                  &$unchanged,
                  &$matchedProducts,
                  &$quantityGreaterThanZero,
                  &$missingItemidProducts,
                  &$notInPelionProducts,
                  &$skippedDelivery24hProducts,
                  &$matchedStockItemIds,
                  &$examples
              ) {
                foreach ($products as $product) {
                    $itemId = $this->normalizePelionItemId($product->itemid);

                    if ((int) $product->delivery_24h === 1) {
                        $skippedDelivery24hProducts++;

                        if ($itemId && array_key_exists($itemId, $stockByItemId)) {
                            $matchedStockItemIds[$itemId] = true;
                        }

                        if (count($examples['skipped_delivery_24h']) < 10) {
                            $examples['skipped_delivery_24h'][] = [
                                'id' => $product->id,
                                'sku' => $product->sku,
                                'name' => $product->name,
                                'itemid' => $itemId,
                                'quantity' => (int) $product->quantity,
                            ];
                        }

                        continue;
                    }

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
                    'pelion_stock_items_quantity_gt_0' => $stockSummary['stock_items_quantity_gt_0'],
                    'pelion_stock_rows_quantity_gt_0' => $stockSummary['stock_rows_quantity_gt_0'],
                    'matched_products' => $matchedProducts,
                    'updated' => $updated,
                    'unchanged' => $unchanged,
                    'quantity_gt_zero' => $quantityGreaterThanZero,
                    'missing_itemid_products' => $missingItemidProducts,
                    'not_in_pelion_products' => $notInPelionProducts,
                    'skipped_delivery_24h_products' => $skippedDelivery24hProducts,
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
            $skippedGenericGroups = 0;
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

                if ($this->isGenericPelionShelf($shelf)) {
                    $skippedGenericGroups++;
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
            $skippedGenericItemGroups = 0;
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
                    $rawShelf = $this->normalizePelionShelf($item['ITEMGRUPNAME'] ?? $item['ITEMGROUPNAME'] ?? null);

                    if ($rawShelf && $this->isGenericPelionShelf($rawShelf)) {
                        $skippedGenericItemGroups++;
                    } else {
                        $skippedMissingGroup++;
                    }

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
                    'skipped_generic_groups' => $skippedGenericGroups,
                    'skipped_empty_shelves' => $skippedEmptyShelves,
                    'skipped_invalid_items' => $skippedInvalidItems,
                    'skipped_missing_group' => $skippedMissingGroup,
                    'skipped_generic_item_groups' => $skippedGenericItemGroups,
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

    private function scanPelionNameMismatches(string $baseUrl, string $apiKey, int $minScore = 88, int $limit = 100)
    {
        if (! $this->hasPelionCorrectionColumns()) {
            return response()->json([
                'error' => 'Nedostaje tablica products ili stupci name / sku / isbn / ean / itemid / quantity / delivery_24h.'
            ], 422);
        }

        try {
            $itemList = $this->fetchPelionJsonArray($baseUrl . '/itemList', $apiKey, 'Pelion itemList');
            $itemRows = $this->normalizePelionItemRows($itemList['body']);
            $pelionItems = $this->normalizePelionCatalogItems($itemRows);

            if (! $pelionItems) {
                return response()->json([
                    'error' => 'Pelion itemList nije vratio nijedan artikl s valjanim ITEMID i ITEMNAME.'
                ], 422);
            }

            $index = $this->buildPelionNameIndex($pelionItems);
            $pelionByItemId = [];

            foreach ($pelionItems as $item) {
                $pelionByItemId[$item['item_id']] = $item;
            }

            $candidates = [];
            $scannedProducts = 0;
            $skippedEmptyNames = 0;
            $skippedBelowScore = 0;
            $skippedAlreadyMatching = 0;

            DB::table('products')
              ->select('id', 'name', 'sku', 'isbn', 'ean', 'itemid', 'quantity', 'delivery_24h', 'status')
              ->orderBy('id')
              ->chunkById(500, function ($products) use (
                  $pelionItems,
                  $pelionByItemId,
                  $index,
                  $minScore,
                  $limit,
                  &$candidates,
                  &$scannedProducts,
                  &$skippedEmptyNames,
                  &$skippedBelowScore,
                  &$skippedAlreadyMatching
              ) {
                foreach ($products as $product) {
                    $scannedProducts++;
                    $normalizedName = $this->normalizeNameForMatching($product->name);

                    if ($normalizedName === '') {
                        $skippedEmptyNames++;
                        continue;
                    }

                    $match = $this->bestPelionNameMatch($normalizedName, $pelionItems, $index, $minScore);

                    if (! $match) {
                        $skippedBelowScore++;
                        continue;
                    }

                    $candidate = $match['item'];
                    $currentItemId = $this->normalizePelionItemId($product->itemid);
                    $currentBarcode = $this->normalizeBarcode($product->isbn ?? null);
                    $itemIdChanged = $currentItemId !== $candidate['item_id'];
                    $barcodeChanged = $candidate['item_barcode'] && $candidate['item_barcode'] !== $currentBarcode;

                    if (! $itemIdChanged && ! $barcodeChanged) {
                        $skippedAlreadyMatching++;
                        continue;
                    }

                    $reasons = [];

                    if ($itemIdChanged) {
                        $reasons[] = 'ITEMID: ' . ($currentItemId ?: '-') . ' -> ' . $candidate['item_id'];
                    }

                    if ($barcodeChanged) {
                        $reasons[] = 'ISBN: ' . ($currentBarcode ?: '-') . ' -> ' . $candidate['item_barcode'];
                    }

                    $candidates[] = [
                        'product' => [
                            'id' => (int) $product->id,
                            'name' => $product->name,
                            'sku' => $product->sku,
                            'isbn' => $product->isbn,
                            'ean' => $product->ean,
                            'itemid' => $currentItemId,
                            'quantity' => (int) $product->quantity,
                            'delivery_24h' => (int) $product->delivery_24h,
                            'status' => (int) $product->status,
                        ],
                        'current_pelion_item' => $currentItemId && isset($pelionByItemId[$currentItemId])
                            ? $this->formatPelionCatalogItem($pelionByItemId[$currentItemId])
                            : null,
                        'candidate' => $this->formatPelionCatalogItem($candidate),
                        'score' => $match['score'],
                        'reasons' => $reasons,
                        'conflict' => null,
                    ];

                    if (count($candidates) >= $limit) {
                        return false;
                    }
                }

                return count($candidates) < $limit;
              });

            $this->annotatePelionCandidateConflicts($candidates);

            return response()->json([
                'success' => true,
                'status' => $itemList['status'],
                'url' => $itemList['url'],
                'body' => [
                    'message' => 'Sumnjivi Pelion parovi su pronađeni po nazivu artikla.',
                    'min_score' => $minScore,
                    'limit' => $limit,
                    'items_received' => count($itemRows),
                    'valid_pelion_items' => count($pelionItems),
                    'products_scanned' => $scannedProducts,
                    'candidates_found' => count($candidates),
                    'skipped_empty_names' => $skippedEmptyNames,
                    'skipped_below_score' => $skippedBelowScore,
                    'skipped_already_matching' => $skippedAlreadyMatching,
                    'candidates' => $candidates,
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Pelion name mismatch scan failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Greška kod Pelion pretrage sumnjivih artikala: ' . $e->getMessage()
            ], 500);
        }
    }

    private function applyPelionNameMismatches(array $data, string $baseUrl, string $apiKey)
    {
        if (! $this->hasPelionCorrectionColumns()) {
            return response()->json([
                'error' => 'Nedostaje tablica products ili stupci name / sku / isbn / ean / itemid / quantity / delivery_24h.'
            ], 422);
        }

        $matches = $this->normalizeApprovedPelionMatches($data['matches'] ?? []);

        if (! $matches['items']) {
            return response()->json([
                'error' => 'Odaberite barem jedan Pelion prijedlog za odobravanje.'
            ], 422);
        }

        try {
            $itemList = $this->fetchPelionJsonArray($baseUrl . '/itemList', $apiKey, 'Pelion itemList');
            $stockList = $this->fetchPelionJsonArray($baseUrl . '/stockList', $apiKey, 'Pelion stockList');
            $pelionItems = collect($this->normalizePelionCatalogItems($this->normalizePelionItemRows($itemList['body'])))->keyBy('item_id');
            $stockRows = $this->normalizeStockRows($stockList['body']);
            $stockByItemId = $this->stockQuantitiesByItemId($stockRows);
            $productIds = array_keys($matches['items']);
            $products = DB::table('products')
                          ->select('id', 'name', 'sku', 'isbn', 'ean', 'itemid', 'quantity', 'delivery_24h')
                          ->whereIn('id', $productIds)
                          ->get()
                          ->keyBy('id');
            $targetCounts = array_count_values(array_values($matches['items']));
            $initialRows = [];
            $skipped = $matches['duplicates'];

            foreach ($matches['items'] as $productId => $itemId) {
                $product = $products->get($productId);
                $pelionItem = $pelionItems->get($itemId);

                if (! $product) {
                    $skipped[] = [
                        'product_id' => $productId,
                        'item_id' => $itemId,
                        'reason' => 'missing_product',
                    ];
                    continue;
                }

                if (! $pelionItem) {
                    $skipped[] = [
                        'product_id' => $productId,
                        'product_name' => $product->name,
                        'item_id' => $itemId,
                        'reason' => 'missing_pelion_item',
                    ];
                    continue;
                }

                if (($targetCounts[$itemId] ?? 0) > 1) {
                    $skipped[] = [
                        'product_id' => $productId,
                        'product_name' => $product->name,
                        'item_id' => $itemId,
                        'reason' => 'duplicate_target_itemid',
                    ];
                    continue;
                }

                $initialRows[] = [
                    'product' => $product,
                    'item' => $pelionItem,
                ];
            }

            $rows = $this->rejectRowsWithTakenItemIds($initialRows, $skipped);

            if (! $rows) {
                return response()->json([
                    'success' => false,
                    'status' => $stockList['status'],
                    'url' => $stockList['url'],
                    'body' => [
                        'message' => 'Nijedan odabrani Pelion prijedlog nije moguće primijeniti.',
                        'applied' => 0,
                        'skipped' => count($skipped),
                        'examples' => [
                            'skipped' => array_slice($skipped, 0, 20),
                        ],
                    ],
                ], 422);
            }

            $now = now();
            $applied = [];
            $quantitySkippedDelivery24h = 0;
            $quantityUpdated = 0;

            DB::transaction(function () use (
                $rows,
                $stockByItemId,
                $now,
                &$applied,
                &$quantitySkippedDelivery24h,
                &$quantityUpdated
            ) {
                $rowProductIds = array_map(function (array $row) {
                    return (int) $row['product']->id;
                }, $rows);

                DB::table('products')
                  ->whereIn('id', $rowProductIds)
                  ->update([
                      'itemid' => null,
                      'updated_at' => $now,
                  ]);

                foreach ($rows as $row) {
                    $product = $row['product'];
                    $item = $row['item'];
                    $itemId = $item['item_id'];
                    $barcode = $item['item_barcode'];
                    $newQuantity = (int) $product->quantity;
                    $update = [
                        'itemid' => $itemId,
                        'updated_at' => $now,
                    ];

                    if ($barcode) {
                        $update['isbn'] = $barcode;
                    }

                    if ((int) $product->delivery_24h === 1) {
                        $quantitySkippedDelivery24h++;
                    } else {
                        $newQuantity = $this->normalizeProductStockQuantity($stockByItemId[$itemId] ?? 0);
                        $update['quantity'] = $newQuantity;
                        $quantityUpdated++;
                    }

                    DB::table('products')
                      ->where('id', $product->id)
                      ->update($update);

                    if (count($applied) < 20) {
                        $applied[] = [
                            'id' => (int) $product->id,
                            'name' => $product->name,
                            'old_isbn' => $product->isbn,
                            'new_isbn' => $update['isbn'] ?? $product->isbn,
                            'old_itemid' => $this->normalizePelionItemId($product->itemid),
                            'new_itemid' => $itemId,
                            'old_quantity' => (int) $product->quantity,
                            'new_quantity' => $newQuantity,
                            'pelion_item_name' => $item['item_name'],
                        ];
                    }
                }
            });

            return response()->json([
                'success' => true,
                'status' => $stockList['status'],
                'url' => $stockList['url'],
                'body' => [
                    'message' => 'Odobrene Pelion korekcije su upisane i količine su osvježene prema stockList.',
                    'requested' => count($data['matches'] ?? []),
                    'applied' => count($rows),
                    'skipped' => count($skipped),
                    'quantity_updated' => $quantityUpdated,
                    'quantity_skipped_delivery_24h' => $quantitySkippedDelivery24h,
                    'stock_rows_received' => count($stockRows),
                    'stock_itemids_received' => count($stockByItemId),
                    'examples' => [
                        'applied' => $applied,
                        'skipped' => array_slice($skipped, 0, 20),
                    ],
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Pelion name mismatch apply failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Greška kod primjene Pelion korekcija: ' . $e->getMessage()
            ], 500);
        }
    }

    private function hasPelionCorrectionColumns(): bool
    {
        if (! Schema::hasTable('products')) {
            return false;
        }

        foreach (['name', 'sku', 'ean', 'isbn', 'itemid', 'quantity', 'delivery_24h'] as $column) {
            if (! Schema::hasColumn('products', $column)) {
                return false;
            }
        }

        return true;
    }

    private function fetchPelionJsonArray(string $url, string $apiKey, string $label): array
    {
        $response = Http::timeout(120)
                        ->withHeaders($this->pelionHeaders($apiKey))
                        ->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException($label . ' nije uspio. Status: ' . $response->status());
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new \RuntimeException($label . ' nije vratio očekivani JSON niz.');
        }

        return [
            'status' => $response->status(),
            'url' => $response->effectiveUri() ? (string) $response->effectiveUri() : $url,
            'body' => $body,
        ];
    }

    private function normalizePelionItemRows($items): array
    {
        if (! is_array($items)) {
            return [];
        }

        if (isset($items['ITEMID']) || isset($items['ItemId']) || isset($items['itemid']) || isset($items['item_id'])) {
            return [$items];
        }

        foreach (['body', 'Body', 'data', 'Data', 'items', 'Items'] as $key) {
            if (isset($items[$key]) && is_array($items[$key])) {
                return $this->normalizePelionItemRows($items[$key]);
            }
        }

        return array_values(array_filter($items, 'is_array'));
    }

    private function normalizePelionCatalogItems(array $items): array
    {
        $rows = $this->normalizePelionItemRows($items);
        $normalized = [];

        foreach ($rows as $item) {
            if (! is_array($item)) {
                continue;
            }

            $itemId = $this->normalizePelionItemId($item['ITEMID'] ?? $item['ItemId'] ?? $item['itemid'] ?? $item['item_id'] ?? null);
            $itemName = $this->normalizePelionText($item['ITEMNAME'] ?? $item['ItemName'] ?? $item['itemname'] ?? $item['item_name'] ?? null);

            if (! $itemId || ! $itemName) {
                continue;
            }

            $normalizedName = $this->normalizeNameForMatching($itemName);

            if ($normalizedName === '') {
                continue;
            }

            $price = $item['ITEMPRICE'] ?? $item['ItemPrice'] ?? $item['itemprice'] ?? $item['item_price'] ?? null;

            $normalized[] = [
                'item_id' => $itemId,
                'item_barcode' => $this->normalizeBarcode($item['ITEMBARCODE'] ?? $item['ItemBarcode'] ?? $item['itembarcode'] ?? $item['item_barcode'] ?? null),
                'item_code' => $this->normalizePelionSku($item['ITEMCODE'] ?? $item['ItemCode'] ?? $item['itemcode'] ?? $item['item_code'] ?? null),
                'item_name' => $itemName,
                'normalized_name' => $normalizedName,
                'item_group_id' => $this->normalizePelionGroupId($item['ITEMGROUPID'] ?? $item['ItemGroupId'] ?? $item['itemgroupid'] ?? $item['item_group_id'] ?? null),
                'item_active' => $this->normalizePelionText($item['ITEMACTIVE'] ?? $item['ItemActive'] ?? $item['itemactive'] ?? $item['item_active'] ?? null),
                'item_type' => $this->normalizePelionText($item['ITEMTYPE'] ?? $item['ItemType'] ?? $item['itemtype'] ?? $item['item_type'] ?? null),
                'item_price' => is_numeric($price) ? (float) $price : null,
            ];
        }

        return $normalized;
    }

    private function buildPelionNameIndex(array $pelionItems): array
    {
        $index = [];

        foreach ($pelionItems as $offset => $item) {
            foreach ($this->nameSearchKeys($item['normalized_name']) as $key) {
                $index[$key][] = $offset;
            }
        }

        return $index;
    }

    private function bestPelionNameMatch(string $normalizedProductName, array $pelionItems, array $index, int $minScore): ?array
    {
        $candidateOffsets = [];

        foreach ($this->nameSearchKeys($normalizedProductName) as $key) {
            foreach ($index[$key] ?? [] as $offset) {
                $candidateOffsets[$offset] = true;
            }
        }

        if (! $candidateOffsets) {
            return null;
        }

        $best = null;
        $bestScore = 0;
        $secondScore = 0;

        foreach (array_keys($candidateOffsets) as $offset) {
            if (! isset($pelionItems[$offset])) {
                continue;
            }

            $item = $pelionItems[$offset];
            $score = $this->pelionNameMatchScore($normalizedProductName, $item['normalized_name']);

            if ($score > $bestScore) {
                $secondScore = $bestScore;
                $bestScore = $score;
                $best = $item;
            } elseif ($score > $secondScore) {
                $secondScore = $score;
            }
        }

        if (! $best || $bestScore < $minScore) {
            return null;
        }

        return [
            'item' => $best,
            'score' => $bestScore,
            'second_score' => $secondScore,
            'score_gap' => $bestScore - $secondScore,
        ];
    }

    private function pelionNameMatchScore(string $productName, string $pelionName): int
    {
        if ($productName === '' || $pelionName === '') {
            return 0;
        }

        if ($productName === $pelionName) {
            return 100;
        }

        $scores = [];
        $productLength = strlen($productName);
        $pelionLength = strlen($pelionName);
        $shorterLength = min($productLength, $pelionLength);
        $longerLength = max($productLength, $pelionLength);

        if ($shorterLength >= 5) {
            $isPrefix = strpos($productName, $pelionName) === 0 || strpos($pelionName, $productName) === 0;
            $isContained = strpos($productName, $pelionName) !== false || strpos($pelionName, $productName) !== false;

            if ($isPrefix) {
                $scores[] = $shorterLength >= 10 ? 98 : 94;
                $scores[] = (int) round(86 + min(12, ($shorterLength / max(1, $longerLength)) * 14));
            }

            if ($isContained) {
                $scores[] = $shorterLength >= 8 ? 95 : 88;
            }
        }

        $productTokens = $this->nameTokens($productName);
        $pelionTokens = $this->nameTokens($pelionName);
        $overlap = $this->tokenOverlap($productTokens, $pelionTokens);

        if ($overlap['count'] > 0 && ($overlap['count'] >= 2 || $overlap['longest'] >= 6)) {
            $coverage = max($overlap['product_coverage'], $overlap['pelion_coverage']);
            $balancedCoverage = ($overlap['product_coverage'] + $overlap['pelion_coverage']) / 2;
            $scores[] = (int) round(72 + ($coverage * 22) + ($balancedCoverage * 6));

            if ($overlap['product_coverage'] >= 1 && count($productTokens) <= 3 && $productLength >= 5) {
                $scores[] = 96;
            }
        }

        similar_text($productName, $pelionName, $similarPercent);
        $scores[] = (int) round($similarPercent);

        if ($longerLength <= 255) {
            $distance = levenshtein($productName, $pelionName);
            $scores[] = (int) round((1 - min($distance, $longerLength) / max(1, $longerLength)) * 100);
        }

        return max($scores ?: [0]);
    }

    private function tokenOverlap(array $productTokens, array $pelionTokens): array
    {
        $matchedPelion = [];
        $longest = 0;
        $count = 0;

        foreach ($productTokens as $productToken) {
            foreach ($pelionTokens as $pelionIndex => $pelionToken) {
                if (isset($matchedPelion[$pelionIndex])) {
                    continue;
                }

                if (! $this->nameTokensCompatible($productToken, $pelionToken)) {
                    continue;
                }

                $matchedPelion[$pelionIndex] = true;
                $longest = max($longest, min(strlen($productToken), strlen($pelionToken)));
                $count++;
                break;
            }
        }

        return [
            'count' => $count,
            'longest' => $longest,
            'product_coverage' => $productTokens ? $count / count($productTokens) : 0,
            'pelion_coverage' => $pelionTokens ? $count / count($pelionTokens) : 0,
        ];
    }

    private function nameTokensCompatible(string $left, string $right): bool
    {
        if ($left === $right) {
            return true;
        }

        $shorterLength = min(strlen($left), strlen($right));

        return $shorterLength >= 4 && (strpos($left, $right) === 0 || strpos($right, $left) === 0);
    }

    private function nameSearchKeys(string $normalizedName): array
    {
        $keys = [];

        foreach ($this->nameTokens($normalizedName) as $token) {
            foreach ($this->nameIndexKeysFromToken($token) as $key) {
                $keys[$key] = true;
            }
        }

        return array_keys($keys);
    }

    private function nameIndexKeysFromToken(string $token): array
    {
        $keys = [$token => true];
        $length = strlen($token);
        $maxPrefixLength = min(8, $length - 1);

        for ($prefixLength = 4; $prefixLength <= $maxPrefixLength; $prefixLength++) {
            $keys[substr($token, 0, $prefixLength)] = true;
        }

        return array_keys($keys);
    }

    private function nameTokens(string $normalizedName): array
    {
        $tokens = preg_split('/\s+/', $normalizedName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stopWords = [
            'and' => true,
            'the' => true,
            'for' => true,
            'with' => true,
        ];
        $result = [];

        foreach ($tokens as $token) {
            if (strlen($token) < 3 || isset($stopWords[$token])) {
                continue;
            }

            $result[$token] = true;
        }

        return array_keys($result);
    }

    private function normalizeNameForMatching($value): string
    {
        $value = Str::ascii((string) $value);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', trim((string) $value));

        return $value ?: '';
    }

    private function normalizePelionText($value): ?string
    {
        $value = preg_replace('/\s+/', ' ', trim((string) $value));

        return $value === '' ? null : $value;
    }

    private function formatPelionCatalogItem(array $item): array
    {
        return [
            'ITEMID' => $item['item_id'],
            'ITEMCODE' => $item['item_code'],
            'ITEMBARCODE' => $item['item_barcode'],
            'ITEMNAME' => $item['item_name'],
            'ITEMGROUPID' => $item['item_group_id'],
            'ITEMACTIVE' => $item['item_active'],
            'ITEMTYPE' => $item['item_type'],
            'ITEMPRICE' => $item['item_price'],
        ];
    }

    private function annotatePelionCandidateConflicts(array &$candidates): void
    {
        if (! $candidates) {
            return;
        }

        $candidateTargetItemIds = array_map(function (array $candidate) {
            return (int) $candidate['candidate']['ITEMID'];
        }, $candidates);
        $targetItemIds = array_values(array_unique($candidateTargetItemIds));
        $candidateTargetCounts = array_count_values($candidateTargetItemIds);
        $owners = DB::table('products')
                    ->select('id', 'name', 'sku', 'itemid')
                    ->whereIn('itemid', $targetItemIds)
                    ->get()
                    ->keyBy(function ($product) {
                        return (int) $product->itemid;
                    });

        foreach ($candidates as &$candidate) {
            $targetItemId = (int) $candidate['candidate']['ITEMID'];
            $reasons = [];
            $existingProduct = null;
            $owner = $owners->get($targetItemId);

            if ($owner && (int) $owner->id !== (int) $candidate['product']['id']) {
                $reasons[] = 'ITEMID je trenutno upisan na drugom artiklu.';
                $existingProduct = [
                    'id' => (int) $owner->id,
                    'name' => $owner->name,
                    'sku' => $owner->sku,
                    'itemid' => (int) $owner->itemid,
                ];
            }

            if (($candidateTargetCounts[$targetItemId] ?? 0) > 1) {
                $reasons[] = 'Isti Pelion ITEMID je predložen na više lokalnih artikala.';
            }

            if ($reasons) {
                $candidate['conflict'] = [
                    'reasons' => $reasons,
                    'existing_product' => $existingProduct,
                ];
            }
        }
        unset($candidate);
    }

    private function normalizeApprovedPelionMatches(array $matches): array
    {
        $items = [];
        $duplicates = [];

        foreach ($matches as $match) {
            if (! is_array($match)) {
                $duplicates[] = [
                    'reason' => 'invalid_payload',
                ];
                continue;
            }

            $productId = $this->positiveInteger($match['product_id'] ?? null);
            $itemId = $this->normalizePelionItemId($match['item_id'] ?? null);

            if (! $productId || ! $itemId) {
                $duplicates[] = [
                    'product_id' => $match['product_id'] ?? null,
                    'item_id' => $match['item_id'] ?? null,
                    'reason' => 'invalid_ids',
                ];
                continue;
            }

            if (isset($items[$productId])) {
                $duplicates[] = [
                    'product_id' => $productId,
                    'item_id' => $itemId,
                    'reason' => 'duplicate_product_submission',
                ];
                continue;
            }

            $items[$productId] = $itemId;
        }

        return [
            'items' => $items,
            'duplicates' => $duplicates,
        ];
    }

    private function positiveInteger($value): ?int
    {
        $value = trim((string) $value);

        if ($value === '' || ! ctype_digit($value)) {
            return null;
        }

        $integer = (int) $value;

        return $integer > 0 ? $integer : null;
    }

    private function rejectRowsWithTakenItemIds(array $rows, array &$skipped): array
    {
        if (! $rows) {
            return [];
        }

        $rowProductIds = [];
        $targetItemIds = [];

        foreach ($rows as $row) {
            $rowProductIds[(int) $row['product']->id] = true;
            $targetItemIds[] = (int) $row['item']['item_id'];
        }

        $owners = DB::table('products')
                    ->select('id', 'name', 'sku', 'itemid')
                    ->whereIn('itemid', array_values(array_unique($targetItemIds)))
                    ->get()
                    ->keyBy(function ($product) {
                        return (int) $product->itemid;
                    });

        return array_values(array_filter($rows, function (array $row) use ($owners, $rowProductIds, &$skipped) {
            $product = $row['product'];
            $itemId = (int) $row['item']['item_id'];
            $owner = $owners->get($itemId);

            if (! $owner || (int) $owner->id === (int) $product->id || isset($rowProductIds[(int) $owner->id])) {
                return true;
            }

            $skipped[] = [
                'product_id' => (int) $product->id,
                'product_name' => $product->name,
                'item_id' => $itemId,
                'reason' => 'itemid_taken_by_other_product',
                'existing_product' => [
                    'id' => (int) $owner->id,
                    'name' => $owner->name,
                    'sku' => $owner->sku,
                    'itemid' => (int) $owner->itemid,
                ],
            ];

            return false;
        }));
    }

    private function stockQuantitiesByItemId(array $stockRows): array
    {
        $stockByItemId = [];

        foreach ($stockRows as $stockRow) {
            if (! is_array($stockRow)) {
                continue;
            }

            $itemId = $this->stockRowItemId($stockRow);

            if (! $itemId) {
                continue;
            }

            $stockByItemId[$itemId] = ($stockByItemId[$itemId] ?? 0) + $this->stockQuantityFromRow($stockRow);
        }

        return $stockByItemId;
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

    private function isGenericPelionShelf(string $shelf): bool
    {
        return in_array($shelf, [
            'Usluga',
            'Trgovačka roba',
            'Komisija',
            'Rezervirano',
            'Pekarski proizvodi',
        ], true);
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

    private function stockRowItemId(array $row): ?int
    {
        return $this->normalizePelionItemId($row['ITEMID'] ?? $row['ItemId'] ?? $row['itemid'] ?? $row['item_id'] ?? null);
    }

    private function stockSummaryFromRows(array $stockRows): array
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
