<?php

namespace App\Models\Front\Checkout;

use App\Helpers\Session\CheckoutSession;
use App\Models\Back\Settings\Settings;
use App\Services\GiftVoucherService;
use Illuminate\Support\Collection;

/**
 * Class ShippingMethod
 * @package App\Models\Front\Checkout
 */
class ShippingMethod
{
    /**
     * Default free shipping threshold for GLS World.
     */
    private const GLS_WORLD_FREE_SHIPPING_FROM = 100.0;

    /**
     * @var array|false|Collection
     */
    protected $methods;


    /**
     * ShippingMethod constructor.
     */
    public function __construct()
    {
        $this->methods = $this->list();
    }


    /**
     * @param bool $only_active
     *
     * @return array|false|Collection
     */
    public function list(bool $only_active = true)
    {
        return Settings::getList('shipping', 'list.%', $only_active);
    }


    /**
     * @param int $id
     *
     * @return mixed
     */
    public function id(int $id)
    {
        return $this->methods->where('id', $id)->first();
    }


    /**
     * @param string $code
     *
     * @return mixed
     */
    public function find(string $code)
    {
        if (GiftVoucherService::isGiftVoucherShipping($code)) {
            return GiftVoucherService::shippingMethod();
        }

        //Log::info($this->methods->where('code', $code)->first()->code);
        return $this->methods->where('code', $code)->first();
    }


    /**
     * @param int $zone
     *
     * @return Collection
     */
    public function findGeo(int $zone): Collection
    {
        if (GiftVoucherService::currentCartContainsOnlyGiftVoucher()) {
            return collect([GiftVoucherService::shippingMethod()]);
        }

        $methods = collect();

        foreach ($this->methods as $method) {
            if ($method->geo_zone == $zone) {
                $methods->push($method);
            }
        }

        return $methods;
    }

    /*******************************************************************************
    *                                Copyright : AGmedia                           *
    *                              email: filip@agmedia.hr                         *
    *******************************************************************************/

    public static function condition($cart = null)
    {
        $shipping = false;
        $condition = false;

        if (CheckoutSession::hasShipping()) {
            $shipping = (new ShippingMethod())->find(CheckoutSession::getShipping());
        }

        if ($shipping) {
            $value = self::priceForTotal($shipping, $cart ? (float) $cart->getTotal() : 0.0);

            $condition = new \Darryldecode\Cart\CartCondition(array(
                'name' => $shipping->title,
                'type' => 'shipping',
                'target' => 'total', // this condition will be applied to cart's subtotal when getSubTotal() is called.
                'value' => '+' . $value,
                'attributes' => [
                    'description' => $shipping->data->short_description,
                    'geo_zone' => $shipping->geo_zone
                ]
            ));
        }

        return $condition;
    }

    /**
     * Resolve the shipping price for the current cart total.
     */
    public static function priceForTotal($shipping, float $cart_total): float
    {
        if (GiftVoucherService::isGiftVoucherShipping(data_get($shipping, 'code'))) {
            return 0.0;
        }

        if (self::hasFreeShipping($shipping, $cart_total)) {
            return 0.0;
        }

        $country_price = self::countryPrice($shipping, self::checkoutCountry());

        if ($country_price !== null) {
            return $country_price;
        }

        return (float) data_get($shipping, 'data.price', 0);
    }


    /**
     * Check if the selected shipping method qualifies for free shipping.
     */
    public static function hasFreeShipping($shipping, float $cart_total): bool
    {
        $threshold = self::freeShippingThreshold($shipping);

        if ($threshold === null) {
            return false;
        }

        if (self::hasCustomFreeShippingThreshold($shipping) || data_get($shipping, 'code') === 'gls_world') {
            return $cart_total >= $threshold;
        }

        return $cart_total > $threshold;
    }


    /**
     * Resolve the shipping method free shipping threshold.
     */
    public static function freeShippingThreshold($shipping): ?float
    {
        $custom_threshold = data_get($shipping, 'data.free_shipping_from');

        if ($custom_threshold !== null && $custom_threshold !== '') {
            return (float) $custom_threshold;
        }

        if (data_get($shipping, 'code') === 'gls_world') {
            return self::GLS_WORLD_FREE_SHIPPING_FROM;
        }

        if ((int) data_get($shipping, 'geo_zone') === 1) {
            return (float) config('settings.free_shipping');
        }

        return null;
    }


    /**
     * Determine if the shipping method has an explicit custom threshold.
     */
    private static function hasCustomFreeShippingThreshold($shipping): bool
    {
        $custom_threshold = data_get($shipping, 'data.free_shipping_from');

        return $custom_threshold !== null && $custom_threshold !== '';
    }


    /**
     * Resolve a country-specific shipping price when configured.
     */
    private static function countryPrice($shipping, ?string $country): ?float
    {
        $country = trim((string) $country);

        if ($country === '') {
            return null;
        }

        $prices = data_get($shipping, 'data.country_prices');

        if (empty($prices)) {
            return null;
        }

        foreach ((array) $prices as $key => $price) {
            $price_country = $key;
            $price_value = $price;

            if (is_array($price)) {
                $price_country = $price['country'] ?? $key;
                $price_value = $price['price'] ?? null;
            } elseif (is_object($price)) {
                $price_country = $price->country ?? $key;
                $price_value = $price->price ?? null;
            }

            if (strcasecmp(trim((string) $price_country), $country) !== 0) {
                continue;
            }

            if ($price_value === null || $price_value === '') {
                return null;
            }

            return (float) $price_value;
        }

        return null;
    }


    /**
     * Resolve the checkout country from the active session address.
     */
    private static function checkoutCountry(): ?string
    {
        if (! CheckoutSession::hasAddress()) {
            return null;
        }

        return data_get(CheckoutSession::getAddress(), 'state');
    }
}
