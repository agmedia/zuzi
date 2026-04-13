<?php

namespace App\Http\Controllers\Front;

use App\Helpers\Breadcrumb;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Imports\ProductImport;
use App\Models\Back\Marketing\Action as MarketingAction;
use App\Models\Back\Settings\Settings;
use App\Models\Front\Blog;
use App\Models\Front\Page;
use App\Models\Front\Faq;
use App\Models\Front\Catalog\Author;
use App\Models\Front\Catalog\Category;
use App\Models\Front\Catalog\CategoryProducts;
use App\Models\Front\Catalog\Product;
use App\Models\Front\Catalog\Publisher;
use App\Models\Seo;
use App\Models\TagManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CatalogRouteController extends Controller
{

    /**
     * Resolver for the Groups, categories and products routes.
     * Route::get('{group}/{cat?}/{subcat?}/{prod?}', 'Front\GCP_RouteController::resolve()')->name('gcp_route');
     *
     * @param               $group
     * @param Category|null $cat
     * @param Category|null $subcat
     * @param Product|null  $prod
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function resolve(Request $request, $group, ?Category $cat = null, ?string $subcat = null, ?Product $prod = null)
    {
        //
        if ($subcat) {
            $sub_category = Category::where('slug', $subcat)->where('parent_id', $cat->id)->first();

            if ( ! $sub_category) {
                $prod = Product::where('slug', $subcat)->first();
            }

            $subcat = $sub_category;
        }

        // Check if there is Product set.
        if ($prod) {
            if ( ! $prod->status) {
                abort(404);
            }

            DB::table('products')
                ->where('id', $prod->id)
                ->increment('viewed');

            $seo = Seo::getProductData($prod);
            $gdl = TagManager::getGoogleProductDataLayer($prod);
            $reviews = $prod->reviews()->get();

            $prod->kat = CategoryProducts::where('product_id', $prod->id)->where('category_id', 109)->first();

            $bc = new Breadcrumb();
            $crumbs = $bc->product($group, $cat, $subcat, $prod)->resolve();
            $bookscheme = $bc->productBookSchema($prod, $cat, $subcat);
            $shipping_methods = Settings::getList('shipping', 'list.%', true);
            $payment_methods = Settings::getList('payment', 'list.%', true);
            $authorProducts = collect();
            $publisherProducts = collect();
            $relatedProducts = collect();

            $prod->kat = CategoryProducts::where('product_id', $prod->id)->where('category_id', 109)->first();

            if ($prod->author_id) {
                $authorProducts = Product::query()
                    ->active()
                    ->hasStock()
                    ->with(['author', 'action'])
                    ->where('author_id', $prod->author_id)
                    ->where('id', '!=', $prod->id)
                    ->inRandomOrder()
                    ->take(8)
                    ->get();
            }

            $hasKnownPublisher = $prod->publisher_id
                && $prod->publisher
                && Str::lower(trim((string) $prod->publisher->title)) !== 'nepoznati izdavač';

            if ($hasKnownPublisher) {
                $publisherProducts = Product::query()
                    ->active()
                    ->hasStock()
                    ->with(['author', 'action'])
                    ->where('publisher_id', $prod->publisher_id)
                    ->where('id', '!=', $prod->id)
                    ->inRandomOrder()
                    ->take(8)
                    ->get();
            }

            $relatedIds = collect(Helper::getRelated($cat, $subcat))
                ->filter()
                ->reject(fn ($item) => $item->id == $prod->id)
                ->pluck('id')
                ->unique()
                ->take(8)
                ->values();

            if ($relatedIds->count()) {
                $relatedProducts = Product::query()
                    ->active()
                    ->hasStock()
                    ->with(['author', 'action'])
                    ->whereIn('id', $relatedIds)
                    ->get()
                    ->sortBy(fn ($item) => $relatedIds->search($item->id))
                    ->values();
            }


            return view('front.catalog.product.index', compact('prod', 'group', 'cat', 'subcat', 'seo', 'crumbs', 'bookscheme', 'shipping_methods', 'payment_methods', 'gdl', 'reviews', 'authorProducts', 'publisherProducts', 'relatedProducts'));
        }

        // If only group...
        if ($group && ! $cat && ! $subcat) {
            if ($group == 'zemljovidi-i-vedute') {
                $group = 'Zemljovidi i vedute';
            }

            $categories = Category::where('group', $group)->first('id');

            if ( ! $categories) {
                abort(404);
            }
        }

        if ($cat) {
            $cat->count = Helper::resolveCache('cats_count')->remember($cat->id, config('cache.life'), function () use ($cat) {
                return $cat->products()->count();
            });
            //$cat->count = $cat->products()->count();
        }
        if ($subcat) {
            $subcat->count = Helper::resolveCache('cats_count')->remember($cat->id, config('cache.life'), function () use ($subcat) {
                return $subcat->products()->count();
            });
            //$subcat->products()->count();
        }

        $meta_tags = Seo::getMetaTags($request, 'filter');

        $crumbs = (new Breadcrumb())->category($group, $cat, $subcat)->resolve();

        return view('front.catalog.category.index', compact('group', 'cat', 'subcat', 'prod', 'crumbs', 'meta_tags'));
    }


    /**
     * @param null $prod
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function resolveOldUrl($prod = null)
    {
        if ($prod) {
            $prod = substr($prod, 0, strrpos($prod, '-'));
            $prod = Product::where('slug', 'LIKE', $prod . '%')->first();

            if ($prod) {
                return redirect()->to(url($prod->url), 301);
            }
        }

        abort(404);
    }


    /**
     * @param null $prod
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function resolveOldCategoryUrl(?string $group = null, $cat = null, $subcat = null)
    {
        if ($group) {
            return redirect()->route('catalog.route', ['group' => $group, 'cat' => $cat, 'subcat' => $subcat]);
        }

        abort(404);
    }


    /**
     *
     *
     * @param Author $author
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function author(Request $request, ?Author $author = null, ?Category $cat = null, ?Category $subcat = null)
    {
        if ( ! $author) {
            $letters = Helper::resolveCache('authors')->remember('letters', config('cache.life'), function () {
                return Author::letters();
            });
            $letter = $this->checkLetter($letters);

            if ($request->has('letter')) {
                $letter = $request->input('letter');
            }

            $currentPage = request()->get('page', 1);

            $authors = Helper::resolveCache('authors')->remember($letter . '.' . $currentPage, config('cache.life'), function () use ($letter) {
                return Author::query()->select('id', 'title', 'url')
                    ->where('status',  1)
                    ->where('letter', $letter)
                    ->orderBy('title')
                    ->withCount('products')
                    ->paginate(36)
                    ->appends(request()->query());
            });

            $meta_tags = Seo::getMetaTags($request, 'ap_filter');

            return view('front.catalog.authors.index', compact('authors', 'letters', 'letter', 'meta_tags'));
        }

        $letter = null;

        if ($cat) { $cat->count = $cat->products()->count(); }
        if ($subcat) { $subcat->count = $subcat->products()->count(); }

        $seo = Seo::getAuthorData($author, $cat, $subcat);

        $crumbs = (new Breadcrumb())->author($author, $cat, $subcat)->resolve();

        return view('front.catalog.category.index', compact('author', 'letter', 'cat', 'subcat', 'seo', 'crumbs'));
    }


    /**
     *
     *
     * @param Publisher $publisher
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function publisher(Request $request, ?Publisher $publisher = null, ?Category $cat = null, ?Category $subcat = null)
    {
        if ( ! $publisher) {
            $letters = Helper::resolveCache('publishers')->remember('letters', config('cache.life'), function () {
                return Publisher::letters();
            });
            $letter = $this->checkLetter($letters);

            if ($request->has('letter')) {
                $letter = $request->input('letter');
            }

            $currentPage = request()->get('page', 1);

            $publishers = Helper::resolveCache('publishers')->remember($letter . '.' . $currentPage, config('cache.life'), function () use ($letter) {
                return Publisher::query()->select('id', 'title', 'url')
                    ->where('status',  1)
                    ->where('letter', $letter)
                    ->orderBy('title')
                    ->withCount('products')
                    ->paginate(36)
                    ->appends(request()->query());
            });

            $meta_tags = Seo::getMetaTags($request, 'ap_filter');

            return view('front.catalog.publishers.index', compact('publishers', 'letters', 'letter', 'meta_tags'));
        }

        $letter = null;

        if ($cat) { $cat->count = $cat->products()->count(); }
        if ($subcat) { $subcat->count = $subcat->products()->count(); }

        $seo = Seo::getPublisherData($publisher, $cat, $subcat);

        $crumbs = (new Breadcrumb())->publisher($publisher, $cat, $subcat)->resolve();

        return view('front.catalog.category.index', compact('publisher', 'letter', 'cat', 'subcat', 'seo', 'crumbs'));
    }


    /**
     *
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        // stranica s rezultatima (legacy – ne diramo)
        if ($request->has(config('settings.search_keyword'))) {
            if (!$request->input(config('settings.search_keyword'))) {
                return redirect()->back()->with(['error' => 'Oops..! Zaboravili ste upisati pojam za pretraživanje..!']);
            }

            $group = null; $cat = null; $subcat = null;

            $ids = Helper::search(
                $request->input(config('settings.search_keyword'))
            );

            $crumbs = null;

            return view('front.catalog.category.index', compact('group', 'cat', 'subcat', 'ids', 'crumbs'));
        }

        // API autocomplete – products + categories + authors (dedupe) + did-you-mean
        if ($request->has(config('settings.search_keyword') . '_api')) {

            $q = (string) $request->input(config('settings.search_keyword') . '_api', '');

            // group iz requesta (za URL-ove kategorija), default 'knjige'
            $group = trim((string) $request->input('group', 'kategorija-proizvoda'), '/');

            // --- PROIZVODI (ID-evi + total + sugestija) ---
            // Helper::search($q, true, true) vraća Collection 'products', int 'total', opcionalno 'suggestion'
            $search        = Helper::search($q, true, true);
            $totalProducts = (int) ($search['total'] ?? 0);
            $productIds    = $search['products'] ?? collect();
            // >>> NOVO: meta s did-you-mean
            $meta = [];
            if (!empty($search['suggestion'])) {
                $meta['did_you_mean'] = $search['suggestion'];
            }

            // Učitaj proizvode i povezanog autora
            $items = Product::query()
                ->with(['author', 'action'])
                ->whereIn('id', $productIds)
                ->get()
                ->keyBy('id');

            $productsPayload = [];
            foreach ($productIds as $id) {
                $p = $items->get($id);
                if (!$p) continue;

                $productsPayload[] = [
                    'id'                => $p->id,
                    'sku'               => $p->sku,
                    'name'              => $p->name,
                    'url'               => url($p->url),
                    'main_price'        => $p->main_price,
                    'main_price_text'   => $p->main_price_text,
                    'main_special'      => $p->main_special,
                    'main_special_text' => $p->main_special_text,
                    'image'             => $p->thumb,
                    // prikaz autora kanonski (npr. "Krleža Miroslav")
                    'author_title'      => $p->author ? Helper::canonicalAuthorDisplay($p->author->title) : null,
                ];
            }

            // --- KATEGORIJE ---
            $catsBase = Category::query()
                ->when(method_exists(Category::class, 'scopeActive'), fn ($q2) => $q2->active())
                ->where(function ($w) use ($q) {
                    $w->where('title', 'like', '%' . $q . '%');
                    if (\Illuminate\Support\Facades\Schema::hasColumn('categories', 'description')) {
                        $w->orWhere('description', 'like', '%' . $q . '%');
                    } elseif (\Illuminate\Support\Facades\Schema::hasColumn('categories', 'meta_description')) {
                        $w->orWhere('meta_description', 'like', '%' . $q . '%');
                    } elseif (\Illuminate\Support\Facades\Schema::hasColumn('categories', 'content')) {
                        $w->orWhere('content', 'like', '%' . $q . '%');
                    }
                });

            $totalCategories = (clone $catsBase)->count();

            $categories = $catsBase
                ->orderBy('title')
                ->limit(10)
                ->get();

            $categoriesPayload = $categories->map(function ($c) use ($group) {
                $slug = $c->slug ?: $c->id;

                if ($group === 'snizenja') {
                    $path = route('catalog.route.actions', ['cat' => $slug]);
                } else {
                    $path = route('catalog.route', ['group' => $group, 'cat' => $slug]);
                }

                return [
                    'id'   => $c->id,
                    'name' => $c->title,
                    'url'  => $path,
                ];
            })->values()->all();

            // --- AUTORI (dedupe po prezimenu + kanonski prikaz) ---
            $rawQ = trim((string)$q);
            $tokens = collect(preg_split('/[\s\.,\-_\|]+/u', $rawQ, -1, PREG_SPLIT_NO_EMPTY))
                ->map(fn($t) => Str::lower($t))
                ->unique()
                ->take(5)
                ->values();

            $authorsBase = Author::query()
                ->select('id', 'title', 'url')
                ->where('status', 1)
                ->whereHas('products', function ($q2) {
                    $q2->where('status', 1)->where('quantity', '>', 0);
                })
                // SVAKA riječ mora biti prisutna (AND)
                ->when($tokens->isNotEmpty(), function ($qA) use ($tokens) {
                    foreach ($tokens as $t) {
                        $qA->where('title', 'like', '%' . $t . '%');
                    }
                }, function ($qA) use ($rawQ) {
                    if ($rawQ !== '') {
                        $qA->where('title', 'like', '%' . $rawQ . '%');
                    }
                })
                ->orderBy('title')
                ->limit(200)
                ->get();

            // izbaci očite organizacije (zavod/akademija/…)
            $orgHints = ['zavod','institut','akademija','leksikografski','društvo','drustvo','udruga','univerzitet','sveučilište','sveuciliste','university','press','publisher'];
            $authorsPeople = $authorsBase->filter(function ($a) use ($orgHints) {
                $low = mb_strtolower($a->title, 'UTF-8');
                foreach ($orgHints as $h) {
                    if (mb_strpos($low, $h) !== false) return false;
                }
                return true;
            });

            // grupiraj po prezimenu (iz kanonskog prikaza) i uzmi najboljeg predstavnika
            $grouped = $authorsPeople->groupBy(function ($a) {
                $disp = Helper::canonicalAuthorDisplay($a->title);
                $key  = Helper::canonicalAuthorKey($disp);
                return explode('_', $key)[0] ?? $key; // prezime
            });

            $authorsPayload = $grouped->map(function ($grp) {
                $best = collect($grp)->sortByDesc(function ($a) {
                    $disp  = Helper::canonicalAuthorDisplay($a->title);
                    $parts = preg_split('/\s+/u', trim($disp)) ?: [];
                    // preferiraj "Ime Prezime" (>=2 riječi), pa dulje ime
                    return (count($parts) >= 2 ? 1000 : 0) + mb_strlen($disp, 'UTF-8');
                })->first();

                return [
                    'id'   => $best->id,
                    'name' => Helper::canonicalAuthorDisplay($best->title),
                    'url'  => url($best->url),
                ];
            })->values()->take(10)->all();

            $totalAuthors = $grouped->count();

            // --- STRUCTURED PAYLOAD + X-Total-Count (+ did-you-mean u meta) ---
            $payload = [
                'counts'     => [
                    'products'   => $totalProducts,
                    'authors'    => $totalAuthors,
                    'categories' => $totalCategories,
                ],
                'products'   => $productsPayload,
                'categories' => $categoriesPayload,
                'authors'    => $authorsPayload,
                'meta'       => $meta,
            ];

            $totalAll = $payload['counts']['products']
                + $payload['counts']['authors']
                + $payload['counts']['categories'];

            return response()->json($payload)
                ->header('X-Total-Count', $totalAll);
        }

        return response()->json(['error' => 'Greška kod pretrage..! Molimo pokušajte ponovo ili nas kotaktirajte! HVALA...']);
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function actions(Request $request, ?Category $cat = null, ?Category $subcat = null)
    {
        $ids = Product::query()
            ->active()
            ->hasStock()
            ->where('special', '!=', '')
            ->where(function (Builder $query) {
                $query->whereDate('special_from', '<=', now())->orWhereNull('special_from');
            })
            ->where(function (Builder $query) {
                $query->whereDate('special_to', '>=', now())->orWhereNull('special_to');
            })
            ->pluck('id');
        $group = 'snizenja';
        $actionLanding = $this->resolveActionLanding($cat, $subcat);

        $crumbs = null;

        return view('front.catalog.category.index', compact('group', 'cat', 'subcat', 'ids', 'crumbs', 'actionLanding'));
    }


    /**
     * @param Page $page
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function page(Page $page)
    {
        return view('front.page', compact('page'));
    }


    /**
     * @param Blog $blog
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function blog(Blog $blog)
    {
        if (! $blog->exists) {
            $blogs = Blog::active()->paginate(12);

            return view('front.blog', compact('blogs'));
        }

        return view('front.blog', compact('blog'));
    }


    /**
     * @param Faq $faq
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function faq()
    {
        $faq = Faq::where('status', 1)->get();
        return view('front.faq', compact('faq'));
    }


    /**
     * @param array $letters
     *
     * @return string
     */
    private function checkLetter(Collection $letters): string
    {
        foreach ($letters->all() as $letter) {
            if ($letter['active']) {
                return $letter['value'];
            }
        }

        return 'A';
    }


    /**
     * @return array<string, mixed>
     */
    private function resolveActionLanding(?Category $cat = null, ?Category $subcat = null): array
    {
        $actions = $this->resolveActiveActionLandingActions();

        return [
            'title' => '7 godina ljubavi prema knjigama 💖',
            'lead' => 'Zuzi knjižara slavi rođendan, a ti biraš poklon!',
            'body' => 'Iskoristi rođendanske popuste i pronađi nove naslove, antikvarne dragulje i omiljene knjiške ulove po posebnim cijenama.',
            'seo_title' => \App\Models\Seo::appendBrand('7 godina ljubavi prema knjigama 💖'),
            'seo_description' => \App\Models\Seo::description(
                null,
                'Zuzi knjižara slavi rođendan, a ti biraš poklon! Iskoristi rođendanske popuste i pronađi nove naslove, antikvarne dragulje i omiljene knjiške ulove po posebnim cijenama.'
            ),
            'landing_url' => route('catalog.route.actions'),
            'promotion_start' => optional(
                $actions->pluck('date_start')
                    ->filter()
                    ->map(fn ($date) => \Illuminate\Support\Carbon::make($date))
                    ->filter()
                    ->sortBy(fn ($date) => $date->getTimestamp())
                    ->first()
            )->toAtomString(),
            'promotion_end' => optional(
                $actions->pluck('date_end')
                    ->filter()
                    ->map(fn ($date) => \Illuminate\Support\Carbon::make($date))
                    ->filter()
                    ->sortByDesc(fn ($date) => $date->getTimestamp())
                    ->first()
            )->toAtomString(),
            'categories' => $this->resolveActionLandingCategories($actions, $cat, $subcat),
            'products' => $this->resolveActionLandingProducts($cat, $subcat),
        ];
    }


    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveActionLandingCategories(Collection $actions, ?Category $cat = null, ?Category $subcat = null): array
    {
        $selectedCategoryId = $subcat?->id ?: $cat?->id;

        if ($actions->isEmpty()) {
            return [];
        }

        $categoryIds = $actions
            ->flatMap(function (MarketingAction $action) {
                return collect(json_decode((string) $action->links, true));
            })
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($categoryIds->isEmpty()) {
            return [];
        }

        $categories = Category::query()
            ->active()
            ->with(['parent:id,parent_id,title,slug'])
            ->whereIn('id', $categoryIds)
            ->get(['id', 'parent_id', 'title', 'slug'])
            ->keyBy('id');

        return $actions
            ->flatMap(function (MarketingAction $action) use ($categories, $selectedCategoryId) {
                $discountLabel = $action->type === 'P'
                    ? rtrim(rtrim(number_format((float) $action->discount, 2, '.', ''), '0'), '.') . '%'
                    : (string) $action->discount_text;

                return collect(json_decode((string) $action->links, true))
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->map(function (int $categoryId) use ($action, $categories, $selectedCategoryId, $discountLabel) {
                        /** @var Category|null $category */
                        $category = $categories->get($categoryId);

                        if (! $category) {
                            return null;
                        }

                        $parent = $category->parent;

                        return [
                            'id' => $category->id,
                            'title' => $category->title,
                            'discount' => $discountLabel,
                            'url' => route('catalog.route.actions', [
                                'cat' => $parent ?: $category,
                                'subcat' => $parent ? $category : null,
                            ]),
                            'is_active' => $selectedCategoryId === $category->id,
                        ];
                    })
                    ->filter();
            })
            ->filter()
            ->unique('id')
            ->values()
            ->all();
    }


    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\Back\Marketing\Action>
     */
    private function resolveActiveActionLandingActions(): Collection
    {
        return MarketingAction::query()
            ->where('status', 1)
            ->where('group', 'category')
            ->where(function (Builder $query) {
                $query->whereNull('coupon')->orWhere('coupon', '');
            })
            ->where(function (Builder $query) {
                $query->whereNull('date_start')->orWhere('date_start', '<=', now());
            })
            ->where(function (Builder $query) {
                $query->whereNull('date_end')->orWhere('date_end', '>=', now());
            })
            ->orderByDesc('discount')
            ->orderBy('title')
            ->get(['id', 'title', 'type', 'discount', 'links', 'date_start', 'date_end']);
    }


    /**
     * @return array<int, array{name: string, url: string}>
     */
    private function resolveActionLandingProducts(?Category $cat = null, ?Category $subcat = null): array
    {
        $query = Product::query()
            ->active()
            ->hasStock()
            ->where('special', '!=', '')
            ->where(function (Builder $builder) {
                $builder->whereDate('special_from', '<=', now())->orWhereNull('special_from');
            })
            ->where(function (Builder $builder) {
                $builder->whereDate('special_to', '>=', now())->orWhereNull('special_to');
            });

        if ($cat) {
            $query->whereHas('categories', function (Builder $builder) use ($cat) {
                $builder->where('category_id', $cat->id);
            });
        }

        if ($subcat) {
            $query->whereHas('categories', function (Builder $builder) use ($subcat) {
                $builder->where('category_id', $subcat->id);
            });
        }

        return $query
            ->orderBy('viewed', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(12)
            ->get(['name', 'url'])
            ->map(function (Product $product) {
                return [
                    'name' => $product->name,
                    'url' => url($product->url),
                ];
            })
            ->values()
            ->all();
    }

}
