<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MarketingMetricsController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);

        $metrics = $this->marketingBaseQuery($from, $to)
            ->selectRaw('
                COALESCE(SUM(campaign_daily_metrics.spend), 0) as total_spend,
                COALESCE(SUM(campaign_daily_metrics.revenue), 0) as revenue,
                COALESCE(SUM(campaign_daily_metrics.conversions), 0) as conversions,
                ROUND(COALESCE(SUM(campaign_daily_metrics.revenue) / NULLIF(SUM(campaign_daily_metrics.spend), 0), 0), 2) as blended_roas,
                ROUND(COALESCE(SUM(campaign_daily_metrics.spend) / NULLIF(SUM(campaign_daily_metrics.conversions), 0), 0), 2) as blended_cac
            ')
            ->first();

        return response()->json([
            'filters' => compact('from', 'to'),
            'data' => $metrics,
            'alerts' => [
                'roas_below_threshold' => (float) $metrics->blended_roas < 2.0,
                'cac_above_threshold' => (float) $metrics->blended_cac > 800,
            ],
        ]);
    }

    public function platform(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => ['required', Rule::in(['meta', 'google'])],
        ]);
        [$from, $to] = $this->dateRange($request);

        $metrics = $this->marketingBaseQuery($from, $to)
            ->where('ad_platforms.slug', $validated['platform'])
            ->selectRaw('
                ad_platforms.slug as platform,
                ad_platforms.name as platform_name,
                COALESCE(SUM(campaign_daily_metrics.spend), 0) as spend,
                COALESCE(SUM(campaign_daily_metrics.revenue), 0) as revenue,
                ROUND(COALESCE(SUM(campaign_daily_metrics.revenue) / NULLIF(SUM(campaign_daily_metrics.spend), 0), 0), 2) as roas,
                ROUND(COALESCE((SUM(campaign_daily_metrics.spend) / NULLIF(SUM(campaign_daily_metrics.impressions), 0)) * 1000, 0), 2) as cpm,
                ROUND(COALESCE((SUM(campaign_daily_metrics.clicks) * 100.0) / NULLIF(SUM(campaign_daily_metrics.impressions), 0), 0), 2) as ctr,
                ROUND(COALESCE(SUM(campaign_daily_metrics.spend) / NULLIF(SUM(campaign_daily_metrics.clicks), 0), 0), 2) as cpc,
                ROUND(COALESCE(SUM(campaign_daily_metrics.spend) / NULLIF(SUM(campaign_daily_metrics.conversions), 0), 0), 2) as cac
            ')
            ->groupBy('ad_platforms.id', 'ad_platforms.slug', 'ad_platforms.name')
            ->first();

        return response()->json([
            'filters' => ['platform' => $validated['platform'], 'from' => $from, 'to' => $to],
            'data' => $metrics,
        ]);
    }

    public function campaigns(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);

        $campaigns = $this->marketingBaseQuery($from, $to)
            ->selectRaw('
                campaigns.id,
                campaigns.name,
                campaigns.status,
                ad_platforms.slug as platform,
                COALESCE(SUM(campaign_daily_metrics.spend), 0) as spend,
                ROUND(COALESCE(SUM(campaign_daily_metrics.revenue) / NULLIF(SUM(campaign_daily_metrics.spend), 0), 0), 2) as roas
            ')
            ->groupBy('campaigns.id', 'campaigns.name', 'campaigns.status', 'ad_platforms.slug')
            ->orderByDesc('spend')
            ->get();

        return response()->json([
            'filters' => compact('from', 'to'),
            'data' => $campaigns,
        ]);
    }

    public function trends(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);

        $trends = $this->marketingBaseQuery($from, $to)
            ->selectRaw('
                campaign_daily_metrics.metric_date,
                ROUND(SUM(campaign_daily_metrics.spend), 2) as spend,
                ROUND(SUM(campaign_daily_metrics.revenue), 2) as revenue,
                ROUND(COALESCE(SUM(campaign_daily_metrics.revenue) / NULLIF(SUM(campaign_daily_metrics.spend), 0), 0), 2) as roas
            ')
            ->groupBy('campaign_daily_metrics.metric_date')
            ->orderBy('campaign_daily_metrics.metric_date')
            ->get();

        return response()->json([
            'filters' => compact('from', 'to'),
            'data' => $trends,
        ]);
    }

    private function marketingBaseQuery(string $from, string $to): Builder
    {
        return DB::table('campaign_daily_metrics')
            ->join('campaigns', 'campaigns.id', '=', 'campaign_daily_metrics.campaign_id')
            ->join('ad_platforms', 'ad_platforms.id', '=', 'campaigns.ad_platform_id')
            ->whereBetween('campaign_daily_metrics.metric_date', [$from, $to]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function dateRange(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return [
            $validated['from'] ?? now()->subDays(30)->toDateString(),
            $validated['to'] ?? now()->toDateString(),
        ];
    }
}
