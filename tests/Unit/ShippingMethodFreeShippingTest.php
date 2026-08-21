<?php

namespace Tests\Unit;

use App\Models\Front\Checkout\ShippingMethod;
use Tests\TestCase;

class ShippingMethodFreeShippingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'session.cart' => 'zuzi_cart',
            'settings.free_shipping' => 50,
        ]);

        session()->start();
        session()->forget('zuzi_cart_coupon');
    }

    public function test_order_above_threshold_has_free_shipping_without_a_code(): void
    {
        $shipping = $this->shippingMethod();

        $this->assertTrue(ShippingMethod::hasFreeShipping($shipping, 75));
        $this->assertSame(0.0, ShippingMethod::priceForTotal($shipping, 75));
    }

    public function test_any_applied_code_disables_all_free_shipping_thresholds(): void
    {
        session(['zuzi_cart_coupon' => 'HVALA10-TEST123']);

        $regular = $this->shippingMethod();
        $custom = $this->shippingMethod('custom', ['free_shipping_from' => 25]);
        $world = $this->shippingMethod('gls_world');

        foreach ([$regular, $custom, $world] as $shipping) {
            $this->assertFalse(ShippingMethod::hasFreeShipping($shipping, 250));
            $this->assertSame(5.0, ShippingMethod::priceForTotal($shipping, 250));
        }
    }

    public function test_empty_gls_world_threshold_explicitly_disables_free_shipping(): void
    {
        foreach ([null, ''] as $disabledThreshold) {
            $shipping = $this->shippingMethod('gls_world', [
                'free_shipping_from' => $disabledThreshold,
            ]);

            $this->assertNull(ShippingMethod::freeShippingThreshold($shipping));
            $this->assertFalse(ShippingMethod::hasFreeShipping($shipping, 250));
            $this->assertSame(5.0, ShippingMethod::priceForTotal($shipping, 250));
        }
    }

    public function test_legacy_gls_world_method_without_threshold_keeps_default(): void
    {
        $shipping = $this->shippingMethod('gls_world');

        $this->assertSame(100.0, ShippingMethod::freeShippingThreshold($shipping));
        $this->assertTrue(ShippingMethod::hasFreeShipping($shipping, 100));
        $this->assertSame(0.0, ShippingMethod::priceForTotal($shipping, 100));
    }

    private function shippingMethod(string $code = 'gls', array $data = []): object
    {
        return (object) [
            'code' => $code,
            'geo_zone' => 1,
            'data' => (object) array_merge([
                'price' => 5,
            ], $data),
        ];
    }
}
