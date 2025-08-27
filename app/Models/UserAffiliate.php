<?php

namespace App\Models;

use App\Models\Front\Loyalty;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UserAffiliate extends Model
{

    /**
     * @var string
     */
    protected $table = 'user_affiliates';

    protected $casts = [
        'active' => 'boolean',
        'registered_at' => 'datetime',
        'first_purchase_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_email', 'email');
    }

    /*******************************************************************************
    *                                Copyright : AGmedia                           *
    *                              email: filip@agmedia.hr                         *
    *******************************************************************************/

    /**
     * @param User $user
     *
     * @return string
     */
    public static function checkAffiliateName(User $user): string
    {
        $details = $user->details;

        if ( ! $details->affiliate_name) {
            $name = Str::slug(strtok($user->name, ' '));

            $has_name = UserDetail::query()->where('affiliate_name', $name)->first();

            if ($has_name) {
                $name = $name . '-' . Str::random(3);
            }

            UserDetail::query()->where('user_id', $user->id)->update([
                'affiliate_name' => $name
            ]);

            return $name;
        }

        return $details->affiliate_name;
    }
}
