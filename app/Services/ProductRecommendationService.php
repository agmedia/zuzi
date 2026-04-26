<?php

namespace App\Services;

use App\Helpers\Helper;
use App\Models\Front\Catalog\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductRecommendationService
{
    private const EXCLUDED_ORDER_STATUSES = [5, 7, 8];
    private const EXCLUDED_TITLE_PATTERNS = [
        '%Hitler Adolf%',
    ];
    private const MONTHLY_BESTSELLER_LIMIT = 180;
    private const MAX_CANDIDATE_PRODUCTS = 240;
    private const TITLE_SEARCH_TOKEN_LIMIT = 8;
    private const TITLE_WEIGHT_LIMIT = 14;
    private const TITLE_MIN_TOKEN_LENGTH = 3;
    private const TITLE_STOP_WORDS = [
        'a',
        'an',
        'and',
        'bez',
        'da',
        'do',
        'for',
        'from',
        'i',
        'ili',
        'in',
        'iz',
        'je',
        'jos',
        'koja',
        'koje',
        'koji',
        'knjiga',
        'knjige',
        'kroz',
        'na',
        'nad',
        'nije',
        'no',
        'o',
        'od',
        'of',
        'po',
        'pod',
        'prica',
        'prirucnik',
        'pri',
        'roman',
        'sa',
        'se',
        'serija',
        'sve',
        'the',
        'to',
        'u',
        'uz',
        'vodic',
        'za',
    ];

    private array $productImageDimensions = [];

    public function forCartItems(Collection $cartItems, int $limit = 10): Collection
    {
        $seedProductIds = $cartItems->map(fn ($item) => (int) data_get($item, 'id'))
            ->filter()
            ->unique()
            ->values();

        if ($seedProductIds->isEmpty()) {
            return collect();
        }

        $seedProducts = $this->resolveSeedProducts($seedProductIds);

        if ($seedProducts->isEmpty()) {
            return collect();
        }

        return $this->buildUpsellRecommendations(
            $seedProducts,
            $limit,
            $this->resolvePricingContextFromCartItems($cartItems, $seedProducts)
        );
    }


    public function randomBookmarkersForCart(Collection $cartItems, int $limit = 10): Collection
    {
        $excludedProductIds = $cartItems->map(fn ($item) => (int) data_get($item, 'id'))
            ->filter()
            ->unique()
            ->values();

        return $this->randomBookmarkers($excludedProductIds, $limit);
    }


    public function randomBookmarkers(?Collection $excludedProductIds = null, int $limit = 10): Collection
    {
        $excludedProductIds = ($excludedProductIds ?: collect())
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $bookmarkers = collect();
        $seenProductIds = $excludedProductIds->values();
        $attempts = 0;

        while ($bookmarkers->count() < $limit && $attempts < 4) {
            $batch = Product::query()
                ->active()
                ->hasStock()
                ->hasImage()
                ->with(['author', 'action', 'categories'])
                ->withReviewSummary()
                ->whereNotIn('id', $seenProductIds)
                ->whereHas('categories', function ($query) {
                    $query->where('slug', 'bookmarkeri');
                })
                ->inRandomOrder()
                ->limit(max($limit * 4, 24))
                ->get();

            if ($batch->isEmpty()) {
                break;
            }

            $seenProductIds = $seenProductIds->merge($batch->pluck('id'))
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values();

            $portraitBookmarkers = $batch->filter(fn (Product $product) => $this->isVerticalBookmarkerCandidate($product));

            $bookmarkers = $bookmarkers->concat($portraitBookmarkers)
                ->unique('id')
                ->take($limit)
                ->values();

            $attempts++;
        }

        return $bookmarkers->values();
    }


    public function forProductIds(Collection $seedProductIds, int $limit = 10): Collection
    {
        $seedProductIds = $seedProductIds->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($seedProductIds->isEmpty()) {
            return collect();
        }

        $seedProducts = $this->resolveSeedProducts($seedProductIds);

        if ($seedProducts->isEmpty()) {
            return collect();
        }

        return $this->buildUpsellRecommendations($seedProducts, $limit);
    }


    private function resolveSeedProducts(Collection $seedProductIds): Collection
    {
        if ($seedProductIds->isEmpty()) {
            return collect();
        }

        return Product::query()
            ->whereIn('id', $seedProductIds)
            ->with('action')
            ->get([
                'id',
                'name',
                'price',
                'special',
                'special_from',
                'special_to',
                'author_id',
                'publisher_id',
                'viewed',
                'action_id',
            ])
            ->sortBy(fn (Product $product) => $seedProductIds->search($product->id))
            ->values();
    }


    private function buildUpsellRecommendations(
        Collection $seedProducts,
        int $limit,
        ?array $pricingContext = null
    ): Collection {
        $seedProductIds = $seedProducts->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $authorIds = $seedProducts->pluck('author_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $publisherIds = $seedProducts->pluck('publisher_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $titleProfile = $this->buildSeedTitleProfile($seedProducts);
        $pricingContext = $pricingContext ?: $this->resolvePricingContextFromProducts($seedProducts);
        $coPurchaseMetrics = $this->resolveCoPurchaseMetrics($seedProductIds);
        $monthlyBestsellerMetrics = $this->resolveMonthlyBestsellerMetrics();

        $candidateIds = collect()
            ->merge($coPurchaseMetrics->keys())
            ->merge($this->resolveTitleCandidateIds(
                $titleProfile['search_tokens'],
                $seedProductIds,
                max($limit * 8, 64)
            ))
            ->merge(
                $monthlyBestsellerMetrics->keys()
                    ->map(fn ($id) => (int) $id)
                    ->take(max($limit * 10, 90))
                    ->values()
            )
            ->merge($this->resolveAuthorPublisherFallbackCandidateIds(
                $authorIds,
                $publisherIds,
                $seedProductIds,
                max($limit * 6, 36)
            ))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->reject(fn ($id) => $seedProductIds->contains((int) $id))
            ->unique()
            ->take(self::MAX_CANDIDATE_PRODUCTS)
            ->values();

        $rankedCandidates = $this->rankCandidateProducts(
            $candidateIds,
            $coPurchaseMetrics,
            $monthlyBestsellerMetrics,
            $titleProfile,
            $pricingContext,
            $authorIds,
            $publisherIds
        );

        if ($rankedCandidates->count() < $limit) {
            $fallbackIds = $this->resolvePopularFallbackCandidateIds(
                $seedProductIds
                    ->merge($rankedCandidates->pluck('product.id'))
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->unique()
                    ->values(),
                max($limit * 8, 60)
            );

            $rankedCandidates = $rankedCandidates
                ->concat($this->rankCandidateProducts(
                    $fallbackIds,
                    $coPurchaseMetrics,
                    $monthlyBestsellerMetrics,
                    $titleProfile,
                    $pricingContext,
                    $authorIds,
                    $publisherIds
                ))
                ->unique(fn (array $item) => (int) data_get($item, 'product.id'))
                ->sort(fn (array $left, array $right) => $this->compareScoredCandidates($left, $right))
                ->values();
        }

        return $rankedCandidates->take($limit)->pluck('product')->values();
    }


    private function rankCandidateProducts(
        Collection $candidateIds,
        Collection $coPurchaseMetrics,
        Collection $monthlyBestsellerMetrics,
        array $titleProfile,
        array $pricingContext,
        Collection $authorIds,
        Collection $publisherIds
    ): Collection {
        if ($candidateIds->isEmpty()) {
            return collect();
        }

        return $this->resolveCandidateProducts($candidateIds)
            ->map(function (Product $product) use (
                $coPurchaseMetrics,
                $monthlyBestsellerMetrics,
                $titleProfile,
                $pricingContext,
                $authorIds,
                $publisherIds
            ) {
                $resolvedPrice = $this->resolveProductPrice($product);
                $coPurchaseData = (array) $coPurchaseMetrics->get($product->id, []);
                $monthlyData = (array) $monthlyBestsellerMetrics->get($product->id, []);

                $score = $this->scoreCoPurchase($coPurchaseData)
                    + $this->scoreTitleSimilarity($product, $titleProfile['weights'])
                    + $this->scoreMonthlyBestseller($monthlyData)
                    + $this->scoreViewed((int) ($product->viewed ?? 0))
                    + $this->scorePriceFit($resolvedPrice, $pricingContext)
                    + $this->scoreRelationFallback($product, $authorIds, $publisherIds);

                return [
                    'product' => $product,
                    'score' => $score,
                    'co_purchase_orders' => (int) ($coPurchaseData['shared_orders'] ?? 0),
                    'monthly_order_count' => (int) ($monthlyData['order_count'] ?? 0),
                    'viewed' => (int) ($product->viewed ?? 0),
                    'resolved_price' => $resolvedPrice,
                ];
            })
            ->sort(fn (array $left, array $right) => $this->compareScoredCandidates($left, $right))
            ->values();
    }


    private function compareScoredCandidates(array $left, array $right): int
    {
        $leftTuple = [
            round((float) $left['score'], 4),
            (int) $left['co_purchase_orders'],
            (int) $left['monthly_order_count'],
            (int) $left['viewed'],
            round((float) $left['resolved_price'], 2),
            -((int) data_get($left, 'product.id')),
        ];

        $rightTuple = [
            round((float) $right['score'], 4),
            (int) $right['co_purchase_orders'],
            (int) $right['monthly_order_count'],
            (int) $right['viewed'],
            round((float) $right['resolved_price'], 2),
            -((int) data_get($right, 'product.id')),
        ];

        return $rightTuple <=> $leftTuple;
    }


    private function resolveCandidateProducts(Collection $candidateIds): Collection
    {
        if ($candidateIds->isEmpty()) {
            return collect();
        }

        $query = Product::query()
            ->active()
            ->hasStock()
            ->hasImage()
            ->with(['author', 'action', 'categories'])
            ->withReviewSummary()
            ->whereIn('id', $candidateIds);

        $this->applyExcludedTitlePatterns($query);

        return $query->get()
            ->sortBy(fn (Product $product) => $candidateIds->search($product->id))
            ->values();
    }


    private function resolveCoPurchaseMetrics(Collection $seedProductIds): Collection
    {
        if ($seedProductIds->isEmpty()) {
            return collect();
        }

        $cacheKey = 'product-recommendations.co-purchase.' . md5(
            $seedProductIds->sort()->implode('-')
        );

        return Helper::rememberCache($cacheKey, now()->addMinutes(20), function () use ($seedProductIds) {
            $matchingOrders = DB::table('order_products as seed_order_product')
                ->join('orders as orders', 'orders.id', '=', 'seed_order_product.order_id')
                ->whereIn('seed_order_product.product_id', $seedProductIds)
                ->whereNotIn('orders.order_status_id', self::EXCLUDED_ORDER_STATUSES)
                ->groupBy('seed_order_product.order_id')
                ->selectRaw('seed_order_product.order_id as order_id')
                ->selectRaw('COUNT(DISTINCT seed_order_product.product_id) as seed_match_count');

            $query = DB::query()
                ->fromSub($matchingOrders, 'matching_orders')
                ->join('order_products as candidate_order_product', 'candidate_order_product.order_id', '=', 'matching_orders.order_id')
                ->join('products as products', 'products.id', '=', 'candidate_order_product.product_id')
                ->whereNotIn('candidate_order_product.product_id', $seedProductIds)
                ->where('products.status', 1)
                ->where('products.quantity', '!=', 0)
                ->whereNotNull('products.image')
                ->where('products.image', '!=', '')
                ->where('products.image', '!=', 'media/avatars/avatar0.jpg')
                ->selectRaw('candidate_order_product.product_id as product_id')
                ->selectRaw('COUNT(DISTINCT matching_orders.order_id) as shared_orders')
                ->selectRaw('SUM(candidate_order_product.quantity) as sold_quantity')
                ->selectRaw('AVG(matching_orders.seed_match_count) as avg_seed_matches')
                ->groupBy('candidate_order_product.product_id')
                ->orderByDesc('shared_orders')
                ->orderByDesc('sold_quantity')
                ->orderByDesc('avg_seed_matches')
                ->limit(max($seedProductIds->count() * 60, 140));

            $this->applyExcludedTitlePatterns($query, 'products.name');

            return $query->get()
                ->mapWithKeys(function ($row) {
                    return [
                        (int) $row->product_id => [
                            'shared_orders' => (int) $row->shared_orders,
                            'sold_quantity' => (int) $row->sold_quantity,
                            'avg_seed_matches' => (float) $row->avg_seed_matches,
                        ],
                    ];
                });
        });
    }


    private function resolveMonthlyBestsellerMetrics(): Collection
    {
        return Helper::rememberCache('product-recommendations.monthly-bestsellers', now()->addMinutes(30), function () {
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
                ->selectRaw('order_product.product_id as product_id')
                ->selectRaw('SUM(order_product.quantity) as sold_quantity')
                ->selectRaw('COUNT(DISTINCT order_product.order_id) as order_count')
                ->groupBy('order_product.product_id')
                ->orderByDesc('sold_quantity')
                ->orderByDesc('order_count')
                ->limit(self::MONTHLY_BESTSELLER_LIMIT);

            $this->applyExcludedTitlePatterns($query, 'products.name');

            return $query->get()
                ->values()
                ->mapWithKeys(function ($row, $index) {
                    return [
                        (int) $row->product_id => [
                            'rank' => $index + 1,
                            'sold_quantity' => (int) $row->sold_quantity,
                            'order_count' => (int) $row->order_count,
                        ],
                    ];
                });
        });
    }


    private function resolveTitleCandidateIds(
        Collection $searchTokens,
        Collection $excludedProductIds,
        int $limit
    ): Collection {
        $searchTokens = $searchTokens
            ->map(fn ($token) => trim((string) $token))
            ->filter()
            ->values();

        if ($searchTokens->isEmpty()) {
            return collect();
        }

        $query = Product::query()
            ->active()
            ->hasStock()
            ->hasImage()
            ->whereNotIn('id', $excludedProductIds)
            ->where(function ($query) use ($searchTokens) {
                foreach ($searchTokens as $index => $token) {
                    if ($index === 0) {
                        $query->where('products.name', 'like', '%' . $this->escapeLike($token) . '%');
                    } else {
                        $query->orWhere('products.name', 'like', '%' . $this->escapeLike($token) . '%');
                    }
                }
            })
            ->orderByDesc('viewed')
            ->limit($limit);

        $this->applyExcludedTitlePatterns($query);

        return $query->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
    }


    private function resolveAuthorPublisherFallbackCandidateIds(
        Collection $authorIds,
        Collection $publisherIds,
        Collection $excludedProductIds,
        int $limit
    ): Collection {
        if ($authorIds->isEmpty() && $publisherIds->isEmpty()) {
            return collect();
        }

        $query = Product::query()
            ->active()
            ->hasStock()
            ->hasImage()
            ->whereNotIn('id', $excludedProductIds)
            ->where(function ($query) use ($authorIds, $publisherIds) {
                if ($authorIds->isNotEmpty()) {
                    $query->whereIn('author_id', $authorIds);
                }

                if ($publisherIds->isNotEmpty()) {
                    if ($authorIds->isNotEmpty()) {
                        $query->orWhereIn('publisher_id', $publisherIds);
                    } else {
                        $query->whereIn('publisher_id', $publisherIds);
                    }
                }
            })
            ->orderByDesc('viewed')
            ->limit($limit);

        $this->applyExcludedTitlePatterns($query);

        return $query->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
    }


    private function resolvePopularFallbackCandidateIds(Collection $excludedProductIds, int $limit): Collection
    {
        $monthlyIds = $this->resolveMonthlyBestsellerMetrics()
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $excludedProductIds->contains($id))
            ->take($limit)
            ->values();

        $query = Product::query()
            ->active()
            ->hasStock()
            ->hasImage()
            ->whereNotIn('id', $excludedProductIds)
            ->orderByDesc('viewed')
            ->limit($limit * 2);

        $this->applyExcludedTitlePatterns($query);

        return $monthlyIds
            ->merge($query->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->take($limit)
            ->values();
    }


    private function buildSeedTitleProfile(Collection $seedProducts): array
    {
        $searchTokens = collect();
        $weights = [];

        foreach ($seedProducts as $seedProduct) {
            $tokens = collect($this->extractTitleTokens((string) $seedProduct->name))->values();

            foreach ($tokens as $index => $token) {
                $normalizedToken = $this->normalizeTitleToken($token);

                if ($normalizedToken === '') {
                    continue;
                }

                $positionWeight = $index === 0 ? 1.55 : ($index < 3 ? 1.25 : 1.0);
                $weights[$normalizedToken] = ($weights[$normalizedToken] ?? 0.0) + $positionWeight;
            }

            foreach ($tokens->unique()->all() as $token) {
                $searchTokens->push($token);
            }
        }

        return [
            'search_tokens' => $searchTokens
                ->unique()
                ->sortByDesc(fn ($token) => mb_strlen((string) $token))
                ->take(self::TITLE_SEARCH_TOKEN_LIMIT)
                ->values(),
            'weights' => collect($weights)
                ->sortDesc()
                ->take(self::TITLE_WEIGHT_LIMIT),
        ];
    }


    private function extractTitleTokens(string $title): array
    {
        $cleanTitle = trim(Str::lower($title));

        if ($cleanTitle === '') {
            return [];
        }

        $normalizedWhitespace = preg_replace('/[^\pL\pN]+/u', ' ', $cleanTitle) ?: '';
        $tokens = preg_split('/\s+/u', trim($normalizedWhitespace)) ?: [];

        return collect($tokens)
            ->map(fn ($token) => trim((string) $token))
            ->filter(function (string $token) {
                $normalizedToken = $this->normalizeTitleToken($token);

                if ($normalizedToken === '') {
                    return false;
                }

                if (in_array($normalizedToken, self::TITLE_STOP_WORDS, true)) {
                    return false;
                }

                if (is_numeric($normalizedToken)) {
                    return strlen($normalizedToken) >= 2;
                }

                return strlen($normalizedToken) >= self::TITLE_MIN_TOKEN_LENGTH;
            })
            ->unique()
            ->values()
            ->all();
    }


    private function normalizeTitleToken(string $token): string
    {
        $normalizedToken = Str::lower(Str::ascii(trim($token)));
        $normalizedToken = preg_replace('/[^a-z0-9]+/', '', $normalizedToken) ?: '';

        return trim($normalizedToken);
    }


    private function scoreCoPurchase(array $coPurchaseData): float
    {
        if ($coPurchaseData === []) {
            return 0.0;
        }

        $sharedOrders = (int) ($coPurchaseData['shared_orders'] ?? 0);
        $soldQuantity = (int) ($coPurchaseData['sold_quantity'] ?? 0);
        $avgSeedMatches = (float) ($coPurchaseData['avg_seed_matches'] ?? 0);

        return min(140.0, ($sharedOrders * 24.0) + ($soldQuantity * 6.5) + ($avgSeedMatches * 10.0));
    }


    private function scoreTitleSimilarity(Product $product, Collection $seedTokenWeights): float
    {
        if ($seedTokenWeights->isEmpty()) {
            return 0.0;
        }

        $candidateTokens = array_values($this->extractTitleTokens((string) $product->name));

        if ($candidateTokens === []) {
            return 0.0;
        }

        $score = 0.0;
        $overlapCount = 0;
        $firstToken = null;

        foreach ($candidateTokens as $index => $token) {
            $normalizedToken = $this->normalizeTitleToken($token);

            if ($normalizedToken === '') {
                continue;
            }

            if ($index === 0) {
                $firstToken = $normalizedToken;
            }

            if (! $seedTokenWeights->has($normalizedToken)) {
                continue;
            }

            $overlapCount++;
            $positionBoost = $index === 0 ? 1.45 : ($index < 3 ? 1.2 : 1.0);
            $score += (float) $seedTokenWeights->get($normalizedToken) * 12.0 * $positionBoost;
        }

        if ($overlapCount >= 2) {
            $score += 8.0;
        }

        if ($firstToken !== null && $seedTokenWeights->has($firstToken)) {
            $score += 6.0;
        }

        return min(78.0, $score);
    }


    private function scoreMonthlyBestseller(array $monthlyData): float
    {
        if ($monthlyData === []) {
            return 0.0;
        }

        $rank = max(1, (int) ($monthlyData['rank'] ?? self::MONTHLY_BESTSELLER_LIMIT));
        $soldQuantity = (int) ($monthlyData['sold_quantity'] ?? 0);
        $orderCount = (int) ($monthlyData['order_count'] ?? 0);

        $score = max(0.0, 38.0 - ($rank * 0.22));
        $score += min(16.0, $soldQuantity * 1.15);
        $score += min(9.0, $orderCount * 0.85);

        return min(56.0, $score);
    }


    private function scoreViewed(int $viewed): float
    {
        if ($viewed <= 0) {
            return 0.0;
        }

        return min(12.0, log($viewed + 1, 10) * 6.0);
    }


    private function scoreRelationFallback(
        Product $product,
        Collection $authorIds,
        Collection $publisherIds
    ): float {
        $score = 0.0;

        if ($authorIds->contains((int) $product->author_id)) {
            $score += 6.0;
        }

        if ($publisherIds->contains((int) $product->publisher_id)) {
            $score += 3.0;
        }

        return $score;
    }


    private function resolvePricingContextFromCartItems(Collection $cartItems, Collection $seedProducts): array
    {
        $pricingRows = $cartItems->map(function ($item) {
            $associatedModel = data_get($item, 'associatedModel');
            $price = $associatedModel instanceof Product
                ? $this->resolveProductPrice($associatedModel)
                : ((float) data_get($item, 'price', 0) + (float) data_get($item, 'conditions.parsedRawValue', 0));

            return [
                'price' => max(0.0, $price),
                'quantity' => max(1, (int) data_get($item, 'quantity', 1)),
            ];
        })->filter(fn (array $row) => $row['price'] > 0);

        if ($pricingRows->isEmpty()) {
            return $this->resolvePricingContextFromProducts($seedProducts);
        }

        $weightedQuantity = max(1, (int) $pricingRows->sum('quantity'));
        $weightedTotal = $pricingRows->sum(fn (array $row) => $row['price'] * $row['quantity']);

        return $this->makePricingContext($weightedTotal / $weightedQuantity);
    }


    private function resolvePricingContextFromProducts(Collection $seedProducts): array
    {
        $referencePrice = $seedProducts
            ->map(fn (Product $product) => $this->resolveProductPrice($product))
            ->filter(fn ($price) => $price > 0)
            ->avg();

        return $this->makePricingContext((float) ($referencePrice ?: 12.0));
    }


    private function makePricingContext(float $referencePrice): array
    {
        $referencePrice = $referencePrice > 0 ? $referencePrice : 12.0;
        $floor = max(7.5, min(18.0, $referencePrice * 0.55));
        $hardFloor = max(5.0, min($floor - 0.75, $referencePrice * 0.38));
        $targetMin = max($floor, $referencePrice * 0.82);
        $targetMax = max($targetMin + 1.5, min(32.0, $referencePrice * 1.35));
        $softCeiling = max($targetMax + 2.5, min(38.0, $referencePrice * 1.85));

        return [
            'reference_price' => round($referencePrice, 2),
            'hard_floor' => round($hardFloor, 2),
            'floor' => round($floor, 2),
            'target_min' => round($targetMin, 2),
            'target_max' => round($targetMax, 2),
            'soft_ceiling' => round($softCeiling, 2),
        ];
    }


    private function scorePriceFit(float $candidatePrice, array $pricingContext): float
    {
        if ($candidatePrice <= 0) {
            return -25.0;
        }

        $hardFloor = (float) $pricingContext['hard_floor'];
        $floor = (float) $pricingContext['floor'];
        $targetMin = (float) $pricingContext['target_min'];
        $targetMax = (float) $pricingContext['target_max'];
        $softCeiling = (float) $pricingContext['soft_ceiling'];

        if ($candidatePrice < $hardFloor) {
            return -55.0;
        }

        if ($candidatePrice < $floor) {
            $progress = ($candidatePrice - $hardFloor) / max($floor - $hardFloor, 0.01);

            return -32.0 + (max(0.0, min(1.0, $progress)) * 12.0);
        }

        if ($candidatePrice <= $targetMin) {
            $progress = ($candidatePrice - $floor) / max($targetMin - $floor, 0.01);

            return 10.0 + (max(0.0, min(1.0, $progress)) * 14.0);
        }

        if ($candidatePrice <= $targetMax) {
            return 24.0;
        }

        if ($candidatePrice <= $softCeiling) {
            $progress = ($candidatePrice - $targetMax) / max($softCeiling - $targetMax, 0.01);

            return 24.0 - (max(0.0, min(1.0, $progress)) * 18.0);
        }

        $overflow = ($candidatePrice - $softCeiling) / max($softCeiling, 1.0);

        return 6.0 - (min(1.5, max(0.0, $overflow)) * 24.0);
    }


    private function resolveProductPrice(Product $product): float
    {
        return max(0.0, (float) $product->special());
    }


    private function applyExcludedTitlePatterns($query, string $column = 'products.name'): void
    {
        $query->where(function ($query) use ($column) {
            foreach (self::EXCLUDED_TITLE_PATTERNS as $pattern) {
                $query->where($column, 'not like', $pattern);
            }
        });
    }


    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }


    private function hasPortraitImage(Product $product): bool
    {
        [$width, $height] = $this->resolveProductImageDimensions($product);

        if (! $width || ! $height) {
            return false;
        }

        return $height > $width;
    }


    private function isVerticalBookmarkerCandidate(Product $product): bool
    {
        if ($this->hasPortraitImage($product)) {
            return true;
        }

        $normalizedName = strtolower(trim((string) $product->name));

        if ($normalizedName === '') {
            return false;
        }

        if (str_starts_with($normalizedName, '3d bookmark')) {
            return false;
        }

        return str_starts_with($normalizedName, 'bookmarker');
    }


    private function resolveProductImageDimensions(Product $product): array
    {
        $imagePath = $this->resolveProductImagePath($product);

        if (! $imagePath) {
            return [0, 0];
        }

        if (isset($this->productImageDimensions[$imagePath])) {
            return $this->productImageDimensions[$imagePath];
        }

        if (! is_file($imagePath)) {
            return $this->productImageDimensions[$imagePath] = [0, 0];
        }

        $dimensions = @getimagesize($imagePath);

        if (! $dimensions || count($dimensions) < 2) {
            return $this->productImageDimensions[$imagePath] = [0, 0];
        }

        return $this->productImageDimensions[$imagePath] = [
            (int) $dimensions[0],
            (int) $dimensions[1],
        ];
    }


    private function resolveProductImagePath(Product $product): ?string
    {
        $resolvedImageUrl = trim((string) $product->image);
        $resolvedImagePath = $resolvedImageUrl !== '' ? parse_url($resolvedImageUrl, PHP_URL_PATH) : null;

        if (is_string($resolvedImagePath) && $resolvedImagePath !== '') {
            return public_path(ltrim($resolvedImagePath, '/'));
        }

        $storedImagePath = trim((string) $product->getRawOriginal('image'));

        if ($storedImagePath === '') {
            return null;
        }

        $webpRelativePath = preg_replace('/\.[^.]+$/', '.webp', parse_url($storedImagePath, PHP_URL_PATH) ?: $storedImagePath);

        if (! $webpRelativePath) {
            return null;
        }

        return public_path(ltrim($webpRelativePath, '/'));
    }
}
