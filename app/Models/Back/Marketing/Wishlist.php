<?php

namespace App\Models\Back\Marketing;

use App\Mail\WishlistArrived;
use App\Models\Front\Catalog\Product;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Helpers\Recaptcha;

class Wishlist extends Model
{

    /**
     * @var string
     */
    protected $table = 'wishlist';

    /**
     * @var array
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * @var array
     */
    protected $casts = [
        'sent_at' => 'datetime',
    ];

    /**
     * @var Request
     */
    protected $request;


    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }


    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', 0);
    }


    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopeSent(Builder $query): Builder
    {
        return $query->where('sent', 1);
    }


    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopeUnsent(Builder $query): Builder
    {
        return $query->where('sent', 0);
    }


    /**
     * @param $query
     *
     * @return mixed
     */
    public function scopeBasic(Builder $query): Builder
    {
        return $query->select('product_id', 'email');
    }

    public function product()
    {
        return $this->belongsTo(\App\Models\Front\Catalog\Product::class, 'product_id');
    }



    /**
     * Validate new category Request.
     *
     * @param Request $request
     *
     * @return $this
     */
    public function validateRequest(Request $request)
    {
        $request->validate([
            'email'      => 'required',
            'product_id' => 'required',
            'recaptcha'  => 'required'
        ]);

        $this->request = $request;

        return $this;
    }


    /**
     * Store new category.
     *
     * @return false
     */
    public function create()
    {
        // Provjeri postoji li već zapis za ovaj email i product_id
        $exists = static::where('email', $this->request->email)
            ->where('product_id', $this->request->product_id)
            ->where('sent', 0)
            ->exists();

        if ($exists) {
            // po želji vrati poruku da je korisnik već prijavljen na obavijest za taj artikl
            return false;
        }

        $id = $this->insertGetId([
            'user_id'    => auth()->guest() ? 0 : auth()->user()->id,
            'email'      => $this->request->email,
            'product_id' => $this->request->product_id,
            'sent'       => 0,
            'sent_at'    => null,
            'status'     => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id ? $this->find($id) : false;
    }


    /**
     * @return int
     */
    public static function check_CRON()
    {
        $log_start = microtime(true);

        $list = Wishlist::active()->unsent()->basic()->get();
        $ids = $list->unique('product_id')->pluck('product_id');
        $products = Product::query()->whereIn('id', $ids)->available()->basicData()->get();

        foreach ($products as $product) {
            $emails = $list->where('product_id', $product->id)->pluck('email');

            foreach ($emails as $email) {
                dispatch(function () use ($email, $product) {
                    Mail::to($email)->send(new WishlistArrived($product));
                });

                $wishlistEntry = Wishlist::query()->where('product_id', $product->id)->where('email', $email)->where('sent', 0)->first();

                if ($wishlistEntry) {
                    $sentAt = now();

                    $wishlistEntry->update([
                        'sent' => 1,
                        'status' => 0,
                        'sent_at' => $sentAt,
                    ]);

                    Log::info('__Wishlist Notification Sent', [
                        'wishlist_id' => $wishlistEntry->id,
                        'product_id' => $product->id,
                        'email' => $email,
                        'sent_at' => $sentAt->toDateTimeString(),
                    ]);
                }
            }
        }

        $log_end = microtime(true);
        Log::info('__Check Wishlist - Total Execution Time: ' . number_format(($log_end - $log_start), 2, ',', '.') . ' sec.');

        return 1;
    }

}
