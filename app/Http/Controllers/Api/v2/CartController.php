<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Front\AgCart;
use App\Models\Front\Product;
use App\Models\Product\ProductAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CartController extends Controller
{

    /**
     * @var Auth
     */
    protected $user;

    /**
     * @var AgCart
     */
    protected $cart;

    /**
     * @var string
     */
    protected $key = 'cart_key';


    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->key = config('session.cart');

            if (session()->has($this->key)) {
                $this->cart = new AgCart(session($this->key));

                Cart::checkLogged($this->cart, session($this->key));
            } else {
                $this->resolveSession();
            }

            return $next($request);
        });
    }


    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function get()
    {
        $response = $this->cart->get();
        
        $this->cart->resolveDB($response);
        
        return response()->json($response);
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function check(Request $request)
    {
        $response = $this->cart->check($request);

        $this->cart->resolveDB();

        return response()->json($response);
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function add(Request $request)
    {
        $response = $this->cart->add($request);

        $this->cart->resolveDB($response);

        return response()->json($response);
    }


    /**
     * @param Request $request
     * @param         $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $response = $this->cart->add($request, $id);
    
        $this->cart->resolveDB($response);
    
        return response()->json($response);
    }


    /**
     * @param $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function remove($id)
    {
        $response = $this->cart->remove($id);
    
        $this->cart->resolveDB($response);
    
        return response()->json($response);
    }


    /**
     * @param $coupon
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function coupon($coupon)
    {
        session([$this->key . '_coupon' => $coupon]);

        return response()->json($this->cart->coupon($coupon));
    }
    
    
    /**
     * Resolve new cart session.
     * If user is logged, check the DB for cart session entries.
     */
    private function resolveSession(): void
    {
        $sl_cart_id = Str::random(8);
        $this->cart = new AgCart($sl_cart_id);
        session([$this->key => $sl_cart_id]);
        
        Cart::checkLogged($this->cart, $sl_cart_id);
    }

}
