<?php

namespace App\Http\Controllers\Front;

use App\Helpers\OrderHelper;
use App\Helpers\Session\CheckoutSession;
use App\Http\Controllers\Controller;
use App\Mail\OrderReceived;
use App\Mail\OrderSent;
use App\Models\Back\Settings\Settings;
use App\Models\Front\AgCart;
use App\Models\Front\Checkout\Order;
use App\Models\Back\Orders\Order as AdminOrderModel;
use App\Models\TagManager;
use App\Models\Front\Loyalty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
        $gdl = TagManager::getGoogleCartDataLayer($this->shoppingCart()->get());

        return view('front.checkout.cart', compact('gdl'));
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
            $order->sendEmails()
                ->decreaseCartItems()
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
