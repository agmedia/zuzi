<?php

namespace App\Helpers;

use App\Models\Back\Catalog\Category;
use App\Models\Back\Catalog\Product\Product;
use Illuminate\Support\Facades\Log;

/**
 *
 */
class Xmlexport
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
      /*  $products = Product::query()->where('status', 1)

                                   ->where('id', '<', 100)
                                    ->where('price', '!=', 0)
                                    ->where('quantity', '!=', 0)
                                    ->select('id', 'name', 'description', 'quantity', 'status', 'price', 'image', 'pages', 'dimensions', 'origin', 'url', 'letter', 'condition', 'binding', 'year')
                                    ->with(['categories' => function ($query) {
                                        return $query->select('id', 'slug');
                                    }])
                                    ->get();*/

        $products = Product::query()
            ->with('images', 'kat', 'subkat', 'publisher', 'author')
            ->take(1000)
            ->get();




        foreach ($products as $product) {

            $this->response[] = [
                'id' => $product->id,
                'name' => $product->name,
                'description' => preg_replace('/[[:cntrl:]]/', '', $product->description),
                'cat' =>  $product->kat,
                'subcat' =>  $product->subkat,
                'author' =>  $product->author,
                'author_id' =>  $product->author_id,
                'publisher' =>  $product->publisher,
                'publisher_id' =>  $product->publisher_id,
                'price' => $product->price,
                'sku' => $product->sku,
                'polica' => $product->polica,
                'quantity' => $product->quantity,
                'tax_id' => $product->tax_id,
                'meta_title' => $product->meta_title,
                'meta_description' => $product->meta_description,
                'pages' => $product->pages,
                'dimensions' => $product->dimensions,
                'origin' => $product->origin,
                'letter' => $product->letter,
                'condition' => $product->condition,
                'binding' => $product->binding,
                'year' => $product->year,
                'status' => $product->status,

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
