<?php

namespace App\Services;

use App\Models\Front\Catalog\CategoryProducts;
use App\Models\Front\Catalog\Product;
use Illuminate\Support\Collection;

class ProductRecommendationService
{
    private array $productImageDimensions = [];

    public function forCartItems(Collection $cartItems, int $limit = 10): Collection
    {
        $seedProductIds = $cartItems->map(fn ($item) => (int) data_get($item, 'id'))
            ->filter()
            ->unique()
            ->values();

        return $this->forProductIds($seedProductIds, $limit);
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

        $seedProducts = Product::query()
            ->whereIn('id', $seedProductIds)
            ->get(['id', 'author_id', 'publisher_id']);

        if ($seedProducts->isEmpty()) {
            return collect();
        }

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

        $categoryTargets = $this->getCategoryTargets($seedProductIds);
        $recommendations = collect();

        if ($categoryTargets['subcategory_ids']->isNotEmpty()) {
            $recommendations = $this->appendRecommendationProducts(
                $recommendations,
                $this->getRecommendationProductIdsForCategories(
                    $categoryTargets['subcategory_ids'],
                    $seedProductIds,
                    false,
                    $limit * 4
                ),
                $limit
            );
        }

        if ($recommendations->count() < $limit && $categoryTargets['main_category_ids']->isNotEmpty()) {
            $recommendations = $this->appendRecommendationProducts(
                $recommendations,
                $this->getRecommendationProductIdsForCategories(
                    $categoryTargets['main_category_ids'],
                    $seedProductIds->merge($recommendations->pluck('id'))->unique()->values(),
                    true,
                    $limit * 4
                ),
                $limit
            );
        }

        if ($recommendations->count() < $limit && $authorIds->isNotEmpty()) {
            $recommendations = $this->appendRecommendationProducts(
                $recommendations,
                Product::query()
                    ->active()
                    ->hasStock()
                    ->hasImage()
                    ->whereNotIn('id', $seedProductIds->merge($recommendations->pluck('id'))->unique()->values())
                    ->whereIn('author_id', $authorIds)
                    ->inRandomOrder()
                    ->limit($limit * 3)
                    ->pluck('id'),
                $limit
            );
        }

        if ($recommendations->count() < $limit && $publisherIds->isNotEmpty()) {
            $recommendations = $this->appendRecommendationProducts(
                $recommendations,
                Product::query()
                    ->active()
                    ->hasStock()
                    ->hasImage()
                    ->whereNotIn('id', $seedProductIds->merge($recommendations->pluck('id'))->unique()->values())
                    ->whereIn('publisher_id', $publisherIds)
                    ->inRandomOrder()
                    ->limit($limit * 2)
                    ->pluck('id'),
                $limit
            );
        }

        $excludedIds = $seedProductIds
            ->merge($recommendations->pluck('id'))
            ->unique()
            ->values();

        $fallback = Product::query()
            ->active()
            ->hasStock()
            ->hasImage()
            ->with(['author', 'action', 'categories'])
            ->withReviewSummary()
            ->whereNotIn('id', $excludedIds)
            ->where(function ($query) {
                $query->whereBetween('price', [10, 15])
                    ->orWhereBetween('special', [10, 15]);
            })
            ->inRandomOrder()
            ->limit($limit * 3)
            ->get()
            ->filter(fn (Product $product) => $this->matchesRecommendationPrice($product))
            ->take($limit - $recommendations->count())
            ->values();

        return $recommendations->concat($fallback)->take($limit)->values();
    }


    private function getCategoryTargets(Collection $seedProductIds): array
    {
        $categoryRows = CategoryProducts::query()
            ->join('categories', 'categories.id', '=', 'product_category.category_id')
            ->whereIn('product_category.product_id', $seedProductIds)
            ->get([
                'product_category.category_id',
                'categories.parent_id',
            ]);

        $subcategoryIds = $categoryRows->filter(fn ($row) => (int) $row->parent_id !== 0)
            ->pluck('category_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $mainCategoryIds = $categoryRows->map(function ($row) {
            $categoryId = (int) $row->category_id;
            $parentId = (int) $row->parent_id;

            return $parentId !== 0 ? $parentId : $categoryId;
        })
            ->filter()
            ->unique()
            ->values();

        return [
            'subcategory_ids' => $subcategoryIds,
            'main_category_ids' => $mainCategoryIds,
        ];
    }


    private function getRecommendationProductIdsForCategories(
        Collection $categoryIds,
        Collection $excludedProductIds,
        bool $includeSubcategories,
        int $limit
    ): Collection {
        if ($categoryIds->isEmpty()) {
            return collect();
        }

        return CategoryProducts::query()
            ->select('product_category.product_id')
            ->join('categories', 'categories.id', '=', 'product_category.category_id')
            ->whereNotIn('product_category.product_id', $excludedProductIds)
            ->where(function ($query) use ($categoryIds, $includeSubcategories) {
                $query->whereIn('categories.id', $categoryIds);

                if ($includeSubcategories) {
                    $query->orWhereIn('categories.parent_id', $categoryIds);
                }
            })
            ->groupBy('product_category.product_id')
            ->inRandomOrder()
            ->limit($limit)
            ->pluck('product_category.product_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
    }


    private function appendRecommendationProducts(Collection $recommendations, Collection $candidateIds, int $limit): Collection
    {
        if ($candidateIds->isEmpty() || $recommendations->count() >= $limit) {
            return $recommendations;
        }

        $resolved = $this->resolveRecommendationProducts(
            $candidateIds->map(fn ($id) => (int) $id)->filter()->unique()->values(),
            $limit - $recommendations->count()
        );

        return $recommendations->concat($resolved)->unique('id')->values();
    }


    private function resolveRecommendationProducts(Collection $candidateIds, int $limit): Collection
    {
        if ($candidateIds->isEmpty()) {
            return collect();
        }

        return Product::query()
            ->active()
            ->hasStock()
            ->hasImage()
            ->with(['author', 'action', 'categories'])
            ->withReviewSummary()
            ->whereIn('id', $candidateIds)
            ->get()
            ->filter(fn (Product $product) => $this->matchesRecommendationPrice($product))
            ->sortBy(fn (Product $product) => $candidateIds->search($product->id))
            ->take($limit)
            ->values();
    }


    private function matchesRecommendationPrice(Product $product): bool
    {
        $resolvedPrice = (float) $product->special();

        return $resolvedPrice >= 10.0 && $resolvedPrice <= 15.0;
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
