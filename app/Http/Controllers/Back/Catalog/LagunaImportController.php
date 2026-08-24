<?php

namespace App\Http\Controllers\Back\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Back\Catalog\Category;
use App\Models\Back\Catalog\LagunaImportProduct;
use App\Models\Back\Catalog\Publisher;
use App\Services\Laguna\LagunaFeedService;
use App\Services\Laguna\LagunaFeedSynchronizer;
use App\Services\Laguna\LagunaImportService;
use App\Services\Laguna\LagunaImportSettings;
use App\Services\Laguna\LagunaPriceCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LagunaImportController extends Controller
{
    public function index(
        Request $request,
        LagunaImportSettings $settingsService,
        LagunaFeedService $feedService,
        LagunaPriceCalculator $priceCalculator
    ) {
        $settings = $settingsService->all();
        $query = LagunaImportProduct::query()->with('product:id,name,sku,itemid,isbn,price,quantity');
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
        $publisherIds = collect([$settings['publisher_id']])->filter();
        $publishers = Publisher::query()
            ->where(function ($query) use ($publisherIds) {
                $query->where('title', 'like', '%Laguna%');
                if ($publisherIds->isNotEmpty()) {
                    $query->orWhereIn('id', $publisherIds);
                }
            })
            ->orderBy('title')
            ->get(['id', 'title']);
        $feedMetadata = $feedService->metadata();
        $feedMetadata['count'] = $statusCounts['all'];

        return view('back.catalog.laguna-import.index', compact(
            'products',
            'settings',
            'statusCounts',
            'inspectionPendingCount',
            'categories',
            'publishers',
            'feedMetadata',
            'priceCalculator'
        ));
    }

    public function refresh(LagunaFeedSynchronizer $synchronizer)
    {
        $lock = Cache::lock('laguna-import-refresh', 300);
        if (! $lock->get()) {
            return redirect()->route('laguna-import.index')
                ->with('error', 'Osvježavanje Laguna feeda već je u tijeku.');
        }

        try {
            $result = $synchronizer->refresh();

            return redirect()->route('laguna-import.index')
                ->with('success', sprintf(
                    'Laguna feed je osvježen: %d knjiga, %d aktualnih, %d uklonjenih i %d nepotpunih preskočenih.',
                    $result['staged'],
                    $result['current'],
                    $result['retired'],
                    $result['skipped']
                ));
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('laguna-import.index')
                ->with('error', $exception->getMessage());
        } finally {
            optional($lock)->release();
        }
    }

    public function inspect(
        Request $request,
        LagunaImportProduct $lagunaImportProduct,
        LagunaImportService $importService
    )
    {
        $lock = Cache::lock('laguna-import-inspect-' . $lagunaImportProduct->id, 60);
        if (! $lock->get()) {
            return response()->json([
                'success' => false,
                'message' => 'Ovu knjigu već provjerava drugi proces.',
            ], 409);
        }

        try {
            $source = $importService->inspect($lagunaImportProduct, ! $request->boolean('only_if_pending'));

            return response()->json([
                'success' => true,
                'status' => $source->ui_status,
                'message' => $source->check_message,
                'product_id' => $source->product_id,
                'isbn' => $source->isbn,
            ]);
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

    public function inspectionQueue(Request $request)
    {
        $limit = min(max((int) $request->input('limit', 20), 1), 50);
        $query = $this->inspectionPendingQuery();

        $remaining = (clone $query)->count();
        $items = $query
            ->orderByRaw('CASE WHEN feed_position IS NULL THEN 1 ELSE 0 END')
            ->orderBy('feed_position')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'name'])
            ->map(function (LagunaImportProduct $product) {
                return [
                    'id' => (int) $product->id,
                    'name' => $product->name,
                ];
            })
            ->values();

        return response()->json([
            'remaining' => $remaining,
            'items' => $items,
        ]);
    }

    public function import(
        Request $request,
        LagunaImportProduct $lagunaImportProduct,
        LagunaImportService $importService
    )
    {
        try {
            $validated = $request->validate([
                'category_id' => 'nullable|integer|exists:categories,id',
            ]);

            $categoryId = isset($validated['category_id']) ? (int) $validated['category_id'] : null;

            return response()->json([
                'success' => true,
            ] + $importService->import($lagunaImportProduct, $categoryId));
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function updateSettings(Request $request, LagunaImportSettings $settingsService)
    {
        $validated = $request->validate([
            'exchange_rate' => 'required|numeric|min:0.0001|max:100000',
            'markup_percent' => 'required|numeric|min:0|max:1000',
            'publisher_parent_category_id' => 'required|integer|exists:categories,id',
            'publisher_category_id' => 'required|integer|exists:categories,id',
            'publisher_id' => 'required|integer|exists:publishers,id',
            'default_quantity' => 'required|integer|min:0|max:100000',
            'existing_action' => 'required|in:skip,price_stock',
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
                'publisher_category_id' => 'Kategorija Laguna mora biti podkategorija odabrane kategorije Nakladnici.',
            ]);
        }

        $validated['activate_new_products'] = $request->boolean('activate_new_products') ? 1 : 0;
        $settingsService->save($validated);

        return redirect()->route('laguna-import.index', ['tab' => 'settings'])
            ->with('success', 'Postavke Laguna importa su spremljene.');
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
                    ->orWhere('external_id', 'like', '%' . $search . '%');

                if ($isbnSearch !== '') {
                    $query->orWhere('isbn', 'like', '%' . $isbnSearch . '%');
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
            $query->whereNull('product_id')
                ->where(function (Builder $query) {
                    $query->whereNull('checked_source_hash')
                        ->orWhereColumn('checked_source_hash', '!=', 'source_hash');
                });
        } elseif ($status === 'new') {
            $query->whereNull('product_id')->where('check_status', 'new')->whereColumn('checked_source_hash', 'source_hash');
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
        $base = LagunaImportProduct::query()->where('is_current', true);

        return [
            'all' => (clone $base)->count(),
            'pending' => (clone $base)->whereNull('product_id')->where(function (Builder $query) {
                $query->whereNull('checked_source_hash')->orWhereColumn('checked_source_hash', '!=', 'source_hash');
            })->count(),
            'new' => (clone $base)->whereNull('product_id')->where('check_status', 'new')->whereColumn('checked_source_hash', 'source_hash')->count(),
            'existing' => (clone $base)->whereNotNull('product_id')->whereNull('imported_at')->whereNotIn('check_status', ['conflict', 'error'])->count(),
            'imported' => (clone $base)->whereNotNull('imported_at')->whereColumn('source_hash', 'imported_hash')->whereNotIn('check_status', ['conflict', 'error'])->count(),
            'changed' => (clone $base)->whereNotNull('imported_at')->whereColumn('source_hash', '!=', 'imported_hash')->whereNotIn('check_status', ['conflict', 'error'])->count(),
            'conflict' => (clone $base)->where('check_status', 'conflict')->count(),
            'error' => (clone $base)->where('check_status', 'error')->count(),
            'missing' => LagunaImportProduct::query()->where('is_current', false)->count(),
        ];
    }

    private function inspectionPendingQuery(): Builder
    {
        return LagunaImportProduct::query()
            ->where('is_current', true)
            ->where(function (Builder $query) {
                $query->whereNull('checked_source_hash')
                    ->orWhereColumn('checked_source_hash', '!=', 'source_hash');
            });
    }
}
