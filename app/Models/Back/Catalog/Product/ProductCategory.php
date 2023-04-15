<?php

namespace App\Models\Back\Catalog\Product;

use App\Models\Back\Catalog\Category;
use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{

    /**
     * @var string $table
     */
    protected $table = 'product_category';

    /**
     * @var array $guarded
     */
    protected $guarded = [];


    /**
     * Update Product categories.
     *
     * @param int $category
     * @param int $product_id
     *
     * @return array
     */
    public static function storeData(int $category, int $product_id): array
    {
        $cat = Category::find($category);

        if ($cat) {
            $created = [];
            self::where('product_id', $product_id)->delete();

            if ($cat->parent_id) {
                $created[] = self::insert([
                    'product_id'  => $product_id,
                    'category_id' => $cat->parent_id
                ]);
            }

            $created[] = self::insert([
                'product_id'  => $product_id,
                'category_id' => $category
            ]);
        }

        return $created;
    }
}
