<?php

namespace App\Helpers;

use App\Helpers\Session\CheckoutSession;
use App\Mail\OrderReceived;
use App\Mail\OrderSent;
use App\Models\Back\Orders\Order;
use App\Models\Front\Loyalty;
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
        if ($this->getOrder() && auth()->check()) {
            $points = floor($this->order->total);

            Loyalty::addPoints($points, $this->order_id, 'order', '', auth()->id());

            $orders = Order::query()->where('user_id', auth()->id())->whereDate('created_at', '>=', now()->subMonth())->count();

            foreach (config('settings.loyalty.rewards.orders_per_month') as $number_of_orders => $reward_points) {
                if ($orders >= $number_of_orders) {
                    Loyalty::addPoints($reward_points, $this->order_id, 'order', 'Multiple orders reward.', auth()->id());

                    break;
                }
            }

            $orders = Order::query()->where('user_id', auth()->id())->count();

            if ( ! $orders) {
                Loyalty::addPoints(config('settings.loyalty.first_order_points'), $this->order_id, 'order', 'First order reward.', auth()->id());
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
