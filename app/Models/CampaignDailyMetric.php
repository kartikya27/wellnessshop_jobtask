<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignDailyMetric extends Model
{
    protected $fillable = [
        'campaign_id',
        'metric_date',
        'spend',
        'revenue',
        'impressions',
        'clicks',
        'conversions',
        'average_order_value',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'spend' => 'decimal:2',
            'revenue' => 'decimal:2',
            'average_order_value' => 'decimal:2',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
