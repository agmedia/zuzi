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
        $sourceGenres = $this->sourceGenreCounts();
        foreach ($taxonomyService->bookGenres() as $genre) {
            if (! array_key_exists($genre, $sourceGenres)) {
                $sourceGenres[$genre] = 0;
            }
        }
        foreach (array_keys((array) ($settings['genre_category_map'] ?? [])) as $genre) {
            if (! array_key_exists($genre, $sourceGenres)) {
                $sourceGenres[$genre] = 0;
            }
        }
        uksort($sourceGenres, 'strnatcasecmp');
        $importUi = [
            'name' => 'Delfi',
            'slug' => 'delfi',
            'route_prefix' => 'delfi-import',
            'route_parameter' => 'delfiImportProduct',
            'config_key' => 'delfi_import',
            'source_site' => 'Delfi.rs',
            'subtitle' => 'Inkrementalni uvoz knjiga s provjerom ISBN-a, autora i naslova te prijevodom opisa na hrvatski',
            'source_id_label' => 'Delfi šifra',
            'allowed_categories_label' => 'Samo Knjiga i Strana knjiga',
            'required_mapping_label' => 'Nakladnici › zadani ili prepoznati nakladnik',
            'publisher_category_label' => 'Rezervna podkategorija nakladnika',
            'default_publisher_label' => 'Delfi (koristi se kad izvorni nakladnik nije mapiran)',
            'supports_source_mapping' => true,
            'inspection_workers' => 1,
            'inspection_delay_ms' => 500,
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
            Cache::forget('delfi-import-source-genre-counts');

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

    private function sourceGenreCounts(): array
    {
        return Cache::remember('delfi-import-source-genre-counts', now()->addMinutes(10), function () {
            $counts = [];
            DelfiImportProduct::query()
                ->whereNotNull('source_genres')
                ->select(['id', 'source_genres'])
                ->orderBy('id')
                ->chunkById(500, function ($products) use (&$counts) {
                    foreach ($products as $product) {
                        foreach ((array) $product->source_genres as $genre) {
                            $genre = trim((string) $genre);
                            if ($genre !== '') {
                                $counts[$genre] = ($counts[$genre] ?? 0) + 1;
                            }
                        }
                    }
                });
            uksort($counts, 'strnatcasecmp');

            return $counts;
        });
    }
}
