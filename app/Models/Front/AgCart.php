<?php

namespace App\Models\Front;

use App\Helpers\Currency;
use App\Helpers\Helper;
use App\Helpers\Session\CheckoutSession;
use App\Models\Back\Settings\Settings;
use App\Models\Front\Cart\Totals;
use App\Models\Front\Catalog\Product;
use App\Models\Front\Catalog\ProductAction;
use App\Models\Front\Checkout\PaymentMethod;
use App\Models\Front\Checkout\ShippingMethod;
use App\Models\TagManager;
use Darryldecode\Cart\CartCondition;
use Darryldecode\Cart\Facades\CartFacade as Cart;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AgCart extends Model
{

    /**
     * @var string
     */
    private $cart_id;

    /**
     * @var
     */
    private $cart;


    /**
     * AgCart constructor.
     *
     * @param string $id
     */
    public function __construct(string $id)
    {
        $this->cart_id = $id;
        $this->cart    = Cart::session($id);
    }


    /**
     * @return array
     */
    public function get()
    {
        $detail_conditions = $this->setCartConditions();
        $eur = $this->getEur();

        $response = [
            'id'         => $this->cart_id,
            'coupon'     => session()->has('sl_cart_coupon') ? session('sl_cart_coupon') : '',
            'items'      => $this->cart->getContent(),
            'count'      => $this->cart->getTotalQuantity(),
            'subtotal'   => $this->cart->getSubTotal(),
            'conditions' => $this->cart->getConditions(),
            'detail_con' => $detail_conditions,
            'total'      => $this->cart->getTotal(),
            'eur'        => $eur,
            'secondary_price' => $eur
        ];
        //$response['tax'] = $this->getTax($response);
        //$response['total'] = $this->cart->getTotal() + $response['tax'][0]['value'];

        //$response['totals'] = $this->getTotals();

        //Log::info($response);

        return $response;
    }


    /**
     * @return null
     */
    public function getEur()
    {
        return Currency::secondary()->value;

        if (isset($eur->status) && $eur->status) {
            return $eur->value;
        }

        return null;
    }


    /**
     * @param      $request
     * @param null $id
     *
     * @return array
     */
    public function check($request)
    {
        $products = Product::whereIn('id', $request['ids'])->pluck('quantity', 'id');
        $message = null;

        foreach ($products as $id => $quantity) {
            if ( ! $quantity) {
                $this->remove(intval($id));

                $product = Product::where('id', intval($id))->first();

                $message = 'Nažalost, knjiga ' . substr($product->name, 0, 150) . ' više nije dostupna.';
            }
        }

        return [
            'cart' => $this->get(),
            'message' => $message
        ];
    }


    /**
     * @param      $request
     * @param null $id
     *
     * @return array
     */
    public function add($request, $id = null)
    {
        // Updejtaj artikl sa apsolutnom količinom.
        foreach ($this->cart->getContent() as $item) {
            if ($item->id == $request['item']['id']) {
                $product = Product::where('id', $request['item']['id'])->first();

                if (($request['item']['quantity'] + $item->quantity) > $product->quantity) {
                    return ['error' => 'Nažalost nema dovoljnih količina artikla..!'];
                }

                return $this->updateCartItem($item->id, $request);
            }
        }

        return $this->addToCart($request);
    }


    /**
     * @param $id
     *
     * @return array
     */
    public function remove($id)
    {
        $this->cart->remove($id);

        return $this->get();
    }


    /**
     * @param $coupon
     *
     * @return array
     */
    public function coupon($coupon)
    {
        $items = $this->cart->getContent();

        // Refreshaj košaricu sa upisanim kuponom.
        foreach ($items as $item) {
            $this->remove($item->id);
            $this->addToCart($this->resolveItemRequest($item));
        }

        /*$has_coupon = ProductAction::active()->where('coupon', $coupon)->get();

        if ($has_coupon->count()) {
            return 1;
        }*/

        return 0;
    }


    /**
     *
     * @return array
     */
    public function flush()
    {
        return $this->cart->clear();
    }
    
    
    /**
     * @param $item
     *
     * @return array[]
     */
    public function resolveItemRequest($item)
    {
        return [
            'item' => [
                'id'       => $item->id,
                'quantity' => $item->quantity
            ]
        ];
    }


    /*******************************************************************************
    *                                Copyright : AGmedia                           *
    *                              email: filip@agmedia.hr                         *
    *******************************************************************************/

    public function setCartConditions()
    {
        $this->cart->clearCartConditions();

        $shipping_method = ShippingMethod::condition($this->cart);
        $payment_method = PaymentMethod::condition($this->cart);

        if ($payment_method) {
            $str = str_replace('+', '', $payment_method->getValue());
            if (number_format($str) > 0) {
                $this->cart->condition($payment_method);
            }
        }

        if ($shipping_method) {
            $this->cart->condition($shipping_method);
        }

        // Style response array
        $response = [];

        foreach ($this->cart->getConditions() as $condition) {
            $value = $condition->getValue();

            $response[] = [
                'name' => $condition->getName(),
                'type' => $condition->getType(),
                'target' => 'total', // this condition will be applied to cart's subtotal when getSubTotal() is called.
                'value' => $value,
                'attributes' => $condition->getAttributes()
            ];
        }

        return $response;
    }


    /**
     * @param $request
     *
     * @return array
     */
    private function addToCart($request): array
    {
        $this->cart->add($this->structureCartItem($request));

        return $this->get();
    }


    /**
     * @param $id
     * @param $request
     *
     * @return array
     */
    private function updateCartItem($id, $request): array
    {
        $this->cart->update($id, [
            'quantity' => [
                'relative' => false,
                'value'    => $request['item']['quantity']
            ],
        ]);

        return $this->get();
    }


    /**
     * @param $request
     *
     * @return array
     */
    private function structureCartItem($request)
    {
        $product = Product::where('id', $request['item']['id'])->first();

        $product->dataLayer = TagManager::getGoogleProductDataLayer($product);

        if ($request['item']['quantity'] > $product->quantity) {
            return ['error' => 'Nažalost nema dovoljnih količina artikla..!'];
        }

        $response = [
            'id'              => $product->id,
            'name'            => $product->name,
            'price'           => $product->price,
            'sec_price'       => $product->secondary_price,
            'quantity'        => $request['item']['quantity'],
            'associatedModel' => $product,
            'attributes'      => $this->structureCartItemAttributes($product)
        ];

        $conditions = $this->structureCartItemConditions($product);

        if ($conditions) {
            $response['conditions'] = $conditions;
        }

        return $response;
    }


    /**
     * @param $product
     *
     * @return string[]
     */
    private function structureCartItemAttributes($product)
    {
        return [
            'path' => $product->url,
            'tax' => $product->tax($product->tax_id)
        ];
    }


    /**
     * @param $product
     *
     * @return CartCondition|bool
     * @throws \Darryldecode\Cart\Exceptions\InvalidConditionException
     */
    private function structureCartItemConditions($product)
    {
        // Ako artikl ima akciju.
        if ($product->special()) {
            return new CartCondition([
                'name'  => 'Akcija',
                'type'  => 'promo',
                'value' => -($product->price - $product->special())
            ]);
        }

        // Ako nema akcije na artiklu.
        // Ako nije ispravan kupon.
        return false;
    }

}
