<?php

namespace App\Http\Controllers\Front;

use App\Helpers\OrderHelper;
use App\Helpers\Session\CheckoutSession;
use App\Http\Controllers\Controller;
use App\Mail\OrderReceived;
use App\Mail\OrderSent;
use App\Models\Back\Settings\Settings;
use App\Models\Front\AgCart;
use App\Models\Front\Catalog\CategoryProducts;
use App\Models\Front\Catalog\Product;
use App\Models\Front\Checkout\Order;
use App\Models\Back\Orders\Order as AdminOrderModel;
use App\Models\TagManager;
use App\Models\Front\Loyalty;
use App\Services\GoogleAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Collection;
use App\Services\WoltDrive\WoltZoneService;

class CheckoutController extends Controller
{

    /**
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function cart(Request $request)
    {
        $cart = $this->shoppingCart()->get();
        $gdl = TagManager::getGoogleCartDataLayer($cart);
        $cartRecommendations = $this->getCartRecommendations(collect($cart['items'] ?? []));

        return view('front.checkout.cart', compact('gdl', 'cartRecommendations'));
    }


    /**
     * @param Request $request
     * @param string  $step
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function checkout(Request $request)
    {
        $step = '';

        if ($request->has('step')) {
            $step = $request->input('step');
        }

        $is_free_shipping = OrderHelper::isFreeShipping($this->shoppingCart()->get());

        return view('front.checkout.checkout', compact('step', 'is_free_shipping'));
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function view(Request $request)
    {
        $data = $this->checkSession();

        if (empty($data)) {
            if ( ! session()->has(config('session.cart'))) {
                return redirect()->route('kosarica');
            }

            return redirect()->route('naplata', ['step' => 'podaci']);
        }

        /**
         * ---------------------------------------------
         * WOLT ZONA VALIDACIJA (server-side, UX-friendly)
         * ---------------------------------------------
         * Ako je korisnik izabrao wolt_drive, provjeri je li adresa u zoni.
         */
        if (isset($data['shipping']) && $this->isWoltDrive($data['shipping'])) {
            $address = $data['address'] ?? [];

            $lat = data_get($address, 'lat');
            $lng = data_get($address, 'lng');

            /** @var WoltZoneService $zone */
            $zone = app(WoltZoneService::class);

            $inZone = null;

            // 1) Preferiraj koordinate ako postoje (čak i 0.0 treba biti dozvoljeno, stoga koristimo isset)
            if (isset($lat, $lng)) {
                $inZone = $zone->containsLatLng((float)$lat, (float)$lng);
                Log::debug('[WOLT] view(): checked by lat/lng', ['lat' => $lat, 'lng' => $lng, 'in_zone' => $inZone]);
            } else {
                // 2) Fallback: složi punu adresu iz polja; ako nešto fali, dodaj “Zagreb, Hrvatska”
                $fullAddress = $this->composeFullAddress($address);
                $inZone = $fullAddress ? $zone->containsAddress($fullAddress) : false;
                Log::debug('[WOLT] view(): checked by address', ['address' => $fullAddress, 'in_zone' => $inZone]);
            }

            if (!$inZone) {
                return redirect()
                    ->route('naplata', ['step' => 'podaci'])
                    ->withErrors([
                        'shipping' => 'Adresa nije unutar Wolt Drive dostavne zone (Grad Zagreb). Odaberite drugi način dostave ili promijenite adresu.'
                    ])
                    ->withInput();
            }
        }

        $data = $this->collectData($data, config('settings.order.status.unfinished'));

        $order = new Order();

        if (CheckoutSession::hasOrder()) {
            $data['id'] = CheckoutSession::getOrder()['id'];

            $order->updateData($data)
                ->setData($data['id']);
        } else {
            $order->createFrom($data);
        }

        if ($order->isCreated()) {
            CheckoutSession::setOrder($order->getData());
        }

        if ( ! isset($data['id'])) {
            $data['id'] = CheckoutSession::getOrder()['id'];
        }

        $data['payment_form'] = $order->resolvePaymentForm();

