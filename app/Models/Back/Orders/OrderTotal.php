<?php

namespace App\Models\Back\Orders;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class OrderTotal extends Model
{
    
    /**
     * @var string
     */
    protected $table = 'order_total';
    
    /**
     * @var array
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];
    
    
    /**
     * @param $totals
     * @param $order_id
     *
     * @return bool
     */
    public static function store($totals, $order_id)
    {
        self::where('order_id', $order_id)->delete();
        
        for ($i = 0; $i < count($totals); $i++) {
            self::insertGetId([
                'order_id'   => $order_id,
                'code'       => $totals[$i]->code,
                'title'      => $totals[$i]->name,
                'value'      => $totals[$i]->value,
                'sort_order' => $i,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);
    
            if ($totals[$i]->code == 'total') {
                Order::where('id', $order_id)->update([
                    'total' => $totals[$i]->value
                ]);
            }
        }
        
        return true;
    }
    
    
    /**
     * @param $request
     * @param $order_id
     *
     * @return bool
     */
    public function make($request, $order_id)
    {
        $totals     = collect(config('settings.totals'))->where('status', 1)->sortBy('sort_order');
        $order_data = json_decode($request->order_data);
        
        foreach ($totals as $code => $total) {
            $value = $this->resolveTotalValue($order_data, $code);
            
            $this->insertGetId([
                'order_id'   => $order_id,
                'code'       => $code,
                'title'      => $total['title'],
                'value'      => $value,
                'sort_order' => $total['sort_order'],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);
        }
        
        Order::where('id', $order_id)->update([
            'total' => $order_data->total
        ]);
        
        return true;
    }
    
    
    /**
     * @param        $request
     * @param string $code
     *
     * @return false|float|int
     */
    public function resolveTotalValue($obj, string $code)
    {
        if ($code == 'subtotal') {
            return intval($obj->subtotal);
        }
        /*if ($code == 'nett') {
            return intval($obj->tax[0]->value);
        }*/
        if ($code == 'tax') {
            return intval($obj->tax[0]->value);
        }
        if ($code == 'total') {
            return intval($obj->total);
        }
        
        return false;
    }
    
    
    /**
     * @param $total
     * @param $action
     *
     * @return int
     */
    public static function resolveSortOrder($total, $action)
    {
        if ($total->code == 'subtotal') {
            return 0;
        }
        if ($action and $total->code == 'action') {
            return 1;
        }
        if ($total->code == 'shipping') {
            return $action ? 2 : 1;
        }
        if ($total->code == 'total') {
            return $action ? 3 : 2;
        }
    }
    
    
    /**
     * @param $totals
     *
     * @return bool
     */
    public static function hasAction($totals)
    {
        foreach ($totals as $total) {
            if ($total->code == 'action') {
                return true;
            }
        }
        
        return false;
    }
    
}
