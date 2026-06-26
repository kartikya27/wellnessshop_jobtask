<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'order_date',
        'customer_state',
        'customer_city',
        'product_category',
        'order_value',
        'payment_method',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'order_value' => 'decimal:2',
        ];
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }
}
