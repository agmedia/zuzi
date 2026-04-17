<?php

namespace App\Models\Front;

use App\Models\Back\Marketing\Review;
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
            $has_any = static::hasLoyaltyTotal(auth()->id());

            if ($has_any && $has_any >= static::minimumRedeemPoints()) {
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
        if (auth()->user() && $points) {
            $has_points = static::hasLoyaltyTotal(auth()->id());

            if ($has_points && $has_points >= $points) {
                return static::configuredDiscountForPoints($points);
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


    public static function addPoints(int $points, int $reference_id, string $reference, string $comment, ?int $user_id = null): bool
    {
        if ($points) {
            $user_id = $user_id ?: auth()->id();

            if ( ! $user_id) {
                return false;
            }

            return Loyalty::query()->insert([
                'user_id'      => $user_id,
                'reference_id' => $reference_id,
                'reference'    => $reference,
                'target'       => $reference === 'birthday' ? (string) now()->year : '',
                'comment'      => $comment,
                'earned'       => $points,
                'spend'        => 0,
                'created_at'   => now(),
                'updated_at'   => now()
            ]);
        }

        return false;
    }


    public static function syncProductReviewReward(Review $review): bool
    {
        $points = static::configuredProductReviewPoints();

        if ($points <= 0 || (int) $review->user_id <= 0 || ! $review->exists) {
            return false;
        }

        $balance = static::productReviewRewardBalance((int) $review->id);

        if ((bool) $review->status && $review->qualifiesForMonthlyReward() && $balance <= 0) {
            return static::query()->insert([
                'user_id'      => (int) $review->user_id,
                'reference_id' => (int) $review->product_id,
                'reference'    => 'product_review',
                'target'       => static::productReviewRewardTarget((int) $review->id),
                'comment'      => 'Reward for approved product review.',
                'earned'       => $points,
                'spend'        => 0,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }

        if ((! (bool) $review->status || ! $review->qualifiesForMonthlyReward()) && $balance > 0) {
            return static::query()->insert([
                'user_id'      => (int) $review->user_id,
                'reference_id' => (int) $review->product_id,
                'reference'    => 'product_review',
                'target'       => static::productReviewRewardTarget((int) $review->id),
                'comment'      => 'Reversal for removed product review reward.',
                'earned'       => -$balance,
                'spend'        => 0,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }

        return false;
    }


    public static function clearProductReviewReward(Review $review): bool
    {
        if ((int) $review->user_id <= 0 || ! $review->exists) {
            return false;
        }

        $balance = static::productReviewRewardBalance((int) $review->id);

        if ($balance <= 0) {
            return false;
        }

        return static::query()->insert([
            'user_id'      => (int) $review->user_id,
            'reference_id' => (int) $review->product_id,
            'reference'    => 'product_review',
            'target'       => static::productReviewRewardTarget((int) $review->id),
            'comment'      => 'Reversal for deleted product review reward.',
            'earned'       => -$balance,
            'spend'        => 0,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }


    /**
     * @param int    $points
     * @param int    $reference_id
     * @param string $target
     *
     * @return bool
     */
    public static function removePoints(int $points, int $reference_id, string $reference, ?int $user_id = null, string $comment = ''): bool
    {
        if ($points) {
            $user_id = $user_id ?: auth()->id();

            if ( ! $user_id) {
                return false;
            }

            return Loyalty::query()->insert([
                'user_id'      => $user_id,
                'reference_id' => $reference_id,
                'reference'    => $reference,
                'target'       => '',
                'comment'      => $comment,
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
            $entries = Loyalty::query()->where('reference_id', $order_id)->get();

            if ($entries->isNotEmpty()) {
                return Loyalty::query()->insert($entries->map(function ($entry) {
                    return [
                        'user_id'      => $entry->user_id,
                        'reference_id' => $entry->reference_id,
                        'reference'    => $entry->reference,
                        'target'       => $entry->target,
                        'comment'      => $entry->comment,
                        'earned'       => -intval($entry->earned),
                        'spend'        => -intval($entry->spend),
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ];
                })->all());
            }
        }

        return false;
    }

    private static function minimumRedeemPoints(): int
    {
        $thresholds = collect(config('settings.loyalty.orders_discount', []))
            ->keys()
            ->map(fn ($points) => intval($points))
            ->filter(fn ($points) => $points > 0);

        return $thresholds->isEmpty() ? 0 : $thresholds->min();
    }

    private static function configuredDiscountForPoints(int $points): int
    {
        return intval(config('settings.loyalty.orders_discount.' . $points, 0));
    }

    private static function configuredProductReviewPoints(): int
    {
        return max(0, (int) config('settings.loyalty.product_review', 5));
    }

    private static function productReviewRewardTarget(int $reviewId): string
    {
        return 'review:' . $reviewId;
    }

    private static function productReviewRewardBalance(int $reviewId): int
    {
        return (int) static::query()
            ->where('reference', 'product_review')
            ->where('target', static::productReviewRewardTarget($reviewId))
            ->selectRaw('COALESCE(SUM(earned), 0) - COALESCE(SUM(spend), 0) as balance')
            ->value('balance');
    }

}
