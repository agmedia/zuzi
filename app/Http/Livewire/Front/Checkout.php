<?php

namespace App\Http\Livewire\Front;

use App\Helpers\Country;
use App\Helpers\Currency;
use App\Helpers\Helper;
use App\Helpers\Session\CheckoutSession;
use App\Models\Back\Settings\Settings;
use App\Models\Front\AgCart;
use App\Models\Front\Checkout\GeoZone;
use App\Models\Front\Checkout\PaymentMethod;
use App\Models\Front\Checkout\ShippingMethod;
use App\Models\TagManager;
use App\Services\AddressDirectoryService;
use App\Services\GiftVoucherService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;

class Checkout extends Component
{
    private const DEFAULT_COUNTRY = 'Croatia';

    /**
     * @var string
     */
    public $step = '';

    /**
     * @var string
     */
    public $is_free_shipping = '';

    /**
     * @var array
     */
    public $login = [
        'email' => '',
        'pass' => '',
        'remember' => false
    ];

    /**
     * @var string[]
     */
    public $address = [
        'fname' => '',
        'lname' => '',
        'email' => '',
        'phone' => '',
        'address' => '',
        'city' => '',
        'zip' => '',
        'company' => '',
        'oib' => '',
        'state' => 'Croatia',
    ];

    /**
     * @var string
     */
    public $shipping = '';

    /**
     * @var string
     */
    public $payment = '';

    /**
     * @var int|bool
     */
    public $secondary_price = false;

    /**
     * @var array
     */
    public $gdl = [];

    public $gdl_event = '';

    public $gdl_shipping = false;

    public $gdl_payment = false;

    protected $cart = false;

    public $giftVoucherOnly = false;


    public $comment = '';
    public $view_comment = false;
    public $view_commentt = false;

    public $hp_paketomat = '';
    public $view_hp_paketomat = false;
    public $search_hp_paketomat_results = [];


    /**
     * @var string[]
     */
    protected $address_rules = [
        'address.fname' => 'required',
        'address.lname' => 'required',
        'address.email' => 'required|email',
        'address.phone' => 'required',
        'address.address' => 'required',
        'address.city' => 'required',
        'address.zip' => 'required',
        'address.state' => 'required',
    ];

    /**
     * @var string[]
     */
    protected $shipping_rules = [
        'shipping' => 'required',

    ];

    protected $comment_rules = [

        'comment'=> 'required',
    ];

    /**
     * @var string[]
     */
    protected $payment_rules = [
        'payment' => 'required',
    ];

    /**
     * @var \string[][]
     */
    protected $queryString = ['step' => ['except' => '']];


    /**
     *
     */
    public function mount()
    {
        if (CheckoutSession::hasAddress()) {
            $this->setAddress(CheckoutSession::getAddress());
        } else {
            $this->setAddress();
        }

        if (CheckoutSession::hasShipping()) {
            $this->shipping = CheckoutSession::getShipping();
            $this->checkShipping($this->shipping);
        }

        if (CheckoutSession::hasPayment()) {
            $this->payment = CheckoutSession::getPayment();
        }

        if (CheckoutSession::hasComment()) {
            $this->comment = CheckoutSession::getComment();
        }

        $this->secondary_price = Currency::secondary() ? Currency::secondary()->value : false;

        $this->checkCart();

        if ($this->giftVoucherOnly && ! CheckoutSession::hasShipping()) {
            $this->shipping = GiftVoucherService::SHIPPING_CODE;
            CheckoutSession::setShipping($this->shipping);
        }

        if ($this->giftVoucherOnly && (! CheckoutSession::hasPayment() || ! GiftVoucherService::isAllowedPaymentCode($this->payment))) {
            $this->payment = GiftVoucherService::firstAllowedPaymentCode() ?: '';
            if ($this->payment) {
                CheckoutSession::setPayment($this->payment);
            }
        }

        $this->changeStep($this->step);
    }

    public function updatingComment($value)
    {
        $this->comment = $value;

        CheckoutSession::setComment($this->comment);
    }

    public function updatedAddress($value, $key)
    {
        if ( ! in_array($key, ['zip', 'city'], true)) {
            CheckoutSession::setAddress($this->address);

            return;
        }

        $this->autofillAddressField($key, (string) $value);
    }


    public function updatingHpPaketomat($value)
    {
        if (strlen($value) > 2) {
            $api_url = 'https://facility-api.posta.hr/api/facility/getfacilities';
            $call = Http::post($api_url, ['facilityType' => 'PAK', 'nextWeek' => 0, 'searchText' => $value]);

            $this->search_hp_paketomat_results = $call->json()['paketomatInfoList'];

        } else {
            $this->search_hp_paketomat_results = [];
        }

        //dd($call->json(), $call->status(), $call);
    }


    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authUser()
    {
        $validated = Validator::make([
            'email' => $this->login['email'],
            'password' => $this->login['pass'],
        ],[
            'email' => ['required', 'email'],
            'password' => ['required'],
        ])->validate();

        if (Auth::attempt($validated, $this->login['remember'])) {
            session()->regenerate();
            $this->setAddress();

            session()->flash('login_success', 'Uspješno ste se prijavili na vaš račun...');
        }

        session()->flash('error', 'Upisani podaci ne odgovaraju našim korisnicima...');
    }


