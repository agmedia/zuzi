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


    public function reference()
    {
      /*  if ($this->target == 'product_review') {
            return $this->hasOne(Product::class, 'id', 'reference_id');
        }*/
        if ($this->target == 'order') {
            return $this->hasOne(Order::class, 'id', 'reference_id');
        }

        return $this->hasOne(Product::class);
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

            $earned = Loyalty::query()->where('user_id', $user_id)->sum('earned');
            $spent = Loyalty::query()->where('user_id', $user_id)->sum('spend');
            $has_points = intval($earned - $spent);

            if ($has_points && $has_points > $points) {
                if ($points == 100) {
                    return 5;
                }
                if ($points == 200) {
                    return 12;
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
                'target'       => 'order',
                'earned'       => intval($order->total),
                'spend'        => $spent,
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
    public static function addPoints(int $points, int $reference_id, string $target, int $user_id = null): bool
    {
        if (auth()->user() && $points) {
            if ( ! $user_id) {
                $user_id = auth()->user()->id;
            }

            return Loyalty::query()->insert([
                'user_id'      => $user_id,
                'reference_id' => $reference_id,
                'target'       => $target,
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
    public static function removePoints(int $points, int $reference_id, string $target, int $user_id = null): bool
    {
        if (auth()->user() && $points) {
            if ( ! $user_id) {
                $user_id = auth()->user()->id;
            }

            return Loyalty::query()->insert([
                'user_id'      => $user_id,
                'reference_id' => $reference_id,
                'target'       => $target,
                'earned'       => 0,
                'spend'        => $points,
                'created_at'   => now(),
                'updated_at'   => now()
            ]);
        }

        return false;
    }

}
