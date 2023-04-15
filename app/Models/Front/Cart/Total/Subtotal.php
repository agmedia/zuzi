<?php

namespace App\Models\Front\Cart\Total;

use App\Models\Front\Cart\Totals;
use Darryldecode\Cart\Cart;
use Illuminate\Support\Facades\Log;

/**
 * Class Subtotal
 * @package App\Models\Front\Cart\Total
 */
class Subtotal extends Totals
{

    /**
     * @var
     */
    private $total;


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
        // Implement situation if Tax is already in product price.

        $this->total = $total;

        $this->currentSum = $this->cart->getSubTotal();

        return [
            'code'       => $this->total->code,
            'title'      => $this->total->name,
            'value'      => $this->currentSum,
            'sort_order' => $this->total->sort_order,
        ];
    }

}