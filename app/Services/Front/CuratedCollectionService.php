<?php

namespace App\Services\Front;

use App\Models\Front\Catalog\Product;
use App\Models\Seo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CuratedCollectionService
{
    private const EXCLUDED_ORDER_STATUSES = [5, 7, 8];
    private const MONTHLY_RANKING_LIMIT = 480;
    private const CANONICAL_MIN_SCORE = 140;
    private const EXCLUDED_TITLE_PATTERNS = [
        '%Hitler Adolf%',
    ];
    private const CANONICAL_NOISE_TERMS = [
        'aktivnosti',
        'bojanka',
        'planer',
        'slikovnica',
        'za najmladje',
        'za najmlađe',
    ];
    private const HOMEPAGE_SNAPSHOT_FILE = 'app/curated-homepage-widget.json';

    /**
     * @var array<string, mixed>|null
     */
    private ?array $homepageSnapshot = null;

    /**
     * @return array<string, mixed>
     */
    public function homepageWidgetData(): array
    {
        return Cache::remember(
            $this->cacheKey('homepage-widget'),
            now()->addMinutes(30),
            function () {
                $collections = collect([
                    $this->resolveHomepageCollection('knjige-do-5-eura'),
                    $this->resolveHomepageCollection('knjige-od-5-do-10-eura'),
                    $this->resolveHomepageCollection('najpopularnije-ovaj-mjesec'),
                    $this->resolveHomepageCollection('verdens-100-klasici'),
                ])->filter()->values();

                return [
                    'collections' => $collections->all(),
                    'featured_products' => $this->resolveFeaturedProducts(),
                ];
            }
        );
    }


    /**
     * @return array<string, mixed>|null
     */
    public function resolveCollection(string $slug): ?array
    {
        $definitions = $this->definitions();

        if (! isset($definitions[$slug])) {
            return null;
        }

        $definition = $definitions[$slug];

        return Cache::remember(
            $this->cacheKey('collection.' . $slug),
            now()->addMinutes(30),
            function () use ($definition, $slug) {
                if ($definition['type'] === 'price') {
                    return $this->buildPriceCollection($slug, $definition);
                }

                if ($definition['type'] === 'canonical_list') {
                    return $this->buildCanonicalCollection($slug, $definition);
                }

                return $this->buildMonthlyRankingCollection($slug, $definition);
            }
        );
    }


    /**
     * @return array<string, mixed>|null
     */
    private function resolveHomepageCollection(string $slug): ?array
    {
        $definitions = $this->definitions();

        if (! isset($definitions[$slug])) {
            return null;
        }

        $definition = $definitions[$slug];
        $snapshot = $this->homepageSnapshotCollection($slug);

        return array_merge($definition, [
            'slug' => $slug,
            'url' => route('catalog.route.curated', ['collection' => $slug]),
            'count' => data_get($snapshot, 'count'),
            'count_label' => data_get($snapshot, 'count_label', $definition['home_count_label'] ?? ''),
        ]);
    }


    /**
     * @return array<string, array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            'knjige-do-5-eura' => [
                'type' => 'price',
                'eyebrow' => 'Brzi ulov',
                'badge' => 'Do 5 €',
                'home_count_label' => 'Povoljni ulovi',
                'title' => 'Uhvati knjige do 5 €',
                'description' => 'Mali iznos, brz klik i osjećaj da ste odlično prošli.',
                'lead' => 'Najpovoljniji naslovi koji se uzimaju odmah.',
                'body' => 'Kliknite i uhvatite dostupne knjige do 5 € prije nego odu.',
                'cta' => 'Uhvati odmah',
                'accent' => '#e50077',
                'surface' => 'linear-gradient(135deg, #fff2f8 0%, #fff9fc 100%)',
                'price_min' => 1,
                'price_max' => 5,
                'default_sort' => 'popular',
                'meta_title' => Seo::appendBrand('Uhvati knjige do 5 €'),
                'meta_description' => Seo::description(null, 'Uhvati knjige do 5 € i pronađi brze, povoljne ulove za sebe ili poklon.'),
            ],
            'knjige-od-5-do-10-eura' => [
                'type' => 'price',
                'eyebrow' => 'Top vrijednost',
                'badge' => 'Od 5 do 10 €',
                'home_count_label' => 'Top raspon',
                'title' => 'Najbolji ulovi od 5 do 10 €',
                'description' => 'Savršen raspon za još bolji ulov bez teške odluke na checkoutu.',
                'lead' => 'Pogodi cijenu koja najlakše pretvara pregled u kupnju.',
                'body' => 'Pregledaj ponudu od 5 do 10 € i ugrabi naslov koji ti prvi zapne za oko.',
                'cta' => 'Pogledaj ulove',
                'accent' => '#1f8f6a',
                'surface' => 'linear-gradient(135deg, #eefbf5 0%, #f8fffc 100%)',
                'price_min' => 5.01,
                'price_max' => 10,
                'default_sort' => 'popular',
                'meta_title' => Seo::appendBrand('Najbolji ulovi od 5 do 10 €'),
                'meta_description' => Seo::description(null, 'Pregledaj knjige od 5 do 10 € i uhvati naslove koji nude najviše za ugodnu cijenu.'),
            ],
            'najpopularnije-ovaj-mjesec' => [
                'type' => 'monthly_ranking',
                'metric' => 'orders',
                'eyebrow' => 'Kupci biraju',
                'badge' => 'Hit izbor',
                'home_count_label' => 'Ovaj mjesec',
                'title' => 'Kupci ovo biraju prvi',
                'description' => 'Klikni i pregledaj najtraženije naslove mjeseca na jednom mjestu',
                'lead' => 'Ovdje su knjige koje kupci sada najviše love.',
                'body' => 'Klikni i pregledaj najtraženije naslove mjeseca na jednom mjestu.',
                'cta' => 'Pogledaj hitove',
                'accent' => '#4a6cf7',
                'surface' => 'linear-gradient(135deg, #eef3ff 0%, #f8fbff 100%)',
                'meta_title' => Seo::appendBrand('Kupci ovo biraju prvi'),
                'meta_description' => Seo::description(null, 'Pogledaj koje knjige kupci ovog mjeseca najčešće naručuju i kreni od aktualnih hitova.'),
                'metric_note' => '',
            ],
            'verdens-100-klasici' => [
                'type' => 'canonical_list',
                'source' => 'verdens_100',
                'eyebrow' => 'Bezvremenski izbor',
                'badge' => 'Klasici',
                'home_count_label' => 'Zuzi odabir',
                'title' => 'Klasici koje vrijedi imati na polici',
                'description' => 'Domaći velikani i bezvremenski hitovi koje čitatelji stalno traže.',
                'lead' => 'Kreni od knjiga koje se vraćaju na velike liste, lektire i osobne police za cijeli život.',
                'body' => 'Na jednome mjestu vidiš dostupne klasike, domaće velikane i bezvremenske naslove koje vrijedi imati u svojoj knjižnici.',
                'cta' => 'Pogledaj klasike',
                'accent' => '#f08a24',
                'surface' => 'linear-gradient(135deg, #fff5ea 0%, #fffaf5 100%)',
                'meta_title' => Seo::appendBrand('Klasici koje vrijedi imati na polici'),
                'meta_description' => Seo::description(null, 'Pregledaj izbor domaće klasike i bezvremenske naslove koje ozbiljni čitatelji uvijek vraćaju na policu.'),
                'metric_note' => '',
            ],
            'najprodavanije-ovaj-mjesec' => [
                'type' => 'monthly_ranking',
                'metric' => 'quantity',
                'eyebrow' => 'Top prodaja',
                'badge' => 'Bestselleri',
                'title' => 'Bestselleri mjeseca',
                'description' => 'Knjige koje se najbrže prodaju dok ih kupci još stignu uhvatiti.',
                'lead' => 'Ovo su knjige koje trenutačno nose prodaju.',
                'body' => 'Uđi među bestsellere mjeseca i provjeri što kupci najbrže grabe.',
                'cta' => 'Pogledaj bestsellere',
                'accent' => '#f08a24',
                'surface' => 'linear-gradient(135deg, #fff5ea 0%, #fffaf5 100%)',
                'meta_title' => Seo::appendBrand('Bestselleri mjeseca'),
                'meta_description' => Seo::description(null, 'Otkrij bestsellere mjeseca i pregledaj naslove koji se trenutno najbrže prodaju.'),
                'metric_note' => '',
            ],
        ];
    }


    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function buildPriceCollection(string $slug, array $definition): array
    {
        $query = Product::query()->active()->hasStock();
        $priceSql = $this->currentListingPriceSql();

        $count = (clone $query)
            ->whereRaw($priceSql . ' >= ?', [$definition['price_min']])
            ->whereRaw($priceSql . ' <= ?', [$definition['price_max']])
            ->count();

        $collection = array_merge($definition, [
            'slug' => $slug,
            'url' => route('catalog.route.curated', ['collection' => $slug]),
            'count' => $count,
            'count_label' => $this->formatCountLabel($count),
            'ids_json' => null,
            'preserve_order' => false,
            'meta_pill' => 'Raspon se računa prema trenutačnoj prodajnoj cijeni.',
        ]);

        $this->persistHomepageSnapshotCollection($slug, $count);

        return $collection;
    }


    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function buildMonthlyRankingCollection(string $slug, array $definition): array
    {
        $rows = $this->resolveMonthlyRanking($definition['metric']);
        $ids = $rows->pluck('product_id')->values();
        $count = $ids->count();

        $collection = array_merge($definition, [
            'slug' => $slug,
            'url' => route('catalog.route.curated', ['collection' => $slug]),
            'count' => $count,
            'count_label' => $this->formatCountLabel($count),
            'ids_json' => $ids->toJson(),
            'preserve_order' => true,
            'default_sort' => '',
            'meta_pill' => $definition['metric_note'] ?? 'Ažurira se prema rezultatima tekućeg mjeseca.',
        ]);

        $this->persistHomepageSnapshotCollection($slug, $count);

        return $collection;
    }


    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function buildCanonicalCollection(string $slug, array $definition): array
    {
        $ids = $this->resolveCanonicalProductIds($definition['source'] ?? '')->values();
        $count = $ids->count();

        $collection = array_merge($definition, [
            'slug' => $slug,
            'url' => route('catalog.route.curated', ['collection' => $slug]),
            'count' => $count,
            'count_label' => $this->formatCountLabel($count),
            'ids_json' => $ids->toJson(),
            'preserve_order' => true,
            'default_sort' => '',
            'meta_pill' => $definition['metric_note'] ?? 'Prikazujemo dostupne naslove iz odabranog kanona.',
        ]);

        $this->persistHomepageSnapshotCollection($slug, $count);

        return $collection;
    }


    /**
     * @return array<string, mixed>|null
     */
    private function resolveFeaturedProducts(): Collection
    {
        return Cache::remember(
            $this->cacheKey('featured-products'),
            now()->addMinutes(30),
            function () {
                $rankedProducts = $this->resolveMonthlyRanking('quantity')->take(5)->values();

                if ($rankedProducts->isEmpty()) {
                    return Product::query()
                        ->active()
                        ->hasStock()
                        ->hasImage()
                        ->where(function ($query) {
                            foreach (self::EXCLUDED_TITLE_PATTERNS as $pattern) {
                                $query->where('name', 'not like', $pattern);
                            }
                        })
                        ->with(['action'])
                        ->popular(5)
                        ->get()
                        ->map(function (Product $product, int $index) {
                            return [
                                'position' => $index + 1,
                                'product' => $product,
                            ];
                        })
                        ->values();
                }

                $products = Product::query()
                    ->active()
                    ->hasStock()
                    ->hasImage()
                    ->with(['action'])
                    ->whereIn('id', $rankedProducts->pluck('product_id'))
                    ->get()
                    ->keyBy('id');

                return $rankedProducts
                    ->map(function (array $rankedProduct, int $index) use ($products) {
                        $product = $products->get($rankedProduct['product_id']);

                        if (! $product) {
                            return null;
                        }

                        return [
                            'position' => $index + 1,
                            'product' => $product,
                        ];
                    })
                    ->filter()
                    ->values();
            }
        );
    }


    /**
     * @return Collection<int, array<string, int>>
     */
    private function resolveMonthlyRanking(string $metric): Collection
    {
        return Cache::remember(
            $this->cacheKey('monthly-ranking.' . $metric),
            now()->addMinutes(30),
            function () use ($metric) {
                $query = DB::table('order_products as order_product')
                    ->join('orders as orders', 'orders.id', '=', 'order_product.order_id')
                    ->join('products as products', 'products.id', '=', 'order_product.product_id')
                    ->whereBetween('orders.created_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->whereNotIn('orders.order_status_id', self::EXCLUDED_ORDER_STATUSES)
                    ->where('products.status', 1)
                    ->where('products.quantity', '!=', 0)
                    ->whereNotNull('products.image')
                    ->where('products.image', '!=', '')
                    ->where('products.image', '!=', 'media/avatars/avatar0.jpg')
                    ->where(function ($query) {
                        foreach (self::EXCLUDED_TITLE_PATTERNS as $pattern) {
                            $query->where('products.name', 'not like', $pattern);
                        }
                    })
                    ->selectRaw('order_product.product_id as product_id')
                    ->selectRaw('SUM(order_product.quantity) as sold_quantity')
                    ->selectRaw('COUNT(DISTINCT order_product.order_id) as order_count')
                    ->selectRaw('SUM(order_product.total) as revenue')
                    ->groupBy('order_product.product_id');

                if ($metric === 'orders') {
                    $query->orderByDesc('order_count')->orderByDesc('revenue')->orderByDesc('sold_quantity');
                } elseif ($metric === 'revenue') {
                    $query->havingRaw('COUNT(DISTINCT order_product.order_id) >= 2')
                        ->orderByDesc('revenue')
                        ->orderByDesc('order_count')
                        ->orderByDesc('sold_quantity');
                } else {
                    $query->orderByDesc('sold_quantity')->orderByDesc('revenue')->orderByDesc('order_count');
                }

                return $query
                    ->limit(self::MONTHLY_RANKING_LIMIT)
                    ->get()
                    ->map(function ($row) {
                        return [
                            'product_id' => (int) $row->product_id,
                            'sold_quantity' => (int) $row->sold_quantity,
                            'order_count' => (int) $row->order_count,
                            'revenue' => (float) $row->revenue,
                        ];
                    });
            }
        );
    }


    /**
     * @return Collection<int, int>
     */
    private function resolveCanonicalProductIds(string $source): Collection
    {
        $sourceConfig = config('curated_sources.' . $source, []);
        $books = collect(data_get($sourceConfig, 'books', []))
            ->merge(data_get($sourceConfig, 'supplemental_books', []));

        if ($books->isEmpty()) {
            return collect();
        }

        $aliases = collect(data_get($sourceConfig, 'aliases', []))
            ->mapWithKeys(function ($value, $key) {
                return [$this->normalizeCanonicalText($key) => collect($value)->filter()->values()->all()];
            });

        $matchedIds = [];

        return $books
            ->map(function (array $book) use (&$matchedIds, $aliases) {
                $match = $this->matchCanonicalBook($book, $matchedIds, $aliases);

                if (! $match) {
                    return null;
                }

                $matchedIds[] = $match->id;

                return (int) $match->id;
            })
            ->filter()
            ->values();
    }


    private function matchCanonicalBook(array $book, array $excludedIds, Collection $aliases): ?Product
    {
        $key = $this->canonicalBookKey($book);
        $variants = collect([$this->cleanCanonicalSourceValue($book['title'])])
            ->merge($aliases->get($key, []))
            ->map(fn ($value) => $this->cleanCanonicalSourceValue((string) $value))
            ->filter()
            ->unique()
            ->values();

        $authorTerms = $this->canonicalAuthorTerms($book['author'] ?? '');
        $seedTerms = $authorTerms->isNotEmpty() ? $authorTerms : $variants;

        if ($seedTerms->isEmpty()) {
            return null;
        }

        $query = Product::query()
            ->active()
            ->hasStock()
            ->with(['author'])
            ->when($excludedIds !== [], fn ($query) => $query->whereNotIn('id', $excludedIds))
            ->where(function ($query) use ($seedTerms, $authorTerms) {
                foreach ($seedTerms as $term) {
                    $like = '%' . addcslashes($term, '%_\\') . '%';

                    $query->orWhere('name', 'like', $like);

                    if ($authorTerms->contains($term)) {
                        $query->orWhereHas('author', fn ($authorQuery) => $authorQuery->where('title', 'like', $like));
                    }
                }
            })
            ->limit(40)
            ->get();

        $rankedCandidates = $query
            ->map(function (Product $product) use ($variants, $authorTerms) {
                return [
                    'product' => $product,
                    'score' => $this->scoreCanonicalProduct($product, $variants, $authorTerms),
                ];
            })
            ->sort(function (array $left, array $right) {
                $leftScore = $left['score'];
                $rightScore = $right['score'];

                if ($leftScore !== $rightScore) {
                    return $rightScore <=> $leftScore;
                }

                $leftViewed = (int) data_get($left, 'product.viewed', 0);
                $rightViewed = (int) data_get($right, 'product.viewed', 0);

                if ($leftViewed !== $rightViewed) {
                    return $rightViewed <=> $leftViewed;
                }

                return strlen(data_get($left, 'product.name', '')) <=> strlen(data_get($right, 'product.name', ''));
            })
            ->values();

        $bestMatch = $rankedCandidates->first(function (array $item) {
            return $item['score'] >= self::CANONICAL_MIN_SCORE;
        });

        return $bestMatch['product'] ?? null;
    }


    /**
     * @param  Collection<int, string>  $titleVariants
     * @param  Collection<int, string>  $authorTerms
     */
    private function scoreCanonicalProduct(Product $product, Collection $titleVariants, Collection $authorTerms): int
    {
        $haystack = $this->normalizeCanonicalText($product->name . ' ' . data_get($product, 'author.title', ''));
        $score = 0;

        foreach ($titleVariants as $variant) {
            $needle = $this->normalizeCanonicalText($variant);

            if ($needle === '') {
                continue;
            }

            if (str_contains($haystack, $needle)) {
                $score = max($score, 220 + strlen($needle));
                continue;
            }

            $tokens = collect(explode(' ', $needle))
                ->filter(fn ($token) => strlen($token) >= 4 && ! in_array($token, ['stories', 'other', 'complete', 'poems'], true))
                ->values();

            if ($tokens->isEmpty()) {
                continue;
            }

            $matched = $tokens->filter(fn ($token) => str_contains($haystack, $token))->count();

            if ($matched === $tokens->count()) {
                $score = max($score, 170 + ($matched * 10));
            } elseif ($matched >= 2) {
                $score = max($score, 90 + ($matched * 14));
            }
        }

        foreach ($authorTerms as $authorTerm) {
            $needle = $this->normalizeCanonicalText($authorTerm);

            if ($needle !== '' && str_contains($haystack, $needle)) {
                $score += strlen($needle) > 8 ? 38 : 18;
            }
        }

        foreach (self::CANONICAL_NOISE_TERMS as $noiseTerm) {
            if (str_contains($haystack, $this->normalizeCanonicalText($noiseTerm))) {
                $score -= 120;
            }
        }

        return $score;
    }


    /**
     * @return Collection<int, string>
     */
    private function canonicalAuthorTerms(string $author): Collection
    {
        $cleanAuthor = $this->cleanCanonicalSourceValue($author);

        if ($cleanAuthor === '' || in_array(Str::lower($cleanAuthor), ['unknown', 'various'], true)) {
            return collect();
        }

        $terms = collect([$cleanAuthor, Str::ascii($cleanAuthor)]);
        $parts = preg_split('/\s+/', Str::ascii($cleanAuthor)) ?: [];

        if (count($parts) > 1) {
            $terms->push(end($parts));
        }

        return $terms
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '' && strlen($value) >= 3)
            ->unique()
            ->values();
    }


    private function canonicalBookKey(array $book): string
    {
        return $this->normalizeCanonicalText(
            $this->cleanCanonicalSourceValue($book['title'] ?? '') . '|' . $this->cleanCanonicalSourceValue($book['author'] ?? '')
        );
    }


    private function cleanCanonicalSourceValue(string $value): string
    {
        return trim(str_replace("''", '', $value));
    }


    private function normalizeCanonicalText(string $value): string
    {
        $value = Str::lower(Str::ascii($this->cleanCanonicalSourceValue($value)));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';

        return trim($value);
    }


    /**
     * @return array<string, mixed>
     */
    private function homepageSnapshotCollection(string $slug): array
    {
        return data_get($this->homepageSnapshot(), 'collections.' . $slug, []);
    }


    /**
     * @return array<string, mixed>
     */
    private function homepageSnapshot(): array
    {
        if ($this->homepageSnapshot !== null) {
            return $this->homepageSnapshot;
        }

        $path = storage_path(self::HOMEPAGE_SNAPSHOT_FILE);

        if (! File::exists($path)) {
            return $this->homepageSnapshot = [];
        }

        $decoded = json_decode((string) File::get($path), true);

        return $this->homepageSnapshot = is_array($decoded) ? $decoded : [];
    }


    private function persistHomepageSnapshotCollection(string $slug, int $count): void
    {
        $snapshot = $this->homepageSnapshot();
        $snapshot['collections'][$slug] = [
            'count' => $count,
            'count_label' => $this->formatCountLabel($count),
            'updated_at' => now()->toAtomString(),
        ];
        $snapshot['updated_at'] = now()->toAtomString();

        $path = storage_path(self::HOMEPAGE_SNAPSHOT_FILE);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->homepageSnapshot = $snapshot;
    }


    private function currentListingPriceSql(string $table = 'products'): string
    {
        return "CASE
            WHEN {$table}.special IS NOT NULL
                AND {$table}.special != ''
                AND ({$table}.special_from IS NULL OR DATE({$table}.special_from) <= CURDATE())
                AND ({$table}.special_to IS NULL OR DATE({$table}.special_to) >= CURDATE())
            THEN {$table}.special
            ELSE {$table}.price
        END";
    }


    private function formatCountLabel(int $count): string
    {
        return number_format($count, 0, ',', '.') . ' artikala';
    }


    private function cacheKey(string $suffix): string
    {
        return 'curated-collections.v5.' . now()->format('Y-m') . '.' . $suffix;
    }
}
