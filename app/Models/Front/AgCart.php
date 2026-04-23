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
use App\Services\GiftWrapService;
use App\Services\GiftVoucherService;
use Darryldecode\Cart\CartCondition;
use Darryldecode\Cart\Facades\CartFacade as Cart;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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
     * @var int
     */
    private $loyalty;



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
        $this->loyalty     = session()->has($this->session_key . '_loyalty') ? session($this->session_key . '_loyalty') : '';
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
            'loyalty'         => $this->loyalty,
            'has_loyalty'     => $this->hasLoyalty(),
            'loyalty_points_per_euro' => $this->loyaltyPointsPerEuro(),
            'has_gift_voucher' => $this->hasGiftVoucherItems(),
            'gift_voucher_only' => $this->hasOnlyGiftVoucherItems(),
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

    private function loyaltyPointsPerEuro(): float
    {
        return max(0, floatval(data_get((array) config('settings.loyalty', []), 'points_per_euro', 1)));
    }


    /**
     * @param $request
     *
     * @return string|null
     */
    public function check($request): array
    {
        $response = [
            'cart' => $this->get(),
            'message' => null,
        ];

        $ids = collect((array) ($request['ids'] ?? $request->input('ids', [])))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values();

        if ($ids->isEmpty() || ! Schema::hasTable('products')) {
            return $response;
        }

        $products = Product::whereIn('id', $ids)->pluck('quantity', 'id');

        foreach ($products as $id => $quantity) {
            if ( ! $quantity) {
                $this->remove(intval($id));

                $product = Product::where('id', intval($id))->first();

                $response['message'] = 'Nažalost, knjiga ' . substr($product->name, 0, 150) . ' više nije dostupna.';
            }
        }

        $response['cart'] = $this->get();

        return $response;
    }


    /**
     * @param      $request
     * @param null $id
     *
     * @return array
     */
    public function add($request, $id = null): array
    {
        $item = $this->extractRequestItem($request);
        $existingItem = $this->findCartItem($item['id'] ?? null);

        if (GiftVoucherService::isGiftVoucherItem($existingItem) || (($item['type'] ?? '') === GiftVoucherService::CART_ITEM_TYPE)) {
            if ($existingItem) {
                return $this->updateCartItem($existingItem->id, 1, false);
            }

            return $this->addToCart(['item' => $item]);
        }

        if (GiftWrapService::isGiftWrapItem($existingItem) || (($item['type'] ?? '') === GiftWrapService::CART_ITEM_TYPE)) {
            return $this->handleGiftWrapItem($item);
        }

        $product = Product::where('id', (int) ($item['id'] ?? 0))->first();

        if (! $product) {
            return ['error' => 'Nažalost, odabrani artikl nije pronađen.'];
        }

        if (($item['gift_wrap'] ?? false) && ! GiftWrapService::isEligibleProduct($product)) {
            $item['gift_wrap'] = false;
        }

        $quantity = max(1, (int) ($item['quantity'] ?? 1));
        $relative = (bool) ($item['relative'] ?? false);

        if ($existingItem) {
            $requestedTotal = $relative
                ? ((int) $existingItem->quantity + $quantity)
                : $quantity;

            if ($requestedTotal > $product->quantity) {
                return ['error' => 'Nažalost nema dovoljnih količina artikla..!'];
            }

            $response = $this->updateCartItem($existingItem->id, $quantity, $relative);
        } else {
            if ($quantity > $product->quantity) {
                return ['error' => 'Nažalost nema dovoljnih količina artikla..!'];
            }

            $response = $this->addToCart(['item' => $item]);
        }

        if (isset($response['error'])) {
            return $response;
        }

        $finalQuantity = $this->currentCartItemQuantity($product->id);

        if (($item['gift_wrap'] ?? false) || $this->hasGiftWrapForProduct($product->id)) {
            $this->syncGiftWrapItem($product, $finalQuantity);
        }

        return $this->get();
    }


    /**
     * @param $id
     *
     * @return array
     */
    public function remove($id)
    {
        $item = $this->findCartItem($id);

        $this->cart->remove($id);

        if ($item && ! GiftVoucherService::isGiftVoucherItem($item) && ! GiftWrapService::isGiftWrapItem($item)) {
            $this->removeGiftWrapForProduct((int) $item->id);
        }

        return $this->get();
    }


    /**
     *
     * @param $coupon
     *
     * @return array
     */
    public function coupon($coupon): array
    {
        if ($this->hasGiftVoucherItems()) {
            session()->forget($this->session_key . '_coupon');
            $this->coupon = '';

            return [
                'success' => false,
                'coupon' => '',
                'cart' => $this->get(),
                'message' => 'Kodovi za popust ne mogu se koristiti pri kupnji poklon bona.',
            ];
        }

        $previousCoupon = Helper::normalizeCoupon($this->coupon);
        $coupon = Helper::normalizeCoupon($coupon);

        if ($coupon === '') {
            session()->forget($this->session_key . '_coupon');
            $this->coupon = '';
            $this->refreshCouponAwareItems();

            return [
                'success' => false,
                'coupon' => '',
                'cart' => $this->get(),
            ];
        }

        session([$this->session_key . '_coupon' => $coupon]);
        $this->coupon = $coupon;

        $this->refreshCouponAwareItems();

        $cart = $this->get();
        $success = Helper::couponEquals(Helper::isCouponUsed($this->cart), $coupon);

        if ( ! $success) {
            if ($previousCoupon !== '' && ! Helper::couponEquals($previousCoupon, $coupon)) {
                session([$this->session_key . '_coupon' => $previousCoupon]);
                $this->coupon = $previousCoupon;
                $this->refreshCouponAwareItems();
                $cart = $this->get();

                return [
                    'success' => false,
                    'coupon' => $this->coupon,
                    'cart' => $cart,
                    'message' => 'Uneseni kod nije valjan. Prethodno primijenjeni kod ostaje aktivan.',
                ];
            }

            session()->forget($this->session_key . '_coupon');
            $this->coupon = '';
            $this->refreshCouponAwareItems();
            $cart = $this->get();
        }

        return [
            'success' => $success,
            'coupon' => $this->coupon,
            'cart' => $cart,
        ];
    }


    /**
     * @return int
     */
    public function hasLoyalty(): int
    {
        if ($this->hasGiftVoucherItems()) {
            return 0;
        }

        $loyalty = Loyalty::hasLoyalty();

        if ($loyalty) {
            return $loyalty;
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
        session()->forget([$this->session_key . '_coupon', $this->session_key . '_loyalty']);
        $this->coupon = '';
        $this->loyalty = '';

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
        if (GiftVoucherService::isGiftVoucherItem($item)) {
            $data = GiftVoucherService::extractVoucherData($item);

            return [
                'item' => [
                    'id' => $item->id,
                    'type' => GiftVoucherService::CART_ITEM_TYPE,
                    'quantity' => 1,
                    'amount' => $data['amount'] ?? $item->price,
                    'recipient_name' => $data['recipient_name'] ?? '',
                    'recipient_email' => $data['recipient_email'] ?? '',
                    'sender_name' => $data['sender_name'] ?? '',
                    'message' => $data['message'] ?? '',
                ],
            ];
        }

        if (GiftWrapService::isGiftWrapItem($item)) {
            return GiftWrapService::buildCartItemRequest([
                'product_id' => GiftWrapService::extractWrappedProductId($item),
                'quantity' => data_get($item, 'quantity', 1),
            ]);
        }

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
        $loyalty_conditions = Helper::hasLoyaltyCartConditions($this->cart, intval($this->loyalty));
        $coupon_conditions = Helper::hasCouponCartConditions($this->cart, $this->coupon);
        $loyalty_conditions = Helper::hasLoyaltyCartConditions($this->cart, intval($this->loyalty));

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

        if ($loyalty_conditions) {
            $this->cart->condition($loyalty_conditions);
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
        $item = $this->structureCartItem($request);

        if (isset($item['error'])) {
            return $item;
        }

        $this->cart->add($item);

        return $this->get();
    }

    private function refreshCouponAwareItems(): void
    {
        $items = $this->cart->getContent();

        foreach ($items as $item) {
            $this->remove($item->id);
            $this->addToCart($this->resolveItemRequest($item));
        }
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
        $item = $this->extractRequestItem($request);

        if (($item['type'] ?? '') === GiftVoucherService::CART_ITEM_TYPE) {
            return GiftVoucherService::buildCartItem($item);
        }

        if (($item['type'] ?? '') === GiftWrapService::CART_ITEM_TYPE) {
            $productId = GiftWrapService::resolveProductId($item);
            $product = Product::where('id', $productId)->first();

            if (! $product) {
                return ['error' => 'Uslugu zamatanja trenutno nije moguće dodati.'];
            }

            if (! GiftWrapService::isEligibleProduct($product)) {
                return ['error' => 'Zamatanje za poklon nije dostupno za ovaj artikl.'];
            }

            $productQuantity = $this->currentCartItemQuantity($productId);

            if (! $productQuantity) {
                return ['error' => 'Za zamatanje prvo dodajte artikl u košaricu.'];
            }

            $quantity = min(max(1, (int) ($item['quantity'] ?? 1)), $productQuantity);

            return GiftWrapService::buildCartItem($product, $quantity);
        }

        $product = Product::where('id', $item['id'])->first();

        if (! $product) {
            return ['error' => 'Nažalost, odabrani artikl nije pronađen.'];
        }

        $product->dataLayer = TagManager::getGoogleProductDataLayer($product);

        if ((int) $item['quantity'] > $product->quantity) {
            return ['error' => 'Nažalost nema dovoljnih količina artikla..!'];
        }

        $response = [
            'id'              => $product->id,
            'name'            => $product->name,
            'price'           => $product->price,
            'sec_price'       => $product->secondary_price,
            'quantity'        => (int) $item['quantity'],
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

    private function hasGiftVoucherItems(): bool
    {
        foreach ($this->cart->getContent() as $item) {
            if (GiftVoucherService::isGiftVoucherItem($item)) {
                return true;
            }
        }

        return false;
    }

    private function hasOnlyGiftVoucherItems(): bool
    {
        $items = $this->cart->getContent();

        if ($items->isEmpty()) {
            return false;
        }

        foreach ($items as $item) {
            if (! GiftVoucherService::isGiftVoucherItem($item)) {
                return false;
            }
        }

        return true;
    }

    private function extractRequestItem($request): array
    {
        $item = $request['item'] ?? (is_object($request) && method_exists($request, 'input') ? $request->input('item', []) : []);

        return json_decode(json_encode($item), true) ?: [];
    }

    private function findCartItem($id)
    {
        if ($id === null || $id === '') {
            return null;
        }

        foreach ($this->cart->getContent() as $item) {
            if ((string) $item->id === (string) $id) {
                return $item;
            }
        }

        return null;
    }

    private function currentCartItemQuantity($id): int
    {
        $item = $this->findCartItem($id);

        return $item ? (int) $item->quantity : 0;
    }

    private function hasGiftWrapForProduct(int $productId): bool
    {
        return (bool) $this->findCartItem(GiftWrapService::cartItemId($productId));
    }

    private function removeGiftWrapForProduct(int $productId): void
    {
        $giftWrapId = GiftWrapService::cartItemId($productId);

        if ($this->findCartItem($giftWrapId)) {
            $this->cart->remove($giftWrapId);
        }
    }

    private function syncGiftWrapItem(Product $product, int $quantity): void
    {
        $giftWrapId = GiftWrapService::cartItemId($product);

        if ($quantity < 1) {
            $this->removeGiftWrapForProduct((int) $product->id);

            return;
        }

        if ($this->findCartItem($giftWrapId)) {
            $this->cart->update($giftWrapId, [
                'quantity' => [
                    'relative' => false,
                    'value' => $quantity,
                ],
            ]);

            return;
        }

        $this->cart->add(GiftWrapService::buildCartItem($product, $quantity));
    }

    private function handleGiftWrapItem(array $item): array
    {
        $productId = GiftWrapService::resolveProductId($item);
        $product = Product::where('id', $productId)->first();

        if (! $product) {
            return ['error' => 'Uslugu zamatanja trenutno nije moguće dodati.'];
        }

        if (! GiftWrapService::isEligibleProduct($product)) {
            return ['error' => 'Zamatanje za poklon nije dostupno za ovaj artikl.'];
        }

        $productQuantity = $this->currentCartItemQuantity($productId);

        if (! $productQuantity) {
            return ['error' => 'Za zamatanje prvo dodajte artikl u košaricu.'];
        }

        $quantity = min(max(1, (int) ($item['quantity'] ?? 1)), $productQuantity);

        $this->syncGiftWrapItem($product, $quantity);

        return $this->get();
    }

}
