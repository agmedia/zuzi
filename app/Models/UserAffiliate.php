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
}
