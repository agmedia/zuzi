<?php

namespace App\Models\Back\Orders;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    //
    /**
     * @var string
     */
    protected $table = 'order_transactions';

    /**
     * @var array
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];
}
