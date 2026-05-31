<?php

namespace App\Models\Back\Marketing;

use App\Models\Back\Catalog\Product\Product;
use App\Models\Back\Orders\Order;
use App\Models\Back\Orders\OrderProduct;
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
        'stars'        => 'integer',
        'featured'     => 'boolean',
        'status'       => 'boolean',
        'has_spoilers' => 'boolean',
        'verified_purchase' => 'boolean',
        'helpful_count' => 'integer',
    ];

    /**
     * @var Request
     */
    protected $request;

    protected ?bool $resolvedVerifiedPurchase = null;


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
            'title'      => ['nullable', 'string', 'max:120'],
            'message'    => ['required', 'string', 'max:1600'],
            'recommended_for' => ['nullable', 'string', 'max:255'],
            'liked_most' => ['nullable', 'string', 'max:255'],
            'tags'       => ['nullable', 'array', 'max:6'],
            'tags.*'     => ['string', 'max:40'],
            'has_spoilers' => ['nullable', 'boolean'],
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


    public static function tagOptions(): array
    {
        return [
            'emotivna' => 'Emotivna',
            'napeta' => 'Napeta',
            'lagana' => 'Lagana',
            'romanticna' => 'Romantična',
            'mracna' => 'Mračna',
            'brzo-se-cita' => 'Brzo se čita',
            'za-poklon' => 'Za poklon',
            'za-klub-citatelja' => 'Za klub čitatelja',
        ];
    }


    public function tagsArray(): array
    {
        $tags = $this->attributes['tags'] ?? null;

        if (blank($tags)) {
            return [];
        }

        $decoded = json_decode((string) $tags, true);

        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->map(fn ($tag) => (string) $tag)
            ->filter()
            ->values()
            ->all();
    }


    public function tagLabels(): array
    {
        $options = static::tagOptions();

        return collect($this->tagsArray())
            ->map(fn ($tag) => $options[$tag] ?? null)
            ->filter()
            ->values()
            ->all();
    }


    public function isVerifiedPurchase(): bool
    {
        if ((bool) $this->verified_purchase) {
            return true;
        }

        if ($this->resolvedVerifiedPurchase !== null) {
            return $this->resolvedVerifiedPurchase;
        }

        $this->resolvedVerifiedPurchase = static::hasPurchasedProduct(
            (int) $this->product_id,
            (int) $this->user_id,
            (string) $this->email
        );

        return $this->resolvedVerifiedPurchase;
    }


    public static function hasPurchasedProduct(int $productId, int $userId = 0, string $email = ''): bool
    {
        $email = trim($email);

        if ($productId <= 0 || ($userId <= 0 && blank($email))) {
            return false;
        }

        $orderIds = Order::query()
            ->whereNotIn('order_status_id', [5, 7, 8])
            ->where(function ($query) use ($userId, $email) {
                if ($userId > 0) {
                    $query->where('user_id', $userId);

                    if (filled($email)) {
                        $query->orWhere('payment_email', $email);
                    }
                } elseif (filled($email)) {
                    $query->where('payment_email', $email);
                }
            })
            ->select('id');

        return OrderProduct::query()
            ->where('product_id', $productId)
            ->whereIn('order_id', $orderIds)
            ->exists();
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
        $productId = (int) $this->request->input('product_id');
        $userId = $method === 'insert' ? (auth()->id() ?: 0) : (int) $this->user_id;
        $email = trim((string) $this->request->input('email'));
        $status = $this->request->has('status')
            ? (int) in_array($this->request->input('status'), ['1', 1, true, 'true', 'on'], true)
            : 0;

        $response = [
            'product_id' => $productId,
            'order_id'   => 0,
            'user_id'    => $userId,
            'lang'       => Str::limit((string) $this->request->input('lang', app()->getLocale()), 2, ''),
            'fname'      => $name[0] ?? '',
            'lname'      => $name[1] ?? '',
            'email'      => $email,
            'avatar'     => 'media/avatar.jpg',
            'title'      => $this->cleanTextInput('title', 120),
            'message'    => $this->cleanTextInput('message', 1600),
            'recommended_for' => $this->cleanTextInput('recommended_for', 255),
            'liked_most' => $this->cleanTextInput('liked_most', 255),
            'tags'       => json_encode($this->selectedTags(), JSON_UNESCAPED_UNICODE),
            'has_spoilers' => $this->truthyInput('has_spoilers') ? 1 : 0,
            'verified_purchase' => static::hasPurchasedProduct($productId, $userId, $email) ? 1 : 0,
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


    private function cleanTextInput(string $key, int $limit): string
    {
        return Str::limit(trim(strip_tags((string) $this->request->input($key, ''))), $limit, '');
    }


    private function selectedTags(): array
    {
        $input = $this->request->input('tags', []);
        $tags = is_array($input) ? $input : [$input];
        $allowed = array_keys(static::tagOptions());

        return collect($tags)
            ->map(fn ($tag) => Str::slug((string) $tag))
            ->filter(fn ($tag) => in_array($tag, $allowed, true))
            ->unique()
            ->take(6)
            ->values()
            ->all();
    }


    private function truthyInput(string $key): bool
    {
        return in_array($this->request->input($key), ['1', 1, true, 'true', 'on'], true);
    }
}
