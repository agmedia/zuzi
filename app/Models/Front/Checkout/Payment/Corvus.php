<?php

namespace App\Models\Front\Checkout\Payment;

use App\Models\Back\Orders\Order;
use App\Models\Back\Orders\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Class Corvus
 * @package App\Models\Front\Checkout\Payment
 */
class Corvus
{

    /**
     * @var Order
     */
    private $order;

    /**
     * @var string[]
     */
    private $url = [
        'test' => 'https://test-wallet.corvuspay.com/checkout/',
        'live' => 'https://wallet.corvuspay.com/checkout/'
    ];


    /**
     * Corvus constructor.
     *
     * @param $order
     */
    public function __construct($order)
    {
        $this->order = $order;
    }


    /**
     * @param Collection|null $payment_method
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function resolveFormView(Collection $payment_method = null, array $options = null)
    {
        if ( ! $payment_method) {
            return '';
        }

        $payment_method = $payment_method->first();

        $action = $this->url['live'];

        if ($payment_method->data->test) {
            $action = $this->url['test'];
        }

        $total = number_format($this->order->total, 2, '.', '');

        $data['currency']  = isset($options['currency']) ? $options['currency'] : 'EUR';
        $data['action']    = $action;
        $data['merchant']  = $payment_method->data->shop_id;
        $data['order_id']  = isset($options['order_number']) ? $options['order_number'] : $this->order->id;
        $data['total']     = isset($options['total']) ? $options['total'] : $total;
        $data['firstname'] = $this->order->payment_fname;
        $data['lastname']  = $this->order->payment_lname;
        $data['address']   = '';
        $data['city']      = '';
        $data['country']   = '';
        $data['postcode']  = '';
        $data['telephone'] = $this->order->payment_phone;
        $data['email']     = $this->order->payment_email;
        $data['lang']      = 'hr';
        $data['plan']      = isset($options['plan']) ? $options['plan'] : '01';
        $data['cc_name']   = isset($options['cc_name']) ? $options['cc_name'] : 'VISA';//...??
        $data['rate']      = isset($options['rate']) ? $options['rate'] : 1;
        $data['return']    = isset($options['return_url']) ? $options['return_url'] : $payment_method->data->callback;
        $data['cancel']    = route('index');
        $data['method']    = 'POST';

        $data['number_of_installments'] = 'Y0299';

        $string = 'amount' . $total . 'cardholder_email' . $data['email'] . 'cardholder_name' . $data['firstname'] . 'cardholder_phone' . $data['telephone'] . 'cardholder_surname' . $data['lastname'] . 'cartWeb shop kupnja ' . $data['order_id'] . 'currency' . $data['currency'] . 'language' . $data['lang'] . 'order_number' . $data['order_id'] . 'payment_all' . $data['number_of_installments'] . 'require_completefalsestore_id' . $data['merchant'] . 'version1.3';

        $keym = $payment_method->data->secret_key;
        $hash = hash_hmac('sha256', $string, $keym);

        $data['md5'] = $hash;

        return view('front.checkout.payment.corvus', compact('data'));
    }


    /**
     * @param Order $order
     * @param null  $request
     *
     * @return bool
     */
    public function finishOrder(Order $order, Request $request): bool
    {

        $status = ($request->has('approval_code') && $request->input('approval_code')!= null) ? config('settings.order.status.paid') : config('settings.order.status.declined');


        $order->update([
            'order_status_id' => $status
        ]);

        if ($request->has('approval_code')) {
            Transaction::insert([
                'order_id'        => $request->input('order_number'),
                'success'         => 1,
              /*  'amount'          => $request->input('Amount'),
                'signature'       => $request->input('Signature'),
                'payment_type'    => $request->input('PaymentType'),
                'payment_plan'    => $request->input('PaymentPlan'),
                'payment_partner' => $request->input('Partner'),
                'datetime'        => $request->input('DateTime'),
                'approval_code'   => $request->input('ApprovalCode'),
                'pg_order_id'     => $request->input('CorvusOrderId'),
                'lang'            => $request->input('Lang'),
                'stan'            => $request->input('STAN'),
                'error'           => $request->input('ErrorMessage'),*/
                'created_at'      => Carbon::now(),
                'updated_at'      => Carbon::now()
            ]);

            return true;
        }

        Transaction::insert([
            'order_id'        => $request->input('order_number'),
            'success'         => 0,
          /*  'amount'          => $request->input('Amount'),
            'signature'       => $request->input('Signature'),
            'payment_type'    => $request->input('PaymentType'),
            'payment_plan'    => $request->input('PaymentPlan'),
            'payment_partner' => null,
            'datetime'        => $request->input('DateTime'),
            'approval_code'   => $request->input('ApprovalCode'),
            'pg_order_id'     => null,
            'lang'            => $request->input('Lang'),
            'stan'            => null,
            'error'           => $request->input('ErrorMessage'),*/
            'created_at'      => Carbon::now(),
            'updated_at'      => Carbon::now()
        ]);

        return false;
    }

}
