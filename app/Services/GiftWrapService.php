<?php

namespace App\Services;

use App\Helpers\Currency;
use App\Models\Front\Catalog\Product;
use Illuminate\Support\Str;

class GiftWrapService
{
    public const CART_ITEM_TYPE = 'gift_wrap';
    public const PRICE = 5.0;
    public const DEFAULT_IMAGE = 'media/img/zuzi-logo.webp';
    public const INFO_TEXT = 'Usluga uključuje ukrasni papir, mašnu i pripremu knjige za poklon.';

    public static function cartItemId(Product|int $product): string
    {
        $productId = $product instanceof Product ? $product->id : (int) $product;

        return 'gift-wrap-' . $productId;
    }

    public static function formatName(Product $product): string
    {
        $parts = array_filter([
            'Zamatanje',
            trim((string) $product->sku),
            trim((string) $product->name),
        ]);

        return implode(' - ', $parts);
    }

    public static function buildCartItemRequest(array $payload): array
    {
        $productId = static::resolveProductId($payload);

        return [
            'item' => [
                'id' => static::cartItemId($productId),
                'type' => static::CART_ITEM_TYPE,
                'product_id' => $productId,
                'quantity' => max(1, (int) ($payload['quantity'] ?? 1)),
            ],
        ];
    }

    public static function buildCartItem(Product $product, int $quantity = 1): array
    {
        $quantity = max(1, $quantity);
        $price = static::PRICE;
        $secondaryRate = Currency::secondary() ? Currency::secondary()->value : null;
        $secondaryPrice = $secondaryRate ? number_format($price * $secondaryRate, 2, ',', '.') . ' kn' : null;
        $formattedPrice = '€ ' . number_format($price, 2, ',', '.');

        $associatedModel = (object) [
            'image' => $product->image ?: asset(static::DEFAULT_IMAGE),
            'quantity' => max(1, (int) $product->quantity),
            'secondary_price' => $secondaryPrice,
            'main_price_text' => $formattedPrice,
            'main_special_text' => $formattedPrice,
            'secondary_price_text' => $secondaryPrice,
            'secondary_special_text' => $secondaryPrice,
            'dataLayer' => [
                'item_id' => 'GIFT-WRAP-' . $product->id,
                'item_name' => static::formatName($product),
                'price' => number_format($price, 2, '.', ''),
                'currency' => 'EUR',
                'discount' => number_format(0, 2, '.', ''),
                'item_category' => 'Usluga',
                'item_category2' => 'Zamatanje poklona',
                'quantity' => $quantity,
            ],
        ];

        return [
            'id' => static::cartItemId($product),
            'name' => static::formatName($product),
            'price' => $price,
            'sec_price' => $secondaryRate ? $price * $secondaryRate : null,
            'quantity' => $quantity,
            'associatedModel' => $associatedModel,
            'attributes' => [
                'path' => $product->url,
                'tax' => ['rate' => 25],
                'item_type' => static::CART_ITEM_TYPE,
                'is_editable_quantity' => false,
                'wrapped_product_id' => (int) $product->id,
                'wrapped_product_sku' => (string) $product->sku,
                'wrapped_product_name' => (string) $product->name,
                'gift_wrap_info' => static::INFO_TEXT,
            ],
        ];
    }

    public static function isGiftWrapItem($item): bool
    {
        return data_get(static::extractAttributes($item), 'item_type') === static::CART_ITEM_TYPE;
    }

    public static function isEligibleProduct(Product $product): bool
    {
        return ! static::isBookmarkerProduct($product);
    }

    public static function isBookmarkerProduct(Product $product): bool
    {
        $categories = $product->relationLoaded('categories') ? $product->categories : null;

        if ($categories && $categories->contains(fn ($category) => (string) data_get($category, 'slug') === 'bookmarkeri')) {
            return true;
        }

        if ($product->categories()->where('slug', 'bookmarkeri')->exists()) {
            return true;
        }

        if ((string) data_get($product->category(), 'slug') === 'bookmarkeri') {
            return true;
        }

        if ((string) data_get($product->subcategory(), 'slug') === 'bookmarkeri') {
            return true;
        }

        $normalizedName = Str::lower(trim((string) $product->name));

        if ($normalizedName === '') {
            return false;
        }

        if (str_starts_with($normalizedName, '3d bookmark')) {
            return false;
        }

        return str_starts_with($normalizedName, 'bookmarker');
    }

    public static function extractWrappedProductId($item): int
    {
        return (int) data_get(static::extractAttributes($item), 'wrapped_product_id', 0);
    }

    public static function resolveProductId(array $payload): int
    {
        $productId = (int) ($payload['product_id'] ?? 0);

        if ($productId > 0) {
            return $productId;
        }

        $id = (string) ($payload['id'] ?? '');

        if (preg_match('/gift-wrap-(\d+)/', $id, $matches)) {
            return (int) ($matches[1] ?? 0);
        }

        return 0;
    }

    private static function extractAttributes($item): array
    {
        $attributes = data_get($item, 'attributes', []);

        return json_decode(json_encode($attributes), true) ?: [];
    }
}