        return view('front.checkout.view', compact('data'));
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function order(Request $request)
    {
        $order = new Order();

        ag_log($request->toArray(), title: 'Response ORDER ::::::::::::::::::::::::::::::::::::::');

        /**
         * -----------------------------------------------------------------
         * WOLT ZONA VALIDACIJA (safety net prije završetka narudžbe)
         * -----------------------------------------------------------------
         */
        $selectedShipping = CheckoutSession::getShipping();
        if ($this->isWoltDrive($selectedShipping)) {
            $address = CheckoutSession::getAddress() ?? [];

            $lat = data_get($address, 'lat');
            $lng = data_get($address, 'lng');

            /** @var WoltZoneService $zone */
            $zone = app(WoltZoneService::class);

            $inZone = null;

            if (isset($lat, $lng)) {
                $inZone = $zone->containsLatLng((float)$lat, (float)$lng);
                Log::debug('[WOLT] order(): checked by lat/lng', ['lat' => $lat, 'lng' => $lng, 'in_zone' => $inZone]);
            } else {
                $fullAddress = $this->composeFullAddress($address);
                $inZone = $fullAddress ? $zone->containsAddress($fullAddress) : false;
                Log::debug('[WOLT] order(): checked by address', ['address' => $fullAddress, 'in_zone' => $inZone]);
            }

            if (!$inZone) {
                return redirect()
                    ->route('naplata', ['step' => 'podaci'])
                    ->withErrors([
                        'shipping' => 'Adresa nije unutar Wolt Drive dostavne zone (Grad Zagreb). Odaberite drugi način dostave ili promijenite adresu.'
                    ])
                    ->withInput();
            }
        }

        if ($request->has('provjera')) {
            $order->setData($request->input('provjera'));
        }

        if ($request->has('order_number')) {
            $order->setData($request->input('order_number'));
        }

        if ($order->finish($request)) {
            if ($order->getData()) {
                CheckoutSession::setOrder($order->getData());

                app(GoogleAnalyticsService::class)->dispatchPurchaseFromRequest($order->getData(), $request);
            }

            return redirect()->route('checkout.success');
        }

        return redirect()->route('checkout.error');
    }


    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function success(Request $request)
    {
        $data['order'] = CheckoutSession::getOrder();

        if ( ! $data['order']) {
            return redirect()->route('index');
        }

        $order = OrderHelper::get($data['order']['id']);

        if ($order->isValid()) {
            app(GoogleAnalyticsService::class)->dispatchPurchaseFromRequest($order->getOrder(), $request);

            $order->sendEmails()
                ->decreaseCartItems(false)
                ->addLoyaltyPoints()
                // ->addCustomerToMailchimp()
                ->forgetCheckoutCache();

            $this->shoppingCart()
                ->flush()
                ->resolveDB();

            $data['google_tag_manager'] = TagManager::getGoogleSuccessDataLayer($order->getOrder());

            return view('front.checkout.success', compact('data'));
        }

        return redirect()->route('front.checkout.checkout', ['step' => '']);
    }


    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function error()
    {
        return view('front.checkout.error');
    }

    /*******************************************************************************
     *                                Copyright : AGmedia                           *
     *                              email: filip@agmedia.hr                         *
     *******************************************************************************/

    /**
     * @return AgCart
     */
    private function shoppingCart(): AgCart
    {
        if (session()->has(config('session.cart'))) {
            return new AgCart(session(config('session.cart')));
        }

        return new AgCart(config('session.cart'));
    }


