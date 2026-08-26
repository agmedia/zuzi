<?php

namespace App\Http\Controllers\Back\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Back\Catalog\Category;
use App\Models\Back\Catalog\DelfiImportProduct;
use App\Models\Back\Catalog\Publisher;
use App\Services\Delfi\DelfiFeedService;
use App\Services\Delfi\DelfiFeedSynchronizer;
use App\Services\Delfi\DelfiImportService;
use App\Services\Delfi\DelfiImportSettings;
use App\Services\Delfi\DelfiPriceCalculator;
use App\Services\Delfi\DelfiProductListApiClient;
use App\Services\Delfi\DelfiRetryableException;
use App\Services\Delfi\DelfiTaxonomyService;
use App\Services\Delfi\DelfiTerminalException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DelfiImportController extends Controller
{
    public function index(
        Request $request,
        DelfiImportSettings $settingsService,
        DelfiFeedService $feedService,
        DelfiPriceCalculator $priceCalculator,
        DelfiTaxonomyService $taxonomyService
    ) {
        $settings = $settingsService->all();
        $sourceGenreCountsByCategory = $this->sourceGenreCountsByCategory();
        $sourceTaxonomy = $taxonomyService->bookGenresByCategory();
        $sourceGenres = [];
        foreach (DelfiProductListApiClient::CATEGORIES as $sourceCategory) {
            $discoveredCounts = $sourceGenreCountsByCategory[$sourceCategory] ?? [];
            $categoryGenres = array_values(array_unique(array_merge(
                (array) ($sourceTaxonomy[$sourceCategory] ?? []),
                array_keys($discoveredCounts)
            )));
            natcasesort($categoryGenres);
            $sourceTaxonomy[$sourceCategory] = array_values($categoryGenres);

            foreach ($categoryGenres as $genre) {
                $sourceGenres[$genre] = ($sourceGenres[$genre] ?? 0) + (int) ($discoveredCounts[$genre] ?? 0);
            }
        }
        foreach (array_keys((array) ($settings['genre_category_map'] ?? [])) as $genre) {
            if (! array_key_exists($genre, $sourceGenres)) {
                $sourceGenres[$genre] = 0;
            }
        }
        uksort($sourceGenres, 'strnatcasecmp');
        $this->normalizeSourceFilters($request, $sourceTaxonomy);

        $query = DelfiImportProduct::query()->with('product:id,name,sku,itemid,isbn,price,quantity');
        $this->applyFilters($query, $request);

        $products = $query->orderByDesc('is_current')
            ->orderByRaw('CASE WHEN feed_position IS NULL THEN 1 ELSE 0 END')
            ->orderBy('feed_position')
            ->orderByDesc('id')
            ->paginate(40)
            ->appends($request->query());

        $statusCounts = $this->statusCounts();
        $inspectionPendingCount = $this->inspectionPendingQuery()->count();
        $categories = (new Category())->getList(false);
        $publishers = Publisher::query()->orderBy('title')->get(['id', 'title']);
        $feedMetadata = $feedService->metadata();
        $feedMetadata['count'] = $statusCounts['all'];
        $importUi = [
            'name' => 'Delfi',
            'slug' => 'delfi',
            'route_prefix' => 'delfi-import',
            'route_parameter' => 'delfiImportProduct',
            'config_key' => 'delfi_import',
            'source_site' => 'Delfi.rs',
            'subtitle' => 'Inkrementalni uvoz knjiga s provjerom ISBN-a, autora i naslova te opcionalnim prijevodom opisa na hrvatski',
            'source_id_label' => 'Delfi šifra',
            'allowed_categories_label' => 'Samo Knjiga i Strana knjiga',
            'required_mapping_label' => 'Nakladnici › zadani ili prepoznati nakladnik',
            'publisher_category_label' => 'Rezervna podkategorija nakladnika',
            'default_publisher_label' => 'Delfi (koristi se kad izvorni nakladnik nije mapiran)',
            'supports_source_mapping' => true,
            'supports_source_category_filter' => true,
            'source_id_field' => 'nav_id',
            'secondary_source_id_label' => 'Delfi ID',
            'source_category_label' => 'Delfi kategorija',
            'source_subcategory_label' => 'Delfi podkategorija',
            'source_taxonomy_item_label' => 'žanr',
            'source_taxonomy_items_label' => 'žanrova',
            'inspection_workers' => 1,
            'inspection_delay_ms' => 500,
            'bulk_inspection_route' => 'delfi-import.inspect-bulk',
            'bulk_inspection_limit' => 100,
            'bulk_inspection_delay_ms' => 350,
        ];

        return view('back.catalog.laguna-import.index', compact(
            'products',
            'settings',
            'statusCounts',
            'inspectionPendingCount',
            'categories',
            'publishers',
            'feedMetadata',
            'priceCalculator',
            'sourceGenres',
            'sourceTaxonomy',
            'importUi'
        ));
    }

    public function refresh(DelfiFeedSynchronizer $synchronizer)
    {
        $lock = Cache::lock('delfi-import-refresh', 1800);
        if (! $lock->get()) {
            return redirect()->route('delfi-import.index')
                ->with('error', 'Osvježavanje Delfi feeda već je u tijeku.');
        }

        try {
            if (function_exists('set_time_limit')) {
                @set_time_limit(1800);
            }

            $result = $synchronizer->refresh();
            if (! empty($result['not_modified'])) {
                return redirect()->route('delfi-import.index')
                    ->with('success', 'Delfi feed nije promijenjen od zadnjeg uspješnog osvježavanja.');
            }

            return redirect()->route('delfi-import.index')
                ->with('success', sprintf(
                    'Delfi feed je osvježen: %s knjiga, %s aktualnih, %s uklonjenih i %s nepotpunih preskočenih.',
                    number_format((int) ($result['staged'] ?? 0), 0, ',', '.'),
                    number_format((int) ($result['current'] ?? 0), 0, ',', '.'),
                    number_format((int) ($result['retired'] ?? 0), 0, ',', '.'),
                    number_format((int) ($result['skipped'] ?? 0), 0, ',', '.')
                ));
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('delfi-import.index')
                ->with('error', $exception->getMessage());
        } finally {
            optional($lock)->release();
        }
    }

    public function inspect(
        Request $request,
        DelfiImportProduct $delfiImportProduct,
        DelfiImportService $importService
    ) {
        $lock = Cache::lock('delfi-import-source-' . $delfiImportProduct->id, 120);
        if (! $lock->get()) {
            return response()->json([
                'success' => false,
                'message' => 'Ovu knjigu već provjerava drugi proces.',
            ], 409);
        }

        try {
            $source = $importService->inspect($delfiImportProduct, ! $request->boolean('only_if_pending'));

            return response()->json([
                'success' => true,
                'status' => $source->ui_status,
                'message' => $source->check_message,
                'product_id' => $source->product_id,
                'isbn' => $source->isbn,
            ]);
        } catch (DelfiRetryableException $exception) {
            report($exception);

            return $this->retryableResponse($exception);
        } catch (DelfiTerminalException $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Provjera je prekinuta zbog neočekivane greške. Pokušajte ponovno.',
            ], 500);
        } finally {
            optional($lock)->release();
        }
    }

    public function inspectionQueue(Request $request)
    {
        $limit = min(max((int) $request->input('limit', 10), 1), 20);
        $includeCount = $request->boolean('include_count');
        $cursor = $this->decodeInspectionCursor((string) $request->input('cursor', ''));
        $baseQuery = $this->inspectionPendingQuery();
        $query = clone $baseQuery;
        $this->applyInspectionCursor($query, $cursor);

        $products = $query
            ->orderByRaw('CASE WHEN feed_position IS NULL THEN 1 ELSE 0 END')
            ->orderBy('feed_position')
            ->orderBy('id')
            ->limit($limit + 1)
            ->get(['id', 'name', 'feed_position']);
        $hasMore = $products->count() > $limit;
        $products = $products->take($limit)->values();
        $last = $products->last();
        $items = $products
            ->map(function (DelfiImportProduct $product) {
                return ['id' => (int) $product->id, 'name' => $product->name];
            })
            ->values();

        $payload = [
            'items' => $items,
            'next_cursor' => $last ? $this->encodeInspectionCursor($last) : null,
            'has_more' => $hasMore,
        ];
        if ($includeCount) {
            $payload['remaining'] = (clone $baseQuery)->count();
        }

        return response()->json($payload);
    }

    /**
     * Inspect up to 100 books through one ascending Delfi product-list page.
     *
     * The server owns the cursor. It is namespaced by the immutable feed token,
     * so refreshing the XML automatically starts a clean bulk pass.
     */
    public function bulkInspect(Request $request, DelfiImportService $importService)
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
            'reset' => 'nullable|boolean',
        ]);
        $limit = (int) ($validated['limit'] ?? 100);
        // Reading one indexed current row is intentionally cheap. A DISTINCT
        // scan across 130k rows is reserved for initialization, not every page.
        $feedToken = $this->currentBulkFeedToken();
        if ($feedToken === null) {
            return response()->json([
                'success' => false,
                'processed' => 0,
                'succeeded' => 0,
                'failed' => 0,
                'remaining' => 0,
                'next_cursor' => null,
                'done' => true,
                'message' => 'Prvo osvježite Delfi feed.',
            ]);
        }
        $stateKey = 'delfi-import-bulk-state:' . $feedToken;
        $lock = Cache::lock('delfi-import-bulk-lock:' . $feedToken, 600);
        if (! $lock->get()) {
            return response()->json([
                'success' => false,
                'processed' => 0,
                'succeeded' => 0,
                'failed' => 0,
                'remaining' => null,
                'next_cursor' => null,
                'done' => false,
                'message' => 'Bulk provjera već je u tijeku u drugom tabu.',
            ], 409);
        }

        $state = null;
        try {
            if ($request->boolean('reset')) {
                Cache::forget($stateKey);
            }
            $state = Cache::get($stateKey);
            if ($this->validBulkState($state, $feedToken)
                && ! empty($state['done'])
                && ! empty($state['incomplete'])) {
                // A manual click after an incomplete two-pass run starts a new
                // reconciliation run. Automatic UI looping already stops on done.
                Cache::forget($stateKey);
                $state = null;
            }
            if (! $this->validBulkState($state, $feedToken)) {
                // This full integrity check runs only when a feed-token state is
                // first created or explicitly reset. Resume requests stay O(1).
                $tokens = $this->currentBulkFeedTokens();
                if ($tokens->count() !== 1 || (string) $tokens->first() !== $feedToken) {
                    Cache::forget($stateKey);

                    return response()->json([
                        'success' => false,
                        'processed' => 0,
                        'succeeded' => 0,
                        'failed' => 0,
                        'remaining' => null,
                        'next_cursor' => null,
                        'done' => false,
                        'message' => 'Aktualni Delfi redovi pripadaju različitim verzijama feeda. Osvježite feed prije provjere.',
                    ], 409);
                }

                $remaining = $this->bulkPendingQuery($feedToken)->count();
                $state = [
                    'feed_token' => $feedToken,
                    'skip' => 0,
                    'last_old_product_id' => null,
                    'pass' => 1,
                    'remaining' => $remaining,
                    'processed_total' => 0,
                    'succeeded_total' => 0,
                    'failed_total' => 0,
                    'ignored_total' => 0,
                    'scan_total' => null,
                    'scan_processed' => 0,
                    'done' => $remaining === 0,
                    'incomplete' => false,
                ];
            }

            if ($state['done']) {
                return response()->json($this->bulkResponse($state, [
                    'processed' => 0,
                    'succeeded' => 0,
                    'failed' => 0,
                    'failures' => [],
                ]));
            }

            $page = $importService->inspectProductListPage($feedToken, (int) $state['skip'], $limit);
            if ($this->currentBulkFeedToken() !== $feedToken) {
                Cache::forget($stateKey);

                return response()->json([
                    'success' => false,
                    'processed' => 0,
                    'succeeded' => 0,
                    'failed' => 0,
                    'remaining' => null,
                    'next_cursor' => null,
                    'done' => false,
                    'message' => 'Delfi feed osvježen je tijekom bulk provjere. Ponovno pokrenite provjeru za novi feed.',
                ], 409);
            }
            $items = array_values((array) ($page['items'] ?? []));
            $firstOldProductId = $items !== [] ? (int) ($items[0]['remote_product_id'] ?? 0) : null;
            $lastOldProductId = $items !== []
                ? (int) ($items[count($items) - 1]['remote_product_id'] ?? 0)
                : $state['last_old_product_id'];
            if ($items !== []
                && $state['last_old_product_id'] !== null
                && $firstOldProductId <= (int) $state['last_old_product_id']) {
                // The ascending offset is no longer trustworthy (for example,
                // an older upstream row was deleted). Keep local rows pending
                // and let the next manual click restart safely from zero.
                Cache::forget($stateKey);

                return response()->json([
                    'success' => false,
                    'processed' => 0,
                    'succeeded' => 0,
                    'failed' => 0,
                    'remaining' => (int) $state['remaining'],
                    'next_cursor' => null,
                    'done' => false,
                    'message' => 'Delfi je promijenio redoslijed bulk rezultata. Ponovno pokrenite provjeru.',
                ], 409);
            }

            $processed = (int) ($page['processed'] ?? 0);
            $state['remaining'] = max(0, (int) $state['remaining'] - $processed);
            $state['processed_total'] = (int) $state['processed_total'] + $processed;
            $state['succeeded_total'] = (int) $state['succeeded_total'] + (int) ($page['succeeded'] ?? 0);
            $state['failed_total'] = (int) $state['failed_total'] + (int) ($page['failed'] ?? 0);
            $state['ignored_total'] = (int) $state['ignored_total'] + (int) ($page['ignored'] ?? 0);
            $state['last_old_product_id'] = $lastOldProductId;
            $state['scan_total'] = (int) ($page['total'] ?? 0);
            $state['scan_processed'] = min(
                $state['scan_total'],
                (int) ($page['next_skip'] ?? $state['scan_total'])
            );
            if ((int) $state['remaining'] === 0) {
                // Most incremental runs touch only the newest pages. Confirm
                // that no pending row appeared concurrently, then stop without
                // scanning the rest of the 130k upstream catalogue.
                $state['remaining'] = $this->bulkPendingQuery($feedToken)->count();
                if ((int) $state['remaining'] === 0) {
                    $state['done'] = true;
                    $state['incomplete'] = false;
                }
            }

            if (! $state['done'] && ! empty($page['has_more'])) {
                $state['skip'] = (int) ($page['next_skip'] ?? ((int) $state['skip'] + $limit));
            } elseif (! $state['done']) {
                $remaining = $this->bulkPendingQuery($feedToken)->count();
                $state['remaining'] = $remaining;
                if ($remaining > 0 && (int) $state['pass'] === 1) {
                    // A second stable ascending pass reconciles rows missed if
                    // Delfi changed its list while the first pass was running.
                    $state['skip'] = 0;
                    $state['last_old_product_id'] = null;
                    $state['pass'] = 2;
                    $state['scan_processed'] = 0;
                } else {
                    $state['done'] = true;
                    $state['incomplete'] = $remaining > 0;
                }
            }
            Cache::put($stateKey, $state, now()->addDays(30));

            return response()->json($this->bulkResponse($state, $page));
        } catch (DelfiRetryableException $exception) {
            report($exception);

            $hasState = $this->validBulkState($state, $feedToken);

            return response()->json([
                'success' => false,
                'retryable' => true,
                'processed' => 0,
                'succeeded' => 0,
                'failed' => 0,
                'remaining' => $hasState ? (int) $state['remaining'] : null,
                'next_cursor' => $hasState ? $this->encodeBulkCursor($state) : null,
                'done' => false,
                'message' => $exception->getMessage(),
            ], $exception->responseStatus())->header(
                'Retry-After',
                (string) $exception->retryAfterSeconds()
            );
        } catch (DelfiTerminalException $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'retryable' => false,
                'processed' => 0,
                'succeeded' => 0,
                'failed' => 0,
                'remaining' => null,
                'next_cursor' => null,
                'done' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'processed' => 0,
                'succeeded' => 0,
                'failed' => 0,
                'remaining' => null,
                'next_cursor' => null,
                'done' => false,
                'message' => 'Bulk provjera prekinuta je zbog neočekivane greške. Pokazivač nije pomaknut.',
            ], 500);
        } finally {
            optional($lock)->release();
        }
    }

    public function import(
        Request $request,
        DelfiImportProduct $delfiImportProduct,
        DelfiImportService $importService
    ) {
        $lock = Cache::lock('delfi-import-source-' . $delfiImportProduct->id, 300);
        if (! $lock->get()) {
            return response()->json([
                'success' => false,
                'message' => 'Ovu knjigu već provjerava ili uvozi drugi proces.',
            ], 409);
        }

        try {
            $validated = $request->validate([
                'category_id' => 'nullable|integer|exists:categories,id',
            ]);
            $categoryId = isset($validated['category_id']) ? (int) $validated['category_id'] : null;

            return response()->json([
                'success' => true,
            ] + $importService->import($delfiImportProduct, $categoryId));
        } catch (DelfiRetryableException $exception) {
            report($exception);

            return $this->retryableResponse($exception);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } finally {
            optional($lock)->release();
        }
    }

    public function updateSettings(Request $request, DelfiImportSettings $settingsService)
    {
        $validated = $request->validate([
            'exchange_rate' => 'required|numeric|min:0.0001|max:100000',
            'markup_percent' => 'required|numeric|min:0|max:1000',
            'publisher_parent_category_id' => 'required|integer|exists:categories,id',
            'publisher_category_id' => 'required|integer|exists:categories,id',
            'publisher_id' => 'required|integer|exists:publishers,id',
            'default_quantity' => 'required|integer|min:0|max:100000',
            'existing_action' => 'required|in:skip,price_stock',
            'source_genres' => 'array',
            'source_genres.*' => 'string|max:255',
            'genre_category_ids' => 'array',
            'genre_category_ids.*' => 'nullable|integer|exists:categories,id',
        ]);

        $parentCategory = Category::query()->find((int) $validated['publisher_parent_category_id']);
        $publisherCategory = Category::query()->find((int) $validated['publisher_category_id']);
        if (! $parentCategory || (int) $parentCategory->parent_id !== 0) {
            return redirect()->back()->withInput()->withErrors([
                'publisher_parent_category_id' => 'Kategorija Nakladnici mora biti glavna kategorija.',
            ]);
        }
        if (! $publisherCategory || (int) $publisherCategory->parent_id !== (int) $parentCategory->id) {
            return redirect()->back()->withInput()->withErrors([
                'publisher_category_id' => 'Rezervna podkategorija mora pripadati odabranoj kategoriji Nakladnici.',
            ]);
        }

        $genreCategoryMap = [];
        foreach (($validated['source_genres'] ?? []) as $index => $genre) {
            $categoryId = (int) (($validated['genre_category_ids'] ?? [])[$index] ?? 0);
            if ($categoryId > 0 && trim($genre) !== '') {
                $genreCategoryMap[trim($genre)] = $categoryId;
            }
        }

        unset($validated['source_genres'], $validated['genre_category_ids']);
        $validated['genre_category_map'] = $genreCategoryMap;
        $validated['map_source_publishers'] = $request->boolean('map_source_publishers') ? 1 : 0;
        $validated['activate_new_products'] = $request->boolean('activate_new_products') ? 1 : 0;
        $validated['translate_descriptions'] = $request->boolean('translate_descriptions') ? 1 : 0;
        $settingsService->save($validated);

        return redirect()->route('delfi-import.index', ['tab' => 'settings'])
            ->with('success', 'Postavke Delfi importa su spremljene.');
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $status = trim((string) $request->input('status', 'new')) ?: 'new';
        if ($status === 'missing') {
            $query->where('is_current', false);
        } elseif (! $request->boolean('include_missing')) {
            $query->where('is_current', true);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $isbnSearch = preg_replace('/\D+/', '', $search);
            $query->where(function (Builder $query) use ($search, $isbnSearch) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('external_id', 'like', '%' . $search . '%')
                    ->orWhere('nav_id', 'like', '%' . $search . '%')
                    ->orWhere('author', 'like', '%' . $search . '%');
                if ($isbnSearch !== '') {
                    $query->orWhere('isbn', 'like', '%' . $isbnSearch . '%')
                        ->orWhere('ean', 'like', '%' . $isbnSearch . '%');
                }
            });
        }

        $sourceCategory = trim((string) $request->input('source_category'));
        if (in_array($sourceCategory, DelfiProductListApiClient::CATEGORIES, true)) {
            $query->where('source_category', $sourceCategory);
        }

        $sourceGenre = trim((string) $request->input('source_genre'));
        if ($sourceGenre !== '' && mb_strlen($sourceGenre) <= 255) {
            $query->whereColumn('checked_source_hash', 'source_hash')
                ->whereJsonContains('source_genres', $sourceGenre);
        }

        if (! in_array($status, ['all', 'missing'], true)) {
            $this->applyStatusFilter($query, $status);
        }
    }

    private function applyStatusFilter(Builder $query, string $status): void
    {
        if ($status === 'pending') {
            $query->whereNull('product_id')->where(function (Builder $query) {
                $query->whereNull('checked_source_hash')->orWhereColumn('checked_source_hash', '!=', 'source_hash');
            });
        } elseif ($status === 'new') {
            $query->whereNull('product_id')->where('check_status', 'new')->whereColumn('checked_source_hash', 'source_hash');
        } elseif ($status === 'existing') {
            $query->whereNotNull('product_id')->whereNull('imported_at')->whereNotIn('check_status', ['conflict', 'error']);
        } elseif ($status === 'imported') {
            $query->whereNotNull('imported_at')->whereColumn('source_hash', 'imported_hash')->whereNotIn('check_status', ['conflict', 'error']);
        } elseif ($status === 'changed') {
            $query->whereNotNull('imported_at')->whereColumn('source_hash', '!=', 'imported_hash')->whereNotIn('check_status', ['conflict', 'error']);
        } elseif (in_array($status, ['conflict', 'error'], true)) {
            $query->where('check_status', $status);
        }
    }

    private function statusCounts(): array
    {
        $counts = DelfiImportProduct::query()
            ->where('is_current', true)
            ->selectRaw("COUNT(*) AS all_count")
            ->selectRaw("SUM(CASE WHEN product_id IS NULL AND (checked_source_hash IS NULL OR checked_source_hash != source_hash) THEN 1 ELSE 0 END) AS pending_count")
            ->selectRaw("SUM(CASE WHEN product_id IS NULL AND check_status = 'new' AND checked_source_hash = source_hash THEN 1 ELSE 0 END) AS new_count")
            ->selectRaw("SUM(CASE WHEN product_id IS NOT NULL AND imported_at IS NULL AND check_status NOT IN ('conflict', 'error') THEN 1 ELSE 0 END) AS existing_count")
            ->selectRaw("SUM(CASE WHEN imported_at IS NOT NULL AND source_hash = imported_hash AND check_status NOT IN ('conflict', 'error') THEN 1 ELSE 0 END) AS imported_count")
            ->selectRaw("SUM(CASE WHEN imported_at IS NOT NULL AND source_hash != imported_hash AND check_status NOT IN ('conflict', 'error') THEN 1 ELSE 0 END) AS changed_count")
            ->selectRaw("SUM(CASE WHEN check_status = 'conflict' THEN 1 ELSE 0 END) AS conflict_count")
            ->selectRaw("SUM(CASE WHEN check_status = 'error' THEN 1 ELSE 0 END) AS error_count")
            ->first();

        return [
            'all' => (int) ($counts->all_count ?? 0),
            'pending' => (int) ($counts->pending_count ?? 0),
            'new' => (int) ($counts->new_count ?? 0),
            'existing' => (int) ($counts->existing_count ?? 0),
            'imported' => (int) ($counts->imported_count ?? 0),
            'changed' => (int) ($counts->changed_count ?? 0),
            'conflict' => (int) ($counts->conflict_count ?? 0),
            'error' => (int) ($counts->error_count ?? 0),
            'missing' => DelfiImportProduct::query()->where('is_current', false)->count(),
        ];
    }

    private function inspectionPendingQuery(): Builder
    {
        return DelfiImportProduct::query()
            ->where('is_current', true)
            ->where(function (Builder $query) {
                $query->whereNull('checked_source_hash')->orWhereColumn('checked_source_hash', '!=', 'source_hash');
            });
    }

    private function bulkPendingQuery(string $feedToken): Builder
    {
        return DelfiImportProduct::query()
            ->where('is_current', true)
            ->where('feed_token', $feedToken)
            ->whereIn('source_category', DelfiProductListApiClient::CATEGORIES)
            ->where(function (Builder $query) {
                $query->whereNull('checked_source_hash')
                    ->orWhereColumn('checked_source_hash', '!=', 'source_hash');
            });
    }

    private function currentBulkFeedTokens()
    {
        return DelfiImportProduct::query()
            ->where('is_current', true)
            ->whereIn('source_category', DelfiProductListApiClient::CATEGORIES)
            ->whereNotNull('feed_token')
            ->distinct()
            ->limit(2)
            ->pluck('feed_token')
            ->values();
    }

    private function currentBulkFeedToken(): ?string
    {
        $token = DelfiImportProduct::query()
            ->where('is_current', true)
            ->whereIn('source_category', DelfiProductListApiClient::CATEGORIES)
            ->whereNotNull('feed_token')
            ->value('feed_token');

        return $token === null ? null : (string) $token;
    }

    private function validBulkState($state, string $feedToken): bool
    {
        return is_array($state)
            && ($state['feed_token'] ?? null) === $feedToken
            && is_int($state['skip'] ?? null)
            && $state['skip'] >= 0
            && (is_null($state['last_old_product_id'] ?? null)
                || (is_int($state['last_old_product_id']) && $state['last_old_product_id'] > 0))
            && in_array($state['pass'] ?? null, [1, 2], true)
            && is_int($state['remaining'] ?? null)
            && $state['remaining'] >= 0
            && is_int($state['processed_total'] ?? null)
            && $state['processed_total'] >= 0
            && is_int($state['succeeded_total'] ?? null)
            && $state['succeeded_total'] >= 0
            && is_int($state['failed_total'] ?? null)
            && $state['failed_total'] >= 0
            && is_int($state['ignored_total'] ?? null)
            && $state['ignored_total'] >= 0
            && (is_null($state['scan_total'] ?? null)
                || (is_int($state['scan_total']) && $state['scan_total'] >= 0))
            && is_int($state['scan_processed'] ?? null)
            && $state['scan_processed'] >= 0
            && is_bool($state['done'] ?? null)
            && is_bool($state['incomplete'] ?? null);
    }

    private function bulkResponse(array $state, array $page): array
    {
        $incomplete = ! empty($state['done']) && ! empty($state['incomplete']);
        if ($incomplete) {
            $message = sprintf(
                'Bulk prolaz je dovršen, ali %s lokalnih knjiga nije pronađeno u Delfi listi. Ostale su neprovjerene.',
                number_format((int) $state['remaining'], 0, ',', '.')
            );
        } elseif ($state['done'] && (int) $state['failed_total'] > 0) {
            $message = sprintf(
                'Bulk provjera je dovršena: %s uspješnih i %s provjera s greškom.',
                number_format((int) $state['succeeded_total'], 0, ',', '.'),
                number_format((int) $state['failed_total'], 0, ',', '.')
            );
        } elseif ($state['done']) {
            $message = 'Sve aktualne Delfi knjige uspješno su provjerene.';
        } elseif ((int) $state['pass'] === 2 && (int) $state['skip'] === 0) {
            $message = 'Prvi prolaz je dovršen. Automatski slijedi završni kontrolni prolaz.';
        } else {
            $message = 'Bulk provjera je spremljena i može se sigurno nastaviti.';
        }

        return [
            'success' => ! $incomplete,
            'processed' => (int) ($page['processed'] ?? 0),
            'succeeded' => (int) ($page['succeeded'] ?? 0),
            'failed' => (int) ($page['failed'] ?? 0),
            'ignored' => (int) ($page['ignored'] ?? 0),
            'failures' => array_values((array) ($page['failures'] ?? [])),
            'remaining' => (int) $state['remaining'],
            'processed_total' => (int) $state['processed_total'],
            'succeeded_total' => (int) $state['succeeded_total'],
            'failed_total' => (int) $state['failed_total'],
            'ignored_total' => (int) $state['ignored_total'],
            'cumulative_succeeded' => (int) $state['succeeded_total'],
            'cumulative_failed' => (int) $state['failed_total'],
            'cumulative_ignored' => (int) $state['ignored_total'],
            'scan_total' => $state['scan_total'] === null ? null : (int) $state['scan_total'],
            'scan_processed' => (int) $state['scan_processed'],
            'next_cursor' => $state['done'] ? null : $this->encodeBulkCursor($state),
            'done' => (bool) $state['done'],
            'incomplete' => $incomplete,
            'can_reset' => (bool) $state['done'],
            'pass' => (int) $state['pass'],
            'message' => $message,
        ];
    }

    private function encodeBulkCursor(array $state): string
    {
        $encoded = base64_encode((string) json_encode([
            'feed_token' => $state['feed_token'],
            'skip' => (int) $state['skip'],
            'last_old_product_id' => $state['last_old_product_id'],
            'pass' => (int) $state['pass'],
        ], JSON_UNESCAPED_SLASHES));

        return rtrim(strtr($encoded, '+/', '-_'), '=');
    }

    private function applyInspectionCursor(Builder $query, ?array $cursor): void
    {
        if ($cursor === null) {
            return;
        }

        if ($cursor['feed_position'] === null) {
            $query->whereNull('feed_position')->where('id', '>', $cursor['id']);

            return;
        }

        $query->where(function (Builder $query) use ($cursor) {
            $query->where('feed_position', '>', $cursor['feed_position'])
                ->orWhere(function (Builder $query) use ($cursor) {
                    $query->where('feed_position', $cursor['feed_position'])
                        ->where('id', '>', $cursor['id']);
                })
                ->orWhereNull('feed_position');
        });
    }

    private function encodeInspectionCursor(DelfiImportProduct $product): string
    {
        $encoded = base64_encode((string) json_encode([
            'feed_position' => $product->feed_position,
            'id' => (int) $product->id,
        ]));

        return rtrim(strtr($encoded, '+/', '-_'), '=');
    }

    private function decodeInspectionCursor(string $cursor): ?array
    {
        if ($cursor === '') {
            return null;
        }

        if (strlen($cursor) > 512 || ! preg_match('/\A[A-Za-z0-9_-]+\z/D', $cursor)) {
            abort(422, 'Neispravan pokazivač reda za provjeru.');
        }

        $padding = (4 - strlen($cursor) % 4) % 4;
        $decoded = base64_decode(strtr($cursor, '-_', '+/') . str_repeat('=', $padding), true);
        $values = is_string($decoded) ? json_decode($decoded, true) : null;
        if (! is_array($values)
            || ! array_key_exists('feed_position', $values)
            || ! array_key_exists('id', $values)
            || (! is_null($values['feed_position']) && (! is_int($values['feed_position']) || $values['feed_position'] < 0))
            || ! is_int($values['id'])
            || $values['id'] < 1) {
            abort(422, 'Neispravan pokazivač reda za provjeru.');
        }

        return $values;
    }

    private function retryableResponse(DelfiRetryableException $exception)
    {
        return response()->json([
            'success' => false,
            'retryable' => true,
            'message' => $exception->getMessage(),
        ], $exception->responseStatus())->header(
            'Retry-After',
            (string) $exception->retryAfterSeconds()
        );
    }

    private function sourceGenreCountsByCategory(): array
    {
        return Cache::remember('delfi-import-source-genre-counts-by-category', now()->addMinutes(10), function () {
            $counts = array_fill_keys(DelfiProductListApiClient::CATEGORIES, []);
            DelfiImportProduct::query()
                ->whereIn('source_category', DelfiProductListApiClient::CATEGORIES)
                ->whereNotNull('source_genres')
                ->whereColumn('checked_source_hash', 'source_hash')
                ->select(['id', 'source_category', 'source_genres'])
                ->orderBy('id')
                ->chunkById(500, function ($products) use (&$counts) {
                    foreach ($products as $product) {
                        $sourceCategory = (string) $product->source_category;
                        if (! array_key_exists($sourceCategory, $counts)) {
                            continue;
                        }
                        foreach ((array) $product->source_genres as $genre) {
                            $genre = trim((string) $genre);
                            if ($genre !== '') {
                                $counts[$sourceCategory][$genre] = ($counts[$sourceCategory][$genre] ?? 0) + 1;
                            }
                        }
                    }
                });
            foreach ($counts as &$categoryCounts) {
                uksort($categoryCounts, 'strnatcasecmp');
            }
            unset($categoryCounts);

            return $counts;
        });
    }

    private function normalizeSourceFilters(Request $request, array $sourceTaxonomy): void
    {
        $sourceCategory = trim((string) $request->query('source_category'));
        if ($sourceCategory !== '' && ! in_array($sourceCategory, DelfiProductListApiClient::CATEGORIES, true)) {
            $request->query->remove('source_category');
            $sourceCategory = '';
        }

        $sourceGenre = trim((string) $request->query('source_genre'));
        if ($sourceGenre === '') {
            return;
        }

        $knownGenres = $sourceCategory !== ''
            ? (array) ($sourceTaxonomy[$sourceCategory] ?? [])
            : array_merge(...array_values($sourceTaxonomy ?: [[]]));

        if (! in_array($sourceGenre, $knownGenres, true)) {
            $request->query->remove('source_genre');
        }
    }
}
