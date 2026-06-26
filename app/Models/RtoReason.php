<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RtoReason extends Model
{
    protected $fillable = [
        'reason',
        'category',
        'is_controllable',
    ];

    protected function casts(): array
    {
        return [
            'is_controllable' => 'boolean',
        ];
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}
