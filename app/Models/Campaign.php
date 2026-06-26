<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $fillable = [
        'ad_platform_id',
        'name',
        'objective',
        'status',
        'started_on',
        'ended_on',
        'daily_budget',
    ];

    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'ended_on' => 'date',
            'daily_budget' => 'decimal:2',
        ];
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(AdPlatform::class, 'ad_platform_id');
    }

    public function dailyMetrics(): HasMany
    {
        return $this->hasMany(CampaignDailyMetric::class);
    }
}