    /**
     * @param string $step
     */
    public function changeStep(string $step = '')
    {
        $this->checkCart();

        if ($this->giftVoucherOnly && ! $this->shipping) {
            $this->shipping = GiftVoucherService::SHIPPING_CODE;
            CheckoutSession::setShipping($this->shipping);
        }

        if (in_array($step, ['', 'podaci']) && $this->cart) {
            $this->gdl = TagManager::getGoogleCartDataLayer($this->cart->get());
            $this->gdl_event = 'begin_checkout';
            $this->gdl_shipping = false;
            $this->gdl_payment = false;
        }
        // Podaci
        if ($step == '') {
            $step = 'podaci';

            if (CheckoutSession::hasStep()) {
                $step = CheckoutSession::getStep();
            }
        }

        // Dostava
        if (in_array($step, ['dostava', 'placanje']) && $this->cart) {
            $this->setAddress($this->address);
            $this->validate($this->address_rules);

            if ($step == 'dostava' && $this->shipping != '') {
                $this->checkShipping($this->shipping);
                $this->gdl = TagManager::getGoogleCartDataLayer($this->cart->get());
                $this->gdl_event = 'add_shipping_info';
            }

            if ($step == 'placanje' && $this->payment != '') {
                $this->checkPayment($this->payment);
                $this->gdl = TagManager::getGoogleCartDataLayer($this->cart->get());
                $this->gdl_event = 'add_payment_info';
            }
        }

        // Dostava
        if ($step == 'dostava') {
            $this->validate($this->address_rules);
        }

        // Plaćanje
        if ($step == 'placanje') {
            $this->validate($this->shipping_rules);
        }

        if ($step == 'placanje' and $this->shipping == 'gls_eu') {
            $this->validate($this->comment_rules);
        }

        if ($step == 'placanje' and $this->shipping == 'gls_paketomat') {
            $this->validate($this->comment_rules);
        }

        $this->step = $step;

        CheckoutSession::setStep($step);
    }


    /**
     * @param string $state
     */
    public function stateSelected($state)
    {
        $this->setAddress(['state' => $state], true);

        if ($this->address['state'] === self::DEFAULT_COUNTRY) {
            if ($this->address['zip']) {
                $this->autofillAddressField('zip', (string) $this->address['zip']);
            } elseif ($this->address['city']) {
                $this->autofillAddressField('city', (string) $this->address['city']);
            }
        }

        CheckoutSession::forgetShipping();
        $this->shipping = '';
        $this->comment = '';
        CheckoutSession::forgetPayment();
        $this->payment = '';

        if ($this->giftVoucherOnly) {
            $this->shipping = GiftVoucherService::SHIPPING_CODE;
            $this->payment = GiftVoucherService::firstAllowedPaymentCode() ?: '';
            CheckoutSession::setShipping($this->shipping);
            if ($this->payment) {
                CheckoutSession::setPayment($this->payment);
            }
        }

        $this->render();
    }


    /**
     * @param string $shipping
     */
    public function selectShipping(string $shipping)
    {
        if ($this->giftVoucherOnly) {
            $shipping = GiftVoucherService::SHIPPING_CODE;
        }

        $this->shipping = $shipping;
        $this->checkShipping($shipping);
        CheckoutSession::setShipping($shipping);
        if($shipping = 'gls_eu'){
            CheckoutSession::setComment('');
        }
        return redirect()->route('naplata', ['step' => 'dostava']);
    }


    /**
     * @param string $payment
     */
    public function selectPayment(string $payment)
    {
        if ($this->giftVoucherOnly && ! GiftVoucherService::isAllowedPaymentCode($payment)) {
            $payment = GiftVoucherService::firstAllowedPaymentCode() ?: $payment;
        }

        $this->payment = $payment;

        $this->checkPayment($payment);

        CheckoutSession::setPayment($payment);
    }


    public function selectHpPak(string $paketomat)
    {
        $this->comment = $paketomat;
         CheckoutSession::setComment($this->comment);

        $this->hp_paketomat = $paketomat;
        $this->search_hp_paketomat_results = [];
    }


    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function render()
    {
        if (trim((string) ($this->address['state'] ?? '')) === '') {
            $this->address['state'] = self::DEFAULT_COUNTRY;
        }

        $geo = (new GeoZone())->findState($this->address['state'] ?: 'Croatia');

        if ( ! isset($geo->id)) {
            $geo->id = 1;
        }

        if ($this->giftVoucherOnly && ! $this->shipping) {
            $this->shipping = GiftVoucherService::SHIPPING_CODE;
            CheckoutSession::setShipping($this->shipping);
        }

        if ($this->giftVoucherOnly && (! $this->payment || ! GiftVoucherService::isAllowedPaymentCode($this->payment))) {
            $this->payment = GiftVoucherService::firstAllowedPaymentCode() ?: '';
            if ($this->payment) {
                CheckoutSession::setPayment($this->payment);
            }
        }

        return view('livewire.front.checkout', [
            'shippingMethods' => (new ShippingMethod())->findGeo($geo->id),
            'paymentMethods' => (new PaymentMethod())->findGeo($geo->id)->checkShipping($this->shipping)->resolve(),
            'countries' => Country::list(),
            'cartSubtotal' => $this->cart ? (float) $this->cart->get()['subtotal'] : 0.0,
            'giftVoucherOnly' => $this->giftVoucherOnly,
        ]);
    }


