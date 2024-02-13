<?php

namespace App\Models\Front;

use App\Helpers\Currency;
use App\Helpers\Helper;
use App\Models\Back\Marketing\Action;
use App\Models\Front\Catalog\Product;
use App\Models\Front\Catalog\ProductAction;
use App\Models\Front\Checkout\PaymentMethod;
use App\Models\Front\Checkout\ShippingMethod;
use App\Models\TagManager;
use Darryldecode\Cart\CartCondition;
use Darryldecode\Cart\Facades\CartFacade as Cart;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
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
     * @var string
     */
    private $session_key;

    /**
     * @var string
     */
    private $coupon;


    /**
     * AgCart constructor.
     *
     * @param string $id
     */
    public function __construct(string $id)
    {
        $this->cart_id     = $id;
        $this->cart        = Cart::session($id);
        $this->session_key = config('session.cart') ?: 'agm';
        $this->coupon      = session()->has($this->session_key . '_coupon') ? session($this->session_key . '_coupon') : '';
    }


    /**
     * @return array
     */
    public function get()
    {
        $eur = $this->getEur();

        $response = [
            'id'              => $this->cart_id,
            'coupon'          => $this->coupon,
            'items'           => $this->cart->getContent(),
            'count'           => $this->cart->getTotalQuantity(),
            'subtotal'        => $this->cart->getSubTotal(),
            'conditions'      => $this->cart->getConditions(),
            'detail_con'      => $this->setCartConditions(),
            'total'           => $this->cart->getTotal(),
            'eur'             => $eur,
            'secondary_price' => $eur
        ];

        return $response;
    }


    /**
     * @param bool $just_basic
     *
     * @return Collection
     */
    public function getCartItems(bool $just_basic = false): Collection
    {
        $response = collect();

        foreach ($this->cart->getContent() as $item) {
            if ($just_basic) {
                $data = ['id' => $item->id, 'quantity' => $item->quantity];
                $response->push($data);
            } else {
                $response->push($item);
            }
        }

        return $response;
    }


    /**
     * @return null
     */
    public function getEur()
    {
        if (isset(Currency::secondary()->value)) {
            return Currency::secondary()->value;
        }

        return null;
    }


    /**
     * @param $request
     *
     * @return string|null
     */
    public function check($request): ?string
    {
        $products = Product::whereIn('id', $request['ids'])->pluck('quantity', 'id');
        $message  = null;

        foreach ($products as $id => $quantity) {
            if ( ! $quantity) {
                $this->remove(intval($id));

                $product = Product::where('id', intval($id))->first();

                $message = 'Nažalost, knjiga ' . substr($product->name, 0, 150) . ' više nije dostupna.';
            }
        }

        return $message;
    }


    /**
     * @param      $request
     * @param null $id
     *
     * @return array
     */
    public function add($request, $id = null): array
    {
        // Updejtaj artikl sa apsolutnom količinom.
        foreach ($this->cart->getContent() as $item) {
            if ($item->id == $request['item']['id']) {
                $quantity = $request['item']['quantity'];
                $product  = Product::where('id', $request['item']['id'])->first();

                if ($quantity > $product->quantity) {
                    return ['error' => 'Nažalost nema dovoljnih količina artikla..!'];
                }

                if ($quantity == 1 && ($item->quantity == 1 || $item->quantity > $quantity)) {
                    if ( ! $id) {
                        $quantity = $item->quantity + 1;
                    }
                }

                $relative = false;

                if (isset($request['item']['relative']) && $request['item']['relative']) {
                    $relative = true;
                }

                return $this->updateCartItem($item->id, $quantity, $relative);
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
     *
     * @param $coupon
     *
     * @return int
     */
    public function coupon($coupon): int
    {
        $items = $this->cart->getContent();

        // Refreshaj košaricu sa upisanim kuponom.
        foreach ($items as $item) {
            $this->remove($item->id);
            $this->addToCart($this->resolveItemRequest($item));
        }

        $has_coupon = ProductAction::active()->where('coupon', $coupon)->get();

        if ($has_coupon->count()) {
            return 1;
        }

        return 0;
    }


    /**
     * @return $this
     */
    public function flush(): static
    {
        if ($this->coupon != '') {
            $is_used = Helper::isCouponUsed($this->cart);

            if ($is_used != '') {
                $action = Action::query()->where('coupon', $is_used)->first();

                if ($action && $action->quantity == 1) {
                    $action->update(['status' => 0]);
                }
            }
        }

        $this->cart->clear();

        Helper::flushCache('cart', $this->cart_id);

        return $this;
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
                'id'       => $item['id'],
                'quantity' => $item['quantity']
            ]
        ];
    }


    /**
     * If user is logged store or update the DB session.
     *
     * @return $this
     */
    public function resolveDB(array $data = null): static
    {
        if ( ! $data) {
            $data = $this->get();
        }

        if (Auth::user()) {
            $has_cart = \App\Models\Cart::where('user_id', Auth::user()->id)->first();

            if ($has_cart) {
                \App\Models\Cart::edit($data);
            } else {
                \App\Models\Cart::store($data);
            }
        }

        return $this;
    }


    /*******************************************************************************
     *                                Copyright : AGmedia                           *
     *                              email: filip@agmedia.hr                         *
     *******************************************************************************/

    public function setCartConditions()
    {
        $this->cart->clearCartConditions();

        $shipping_method   = ShippingMethod::condition($this->cart);
        $payment_method    = PaymentMethod::condition($this->cart);
        $special_condition = Helper::hasSpecialCartCondition($this->cart);
        $coupon_conditions = Helper::hasCouponCartConditions($this->cart, $this->coupon);

        if ($payment_method) {
            $str = str_replace('+', '', $payment_method->getValue());
            if (number_format(floatval($str), 2) > 0) {
                $this->cart->condition($payment_method);
            }
        }

        if ($shipping_method) {
            $this->cart->condition($shipping_method);
        }

        if ($special_condition) {
            $this->cart->condition($special_condition);
        }

        if ($coupon_conditions) {
            $this->cart->condition($coupon_conditions);
        }

        // Style response array
        $response = [];

        foreach ($this->cart->getConditions() as $condition) {
            $value = $condition->getValue();

            $response[] = [
                'name'       => $condition->getName(),
                'type'       => $condition->getType(),
                'target'     => 'total', // this condition will be applied to cart's subtotal when getSubTotal() is called.
                'value'      => $value,
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
     * @param      $id
     * @param      $quantity
     * @param bool $relative
     *
     * @return array
     */
    private function updateCartItem($id, $quantity, bool $relative): array
    {
        $this->cart->update($id, [
            'quantity' => [
                'relative' => $relative,
                'value'    => $quantity
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
            'tax'  => $product->tax($product->tax_id)
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
            $coupon = $product->coupon();

            if ($coupon != '') {
                return new CartCondition([
                    'name'   => 'Kupon akcija',
                    'type'   => 'coupon',
                    'target' => $coupon,
                    'value'  => -($product->price - $product->special())
                ]);
            }

            return new CartCondition([
                'name'   => 'Akcija',
                'type'   => 'promo',
                'target' => '',
                'value'  => -($product->price - $product->special())
            ]);
        }

        // Ako nema akcije na artiklu.
        // Ako nije ispravan kupon.
        return false;
    }

}
