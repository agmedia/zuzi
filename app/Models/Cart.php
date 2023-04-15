<?php

namespace App\Models;


use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Cart extends Model
{

    /**
     * @var string
     */
    protected $table = 'carts';

    /**
     * @var array
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];


    /**
     * @param $value
     *
     * @return mixed
     */
    public function getCartDataAttribute($value)
    {
        return unserialize($value);
    }


    /**
     * @param $value
     */
    public function setCartDataAttribute($value)
    {
        $this->attributes['cart_data'] = serialize($value);
    }


    /**
     * @param $request
     *
     * @return mixed
     */
    public static function store($request)
    {
        return self::create([
            'user_id'    => Auth::user()->id,
            'session_id' => session(config('session.cart')),
            'cart_data'  => $request
        ]);
    }


    /**
     * @param array $request
     *
     * @return bool
     */
    public static function edit($request)
    {
        return self::where('user_id', Auth::user()->id)->update([
            'cart_data'  => serialize($request),
            'updated_at' => Carbon::now()
        ]);
    }


    /**
     * @param      $session_id
     * @param null $cart
     *
     * @return bool
     */
    public static function checkLogged($session_id, $cart = null)
    {
        if (Auth::user()) {
            $has_cart = Cart::where('user_id', Auth::user()->id)->first();

            if ($has_cart) {
                $cart_data = json_decode(json_encode($has_cart->cart_data));

                if (isset($cart_data->items)) {
                    foreach ($cart_data->items as $item) {
                        $cart->add($cart->resolveItemRequest($item));
                    }
                }

                if (isset($cart_data->coupon) && ! empty($cart_data->coupon)) {
                    $cart->coupon($cart_data->coupon);
                }

                $has_cart->update(['session_id' => $session_id]);

                return true;
            }

            return false;
        }

        return false;
    }
}
