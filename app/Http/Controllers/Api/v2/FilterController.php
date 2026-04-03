<?php

namespace App\Http\Controllers\Api\v2;

use App\Helpers\Helper;
use App\Models\Back\Settings\Settings;
use App\Models\Front\Catalog\Author;
use App\Models\Front\Catalog\Category;
use App\Models\Front\Catalog\Product;
use App\Models\Front\Catalog\Publisher;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FilterController extends Controller
{

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function categories(Request $request)
    {
        if ( ! $request->input('params')) {
            return response()->json(['status' => 300, 'message' => 'Error!']);
        }

        $response = [];
        $params = $request->input('params');

        // Ako je normal kategorija
        if ($params['group']) {
            $response = Helper::resolveCache('categories')->remember($params['group'], config('cache.life'), function () use ($params) {
                $response = Category::query()
                    ->active()
                    ->topList($params['group'])
                    ->sortByName()
                    ->select('id', 'title', 'slug', 'group')
                    ->with(['subcategories' => function ($query) {
                        $query->select('id', 'parent_id', 'title', 'slug', 'group');
                    }])
                    ->get()
                    ->toArray();

                return $this->resolveCategoryArray($response, 'categories');
            });
        }

        // Ako su posebni ID artikala.
        if ($params['ids'] && $params['ids'] != '[]') {
            $_ids = collect(explode(',', substr($params['ids'], 1, -1)))->unique();

            $categories = Category::active()->whereHas('products', function ($query) use ($_ids) {
                $query->active()->hasStock()->whereIn('id', $_ids);
            })->sortByName()->withCount('products')->get()->toArray();

            $response = $this->resolveCategoryArray($categories, 'categories');
        }

        return response()->json($response);
    }


    /**
     * @param             $categories
     * @param string      $type
     * @param null        $target
     * @param string|null $parent_slug
     *
     * @return array
     */
    private function resolveCategoryArray($categories, string $type, $target = null, string $parent_slug = null): array
    {
        $response = [];

        foreach ($categories as $category) {
            $url = $this->resolveCategoryUrl($category, $type, $target, $parent_slug);
            $subs = null;

            if (isset($category['subcategories']) && ! empty($category['subcategories'])) {
                foreach ($category['subcategories'] as $subcategory) {
                    $sub_url = $this->resolveCategoryUrl($subcategory, $type, $target, $category['slug']);

                    $subs[] = [
                        'id' => $subcategory['id'],
                        'title' => $subcategory['title'],
                        'count' => 0,//Category::find($subcategory['id'])->products()->count(),
                        'url' => $sub_url
                    ];
                }
            }

            $response[] = [
                'id' => $category['id'],
                'title' => $category['title'],
                'count' => $category['products_count'] ?? 0,
                'url' => $url,
                'subs' => $subs
            ];


        }

        return $response;
    }


    /**
     * @param             $category
     * @param string      $type
     * @param             $target
     * @param string|null $parent_slug
     *
     * @return string
     */
    private function resolveCategoryUrl($category, string $type, $target, string $parent_slug = null): string
    {
        if ($type == 'author') {
            return route('catalog.route.author', [
                'author' => $target,
                'cat' => $parent_slug ?: $category['slug'],
                'subcat' => $parent_slug ? $category['slug'] : null
            ]);

        } elseif ($type == 'publisher') {
            return route('catalog.route.publisher', [
                'publisher' => $target,
                'cat' => $parent_slug ?: $category['slug'],
                'subcat' => $parent_slug ? $category['slug'] : null
            ]);

        } else {
            return route('catalog.route', [
                'group' => Str::slug($category['group']),
                'cat' => $parent_slug ?: $category['slug'],
                'subcat' => $parent_slug ? $category['slug'] : null
            ]);
        }
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function products(Request $request)
    {
        if ( ! $request->has('params')) {
            return response()->json(['status' => 300, 'message' => 'Error!']);
        }

        $params = $request->input('params');
        $cache_string = '';
        $request_data = $this->buildProductRequestData($params);

        foreach (['ids', 'group', 'cat', 'subcat', 'start', 'end', 'condition', 'binding', 'letter', 'sort'] as $key) {
            if (isset($params[$key]) && $params[$key] !== '') {
                $cache_string .= $key . '=' . $params[$key];
            }
        }

        if (isset($request_data['autor'])) {
            $cache_string .= 'autor=' . collect($request_data['autor'])->pluck('slug')->implode(',');
        }

        if (isset($request_data['nakladnik'])) {
            $cache_string .= 'nakladnik=' . collect($request_data['nakladnik'])->pluck('slug')->implode(',');
        }

        $request_data['page'] = $request->input('page');

        $cache_string .= 'page=' . $request_data['page'];
        $cache_string = md5($cache_string);

        $request = new Request($request_data);

        if (isset($params['ids']) && $params['ids'] != '') {
            $products = (new Product())->filter($request)
                                       ->with(['author', 'action'])
                                       ->paginate(config('settings.pagination.front'));
        } else {

            $products = Helper::resolveCache('products')->remember($cache_string, config('cache.life'), function () use ($request) {
                $products = (new Product())->filter($request)
                                           ->with(['author', 'action'])
                                           ->paginate(config('settings.pagination.front'));
                return $products;

                /*return (new Product())->filter($request)
                                      ->with('author')
                                      ->paginate(config('settings.pagination.front'), ['*'], 'page', $request->input('page'));*/
            });

            /*$products = (new Product())->filter($request)
                                       ->with('author')
                                       ->paginate(config('settings.pagination.front'));*/
        }

        return response()->json($products);
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function toolbarFilters(Request $request)
    {
        if ( ! $request->has('params')) {
            return response()->json([
                'authors' => [],
                'conditions' => [],
                'bindings' => [],
                'letters' => [],
            ]);
        }

        $params = $request->input('params');
        $cache_key = 'toolbar-filters-' . md5(json_encode($this->normalizeToolbarCacheParams($params)));

        $response = Helper::resolveCache('products')->remember($cache_key, config('cache.life'), function () use ($params) {
            return [
                'authors' => $this->resolveAuthorsForToolbar(
                    $this->resolveToolbarBaseQuery($params, ['autor', 'sort'])
                ),
                'conditions' => $this->resolveDistinctProductValues(
                    $this->resolveToolbarBaseQuery($params, ['condition', 'sort']),
                    'condition',
                    Settings::get('product', 'condition_styles')->filter()->values()->all()
                ),
                'bindings' => $this->resolveDistinctProductValues(
                    $this->resolveToolbarBaseQuery($params, ['binding', 'sort']),
                    'binding',
                    Settings::get('product', 'binding_styles')->filter()->values()->all()
                ),
                'letters' => $this->resolveDistinctProductValues(
                    $this->resolveToolbarBaseQuery($params, ['letter', 'sort']),
                    'letter',
                    ['Latinica', 'Ćirilica', 'Gotica', 'Arapsko', 'Glagoljica']
                ),
            ];
        });

        return response()->json($response);
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function authors(Request $request)
    {
        if ($request->has('params')) {
            return response()->json(
                (new Author())->filter($request->input('params'))
                              ->get()
                              ->toArray()
            );
        }

        return response()->json(
            Helper::resolveCache('authors')->remember('featured', config('cache.life'), function () {
                return Author::query()->active()
                             ->featured()
                             ->basicData()
                             ->withCount('products')
                             ->get()
                             ->toArray();
            })
        );
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function publishers(Request $request)
    {
        if ($request->has('params')) {
            return response()->json(
                (new Publisher())->filter($request->input('params'))
                                 ->basicData()
                                 ->withCount('products')
                                 ->get()
                                 ->toArray()
            );
        }

        return response()->json(
            Helper::resolveCache('publishers')->remember('featured', config('cache.life'), function () {
                return Publisher::active()
                                ->featured()
                                ->basicData()
                                ->withCount('products')
                                ->get()
                                ->toArray();
            })
        );
    }


    /**
     * @param array $params
     * @param array $except
     *
     * @return array
     */
    private function buildProductRequestData(array $params, array $except = []): array
    {
        $request_data = [];

        foreach (['ids', 'group', 'cat', 'subcat', 'start', 'end', 'condition', 'binding', 'letter', 'sort'] as $key) {
            if (in_array($key, $except, true)) {
                continue;
            }

            if (isset($params[$key]) && $params[$key] !== '') {
                $request_data[$key] = $params[$key];
            }
        }

        if ( ! in_array('autor', $except, true) && isset($params['autor']) && $params['autor']) {
            $authors = $this->resolveModelsFromSlugString($params['autor'], Author::class);

            if ($authors->isNotEmpty()) {
                $request_data['autor'] = $authors->all();
            }
        }

        if ( ! in_array('nakladnik', $except, true) && isset($params['nakladnik']) && $params['nakladnik']) {
            $publishers = $this->resolveModelsFromSlugString($params['nakladnik'], Publisher::class);

            if ($publishers->isNotEmpty()) {
                $request_data['nakladnik'] = $publishers->all();
            }
        }

        return $request_data;
    }


    /**
     * @param string|null $value
     * @param string $modelClass
     *
     * @return Collection
     */
    private function resolveModelsFromSlugString(?string $value, string $modelClass): Collection
    {
        if ( ! $value) {
            return collect();
        }

        $slugs = collect(explode('+', $value))
            ->filter()
            ->unique()
            ->values();

        if ($slugs->isEmpty()) {
            return collect();
        }

        return $modelClass::query()
            ->whereIn('slug', $slugs)
            ->get()
            ->sortBy(function ($item) use ($slugs) {
                return $slugs->search($item->slug);
            })
            ->values();
    }


    /**
     * @param Builder $query
     * @param string $column
     * @param array $allowed_values
     *
     * @return array
     */
    private function resolveDistinctProductValues(Builder $query, string $column, array $allowed_values = []): array
    {
        $values = (clone $query)
            ->select($column)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->pluck($column)
            ->map(function ($value) {
                return trim((string) $value);
            })
            ->filter()
            ->values();

        if ( ! empty($allowed_values)) {
            return $this->resolveAllowedFacetValues($values, $allowed_values);
        }

        return $values
            ->unique(function ($value) {
                return $this->normalizeFacetValue($value);
            })
            ->sortBy(function ($value) {
                return $this->normalizeFacetValue($value);
            })
            ->values()
            ->all();
    }


    /**
     * @param Builder $query
     *
     * @return array
     */
    private function resolveAuthorsForToolbar(Builder $query): array
    {
        $author_ids = (clone $query)
            ->select('author_id')
            ->where('author_id', '>', 0)
            ->distinct()
            ->pluck('author_id')
            ->filter()
            ->values();

        if ($author_ids->isEmpty()) {
            return [];
        }

        return Author::query()
            ->active()
            ->whereIn('id', $author_ids)
            ->whereNotNull('title')
            ->where('title', '!=', '')
            ->orderBy('title')
            ->get(['id', 'title', 'slug'])
            ->values()
            ->toArray();
    }


    /**
     * @param array $params
     * @param array $except
     *
     * @return Builder
     */
    private function resolveToolbarBaseQuery(array $params, array $except = ['sort']): Builder
    {
        return (new Product())
            ->filter(new Request($this->buildProductRequestData($params, $except)))
            ->reorder();
    }


    /**
     * @param array $params
     *
     * @return array
     */
    private function normalizeToolbarCacheParams(array $params): array
    {
        return [
            'ids' => (string) ($params['ids'] ?? ''),
            'group' => (string) ($params['group'] ?? ''),
            'cat' => (string) ($params['cat'] ?? ''),
            'subcat' => (string) ($params['subcat'] ?? ''),
            'autor' => $this->normalizeSlugString($params['autor'] ?? ''),
            'nakladnik' => $this->normalizeSlugString($params['nakladnik'] ?? ''),
            'start' => (string) ($params['start'] ?? ''),
            'end' => (string) ($params['end'] ?? ''),
            'condition' => (string) ($params['condition'] ?? ''),
            'binding' => (string) ($params['binding'] ?? ''),
            'letter' => (string) ($params['letter'] ?? ''),
        ];
    }


    /**
     * @param string|null $value
     *
     * @return array
     */
    private function normalizeSlugString(?string $value): array
    {
        return collect(explode('+', (string) $value))
            ->map(function ($item) {
                return trim((string) $item);
            })
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }


    /**
     * @param Collection $values
     * @param array $allowed_values
     *
     * @return array
     */
    private function resolveAllowedFacetValues(Collection $values, array $allowed_values): array
    {
        $normalized_values = $values
            ->unique(function ($value) {
                return $this->normalizeFacetValue($value);
            })
            ->keyBy(function ($value) {
                return $this->normalizeFacetValue($value);
            });

        return collect($allowed_values)
            ->map(function ($value) {
                return trim((string) $value);
            })
            ->filter()
            ->unique()
            ->filter(function ($value) use ($normalized_values) {
                return $normalized_values->has($this->normalizeFacetValue($value));
            })
            ->values()
            ->all();
    }


    /**
     * @param string|null $value
     *
     * @return string
     */
    private function normalizeFacetValue(?string $value): string
    {
        return Str::lower(Str::ascii(trim((string) $value)));
    }

}
