<?php

namespace App\Models\Front;

use App\Models\BlogCtaBlock;
use App\Models\Front\Catalog\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Blog extends Model
{

    /**
     * @var string
     */
    protected $table = 'pages';

    /**
     * @var array
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'publish_date' => 'datetime',
        'related_products' => 'array',
    ];


    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'slug';
    }


    /**
     * @param $value
     *
     * @return array|string|string[]
     */
    public function getImageAttribute($value)
    {
        return config('settings.images_domain') . str_replace('.jpg', '.webp', $value);
    }


    /**
     *
     */
    protected static function booted()
    {
        static::addGlobalScope('blogs', function (Builder $builder) {
            $builder->where('group', 'blog');
        });
    }


    /**
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1)->orderBy('created_at', 'desc');
    }


    /**
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', 0);
    }


    /**
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeLast(Builder $query, $count = 9): Builder
    {
        return $query->orderBy('updated_at', 'desc')->limit($count);
    }


    /**
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopePopular(Builder $query, $count = 9): Builder
    {
        return $query->orderBy('viewed', 'desc')->limit($count);
    }


    /**
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeFeatured(Builder $query, $count = 9): Builder
    {
        return $query->where('featured', 1)->orderBy('updated_at', 'desc')->limit($count);
    }


    /**
     * @return HasMany
     */
    public function ctaBlocks(): HasMany
    {
        return $this->hasMany(BlogCtaBlock::class, 'blog_post_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }


    /**
     * Resolve active CTA blocks with active buttons in the saved order.
     */
    public function activeCtaBlocks(): Collection
    {
        return $this->ctaBlocks()
            ->active()
            ->ordered()
            ->with(['buttons' => function ($query) {
                $query->active()->ordered();
            }])
            ->get()
            ->filter(fn (BlogCtaBlock $block) => $block->buttons->isNotEmpty())
            ->values();
    }


    /**
     * Resolve normalized related product ids from the saved payload.
     */
    public function relatedProductIds(): Collection
    {
        $relatedProducts = $this->related_products;

        while (is_string($relatedProducts)) {
            $decodedRelatedProducts = json_decode($relatedProducts, true);

            if (! is_array($decodedRelatedProducts) && ! is_string($decodedRelatedProducts)) {
                $relatedProducts = [];
                break;
            }

            $relatedProducts = $decodedRelatedProducts;
        }

        return collect(is_array($relatedProducts) ? $relatedProducts : [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();
    }


    /**
     * Resolve the newest visible blog review that references the given product.
     */
    public static function latestActiveRelatedReviewForProduct(int $productId): ?self
    {
        return static::query()
            ->where('status', 1)
            ->whereNotNull('related_products')
            ->where(function (Builder $query) {
                $query->whereNull('publish_date')
                    ->orWhere('publish_date', '<=', now());
            })
            ->orderByDesc('publish_date')
            ->orderByDesc('created_at')
            ->get()
            ->first(fn (self $blog) => $blog->relatedProductIds()->contains($productId));
    }


    /**
     * Build a plain-text review teaser from the blog content.
     */
    public function reviewTeaser(int $characters = 200): string
    {
        $content = strip_tags((string) ($this->description ?: $this->short_description ?: ''));
        $content = trim(preg_replace('/\s+/u', ' ', $content) ?: '');

        if (mb_strlen($content) <= $characters) {
            return $content;
        }

        $ending = '...';
        $availableCharacters = max($characters - mb_strlen($ending), 0);

        return rtrim(mb_substr($content, 0, $availableCharacters)) . $ending;
    }


    /**
     * Resolve related products in the saved order.
     */
    public function relatedProducts(int $limit = 12): Collection
    {
        $ids = $this->relatedProductIds();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Product::query()
            ->active()
            ->hasStock()
            ->with(['author', 'action'])
            ->withReviewSummary()
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Product $product) => $ids->search($product->id))
            ->values()
            ->take($limit);
    }
}
