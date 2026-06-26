<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LostCase extends Model
{
    protected $fillable = [
        'shipment_id',
        'case_number',
        'reported_on',
        'status',
        'claim_filed',
        'claim_amount',
        'amount_recovered',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'reported_on' => 'date',
            'claim_filed' => 'boolean',
            'claim_amount' => 'decimal:2',
            'amount_recovered' => 'decimal:2',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
