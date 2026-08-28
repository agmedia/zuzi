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
use App\Models\Front\Checkout\Payment\Corvus;
use App\Models\Front\Checkout\PaymentMethod;
use App\Models\Front\Checkout\ShippingMethod;
use App\Models\Back\Orders\Order as AdminOrderModel;
use App\Models\TagManager;
use App\Models\Front\Loyalty;
use App\Models\Front\Page;
use App\Services\CouponUsageService;
use App\Services\GoogleAnalyticsService;
use App\Services\GiftVoucherService;
use App\Services\MailchimpAttributionService;
use App\Services\MailchimpOrderSynchronizer;
use App\Services\ProductRecommendationService;
use App\Services\Pelion\PelionStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Services\WoltDrive\WoltDriveService;

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
        $cartItems = collect($cart['items'] ?? []);
        $gdl = TagManager::getGoogleCartDataLayer($cart);
        $recommendationService = app(ProductRecommendationService::class);
        $cartRecommendations = $recommendationService->forCartItems($cartItems);
        $cartBookmarkers = $recommendationService->randomBookmarkersForCart($cartItems);
        $showCartWalletButtons = $cartItems->count() > 0 && (auth()->check() || CheckoutSession::hasAddress());

        return view('front.checkout.cart', compact('gdl', 'cartRecommendations', 'cartBookmarkers', 'showCartWalletButtons'));
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
     * @param string  $wallet
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function wallet(Request $request, string $wallet)
    {
        $wallet = Corvus::normalizeWallet($wallet);

        if (! $wallet) {
            abort(404);
        }

        if (! session()->has(config('session.cart'))) {
            return redirect()->route('kosarica');
        }

        CheckoutSession::setPayment('corvus');
        CheckoutSession::setPaymentWallet($wallet);

        $missingStep = $this->firstMissingCheckoutStepForWallet();

        if ($missingStep) {
            return redirect()->route('naplata', ['step' => $missingStep]);
        }

        return redirect()->route('pregled');
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

        $shoppingCart = $this->shoppingCart();
        $couponUsageError = $this->rejectAlreadyUsedCoupon(
            $shoppingCart,
            data_get($data, 'address.email'),
            (int) data_get(CheckoutSession::getOrder(), 'id', 0) ?: null
        );

        if ($couponUsageError) {
            return redirect()->route('kosarica')->with('error', $couponUsageError);
        }

        $cart = $shoppingCart->get();

        if (GiftVoucherService::cartContainsGiftVoucher($cart)) {
            if (! GiftVoucherService::isGiftVoucherShipping($this->shippingCode($data['shipping']))) {
                return redirect()
                    ->route('naplata', ['step' => 'dostava'])
                    ->withErrors(['shipping' => 'Poklon bon se šalje isključivo e-mailom primatelju.']);
            }

            if (! GiftVoucherService::isAllowedPaymentCode((string) $data['payment'])) {
                return redirect()
                    ->route('naplata', ['step' => 'placanje'])
                    ->withErrors(['shipping' => 'Poklon bon je moguće platiti isključivo karticom.']);
            }
        }

        /** Wolt shipment-promise provjera (server-side safety net). */
        if (isset($data['shipping']) && $this->isWoltDrive($data['shipping'])) {
            $address = $data['address'] ?? [];
            $availability = app(WoltDriveService::class)->checkAddressAvailability($address);

            if (!($availability['available'] ?? false)) {
                CheckoutSession::forgetShipping();
                CheckoutSession::forgetPayment();

                return redirect()
                    ->route('naplata', ['step' => 'dostava'])
                    ->withErrors([
                        'shipping' => $availability['message'] ?? 'Wolt Drive dostava nije dostupna za ovu adresu.'
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

        if (! empty($data['id'])) {
            // Attribution is consent-gated metadata and never participates in
            // order creation or payment resolution.
            app(MailchimpAttributionService::class)->attachToOrder((int) $data['id'], $request);
        }

        $data['payment_form'] = $order->resolvePaymentForm();
        $termsPage = Page::query()->select('id', 'title', 'description')->find(1);

        return view('front.checkout.view', compact('data', 'termsPage'));
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

        /** Wolt shipment-promise safety net prije završetka narudžbe. */
        $selectedShipping = CheckoutSession::getShipping();
        if ($this->isWoltDrive($selectedShipping)) {
            $address = CheckoutSession::getAddress() ?? [];
            $availability = app(WoltDriveService::class)->checkAddressAvailability($address);

            if (!($availability['available'] ?? false)) {
                CheckoutSession::forgetShipping();
                CheckoutSession::forgetPayment();

                return redirect()
                    ->route('naplata', ['step' => 'dostava'])
                    ->withErrors([
                        'shipping' => $availability['message'] ?? 'Wolt Drive dostava nije dostupna za ovu adresu.'
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

        $currentOrder = $order->getData();
        $shoppingCart = $this->shoppingCart();
        $couponUsageError = $this->rejectAlreadyUsedCoupon(
            $shoppingCart,
            data_get(CheckoutSession::getAddress(), 'email') ?: data_get($currentOrder, 'payment_email'),
            (int) data_get($currentOrder, 'id', 0) ?: null
        );

        if ($couponUsageError) {
            return redirect()->route('kosarica')->with('error', $couponUsageError);
        }

        $stockCheck = $this->pelionCheckoutStockCheck(app(PelionStockService::class));

        if (! $stockCheck['ok']) {
            return redirect()
                ->route('pregled')
                ->withErrors(['stock' => $stockCheck['message']])
                ->withInput();
        }

        if ($order->finish($request)) {
            if ($order->getData()) {
                CheckoutSession::setOrder($order->getData());

                // Finalize the conversion before redirecting: the customer may
                // close the tab and never load the success page.
                app(MailchimpAttributionService::class)->attachToOrder(
                    (int) $order->getData()->id,
                    $request
                );
                app(MailchimpOrderSynchronizer::class)->markCheckoutProcessed(
                    (int) $order->getData()->id
                );

                app(GoogleAnalyticsService::class)->dispatchPurchaseFromRequest($order->getData(), $request);
            }

            return redirect()->route('checkout.success');
        }

        return redirect()->route('checkout.error');
    }

    public function checkPelionStock(Request $request, PelionStockService $stockService)
    {
        $stockCheck = $this->pelionCheckoutStockCheck($stockService);

        return response()->json($stockCheck, $stockCheck['ok'] ? 200 : 422);
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
            // Idempotent fallback for orders finalized before this hook was
            // deployed. A missing cookie does not erase prior consented data.
            app(MailchimpAttributionService::class)->attachToOrder(
                (int) $order->getOrder()->id,
                $request
            );
            app(MailchimpOrderSynchronizer::class)->markCheckoutProcessed(
                (int) $order->getOrder()->id
            );

            $selected_loyalty = intval(session(config('session.cart') . '_loyalty', 0));
            $subscribe_to_newsletter = (bool) CheckoutSession::getNewsletter();

            app(GoogleAnalyticsService::class)->dispatchPurchaseFromRequest($order->getOrder(), $request);

            GiftVoucherService::fulfillOrder($order->getOrder());

            $order->sendEmails()
                ->decreaseCartItems(false)
                ->syncCustomerDetails()
                ->addLoyaltyPoints($selected_loyalty);

            if ($subscribe_to_newsletter) {
                $order->addCustomerToMailchimp();
            }

            $order->forgetCheckoutCache();

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

    private function rejectAlreadyUsedCoupon(AgCart $cart, ?string $email, ?int $excludeOrderId = null): ?string
    {
        $coupon = (string) data_get($cart->get(), 'coupon', '');

        if (! app(CouponUsageService::class)->hasBeenUsed($coupon, $email, $excludeOrderId)) {
            return null;
        }

        $response = $cart->coupon('');
        $cart->resolveDB($response['cart'] ?? null);

        return CouponUsageService::ALREADY_USED_MESSAGE;
    }

    private function pelionCheckoutStockCheck(PelionStockService $stockService): array
    {
        if (! config('services.pelion.checkout_stock_check_enabled', false)) {
            return [
                'ok' => true,
                'message' => null,
                'checked' => [],
                'skipped' => [],
                'unavailable' => [],
                'zeroed_product_ids' => [],
                'stock_check_skipped' => true,
                'skip_reason' => 'pelion_checkout_disabled',
            ];
        }

        try {
            $cart = $this->shoppingCart()->get();

            return $stockService->validateCheckoutItems($cart['items'] ?? []);
        } catch (\Throwable $e) {
            Log::warning('Pelion checkout stock check skipped', [
                'message' => $e->getMessage(),
            ]);

            return [
                'ok' => true,
                'message' => null,
                'checked' => [],
                'skipped' => [],
                'unavailable' => [],
                'zeroed_product_ids' => [],
                'stock_check_skipped' => true,
                'skip_reason' => 'pelion_unavailable',
            ];
        }
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
     * @return string|null
     */
    private function firstMissingCheckoutStepForWallet(): ?string
    {
        $cart = $this->shoppingCart()->get();

        if (empty($cart['items'])) {
            return 'podaci';
        }

        $address = $this->checkoutAddressForWalletShortcut();

        if (! $this->hasCompleteCheckoutAddress($address)) {
            return 'podaci';
        }

        if (GiftVoucherService::cartContainsGiftVoucher($cart) && ! CheckoutSession::hasShipping()) {
            CheckoutSession::setShipping(GiftVoucherService::SHIPPING_CODE);
        }

        if (! CheckoutSession::hasShipping()) {
            return 'dostava';
        }

        $shippingCode = $this->shippingCode(CheckoutSession::getShipping());

        if (in_array($shippingCode, ['gls_eu', 'gls_paketomat'], true) && trim((string) CheckoutSession::getComment()) === '') {
            return 'dostava';
        }

        return null;
    }


    /**
     * @return array
     */
    private function checkoutAddressForWalletShortcut(): array
    {
        if (CheckoutSession::hasAddress()) {
            return (array) CheckoutSession::getAddress();
        }

        if (! auth()->check()) {
            return [];
        }

        $user = auth()->user();
        $details = $user->details ?? null;
        $address = [
            'fname' => $details?->fname ?? '',
            'lname' => $details?->lname ?? '',
            'email' => $user->email ?? '',
            'phone' => $details?->phone ?? '',
            'address' => $details?->address ?? '',
            'city' => $details?->city ?? '',
            'zip' => $details?->zip ?? '',
            'company' => $details?->company ?? '',
            'oib' => $details?->oib ?? '',
            'state' => trim((string) ($details?->state ?? '')) ?: 'Croatia',
        ];

        CheckoutSession::setAddress($address);

        return $address;
    }


    /**
     * @param array $address
     *
     * @return bool
     */
    private function hasCompleteCheckoutAddress(array $address): bool
    {
        foreach (['fname', 'lname', 'email', 'phone', 'address', 'city', 'zip', 'state'] as $key) {
            if (trim((string) ($address[$key] ?? '')) === '') {
                return false;
            }
        }

        return filter_var($address['email'], FILTER_VALIDATE_EMAIL) !== false;
    }


    /**
     * @param array $data
     * @param int   $order_status_id
     *
     * @return array
     */
    private function collectData(array $data, int $order_status_id): array
    {
        $cart = $this->shoppingCart()->get();
        $shipping = (new ShippingMethod())->find($this->shippingCode($data['shipping']));
        $payment  = (new PaymentMethod())->find((string) $data['payment']);

        $response                    = [];
        $response['address']         = $data['address'];
        $response['shipping']        = $shipping;
        $response['comment']         = GiftVoucherService::cartContainsGiftVoucher($cart)
            ? GiftVoucherService::buildOrderComment($cart)
            : (isset($data['comment']) ? $data['comment'] : '');
        $response['payment']         = $payment;
        $response['cart']            = $cart;
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

}
