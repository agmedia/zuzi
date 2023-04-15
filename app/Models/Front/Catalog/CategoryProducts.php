<?php

namespace App\Models\Front\Catalog;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class CategoryProducts extends Model
{
    /**
     * @var string
     */
    protected $table = 'product_category';

    /**
     * @var array
     */
    protected $guarded = [];
}
