<?php

namespace App\Http\Controllers\Back\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Back\Catalog\Category;
use App\Models\Back\Catalog\NovellaImportProduct;
use App\Models\Back\Catalog\Publisher;
use App\Services\Catalog\CachedNewImportProductReconciler;
use App\Services\Catalog\ImportFilterMemory;
use App\Services\Novella\NovellaFeedService;
use App\Services\Novella\NovellaFeedSynchronizer;
use App\Services\Novella\NovellaImportService;
use App\Services\Novella\NovellaImportSettings;
use App\Services\Novella\NovellaPriceCalculator;
use App\Services\Novella\NovellaRetryableException;
use App\Services\Novella\NovellaTerminalException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class NovellaImportController extends Controller
{
    private const SOURCE_CATEGORY = 'Knjige';

    public function index(
        Request $request,
        NovellaImportSettings $settingsService,
        NovellaFeedService $feedService,
        NovellaPriceCalculator $priceCalculator,
        CachedNewImportProductReconciler $catalogReconciler,
        ImportFilterMemory $filterMemory
    ) {
        if ($filterMemory->restore($request, 'novella')) {
            return redirect()->route('novella-import.index');
        }

        $settings = $settingsService->all();
        $sourceGenres = $this->sourceGenreCounts();
        foreach (array_keys((array) ($settings['category_map'] ?? [])) as $sourceGenre) {
            if (! array_key_exists($sourceGenre, $sourceGenres)) {
                $sourceGenres[$sourceGenre] = 0;
            }
        }
        uksort($sourceGenres, 'strnatcasecmp');
        $sourceTaxonomy = [self::SOURCE_CATEGORY => array_keys($sourceGenres)];
        $this->normalizeSourceFilters($request, $sourceTaxonomy);
        $filterMemory->remember($request, 'novella');

        $query = NovellaImportProduct::query()
            ->with('product:id,name,sku,itemid,isbn,price,quantity');
        $this->applyFilters($query, $request);

        // The API is synchronized oldest-first to make pagination stable, while
        // the admin list intentionally presents the newest Novella titles first.
        $paginate = function () use ($query, $request) {
            return (clone $query)->orderByDesc('is_current')
                ->orderByRaw('CASE WHEN feed_position IS NULL THEN 1 ELSE 0 END')
                ->orderByDesc('feed_position')
                ->orderByDesc('remote_product_id')
                ->orderByDesc('id')
                ->paginate(40)
                ->appends($request->query());
        };
        $products = $paginate();
        $products->setCollection($catalogReconciler->reconcile($products->getCollection()));

        // Reconciliation can change a cached "new" row after the SQL page was
        // selected. Reload once so the paginator, total and rendered rows all
        // reflect the active Novi filter without an unbounded reconciliation loop.
        $selectedStatus = trim((string) $request->input('status', 'new')) ?: 'new';
        if ($selectedStatus === 'new'
            && $products->getCollection()->contains(fn ($product) => $product->ui_status !== 'new')) {
            $products = $paginate();
        }

        $statusCounts = $this->statusCounts();
        $inspectionPendingCount = $this->inspectionPendingQuery()->count();
        $categories = (new Category())->getList(false);
        $publishers = Publisher::query()->orderBy('title')->get(['id', 'title']);
        $feedMetadata = $this->feedMetadata($feedService, $statusCounts['all']);
        $importUi = [
            'name' => 'Novella',
            'slug' => 'novella',
            'route_prefix' => 'novella-import',
            'route_parameter' => 'novellaImportProduct',
            'config_key' => 'novella_import',
            'source_site' => 'Novella.hr',
            'subtitle' => 'Inkrementalni uvoz knjiga iz Novella API-ja s provjerom ISBN-a, autora i naslova',
            'source_id_label' => 'Novella ID',
            'source_id_field' => 'remote_product_id',
            'secondary_source_id_label' => null,
            'allowed_categories_label' => 'Samo kategorija Knjige',
            'required_mapping_label' => 'Nakladnici › Novella',
            'publisher_category_label' => 'Novella podkategorija',
            'default_publisher_label' => 'Novella',
            'supports_source_mapping' => false,
            'supports_source_publisher_mapping' => false,
            'supports_source_taxonomy_mapping' => true,
            'supports_source_category_filter' => true,
            'supports_translation' => false,
            'uses_exchange_rate' => false,
            'source_price_field' => 'price_eur',
            'source_sale_price_field' => 'sale_price_eur',
            'source_currency' => 'EUR',
            'price_preview_source_amount' => 15,
            'feed_link_label' => 'Otvori API',
            'feed_url_config_key' => 'products_api_url',
            'source_category_label' => 'Novella kategorija',
            'source_subcategory_label' => 'Novella podkategorija',
            'source_filter_help' => 'Podkategorije dolaze iz Novella API-ja i dostupne su odmah nakon osvježavanja feeda.',
            'source_taxonomy_label' => 'Novella',
            'source_taxonomy_item_label' => 'podkategorija',
            'source_taxonomy_items_label' => 'podkategorija',
            'inspection_workers' => 2,
            'inspection_delay_ms' => 250,
            'bulk_inspection_route' => null,
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

    public function refresh(NovellaFeedSynchronizer $synchronizer)
    {
        $lock = Cache::lock('novella-import-refresh', 600);
        if (! $lock->get()) {
            return redirect()->route('novella-import.index')
                ->with('error', 'Osvježavanje Novella feeda već je u tijeku.');
        }

        try {
            if (function_exists('set_time_limit')) {
                @set_time_limit(600);
            }

            $result = $synchronizer->refresh();
            Cache::forget('novella-import-source-genre-counts');

            $message = sprintf(
                'Novella feed je osvježen: %s knjiga, %s aktualnih, %s uklonjenih i %s nepotpunih preskočenih.',
                number_format((int) ($result['staged'] ?? 0), 0, ',', '.'),
                number_format((int) ($result['current'] ?? 0), 0, ',', '.'),
                number_format((int) ($result['retired'] ?? 0), 0, ',', '.'),
                number_format((int) ($result['skipped'] ?? 0), 0, ',', '.')
            );
            if (! empty($result['snapshot_warning'])) {
                $message .= ' Upozorenje: ' . trim((string) $result['snapshot_warning']);
            }

            return redirect()->route('novella-import.index')
                ->with('success', $message);
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('novella-import.index')
                ->with('error', $exception->getMessage());
        } finally {
            optional($lock)->release();
        }
    }

    public function inspect(
        Request $request,
        NovellaImportProduct $novellaImportProduct,
        NovellaImportService $importService
    ) {
        $lock = Cache::lock('novella-import-source-' . $novellaImportProduct->id, 120);
        if (! $lock->get()) {
            return response()->json([
                'success' => false,
                'message' => 'Ovu knjigu već provjerava drugi proces.',
            ], 409);
        }

        try {
            $source = $importService->inspect(
                $novellaImportProduct,
                ! $request->boolean('only_if_pending')
            );
            Cache::forget('novella-import-source-genre-counts');

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
        } catch (NovellaRetryableException $exception) {
            report($exception);

            return $this->retryableResponse($exception);
        } catch (NovellaTerminalException $exception) {
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
        $limit = min(max((int) $request->input('limit', 20), 1), 50);
        $query = $this->inspectionPendingQuery();
        $remaining = (clone $query)->count();
        $items = $query
            ->orderByRaw('CASE WHEN feed_position IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('feed_position')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'name'])
            ->map(function (NovellaImportProduct $product) {
                return ['id' => (int) $product->id, 'name' => $product->name];
            })
            ->values();

        return response()->json([
            'remaining' => $remaining,
            'items' => $items,
        ]);
    }

    public function import(
        Request $request,
        NovellaImportProduct $novellaImportProduct,
        NovellaImportService $importService
    ) {
        $lock = Cache::lock('novella-import-source-' . $novellaImportProduct->id, 300);
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

            $result = $importService->import($novellaImportProduct, $categoryId);
            $source = $novellaImportProduct->fresh(['product']);

            return response()->json([
                'success' => true,
            ] + $result + [
                'status' => $source->ui_status,
                'check_message' => $source->check_message,
                'product_url' => $source->product_id
                    ? route('products.edit', ['product' => $source->product_id])
                    : null,
            ]);
        } catch (NovellaRetryableException $exception) {
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

    public function updateSettings(Request $request, NovellaImportSettings $settingsService)
    {
        $validated = $request->validate([
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
        if (! $publisherCategory
            || (int) $publisherCategory->parent_id !== (int) $parentCategory->id) {
            return redirect()->back()->withInput()->withErrors([
                'publisher_category_id' => 'Kategorija Novella mora biti podkategorija odabrane kategorije Nakladnici.',
            ]);
        }

        $categoryMap = [];
        foreach (($validated['source_genres'] ?? []) as $index => $sourceGenre) {
            $categoryId = (int) (($validated['genre_category_ids'] ?? [])[$index] ?? 0);
            $sourceGenre = trim($sourceGenre);
            if ($sourceGenre !== '' && $categoryId > 0) {
                $categoryMap[$sourceGenre] = $categoryId;
            }
        }

        unset($validated['source_genres'], $validated['genre_category_ids']);
        $validated['category_map'] = $categoryMap;
        $validated['map_source_publishers'] = 0;
        $validated['activate_new_products'] = $request->boolean('activate_new_products') ? 1 : 0;
        $settingsService->save($validated);

        return redirect()->route('novella-import.index', ['tab' => 'settings'])
            ->with('success', 'Postavke Novella importa su spremljene.');
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
                    ->orWhere('sku', 'like', '%' . $search . '%')
                    ->orWhere('author', 'like', '%' . $search . '%');
                if ($identifierSearch !== '') {
                    $query->orWhere('isbn', 'like', '%' . $identifierSearch . '%')
                        ->orWhere('ean', 'like', '%' . $identifierSearch . '%');
                }
            });
        }

        $sourceCategory = trim((string) $request->input('source_category'));
        if ($sourceCategory === self::SOURCE_CATEGORY) {
            $query->where('source_category', self::SOURCE_CATEGORY);
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
        $counts = NovellaImportProduct::query()
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
            'missing' => NovellaImportProduct::query()->where('is_current', false)->count(),
        ];
    }

    private function inspectionPendingQuery(): Builder
    {
        return NovellaImportProduct::query()
            ->where('is_current', true)
            ->where(function (Builder $query) {
                $query->whereNull('checked_source_hash')
                    ->orWhereColumn('checked_source_hash', '!=', 'source_hash');
            });
    }

    private function sourceGenreCounts(): array
    {
        return Cache::remember('novella-import-source-genre-counts', now()->addMinutes(10), function () {
            $counts = [];
            NovellaImportProduct::query()
                ->where('is_current', true)
                ->whereNotNull('source_genres')
                ->select(['id', 'source_genres'])
                ->orderBy('id')
                ->chunkById(200, function ($products) use (&$counts) {
                    foreach ($products as $product) {
                        foreach ((array) $product->source_genres as $sourceGenre) {
                            $sourceGenre = trim((string) $sourceGenre);
                            if ($sourceGenre !== '') {
                                $counts[$sourceGenre] = ($counts[$sourceGenre] ?? 0) + 1;
                            }
                        }
                    }
                });
            uksort($counts, 'strnatcasecmp');

            return $counts;
        });
    }

    private function normalizeSourceFilters(Request $request, array $sourceTaxonomy): void
    {
        $sourceCategory = trim((string) $request->query('source_category'));
        if ($sourceCategory !== '' && $sourceCategory !== self::SOURCE_CATEGORY) {
            $request->query->remove('source_category');
            $sourceCategory = '';
        }

        $sourceGenre = trim((string) $request->query('source_genre'));
        if ($sourceGenre !== ''
            && ! in_array($sourceGenre, (array) ($sourceTaxonomy[self::SOURCE_CATEGORY] ?? []), true)) {
            $request->query->remove('source_genre');
        }
    }

    private function feedMetadata(NovellaFeedService $feedService, int $count): array
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
            'count' => $count,
            'bytes' => $exists ? (int) filesize($path) : 0,
            'modified_at' => $modifiedAt ?: '—',
        ]);
    }

    private function retryableResponse(NovellaRetryableException $exception)
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
}
