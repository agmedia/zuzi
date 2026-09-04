<?php

namespace App\Http\Controllers\Back\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Back\Catalog\Category;
use App\Models\Back\Catalog\Publisher;
use App\Models\Back\Catalog\ZnanjeImportProduct;
use App\Services\Catalog\CachedNewImportProductReconciler;
use App\Services\Catalog\ImportFilterMemory;
use App\Services\Znanje\ZnanjeFeedService;
use App\Services\Znanje\ZnanjeFeedSynchronizer;
use App\Services\Znanje\ZnanjeImportService;
use App\Services\Znanje\ZnanjeImportSettings;
use App\Services\Znanje\ZnanjePriceCalculator;
use App\Services\Znanje\ZnanjeRetryableException;
use App\Services\Znanje\ZnanjeTerminalException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ZnanjeImportController extends Controller
{
    private const SOURCE_CATEGORIES = ['Knjige', 'Strane knjige'];

    public function index(
        Request $request,
        ZnanjeImportSettings $settingsService,
        ZnanjeFeedService $feedService,
        ZnanjePriceCalculator $priceCalculator,
        CachedNewImportProductReconciler $catalogReconciler,
        ImportFilterMemory $filterMemory
    ) {
        if ($filterMemory->restore($request, 'znanje')) {
            return redirect()->route('znanje-import.index');
        }

        $settings = $settingsService->all();
        $countsByCategory = $this->sourceGenreCountsByCategory();
        $sourceCategoryCounts = $this->sourceCategoryCounts();
        $sourceTaxonomy = [];
        $sourceGenres = [];
        foreach (self::SOURCE_CATEGORIES as $sourceCategory) {
            $categoryCounts = (array) ($countsByCategory[$sourceCategory] ?? []);
            $genres = array_keys($categoryCounts);
            natcasesort($genres);
            $sourceTaxonomy[$sourceCategory] = array_values($genres);
            // Root categories are valid mapping keys too. This lets Knjige and
            // Strane knjige receive different Zuzi categories independently of
            // the optional per-import category selected on the list tab.
            $sourceGenres[$sourceCategory] = (int) ($sourceCategoryCounts[$sourceCategory] ?? 0);
            foreach ($categoryCounts as $genre => $count) {
                $sourceGenres[$sourceCategory . ' › ' . $genre] = (int) $count;
            }
        }
        foreach (array_keys((array) ($settings['genre_category_map'] ?? $settings['category_map'] ?? [])) as $genre) {
            if (! array_key_exists($genre, $sourceGenres)) {
                $sourceGenres[$genre] = 0;
            }
        }
        uksort($sourceGenres, 'strnatcasecmp');
        $this->normalizeSourceFilters($request, $sourceTaxonomy);
        $filterMemory->remember($request, 'znanje');

        $query = ZnanjeImportProduct::query()
            ->with('product:id,name,sku,itemid,isbn,price,quantity');
        $this->applyFilters($query, $request);

        $paginate = function () use ($query, $request) {
            return (clone $query)
                ->orderByDesc('is_current')
                ->orderByRaw('CASE WHEN publication_year IS NULL THEN 1 ELSE 0 END')
                ->orderByDesc('publication_year')
                ->orderByRaw('CASE WHEN feed_position IS NULL THEN 1 ELSE 0 END')
                ->orderByDesc('feed_position')
                ->orderByDesc('remote_product_id')
                ->orderByDesc('id')
                ->paginate(40)
                ->appends($request->query());
        };
        $selectedStatus = trim((string) $request->input('status', 'new')) ?: 'new';
        $reconciliationPass = 0;
        do {
            $products = $paginate();
            $products->setCollection($catalogReconciler->reconcile($products->getCollection()));
            $pageChanged = $selectedStatus === 'new'
                && $products->getCollection()->contains(
                    fn ($product) => $product->ui_status !== 'new'
                );
            $reconciliationPass++;
            if ($pageChanged && $reconciliationPass >= 1000) {
                throw new \RuntimeException(
                    'Provjera statusa nije se mogla stabilizirati. Osvježite stranicu i pokušajte ponovno.'
                );
            }
        } while ($pageChanged);

        $statusCounts = $this->statusCounts();
        $inspectionPendingCount = $this->inspectionPendingQuery()->count();
        $categories = (new Category())->getList(false);
        $publishers = Publisher::query()->orderBy('title')->get(['id', 'title']);
        $feedMetadata = $this->feedMetadata($feedService, $statusCounts['all']);
        $importUi = [
            'name' => 'Znanje',
            'slug' => 'znanje',
            'route_prefix' => 'znanje-import',
            'route_parameter' => 'znanjeImportProduct',
            'config_key' => 'znanje_import',
            'source_site' => 'Znanje.hr',
            'subtitle' => 'Inkrementalni uvoz samo dostupnih hrvatskih i stranih knjiga s provjerom ISBN-a, autora i naslova',
            'source_id_label' => 'Znanje ID',
            'source_id_field' => 'remote_product_id',
            'secondary_source_id_label' => null,
            'allowed_categories_label' => 'Samo dostupne knjige iz kategorija Knjige i Strane knjige',
            'required_mapping_label' => 'Nakladnici › Znanje ili prepoznati nakladnik',
            'publisher_category_label' => 'Rezervna podkategorija nakladnika',
            'default_publisher_label' => 'Znanje (koristi se kad izvorni nakladnik nije mapiran)',
            'supports_source_mapping' => true,
            'supports_source_publisher_mapping' => true,
            'supports_source_taxonomy_mapping' => true,
            'supports_source_category_filter' => true,
            'supports_translation' => false,
            'uses_exchange_rate' => false,
            'source_price_field' => 'price_eur',
            'source_sale_price_field' => 'sale_price_eur',
            'source_currency' => 'EUR',
            'price_preview_source_amount' => 15,
            'feed_link_label' => 'Otvori sitemap',
            'feed_url_config_key' => 'sitemap_url',
            'source_category_label' => 'Znanje kategorija',
            'source_subcategory_label' => 'Znanje podkategorija',
            'source_filter_help' => 'Podkategorije se otkrivaju iz Znanje kataloga i dostupne su nakon osvježavanja feeda.',
            'source_taxonomy_label' => 'Znanje',
            'source_taxonomy_item_label' => 'kategorija ili podkategorija',
            'source_taxonomy_items_label' => 'kategorija i podkategorija',
            'inspection_workers' => 2,
            'inspection_delay_ms' => 250,
            'bulk_inspection_route' => null,
            'bulk_inspection_limit' => 100,
            'bulk_inspection_delay_ms' => 350,
            'supports_batched_refresh' => true,
            'refresh_start_route' => 'znanje-import.refresh-start',
            'refresh_step_route' => 'znanje-import.refresh-step',
            'refresh_cancel_route' => 'znanje-import.refresh-cancel',
            'refresh_root_options' => [
                'all' => 'Sve dostupne knjige',
                '500' => 'Knjige',
                '505' => 'Strane knjige (engleske)',
            ],
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

    public function refresh(Request $request, ZnanjeFeedSynchronizer $synchronizer)
    {
        $rootCategoryId = $this->validatedRefreshRoot($request);

        try {
            $state = $synchronizer->start($rootCategoryId);

            return redirect()->route('znanje-import.index', [
                'refresh_token' => $state['token'],
            ])->with('success', 'Osvježavanje Znanje feeda je pokrenuto i nastavit će se u kratkim koracima.');
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('znanje-import.index')->with('error', $exception->getMessage());
        }
    }

    public function refreshStart(Request $request, ZnanjeFeedSynchronizer $synchronizer)
    {
        $rootCategoryId = $this->validatedRefreshRoot($request);

        try {
            $state = $synchronizer->start($rootCategoryId);

            return response()->json(array_merge([
                'success' => true,
                'done' => false,
                'message' => 'Preuzimanje dostupnih Znanje knjiga je pokrenuto.',
            ], $state));
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 409);
        }
    }

    private function validatedRefreshRoot(Request $request): ?int
    {
        $validated = $request->validate([
            'refresh_scope' => 'nullable|in:all,500,505',
        ]);
        $root = (string) ($validated['refresh_scope'] ?? 'all');

        return $root === 'all' ? null : (int) $root;
    }

    public function refreshStep(Request $request, ZnanjeFeedSynchronizer $synchronizer)
    {
        $validated = $request->validate([
            'token' => 'required|uuid',
        ]);
        $token = (string) $validated['token'];

        try {
            $state = $synchronizer->step(
                $token,
                // Never let an environment override turn one admin AJAX call
                // back into a long multi-page crawl.
                1
            );
            if (! empty($state['completed']) && is_array($state['result'] ?? null)) {
                $result = $state['result'];
                $this->clearSourceTaxonomyCaches();

                return response()->json(array_merge($state, $result, [
                    'success' => true,
                    'done' => true,
                    'message' => $this->refreshSuccessMessage($result),
                ]));
            }
            if (! empty($state['ready_to_finalize'])) {
                $result = $synchronizer->finalize($token);
                $this->clearSourceTaxonomyCaches();

                return response()->json(array_merge($state, $result, [
                    'success' => true,
                    'done' => true,
                    'message' => $this->refreshSuccessMessage($result),
                ]));
            }

            return response()->json(array_merge([
                'success' => true,
                'done' => false,
                'message' => 'Preuzimam dostupne Znanje knjige…',
            ], $state));
        } catch (ZnanjeRetryableException $exception) {
            report($exception);

            return $this->retryableResponse($exception);
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 410);
        } catch (\Throwable $exception) {
            report($exception);

            try {
                $state = $synchronizer->status($token);
                $terminal = in_array($state['phase'] ?? null, ['error', 'cancelled'], true);
            } catch (\Throwable $statusException) {
                $state = [];
                $terminal = true;
            }

            return response()->json(array_merge($state, [
                'success' => false,
                'message' => $exception->getMessage(),
            ]), $terminal ? 410 : 409);
        }
    }

    public function refreshCancel(Request $request, ZnanjeFeedSynchronizer $synchronizer)
    {
        $validated = $request->validate([
            'token' => 'required|uuid',
        ]);

        try {
            $result = $synchronizer->cancel((string) $validated['token'], true);
        } catch (ZnanjeRetryableException $exception) {
            return $this->retryableResponse($exception);
        }

        return response()->json(array_merge([
            'success' => true,
            'done' => true,
        ], $result));
    }

    public function inspect(
        Request $request,
        ZnanjeImportProduct $znanjeImportProduct,
        ZnanjeImportService $importService
    ) {
        $lock = Cache::lock('znanje-import-source-' . $znanjeImportProduct->id, 120);
        if (! $lock->get()) {
            return response()->json([
                'success' => false,
                'message' => 'Ovu knjigu već provjerava drugi proces.',
            ], 409);
        }

        try {
            $source = $importService->inspect(
                $znanjeImportProduct,
                ! $request->boolean('only_if_pending')
            );
            $this->clearSourceTaxonomyCaches();

            return response()->json([
                'success' => true,
                'status' => $source->ui_status,
                'message' => $source->check_message,
                'check_message' => $source->check_message,
                'product_id' => $source->product_id,
                'product_url' => $source->product_id
                    ? route('products.edit', ['product' => $source->product_id])
                    : null,
                'isbn' => $source->isbn,
            ]);
        } catch (ZnanjeRetryableException $exception) {
            report($exception);

            return $this->retryableResponse($exception);
        } catch (ZnanjeTerminalException $exception) {
            report($exception);

            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
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
        $limit = min(max((int) $request->input('limit', 20), 1), 50);
        $query = $this->inspectionPendingQuery();
        $remaining = (clone $query)->count();
        $items = $query
            ->orderByRaw('CASE WHEN publication_year IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('publication_year')
            ->orderByDesc('remote_product_id')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'name'])
            ->map(fn (ZnanjeImportProduct $product) => [
                'id' => (int) $product->id,
                'name' => $product->name,
            ])
            ->values();

        return response()->json(['remaining' => $remaining, 'items' => $items]);
    }

    public function import(
        Request $request,
        ZnanjeImportProduct $znanjeImportProduct,
        ZnanjeImportService $importService
    ) {
        $lock = Cache::lock('znanje-import-source-' . $znanjeImportProduct->id, 300);
        if (! $lock->get()) {
            return response()->json([
                'success' => false,
                'message' => 'Ovu knjigu već obrađuje drugi proces.',
            ], 409);
        }

        try {
            $validated = $request->validate([
                'category_id' => 'nullable|integer|exists:categories,id',
            ]);
            $categoryId = isset($validated['category_id'])
                ? (int) $validated['category_id']
                : null;
            $result = $importService->import($znanjeImportProduct, $categoryId);
            $source = $znanjeImportProduct->fresh(['product']);

            return response()->json([
                'success' => true,
            ] + $result + [
                'status' => $source->ui_status,
                'check_message' => $source->check_message,
                'product_url' => $source->product_id
                    ? route('products.edit', ['product' => $source->product_id])
                    : null,
            ]);
        } catch (ZnanjeRetryableException $exception) {
            report($exception);

            return $this->retryableResponse($exception);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        } finally {
            optional($lock)->release();
        }
    }

    public function updateSettings(Request $request, ZnanjeImportSettings $settingsService)
    {
        $validated = $request->validate([
            'markup_percent' => 'required|numeric|min:0|max:1000',
            'publisher_parent_category_id' => 'required|integer|exists:categories,id',
            'publisher_category_id' => 'required|integer|exists:categories,id',
            'publisher_id' => 'required|integer|exists:publishers,id',
            'default_quantity' => 'required|integer|min:0|max:100000',
            'existing_action' => 'required|in:skip,price_stock',
            'source_genres' => 'array',
            'source_genres.*' => 'string|max:512',
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
        if (! $publisherCategory
            || (int) $publisherCategory->parent_id !== (int) $parentCategory->id) {
            return redirect()->back()->withInput()->withErrors([
                'publisher_category_id' => 'Rezervna podkategorija mora pripadati odabranoj kategoriji Nakladnici.',
            ]);
        }

        $categoryMap = [];
        foreach (($validated['source_genres'] ?? []) as $index => $genre) {
            $categoryId = (int) (($validated['genre_category_ids'] ?? [])[$index] ?? 0);
            $genre = trim((string) $genre);
            if ($genre !== '' && $categoryId > 0) {
                $categoryMap[$genre] = $categoryId;
            }
        }

        unset($validated['source_genres'], $validated['genre_category_ids']);
        $validated['category_map'] = $categoryMap;
        $validated['genre_category_map'] = $categoryMap;
        $validated['map_source_publishers'] = $request->boolean('map_source_publishers') ? 1 : 0;
        $validated['activate_new_products'] = $request->boolean('activate_new_products') ? 1 : 0;
        $settingsService->save($validated);

        return redirect()->route('znanje-import.index', ['tab' => 'settings'])
            ->with('success', 'Postavke Znanje importa su spremljene.');
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
            $identifierSearch = preg_replace('/\D+/', '', $search);
            $query->where(function (Builder $query) use ($search, $identifierSearch) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('external_id', 'like', '%' . $search . '%')
                    ->orWhere('author', 'like', '%' . $search . '%');
                if ($identifierSearch !== '') {
                    $query->orWhere('isbn', 'like', '%' . $identifierSearch . '%')
                        ->orWhere('ean', 'like', '%' . $identifierSearch . '%');
                }
            });
        }

        $sourceCategory = trim((string) $request->input('source_category'));
        if (in_array($sourceCategory, self::SOURCE_CATEGORIES, true)) {
            $query->where('source_category', $sourceCategory);
        }

        $sourceGenre = trim((string) $request->input('source_genre'));
        if ($sourceGenre !== '' && mb_strlen($sourceGenre) <= 255) {
            $query->whereJsonContains('source_genres', $sourceGenre);
        }

        if (! in_array($status, ['all', 'missing'], true)) {
            $this->applyStatusFilter($query, $status);
        }
    }

    private function applyStatusFilter(Builder $query, string $status): void
    {
        if ($status === 'pending') {
            $query->whereNull('product_id')->where(function (Builder $query) {
                $query->whereNull('checked_source_hash')
                    ->orWhereColumn('checked_source_hash', '!=', 'source_hash');
            });
        } elseif ($status === 'new') {
            $query->whereNull('product_id')
                ->where('check_status', 'new')
                ->whereColumn('checked_source_hash', 'source_hash');
        } elseif ($status === 'existing') {
            $query->whereNotNull('product_id')
                ->whereNull('imported_at')
                ->whereNotIn('check_status', ['conflict', 'error']);
        } elseif ($status === 'imported') {
            $query->whereNotNull('imported_at')
                ->whereColumn('source_hash', 'imported_hash')
                ->whereNotIn('check_status', ['conflict', 'error']);
        } elseif ($status === 'changed') {
            $query->whereNotNull('imported_at')
                ->whereColumn('source_hash', '!=', 'imported_hash')
                ->whereNotIn('check_status', ['conflict', 'error']);
        } elseif (in_array($status, ['conflict', 'error'], true)) {
            $query->where('check_status', $status);
        }
    }

    private function statusCounts(): array
    {
        $counts = ZnanjeImportProduct::query()
            ->where('is_current', true)
            ->selectRaw('COUNT(*) AS all_count')
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
            'missing' => ZnanjeImportProduct::query()->where('is_current', false)->count(),
        ];
    }

    private function inspectionPendingQuery(): Builder
    {
        return ZnanjeImportProduct::query()
            ->where('is_current', true)
            ->where(function (Builder $query) {
                $query->whereNull('checked_source_hash')
                    ->orWhereColumn('checked_source_hash', '!=', 'source_hash');
            });
    }

    private function sourceGenreCountsByCategory(): array
    {
        return Cache::remember('znanje-import-source-genre-counts-by-category', now()->addMinutes(10), function () {
            $counts = array_fill_keys(self::SOURCE_CATEGORIES, []);
            ZnanjeImportProduct::query()
                ->where('is_current', true)
                ->whereIn('source_category', self::SOURCE_CATEGORIES)
                ->whereNotNull('source_genres')
                ->select(['id', 'source_category', 'source_genres'])
                ->orderBy('id')
                ->chunkById(500, function ($products) use (&$counts) {
                    foreach ($products as $product) {
                        $sourceCategory = trim((string) $product->source_category);
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

    private function sourceCategoryCounts(): array
    {
        return Cache::remember('znanje-import-source-category-counts', now()->addMinutes(10), function () {
            return ZnanjeImportProduct::query()
                ->where('is_current', true)
                ->whereIn('source_category', self::SOURCE_CATEGORIES)
                ->selectRaw('source_category, COUNT(*) AS aggregate')
                ->groupBy('source_category')
                ->pluck('aggregate', 'source_category')
                ->map(fn ($count) => (int) $count)
                ->all();
        });
    }

    private function normalizeSourceFilters(Request $request, array $sourceTaxonomy): void
    {
        $sourceCategory = trim((string) $request->query('source_category'));
        if ($sourceCategory !== '' && ! in_array($sourceCategory, self::SOURCE_CATEGORIES, true)) {
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

    private function feedMetadata(ZnanjeFeedService $feedService, int $count): array
    {
        $metadata = $feedService->metadata();
        $path = (string) ($metadata['path'] ?? '');
        $exists = ! empty($metadata['exists']) && $path !== '' && is_file($path);
        $modifiedAt = $metadata['synced_at'] ?? null;
        if (! $modifiedAt && $exists) {
            $modifiedAt = date('d.m.Y. H:i', (int) filemtime($path));
        }

        return array_merge($metadata, [
            'exists' => $exists,
            'count' => $exists ? (int) ($metadata['count'] ?? $count) : $count,
            'bytes' => $exists ? (int) filesize($path) : 0,
            'modified_at' => $modifiedAt ?: '—',
        ]);
    }

    private function retryableResponse(ZnanjeRetryableException $exception)
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

    private function clearSourceTaxonomyCaches(): void
    {
        Cache::forget('znanje-import-source-genre-counts-by-category');
        Cache::forget('znanje-import-source-category-counts');
    }

    private function refreshSuccessMessage(array $result): string
    {
        $message = sprintf(
            'Znanje feed je osvježen: %s knjiga, %s aktualnih, %s uklonjenih i %s nepotpunih preskočenih.',
            number_format((int) ($result['staged'] ?? 0), 0, ',', '.'),
            number_format((int) ($result['current'] ?? 0), 0, ',', '.'),
            number_format((int) ($result['retired_now'] ?? $result['retired'] ?? 0), 0, ',', '.'),
            number_format((int) ($result['skipped'] ?? 0), 0, ',', '.')
        );
        if (! empty($result['snapshot_warning'])) {
            $message .= ' Upozorenje: ' . trim((string) $result['snapshot_warning']);
        }

        return $message;
    }
}
