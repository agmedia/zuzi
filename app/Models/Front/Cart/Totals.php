<?php

namespace App\Models\Front\Cart;

require_once('Total/providers.php');

use App\Models\Back\Settings\Store\Total;
use Darryldecode\Cart\Cart;
use Illuminate\Support\Facades\Log;

/**
 * Class Total
 * @package App\Models\Front\Cart
 */
class Totals
{

    /**
     * @var
     */
    protected $cart;

    /**
     * @var
     */
    protected $coupon;

    /**
     * @var
     */
    protected $totals;

    /**
     * @var float
     */
    protected $currentSum = 0;


    /**
     * Total constructor.
     *
     * @param string $code
     * @param        $cart
     */
    public function __construct(Cart $cart)
    {
        $this->cart   = $cart;
        $this->coupon = $this->setCoupon();
        $this->totals = Total::where('status', 1)->orderBy('sort_order')->get();
    }


    /**
     * @return array
     */
    public function fetch()
    {
        $response = [];

        foreach ($this->totals as $total) {
            // Load class from providers array based on total code.
            $class = providers($total->code);
            // Instantiate new class with cart, coupon and current sum.
            $instance = new $class(
                $this->cart,
                $this->currentSum,
                $this->coupon
            );

            $response[$total->code] = $instance->resolveTotal($total);

            $this->currentSum = $instance->getCurrentSum();
        }

        //Log::warning($response);

        return $response;
    }


    /**
     * @return bool
     */
    public function hasActive(): bool
    {
        return $this->totals->count() ? true : false;
    }


    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Session\SessionManager|\Illuminate\Session\Store|mixed|null
     */
    private function setCoupon()
    {
        return session()->has('sl_cart_coupon') ? session('sl_cart_coupon') : '';
    }
}