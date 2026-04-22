<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogCtaButton extends Model
{
    /**
     * @var array<int, string>
     */
    public const STYLES = [
        'primary',
        'secondary',
        'outline',
    ];

    /**
     * @var string
     */
    protected $table = 'blog_cta_buttons';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'cta_block_id',
        'label',
        'url',
        'icon',
        'style',
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
    public function block(): BelongsTo
    {
        return $this->belongsTo(BlogCtaBlock::class, 'cta_block_id');
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
