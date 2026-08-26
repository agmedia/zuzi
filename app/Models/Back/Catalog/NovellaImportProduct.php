<?php

namespace App\Models\Back\Catalog;

use App\Models\Back\Catalog\Product\Product;
use Illuminate\Database\Eloquent\Model;

class NovellaImportProduct extends Model
{
    protected $table = 'novella_import_products';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'additional_image_urls' => 'array',
        'source_categories' => 'array',
        'source_genres' => 'array',
        'detail_payload' => 'array',
        'remote_product_id' => 'integer',
        'feed_position' => 'integer',
        'price_eur' => 'float',
        'sale_price_eur' => 'float',
        'pages' => 'integer',
        'publication_year' => 'integer',
        'is_current' => 'boolean',
        'checked_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'imported_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function getUiStatusAttribute(): string
    {
        if (! $this->is_current) {
            return 'missing';
        }

        if (in_array($this->check_status, ['conflict', 'error'], true)) {
            return $this->check_status;
        }

        if ($this->product_id) {
            if (! $this->imported_at) {
                return 'existing';
            }

            return hash_equals((string) $this->source_hash, (string) $this->imported_hash)
                ? 'imported'
                : 'changed';
        }

        if (! hash_equals((string) $this->source_hash, (string) $this->checked_source_hash)) {
            return 'pending';
        }

        return $this->check_status === 'new' ? 'new' : 'pending';
    }
}
