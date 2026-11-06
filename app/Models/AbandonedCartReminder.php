<?php

namespace App\Models;

use App\Models\Back\Orders\Order;
use Illuminate\Database\Eloquent\Model;

class AbandonedCartReminder extends Model
{
    public const SOURCE_AUTOMATIC = 'automatic';
    public const SOURCE_MANUAL = 'manual';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'sequence' => 'integer',
        'scheduled_for' => 'datetime',
        'attempted_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
