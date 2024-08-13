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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
        $shipping = Settings::getList('shipping')->where('code', $data['shipping'])->first();
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

}
