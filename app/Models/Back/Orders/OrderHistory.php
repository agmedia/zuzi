<?php

namespace App\Models\Back\Orders;

use App\Models\Back\Settings\Settings;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class OrderHistory extends Model
{

    /**
     * @var string
     */
    protected $table = 'order_history';

    /**
     * @var array
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];


    /**
     * @return mixed
     */
    public function getStatusAttribute($value)
    {
        $statuses = Settings::get('order', 'statuses');

        return $statuses->where('id', $value)->first();
    }


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function order()
    {
        return $this->hasOne(Order::class, 'id', 'order_id');
    }


    public static function store(int $order_id, Request $request = null)
    {
        $id = self::insertGetId([
            'order_id'   => $order_id,
            'user_id'    => auth()->user()->id,
            'status'     => $request ? $request->input('status') : config('settings.order.status.new'),
            'comment'    => $request ? ($request->input('status') ? 'Status promijenjen... ' . $request->input('comment') : $request->input('comment')) : 'Narudžba napravljena.',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);

        if ($id) {
            return true;
        }

        return false;
    }
}
