<?php

namespace App\Models\Back\Marketing;

use App\Models\Back\Catalog\Product\Product;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class Review extends Model
{
    /**
     * @var string
     */
    protected $table = 'reviews';

    /**
     * @var array
     */
    protected $guarded = ['id', 'created_at'];

    /**
     * @var array
     */
    protected $casts = [
        'stars'    => 'integer',
        'featured' => 'boolean',
        'status'   => 'boolean',
    ];

    /**
     * @var Request
     */
    protected $request;


    /**
     * @param Request $request
     *
     * @return $this
     */
    public function validateRequest(Request $request)
    {
        $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'email'      => ['required', 'string', 'email', 'max:255'],
            'stars'      => ['required', 'integer', 'between:1,5'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'lang'       => ['required', 'string', 'max:2'],
            'message'    => ['required', 'string', 'max:1000'],
        ]);

        $this->request = $request;

        return $this;
    }


    /**
     * @return false|self
     */
    public function create()
    {
        $id = $this->insertGetId($this->createModelArray());

        if ($id) {
            return $this->find($id);
        }

        return false;
    }


    /**
     * @return bool|self
     */
    public function edit()
    {
        $updated = $this->update($this->createModelArray('update'));

        if ($updated) {
            return $this;
        }

        return false;
    }


    /**
     * @return BelongsTo
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }


    public static function monthlyLimit(): int
    {
        return max(0, (int) config('settings.loyalty.product_review_monthly_limit', 3));
    }


    public static function rewardPoints(): int
    {
        return max(0, (int) config('settings.loyalty.product_review', 5));
    }


    public static function countForUserInMonth(int $userId, ?CarbonInterface $date = null): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $date = $date ? Carbon::instance($date) : now();

        return static::query()
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()])
            ->count();
    }


    public static function hasReachedMonthlyLimitForUser(int $userId, ?CarbonInterface $date = null): bool
    {
        $limit = static::monthlyLimit();

        if ($limit <= 0 || $userId <= 0) {
            return false;
        }

        return static::countForUserInMonth($userId, $date) >= $limit;
    }


    public function qualifiesForMonthlyReward(): bool
    {
        if ((int) $this->user_id <= 0) {
            return false;
        }

        $limit = static::monthlyLimit();

        if ($limit <= 0 || ! $this->exists) {
            return false;
        }

        $createdAt = $this->created_at ? Carbon::parse($this->created_at) : now();

        $eligibleReviewIds = static::query()
            ->where('user_id', (int) $this->user_id)
            ->whereBetween('created_at', [$createdAt->copy()->startOfMonth(), $createdAt->copy()->endOfMonth()])
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        return $eligibleReviewIds->contains((int) $this->id);
    }


    /**
     * @param string $method
     *
     * @return array
     */
    private function createModelArray(string $method = 'insert'): array
    {
        $name = preg_split('/\s+/', trim((string) $this->request->input('name')), 2) ?: [];
        $status = $this->request->has('status')
            ? (int) in_array($this->request->input('status'), ['1', 1, true, 'true', 'on'], true)
            : 0;

        $response = [
            'product_id' => (int) $this->request->input('product_id'),
            'order_id'   => 0,
            'user_id'    => auth()->id() ?: 0,
            'lang'       => Str::limit((string) $this->request->input('lang', app()->getLocale()), 2, ''),
            'fname'      => $name[0] ?? '',
            'lname'      => $name[1] ?? '',
            'email'      => trim((string) $this->request->input('email')),
            'avatar'     => 'media/avatar.jpg',
            'message'    => trim(strip_tags((string) $this->request->input('message'))),
            'stars'      => (int) $this->request->input('stars', 5),
            'sort_order' => (int) $this->request->input('sort_order', 0),
            'featured'   => $this->request->has('featured')
                ? (int) in_array($this->request->input('featured'), ['1', 1, true, 'true', 'on'], true)
                : 0,
            'status'     => $status,
            'updated_at' => Carbon::now(),
        ];

        if ($method == 'insert') {
            $response['created_at'] = Carbon::now();
        }

        return $response;
    }
}
