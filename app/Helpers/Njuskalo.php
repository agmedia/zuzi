<?php

namespace App\Helpers;

use App\Models\Back\Catalog\Category;
use App\Models\Back\Catalog\Product\Product;
use Illuminate\Support\Facades\Log;

/**
 *
 */
class Njuskalo
{

    /**
     * @var array
     */
    private $response = [];


    /**
     * @return array
     */
    public function getItems(): array
    {
        $products = Product::query()->where('status', 1)
                                    ->where('price', '!=', 0)
                                    ->where('quantity', '!=', 0)
                                    ->select('id', 'name', 'description', 'quantity', 'status', 'price', 'image', 'pages', 'dimensions', 'origin', 'url', 'letter', 'condition', 'binding', 'year')
                                    ->with(['categories' => function ($query) {
                                        return $query->select('id', 'slug');
                                    }])
                                    ->get();

        foreach ($products as $product) {
            $category = (isset($this->categories[0]['slug'])) ? $this->categories[0]['slug'] : 'ostala-literatura';

            $this->response[] = [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $this->getDescription($product),
                'group' => config('settings.njuskalo.sync.' . $category),
                'price' => $product->price,
                'slug' => $product->url,
                'image' => asset($product->image),
            ];
        }

        return $this->response;
    }


    /**
     * @param Product $product
     *
     * @return string
     */
    private function getDescription(Product $product): string
    {
        $str = '';

        if ($product->description != '') {
            $str .=  preg_replace('/[[:cntrl:]]/', '', $product->description) . '<br><br>';


        }
        if ($product->pages) {
            $str .= 'Stranica: ' . $product->pages . '<br>';
        }
        if ($product->dimensions) {
            $str .= 'Dimenzije: ' . $product->dimensions . '<br>';
        }
        if ($product->origin) {
            $str .= 'Jezik: ' . $product->origin . '<br>';
        }
        if ($product->letter) {
            $str .= 'Pismo: ' . $product->letter . '<br>';
        }
        if ($product->condition) {
            $str .= 'Stanje: ' . $product->condition . '<br>';
        }
        if ($product->binding) {
            $str .= 'Uvez: ' . $product->binding . '<br>';
        }
        if ($product->year) {
            $str .= 'Godina: ' . $product->year . '<br>';
        }

        return $str;
    }
}
