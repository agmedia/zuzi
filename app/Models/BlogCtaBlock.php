<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlogCtaBlock extends Model
{
    /**
     * @var string
     */
    protected $table = 'blog_cta_blocks';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'blog_post_id',
        'title',
        'description',
        'sort_order',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo
     */
    public function blog(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Front\Blog::class, 'blog_post_id');
    }

    /**
     * @return HasMany
     */
    public function buttons(): HasMany
    {
        return $this->hasMany(BlogCtaButton::class, 'cta_block_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
