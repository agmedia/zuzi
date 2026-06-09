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
            'action' => 'required|string|in:item-list,item-list-attrs,item-by-id,group-items,group-active,item-type,item-groups,stock-list,stock-by-item,sync-item-index,stock-by-isbn',
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

    private function findLocalProductByIsbn(string $isbn)
    {
        if (! Schema::hasTable('products')) {
            return null;
        }

        return DB::table('products')
                 ->select('id', 'name', 'sku', 'isbn', 'quantity', 'status')
                 ->where('isbn', $isbn)
                 ->first();
    }

    private function normalizeStockRows($stock): array
    {
        if (! is_array($stock)) {
            return [];
        }

        if (isset($stock['STOCKQUANTITY'])) {
            return [$stock];
        }

        return array_values(array_filter($stock, 'is_array'));
    }

    private function sumStockQuantity(array $stockRows): float
    {
        return array_reduce($stockRows, function (float $total, array $row) {
            $quantity = str_replace(',', '.', trim((string) ($row['STOCKQUANTITY'] ?? 0)));

            return $total + (is_numeric($quantity) ? (float) $quantity : 0);
        }, 0.0);
    }

}
