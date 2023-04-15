<?php

namespace App\Models\Front\Cart\Total;


use App\Models\Front\Cart\Totals;
use Darryldecode\Cart\Cart;

/**
 * Class Total
 * @package App\Models\Front\Cart
 */
class Tax extends Totals
{

    /**
     * @var
     */
    private $tax;


    /**
     * Subtotal constructor.
     *
     * @param array $cart
     * @param       $sum
     * @param       $coupon
     */
    public function __construct(Cart $cart, float $sum, string $coupon)
    {
        $this->cart   = $cart;
        $this->coupon = $coupon;
        $this->currentSum = $sum;
    }


    /**
     * @return float
     */
    public function getCurrentSum(): float
    {
        return $this->currentSum;
    }


    /**
     * @param $total
     *
     * @return array
     */
    public function resolveTotal($total): array
    {
        $this->tax = $this->currentSum * 0.25;
        $this->currentSum = $this->currentSum + $this->tax;

        return [
            'code'       => $total->code,
            'title'      => $total->name,
            'value'      => $this->tax,
            'sort_order' => $total->sort_order,
        ];
    }

}