    /**
     * Build a short recommendation shelf for the cart using similar products
     * that currently resolve to the 10-15 EUR range.
     */
    private function getCartRecommendations(Collection $cart_items, int $limit = 10): Collection
    {
        if ($cart_items->isEmpty()) {
            return collect();
        }

        $cart_product_ids = $cart_items->map(fn ($item) => (int) data_get($item, 'id'))
            ->filter()
            ->unique()
            ->values();

        if ($cart_product_ids->isEmpty()) {
            return collect();
        }

        $author_ids = $cart_items->map(fn ($item) => (int) data_get($item, 'associatedModel.author_id'))
            ->filter()
            ->unique()
            ->values();

        $publisher_ids = $cart_items->map(fn ($item) => (int) data_get($item, 'associatedModel.publisher_id'))
            ->filter()
            ->unique()
            ->values();

        $category_ids = CategoryProducts::query()
            ->whereIn('product_id', $cart_product_ids)
            ->pluck('category_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $candidate_ids = collect();

        if ($author_ids->isNotEmpty()) {
            $candidate_ids = $candidate_ids->concat(
                Product::query()
                    ->active()
                    ->hasStock()
                    ->hasImage()
                    ->whereNotIn('id', $cart_product_ids)
                    ->whereIn('author_id', $author_ids)
                    ->inRandomOrder()
                    ->limit($limit * 3)
                    ->pluck('id')
            );
        }

        if ($category_ids->isNotEmpty()) {
            $candidate_ids = $candidate_ids->concat(
                CategoryProducts::query()
                    ->select('product_id')
                    ->whereIn('category_id', $category_ids)
                    ->whereNotIn('product_id', $cart_product_ids)
                    ->groupBy('product_id')
                    ->inRandomOrder()
                    ->limit($limit * 4)
                    ->pluck('product_id')
            );
        }

        if ($publisher_ids->isNotEmpty()) {
            $candidate_ids = $candidate_ids->concat(
                Product::query()
                    ->active()
                    ->hasStock()
                    ->hasImage()
                    ->whereNotIn('id', $cart_product_ids)
                    ->whereIn('publisher_id', $publisher_ids)
                    ->inRandomOrder()
                    ->limit($limit * 2)
                    ->pluck('id')
            );
        }

        $recommendations = $this->resolveRecommendationProducts(
            $candidate_ids->map(fn ($id) => (int) $id)->filter()->unique()->values(),
            $limit
        );

        if ($recommendations->count() >= $limit) {
            return $recommendations;
        }

        $excluded_ids = $cart_product_ids
            ->merge($recommendations->pluck('id'))
            ->unique()
            ->values();

        $fallback = Product::query()
            ->active()
            ->hasStock()
            ->hasImage()
            ->with(['author', 'action'])
            ->whereNotIn('id', $excluded_ids)
            ->where(function ($query) {
                $query->whereBetween('price', [10, 15])
                    ->orWhereBetween('special', [10, 15]);
            })
            ->inRandomOrder()
            ->limit($limit * 3)
            ->get()
            ->filter(fn (Product $product) => $this->matchesRecommendationPrice($product))
            ->take($limit - $recommendations->count())
            ->values();

        return $recommendations->concat($fallback)->take($limit)->values();
    }


    private function resolveRecommendationProducts(Collection $candidate_ids, int $limit): Collection
    {
        if ($candidate_ids->isEmpty()) {
            return collect();
        }

        return Product::query()
            ->active()
            ->hasStock()
            ->hasImage()
            ->with(['author', 'action'])
            ->whereIn('id', $candidate_ids)
            ->get()
            ->filter(fn (Product $product) => $this->matchesRecommendationPrice($product))
            ->sortBy(fn (Product $product) => $candidate_ids->search($product->id))
            ->take($limit)
            ->values();
    }


    private function matchesRecommendationPrice(Product $product): bool
    {
        $resolved_price = (float) $product->special();

        return $resolved_price >= 10.0 && $resolved_price <= 15.0;
    }


    /**
     * @return array
     */
    private function checkSession(): array
    {
        if (CheckoutSession::hasAddress() && CheckoutSession::hasShipping() && CheckoutSession::hasPayment()) {
            return [
                'address'  => CheckoutSession::getAddress(),
                'shipping' => CheckoutSession::getShipping(),
                'payment'  => CheckoutSession::getPayment(),
                'comment'  => CheckoutSession::getComment()
            ];
        }

        return [];
    }


    /**
     * @param array $data
     * @param int   $order_status_id
     *
     * @return array
     */
    private function collectData(array $data, int $order_status_id): array
    {
        $shipping = Settings::getList('shipping')->where('code', $this->shippingCode($data['shipping']))->first();
        $payment  = Settings::getList('payment')->where('code', $data['payment'])->first();

        $response                    = [];
        $response['address']         = $data['address'];
        $response['shipping']        = $shipping;
        $response['comment']         = isset($data['comment']) ? $data['comment'] : '';
        $response['payment']         = $payment;
        $response['cart']            = $this->shoppingCart()->get();
        $response['order_status_id'] = $order_status_id;

        return $response;
    }


    /**
     * Helper: jesmo li na wolt_drive shippingu (string ili array)
     */
    private function isWoltDrive($shipping): bool
    {
        if (is_array($shipping)) {
            $code = $shipping['code'] ?? null;
            return $code === 'wolt_drive';
        }

        return (string)$shipping === 'wolt_drive';
    }

    /**
     * Helper: vrati code iz shipping vrijednosti (string ili array)
     */
    private function shippingCode($shipping): string
    {
        if (is_array($shipping)) {
            return (string) ($shipping['code'] ?? '');
        }
        return (string) $shipping;
    }

    /**
     * Složi full address; ako nema grada/države, dodaj “Zagreb, Hrvatska”
     */
    /**
     * Složi punu adresu iz Livewire forme:
     * - address.address  (ulica + broj)
     * - address.city     (grad)
     * - address.zip      (poštanski broj)
     * - address.state    (država)
     * Ako nedostaje grad/država, dodaj default (Zagreb, Hrvatska) radi preciznijeg geokodiranja.
     */
    private function composeFullAddress(array $address): string
    {
        // Primarni ključevi iz tvoje forme
        $street   = trim((string) ($address['address'] ?? ''));   // npr. "Zapoljska ulica 20"
        $city     = trim((string) ($address['city'] ?? ''));
        $zip      = trim((string) ($address['zip'] ?? ''));
        $country  = trim((string) ($address['state'] ?? ''));     // u tvojoj formi je "state" = država

        // Sastavi dijelove adrese redoslijedom: ulica, grad, zip, država
        $parts = [];
        if ($street !== '') { $parts[] = $street; }
        if ($city   !== '') { $parts[] = $city; }
        if ($zip    !== '') { $parts[] = $zip; }
        if ($country!== '') { $parts[] = $country; }

        // Ako fali grad/država, dodaj defaulte (pomaže geokoderu)
        if ($city === '')    { $parts[] = 'Zagreb'; }
        if ($country === '') { $parts[] = 'Croatia'; }

        $full = implode(', ', array_filter($parts, fn($v) => $v !== ''));

        // (opcionalno) log za debug
        Log::debug('[WOLT] composeFullAddress LW', [
            'raw'  => $address,
            'full' => $full,
        ]);

        return $full;
    }


}
