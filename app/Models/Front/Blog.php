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
     * Resolve related products in the saved order.
     */
    public function relatedProducts(int $limit = 12): Collection
    {
        $relatedProducts = $this->related_products;

        if (is_string($relatedProducts)) {
            $decodedRelatedProducts = json_decode($relatedProducts, true);
            $relatedProducts = is_array($decodedRelatedProducts) ? $decodedRelatedProducts : [];
        }

        $ids = collect($relatedProducts ?: [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

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