    /**
     * @param array $value
     *
     * @return array
     */
    private function setAddress(array $value = [], bool $only_state = false)
    {
        if ( ! empty($value)) {
            $value['state'] = trim((string) ($value['state'] ?? '')) ?: self::DEFAULT_COUNTRY;

            if ($only_state) {
                $this->address['state'] = $value['state'];

            } else {
                $this->address = [
                    'fname' => $value['fname'] ?? '',
                    'lname' => $value['lname'] ?? '',
                    'email' => $value['email'] ?? '',
                    'phone' => $value['phone'] ?? '',
                    'address' => $value['address'] ?? '',
                    'city' => $value['city'] ?? '',
                    'company' => $value['company'] ?? '',
                    'oib' => $value['oib'] ?? '',
                    'zip' => $value['zip'] ?? '',
                    'state' => $value['state'],
                ];
            }
        } else {
            if (auth()->user()) {
                $this->address = [
                    'fname' => auth()->user()->details->fname ?? '',
                    'lname' => auth()->user()->details->lname ?? '',
                    'email' => auth()->user()->email ?? '',
                    'phone' => auth()->user()->details->phone ?? '',
                    'address' => auth()->user()->details->address ?? '',
                    'city' => auth()->user()->details->city ?? '',
                    'company' => auth()->user()->details->company ?? '',
                    'oib' => auth()->user()->details->oib ?? '',
                    'zip' => auth()->user()->details->zip ?? '',
                    'state' => trim((string) (auth()->user()->details->state ?? '')) ?: self::DEFAULT_COUNTRY
                ];
            } else {
                $this->address['state'] = self::DEFAULT_COUNTRY;
            }
        }

        CheckoutSession::setAddress($this->address);

        /*CheckoutSession::setGeoZone(
            GeoZone::findState($this->address['state'])
        );*/

        //dd($this->address);

        return $this->address;
    }

    private function autofillAddressField(string $field, string $value): void
    {
        /** @var AddressDirectoryService $directory */
        $directory = app(AddressDirectoryService::class);
        $country = (string) ($this->address['state'] ?? self::DEFAULT_COUNTRY);

        $place = $field === 'zip'
            ? $directory->findByPostal($value, $country)
            : $directory->findByCity($value, $country);

        if ($place) {
            $this->address['zip'] = $place['postal_code'];
            $this->address['city'] = $place['city'];
            $this->address['state'] = self::DEFAULT_COUNTRY;
        }

        CheckoutSession::setAddress($this->address);
    }


    /**
     * @param string $shipping
     *
     * @return void
     */
    private function checkShipping(string $shipping): void
    {
        if ($shipping === GiftVoucherService::SHIPPING_CODE) {
            $this->gdl_shipping = 'poklon bon e-mail dostava';
            $this->view_comment = false;
            $this->view_commentt = false;
            $this->view_hp_paketomat = false;

            return;
        }

        if ($shipping == 'pickup') {
            $this->gdl_shipping = 'osobno preuzimanje';
        } else {
            $this->gdl_shipping = 'dostava';
        }

        if ($shipping == 'gls_eu') {
            $this->view_comment = true;
        } else {
            $this->view_comment = false;
        }

        if ($shipping == 'gls_paketomat') {
            $this->view_commentt = true;
        } else {
            $this->view_commentt = false;
        }

        if ($shipping == 'hp_paketomat') {
            $this->view_hp_paketomat = true;
        } else {
            $this->view_hp_paketomat = false;
        }
    }


    /**
     * @param string $payment
     *
     * @return void
     */
    private function checkPayment(string $payment): void
    {
        if ($payment == 'bank') {
            $this->gdl_payment = 'uplatnica';
        } elseif ($payment == 'cod') {
            $this->gdl_payment = 'pouzeće';
        } else {
            $this->gdl_payment = 'kartica';
        }
    }


    /**
     * @return void
     */
    private function checkCart(): void
    {
        if (session()->has(config('session.cart'))) {
            $this->cart = new AgCart(session(config('session.cart')));
            $this->giftVoucherOnly = (bool) data_get($this->cart->get(), 'gift_voucher_only', false);
        } else {
            $this->cart = false;
            $this->giftVoucherOnly = false;
        }
    }
}
