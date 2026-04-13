<?php

namespace App\Models;

use App\Models\Back\Marketing\Action;
use App\Models\Back\Orders\Order;
use Illuminate\Database\Eloquent\Model;

class GiftVoucher extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'fulfilled_at' => 'datetime',
        'email_sent_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function action()
    {
        return $this->belongsTo(Action::class, 'action_id');
    }

    public function getIsRedeemedAttribute(): bool
    {
        return (bool) ($this->action && ! $this->action->status);
    }

    public function getDisplayStatusAttribute(): string
    {
        if ($this->status === 'cancelled') {
            return 'Otkazan';
        }

        if ($this->is_redeemed) {
            return 'Iskorišten';
        }

        if ($this->email_sent_at) {
            return 'Poslan';
        }

        if ($this->fulfilled_at) {
            return 'Plaćen';
        }

        return 'Na čekanju';
    }

    public function getStatusColorAttribute(): string
    {
        if ($this->status === 'cancelled') {
            return 'danger';
        }

        if ($this->is_redeemed) {
            return 'secondary';
        }

        if ($this->email_sent_at) {
            return 'success';
        }

        if ($this->fulfilled_at) {
            return 'primary';
        }

        return 'warning';
    }
}
