<?php


/**
 * Returns a single provider
 * or an array of providers
 *
 * @param null $key
 *
 * @return array|mixed
 */
function providers($key = null)
{
    $providers = array(
        'subtotal' => \App\Models\Front\Cart\Total\Subtotal::class,
        'total'    => \App\Models\Front\Cart\Total\Total::class,
        'tax'      => \App\Models\Front\Cart\Total\Tax::class,
        'payment'  => \App\Models\Front\Cart\Total\Payment::class,
        'shipping' => \App\Models\Front\Cart\Total\Shipping::class,
    );

    if ($key) {
        return $providers[$key];
    }

    return $providers;
}