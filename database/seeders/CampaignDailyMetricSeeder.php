<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\CampaignDailyMetric;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;

class CampaignDailyMetricSeeder extends Seeder
{
    public function run(): void
    {
        $campaigns = Campaign::query()->with('platform')->get();
        $start = CarbonImmutable::today()->subMonths(3)->startOfDay();
        $end = CarbonImmutable::yesterday()->startOfDay();

        foreach (CarbonPeriod::create($start, $end) as $date) {
            $weekdayMultiplier = in_array($date->dayOfWeekIso, [6, 7], true) ? 1.18 : 1.0;
            $seasonality = 1 + (sin($start->diffInDays($date) / 8) * 0.08);

            foreach ($campaigns as $campaign) {
                $statusMultiplier = $campaign->status === 'paused' ? 0.35 : 1.0;
                $platformMultiplier = $campaign->platform->slug === 'google' ? 0.92 : 1.06;
                $spend = round($campaign->daily_budget * $weekdayMultiplier * $seasonality * $statusMultiplier * $platformMultiplier * fake()->randomFloat(2, 0.84, 1.13), 2);
                $cpm = $campaign->platform->slug === 'meta' ? fake()->randomFloat(2, 145, 235) : fake()->randomFloat(2, 95, 180);
                $ctr = $campaign->platform->slug === 'meta' ? fake()->randomFloat(4, 0.0095, 0.021) : fake()->randomFloat(4, 0.025, 0.055);
                $impressions = max(1000, (int) round(($spend / $cpm) * 1000));
                $clicks = max(20, (int) round($impressions * $ctr));
                $aov = fake()->randomFloat(2, 1320, 2650);
                $roasBase = $campaign->platform->slug === 'google' ? fake()->randomFloat(2, 2.25, 4.85) : fake()->randomFloat(2, 1.75, 4.25);
                $revenue = round($spend * $roasBase, 2);
                $conversions = max(1, (int) round($revenue / $aov));

                CampaignDailyMetric::query()->create([
                    'campaign_id' => $campaign->id,
                    'metric_date' => $date->toDateString(),
                    'spend' => $spend,
                    'revenue' => $revenue,
                    'impressions' => $impressions,
                    'clicks' => $clicks,
                    'conversions' => $conversions,
                    'average_order_value' => $aov,
                ]);
            }
        }
    }
}
