<?php

namespace App\Models\Front\Cart\Total;

use Darryldecode\Cart\Cart;

interface TotalInterface
{

    public function __construct(Cart $cart, int $sum, string $coupon = '');


    public function resolveTotal($total): array;
}