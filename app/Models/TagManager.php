<?php

namespace App\Models;

use App\Helpers\Helper;
use App\Models\Back\Orders\Order;
use App\Models\Back\Orders\OrderProduct;
use App\Models\Front\Catalog\Product;
use Darryldecode\Cart\CartCollection;

/**
 * Class Sitemap
 * @package App\Models
 */
class TagManager
{

    /**
     * @param Order $order
     *
     * @return array
     */
    public static function getGoogleSuccessDataLayer(Order $order)
    {
        return [
            'event'     => 'purchase',
            'ecommerce' => static::getGooglePurchaseEventParams($order),
        ];
    }


    /**
     * Shared purchase params for browser and server-side GA4 events.
     */
    public static function getGooglePurchaseEventParams(Order $order): array
    {
        $products = [];
        $shipping = 0;
        $tax      = 0;

        foreach ($order->products as $product) {
            $products[] = static::getGoogleOrderProductDataLayer($product);
        }

        foreach ($order->totals()->get() as $total) {
            if ($total->code == 'subtotal') {
                $tax += $total->value - ($total->value / 1.05);
            }
            if ($total->code == 'shipping') {
                $tax      += $total->value - ($total->value / 1.25);
                $shipping = $total->value;
            }
        }

        return [
            'transaction_id' => (string) $order->id,
            'affiliation'    => 'Zuzi webshop',
            'value'          => static::normalizeGoogleNumber($order->total),
            'tax'            => static::normalizeGoogleNumber($tax),
            'shipping'       => static::normalizeGoogleNumber($shipping),
            'currency'       => 'EUR',
            'items'          => $products
        ];
    }


    /**
     * @param Product $product
     *
     * @return array
     */
    public static function getGoogleProductDataLayer(Product $product): array
    {
        $discount = 0;

        if ($product->main_price > $product->main_special) {
            $discount = Helper::calculateDiscount($product->main_price, $product->main_special);
        }

        $item = [
            'item_id'        => $product->sku,
            'item_name'      => $product->name,
            'price'          => number_format((float) str_replace(',', '.', $product->main_price), 2),
            'currency'       => 'EUR',
            'discount'       => number_format((float) $discount, 2),
            'item_category'  => $product->category() ? $product->category()->title : '',
            'item_category2' => $product->subcategory() ? $product->subcategory()->title : '',
            'quantity'       => 1,
        ];

        return $item;
    }


    /**
     * Build purchase payload from the stored order line, not the current catalog state.
     */
    public static function getGoogleOrderProductDataLayer(OrderProduct $product): array
    {
        $realProduct = $product->real;
        $discount    = max(
            static::normalizeInputNumber($product->org_price) - static::normalizeInputNumber($product->price),
            0
        );

        return [
            'item_id'        => (string) ($realProduct ? $realProduct->sku : $product->product_id),
            'item_name'      => $product->name,
            'price'          => static::normalizeGoogleNumber($product->price),
            'currency'       => 'EUR',
            'discount'       => static::normalizeGoogleNumber($discount),
            'item_category'  => $realProduct && $realProduct->category() ? $realProduct->category()->title : '',
            'item_category2' => $realProduct && $realProduct->subcategory() ? $realProduct->subcategory()->title : '',
            'quantity'       => (int) $product->quantity,
        ];
    }


    /**
     * @param CartCollection $cart_collection
     *
     * @return array
     */
    public static function getGoogleCartDataLayer(array $cart_collection): array
    {
        $items = [];

        foreach ($cart_collection['items'] as $item) {
            $googleItem = $item->associatedModel->dataLayer;
            $googleItem['quantity'] = (int) $item->quantity;

            $items[] = $googleItem;
        }

        return $items;
    }


    private static function normalizeGoogleNumber($value): float
    {
        return round(static::normalizeInputNumber($value), 2);
    }


    private static function normalizeInputNumber($value): float
    {
        if (is_string($value)) {
            $value = trim($value);

            if (str_contains($value, ',') && str_contains($value, '.')) {
                $value = strrpos($value, ',') > strrpos($value, '.')
                    ? str_replace(',', '.', str_replace('.', '', $value))
                    : str_replace(',', '', $value);
            } elseif (str_contains($value, ',')) {
                $value = str_replace(',', '.', $value);
            }
        }

        return (float) $value;
    }

}
