<?php

namespace App\Models;


use App\Models\Front\AgCart;
use App\Models\Front\Catalog\Product;
use App\Services\GiftWrapService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
     * @param Builder $query
     * @param int     $days
     *
     * @return Builder
     */
    public function scopeNotOlderThan(Builder $query, int $days = 30): Builder
    {
        return $query->where('updated_at', '>', now()->subDays($days)->endOfDay());
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
            'cart_data'  => json_encode($request)
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
            'cart_data'  => $request,
            'updated_at' => Carbon::now()
        ]);
    }


    /**
     * @param AgCart $cart
     * @param        $session_id
     *
     * @return string
     */
    public static function checkLogged(AgCart $cart, $session_id = null): string
    {
        if (Auth::user()) {
            $has_cart = Cart::where('user_id', Auth::user()->id)->first();

            if ($has_cart) {
                $cart_items = $cart->getCartItems(true);
                $cart_data = json_decode($has_cart->cart_data, true);

                if (isset($cart_data['items'])) {
                    $storedItems = collect($cart_data['items'])->values();
                    $passes = [
                        $storedItems->reject(fn ($item) => GiftWrapService::isGiftWrapItem($item)),
                        $storedItems->filter(fn ($item) => GiftWrapService::isGiftWrapItem($item)),
                    ];

                    foreach ($passes as $items) {
                        foreach ($items as $item) {
                            $cart_item = $cart->resolveItemRequest($item);
                            $itemId = data_get($cart_item, 'item.id');

                            if (! $itemId || $cart_items->where('id', $itemId)->first()) {
                                continue;
                            }

                            $result = $cart->add($cart_item);

                            if (! isset($result['error'])) {
                                $cart_items = $cart->getCartItems(true);
                            }
                        }
                    }
                }

                if (isset($cart_data['coupon']) && ! empty($cart_data['coupon'])) {
                    $cart->coupon($cart_data['coupon']);
                }

                $has_cart->update(['session_id' => $session_id]);

                return $session_id;
            }
        }

        return Str::random(8);
    }
}
