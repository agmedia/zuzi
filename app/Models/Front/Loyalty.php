<?php

namespace App\Models\Front;

use App\Models\Front\Catalog\Product;
use App\Models\Back\Orders\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Loyalty extends Model
{

    /**
     * @var string
     */
    protected $table = 'loyalty';

    /**
     * @var array
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne|mixed
     */
    public function getReferenceModel()
    {
        if ($this->reference == 'product_review') {
            return $this->hasOne(Product::class, 'id', 'reference_id');
        }
        if ($this->reference == 'order') {
            return $this->hasOne(Order::class, 'id', 'reference_id');
        }

        return $this->reference;
    }


    /**
     * @return int
     */
    public static function hasLoyalty(): int
    {
        if (auth()->user()) {
            $user_id = auth()->user()->id;

            $earned = Loyalty::query()->where('user_id', $user_id)->sum('earned');
            $spent = Loyalty::query()->where('user_id', $user_id)->sum('spend');
            $has_any = intval($earned - $spent);

            if ($has_any && $has_any > 100) {
                return $has_any;
            }
        }

        return 0;
    }


    /**
     * @return int
     */
    public static function hasLoyaltyTotal($user_id): int
    {
        if ($user_id) {
            $earned = Loyalty::query()->where('user_id', $user_id)->sum('earned');
            $spent = Loyalty::query()->where('user_id', $user_id)->sum('spend');
            $has_any = intval($earned - $spent);

            if ($has_any) {
                return $has_any;
            }
        }

        return 0;
    }


    /**
     * @param int $points
     *
     * @return int
     */
    public static function calculateLoyalty(int $points = 0): int
    {
        /*Log::info('calculateLoyalty:: $points');
        Log::info($points);*/

        if (auth()->user() && $points) {
            $user_id = auth()->user()->id;

            $earned     = Loyalty::query()->where('user_id', $user_id)->sum('earned');
            $spent      = Loyalty::query()->where('user_id', $user_id)->sum('spend');
            $has_points = intval($earned - $spent);

            if ($has_points && $has_points > $points) {
                foreach (config('settings.loyalty.orders_discount') as $spent_points => $earned_euros) {
                    if ($points == $spent_points) {
                        return $earned_euros;
                    }
                }
            }
        }

        return 0;
    }


    /**
     * @param array $cart
     * @param Order $order
     *
     * @return bool
     */
    public static function resolveOrder(array $cart, Order $order): bool
    {
        $spent = 0;

        if ($cart['loyalty']) {
            $spent = $cart['loyalty'];
        }

        if (auth()->user()) {
            return Loyalty::query()->insert([
                'user_id'      => auth()->user()->id,
                'reference_id' => $order->id,
                'reference'    => 'order',
                'target'       => '',
                'comment'      => '',
                'earned'       => intval($order->total),
                'spend'        => $spent,
                'created_at'   => now(),
                'updated_at'   => now()
            ]);
        }

        return false;
    }


    public static function addPoints(int $points, int $reference_id, string $reference, string $comment, int $user_id = null): bool

    {
        if (auth()->user() && $points) {
            if ( ! $user_id) {
                $user_id = auth()->user()->id;
            }

            return Loyalty::query()->insert([
                'user_id'      => $user_id,
                'reference_id' => $reference_id,
                'reference'    => $reference,
                'target'       => '',
                'comment'      => '',
                'earned'       => $points,
                'spend'        => 0,
                'created_at'   => now(),
                'updated_at'   => now()
            ]);
        }

        return false;
    }


    /**
     * @param int    $points
     * @param int    $reference_id
     * @param string $target
     *
     * @return bool
     */
    public static function removePoints(int $points, int $reference_id, string $reference, int $user_id = null): bool
    {
        if (auth()->user() && $points) {
            if ( ! $user_id) {
                $user_id = auth()->user()->id;
            }

            return Loyalty::query()->insert([
                'user_id'      => $user_id,
                'reference_id' => $reference_id,
                'reference'    => $reference,
                'target'       => '',
                'comment'      => '',
                'earned'       => 0,
                'spend'        => $points,
                'created_at'   => now(),
                'updated_at'   => now()
            ]);
        }

        return false;
    }


    /**
     * @param int $order_id
     *
     * @return bool
     */
    public static function cancelPoints(int $order_id): bool
    {
        if (auth()->user() && $order_id) {
            $loyalty = Loyalty::query()->where('order_id', $order_id)->first();

            if ($loyalty) {
                return Loyalty::query()->insert([
                    'user_id'      => $loyalty->user_id,
                    'reference_id' => $loyalty->reference_id,
                    'reference'    => $loyalty->reference,
                    'target'       => $loyalty->target,
                    'comment'      => $loyalty->comment,
                    'earned'       => -$loyalty->earned,
                    'spend'        => 0,
                    'created_at'   => now(),
                    'updated_at'   => now()
                ]);
            }
        }

        return false;
    }

}
