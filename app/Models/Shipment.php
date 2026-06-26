<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Shipment extends Model
{
    protected $fillable = [
        'order_id',
        'courier_id',
        'rto_reason_id',
        'tracking_number',
        'shipped_on',
        'expected_delivery_on',
        'delivered_on',
        'rto_on',
        'status',
        'ship_time_hours',
        'shipping_cost',
    ];

    protected function casts(): array
    {
        return [
            'shipped_on' => 'date',
            'expected_delivery_on' => 'date',
            'delivered_on' => 'date',
            'rto_on' => 'date',
            'shipping_cost' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class);
    }

    public function rtoReason(): BelongsTo
    {
        return $this->belongsTo(RtoReason::class);
    }

    public function lostCase(): HasOne
    {
        return $this->hasOne(LostCase::class);
    }
}
