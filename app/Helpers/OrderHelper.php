<?php

namespace App\Helpers;

use App\Helpers\Session\CheckoutSession;
use App\Mail\OrderReceived;
use App\Mail\OrderSent;
use App\Models\Back\Orders\Order;
use App\Models\Front\Loyalty;
use App\Models\UserAffiliate;
use App\Models\UserDetail;
use App\Services\AffiliateService;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Mail;

/**
 *
 */
class OrderHelper
{

    /**
     * @var int|string|null
     */
    private $order_id = null;

    /**
     * @var \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    private $order;

    /**
     * @var null
     */
    private static $_instance = null;


    /**
     * @param string|int|null $order_id
     */
    public function __construct(string|int $order_id = null)
    {
        if ($order_id) {
            $this->order_id = $order_id;
            $this->order    = Order::query()->where('id', $order_id)->first();
        }

        return $this;
    }


    /**
     * @return bool
     */
    public function isValid(): bool
    {
        if ($this->getOrder()) {
            return true;
        }

        return false;
    }


    /**
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function getOrder()
    {
        return $this->order;
    }


    /**
     * @param string $column
     *
     * @return $this|\Illuminate\Database\Eloquent\HigherOrderBuilderProxy|mixed
     */
    public function getEmail(string $column = 'payment')
    {
        if ($this->getOrder()) {
            if (isset($this->order->{$column . '_email'})) {
                return $this->order->{$column . '_email'};
            }

            return $this->order->payment_email;
        }

        return $this;
    }


    /**
     * @return $this
     */
    public function sendEmails()
    {
        if ($this->getOrder()) {
            $order_data = $this->order;
            $email = $this->getEmail();

            dispatch(function () use ($order_data, $email) {
                Mail::to(config('mail.admin'))->send(new OrderReceived($order_data));
                Mail::to($email)->send(new OrderSent($order_data));
            })->afterResponse();
        }

        return $this;
    }


    /**
     * @param bool $disable_on_zero
     *
     * @return $this
     */
    public function decreaseCartItems(bool $disable_on_zero = true)
    {
        if ($this->getOrder()) {
            foreach ($this->order->products as $product) {
                $real = $product->real;

                if ($real->decrease && $real->quantity >= $product->quantity ) {
                    $real->decrement('quantity', $product->quantity);
                }

                if ($disable_on_zero) {
                    if ( ! $real->quantity) {
                        $real->update([
                            'status' => 0
                        ]);
                    }
                }
            }
        }

        return $this;
    }


    /**
     * @return $this
     */
    public function addLoyaltyPoints()
    {
        if ($this->getOrder()) {
            // ako je kupac regan
            if (auth()->check()) {
                $points = floor($this->order->total);

                // Dobiva bodove za narudžbu, default.
                Loyalty::addPoints($points, $this->order_id, 'order', '', auth()->id());

                // Provjerava ima li više narudžbi ovaj mjesec za dodatne bodove.
                $orders = Order::query()->where('user_id', auth()->id())->whereDate('created_at', '>=', now()->subMonth())->count();

                foreach (config('settings.loyalty.rewards.orders_per_month') as $number_of_orders => $reward_points) {
                    if ($orders >= $number_of_orders) {
                        Loyalty::addPoints($reward_points, $this->order_id, 'order', $number_of_orders . ' Multiple orders reward.', auth()->id());

                        break;
                    }
                }

                // Provjerava je li ovo prva narudžba
                $orders = Order::query()->where('user_id', auth()->id())->count();

                if ( ! $orders) {
                    Loyalty::addPoints(config('settings.loyalty.first_order_points'), $this->order_id, 'order', 'First order reward.', auth()->id());
                }
            }

            // Provjerava je li možda affiliate korisnik.
            if (Cookie::has('affiliate')) {
                $customer_email = $this->getOrder()->payment_email;

                if (auth()->check()) {
                    $customer_email = auth()->user()->email;
                }

                $has_first_purchase = UserAffiliate::query()->where('customer_email', $customer_email)->first();

                if ( ! $has_first_purchase) {
                    $user_details = UserDetail::query()->where('affiliate_nema', request()->cookie('affiliate'))->first();

                    if ($user_details) {
                        UserAffiliate::create([
                            'user_id' => $user_details->user_id,
                            'customer_email' => $customer_email,
                            'affiliate_code' => $this->generateAffiliateCode($user),
                            'active' => 1
                        ]);

                        $referal_points = config('settings.loyalty.affiliate_points');
                        $order_id = $this->getOrder()->id;

                        Loyalty::addPoints($referal_points, $order_id, 'affiliate_referral', 'Referral reward points', $user_details->user_id);

                        // Award points to referred customer
                        if (auth()->check()) {
                            Loyalty::addPoints($referal_points, $order_id, 'order', 'Welcome referral points', auth()->user()->id);
                        }
                    }
                }

            }

        }

        return $this;
    }


    /**
     * @param string      $email_column
     * @param string|null $audience_id
     *
     * @return $this
     */
    public function addCustomerToMailchimp(string $email_column = 'payment', string $audience_id = null)
    {
        if ($this->getOrder()) {
           $mailchimp   = new Mailchimp();
            $audience_id = $audience_id ?: config('services.mailchimp.audience_id');

            $mailchimp->addMemberToList(
                $audience_id,
                $this->getEmail($email_column),
                $this->order->payment_fname,
                $this->order->payment_lname
            );
        }

       // return $this;
    }


    /**
     * @return $this
     */
    public function forgetCheckoutCache()
    {
        CheckoutSession::forgetCheckout();

        return $this;
    }

    /*******************************************************************************
     *                                Copyright : AGmedia                           *
     *                              email: filip@agmedia.hr                         *
     *******************************************************************************/

    /**
     * @param string|int $order_id
     *
     * @return OrderHelper|null
     */
    public static function get(string|int $order_id)
    {
        if (self::$_instance === null) {
            self::$_instance = new OrderHelper($order_id);
        }

        return self::$_instance;
    }


    /**
     * @param int $status
     *
     * @return bool
     */
    public static function isCanceled(int $status): bool
    {
        if (is_array(config('settings.order.canceled_status'))) {
            foreach (config('settings.order.canceled_status') as $value) {
                if ($value == $status) {
                    return true;
                }
            }
        } else {
            if (config('settings.order.canceled_status') == $status) {
                return true;
            }
        }

        return false;
    }


    /**
     * @param array $data
     *
     * @return bool
     */
    public static function isFreeShipping(array $data): bool
    {
        return (config('settings.free_shipping') < $data['total']) ? true : false;
    }
}
