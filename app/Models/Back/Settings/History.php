<?php

namespace App\Models\Back\Settings;

use App\Models\Back\Catalog\Product\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class History extends Model
{

    /**
     * @var string
     */
    protected $table = 'history_log';

    /**
     * @var array
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * @var null|string
     */
    private $target_url = null;


    /**
     * @return string|null
     */
    public function getTargetUrl(): ?string
    {
        return $this->tagret_url;
    }


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id')->first();
    }


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function product()
    {
        return $this->hasOne(Product::class, 'id', 'target_id');
    }


    /**
     * @param string $type
     * @param int    $id
     *
     * @return mixed
     */
    public function getTargetModel()
    {
        if ($this->target == 'product') {
            $product = Product::find($this->target_id);
            $this->tagret_url = route('products.edit', ['product' => $product]);

            return $product;
        }
    }
}
