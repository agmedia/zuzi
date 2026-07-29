<?php

namespace App\Models;

use App\Models\Back\Orders\Order;
use Illuminate\Database\Eloquent\Model;

class ContractWithdrawal extends Model
{
    public const STATUS_RECEIVED = 'received';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_DECLINED = 'declined';

    protected $fillable = [
        'reference',
        'submission_key',
        'user_id',
        'order_id',
        'order_number',
        'full_name',
        'email',
        'phone',
        'address_line',
        'postal_code',
        'city',
        'country_code',
        'contract_date',
        'received_date',
        'items',
        'note',
        'declaration',
        'request_snapshot',
        'snapshot_hash',
        'status',
        'internal_note',
        'locale',
        'submitted_at',
        'consumer_notified_at',
        'admin_notified_at',
        'notification_error',
        'handled_by',
        'handled_at',
        'completed_at',
        'ip_address',
        'user_agent',
    ];

    protected $hidden = [
        'submission_key',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'order_id' => 'integer',
        'contract_date' => 'date',
        'received_date' => 'date',
        'request_snapshot' => 'array',
        'submitted_at' => 'datetime',
        'consumer_notified_at' => 'datetime',
        'admin_notified_at' => 'datetime',
        'handled_by' => 'integer',
        'handled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public static function statuses(): array
    {
        return [
            self::STATUS_RECEIVED => 'Zaprimljeno',
            self::STATUS_PROCESSING => 'U obradi',
            self::STATUS_COMPLETED => 'Dovršeno',
            self::STATUS_DECLINED => 'Odbijeno',
        ];
    }

    public static function statusColors(): array
    {
        return [
            self::STATUS_RECEIVED => 'primary',
            self::STATUS_PROCESSING => 'warning',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_DECLINED => 'danger',
        ];
    }

    public static function snapshotHash(array $snapshot): string
    {
        return hash(
            'sha256',
            json_encode(
                static::canonicalizeSnapshotValue($snapshot),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            )
        );
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    private static function canonicalizeSnapshotValue($value)
    {
        if (! is_array($value)) {
            return $value;
        }

        $keys = array_keys($value);
        $isList = $value === [] || $keys === range(0, count($value) - 1);

        if (! $isList) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            $value[$key] = static::canonicalizeSnapshotValue($item);
        }

        return $value;
    }
}
