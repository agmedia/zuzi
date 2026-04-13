<?php

namespace App\Services;

use App\Models\Front\Catalog\CategoryProducts;
use App\Models\Front\Catalog\Product;
use Illuminate\Support\Collection;

class ProductRecommendationService
{
    public function forCartItems(Collection $cartItems, int $limit = 10): Collection
    {
        $seedProductIds = $cartItems->map(fn ($item) => (int) data_get($item, 'id'))
            ->filter()
            ->unique()
            ->values();

        return $this->forProductIds($seedProductIds, $limit);
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
            ->with(['author', 'action'])
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
            ->with(['author', 'action'])
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
}
