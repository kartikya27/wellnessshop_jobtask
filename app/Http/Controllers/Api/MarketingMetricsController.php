<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignDailyMetric;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketingMetricsController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);
        $metrics = $this->metricsQuery($from, $to)->get();

        $totalSpend = $metrics->sum('spend');
        $revenue = $metrics->sum('revenue');
        $conversions = $metrics->sum('conversions');

        return response()->json([
            'filters' => compact('from', 'to'),
            'data' => [
                'total_spend' => round($totalSpend, 2),
                'revenue' => round($revenue, 2),
                'conversions' => $conversions,
                'blended_roas' => round($revenue / max($totalSpend, 1), 2),
                'blended_cac' => round($totalSpend / max($conversions, 1), 2),
            ],
            'alerts' => [
                'roas_below_threshold' => ($revenue / max($totalSpend, 1)) < 2.0,
                'cac_above_threshold' => ($totalSpend / max($conversions, 1)) > 800,
            ],
        ]);
    }

    public function platform(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => ['required', Rule::in(['meta', 'google'])],
        ]);
        [$from, $to] = $this->dateRange($request);

        $metrics = $this->metricsQuery($from, $to)
            ->whereHas('campaign.platform', fn ($query) => $query->where('slug', $validated['platform']))
            ->get();

        $spend = $metrics->sum('spend');
        $revenue = $metrics->sum('revenue');
        $conversions = $metrics->sum('conversions');
        $impressions = $metrics->sum('impressions');
        $clicks = $metrics->sum('clicks');

        $platform = $metrics->first()?->campaign?->platform;

        return response()->json([
            'filters' => ['platform' => $validated['platform'], 'from' => $from, 'to' => $to],
            'data' => [
                'platform' => $validated['platform'],
                'platform_name' => $platform?->name ?? ucfirst($validated['platform']),
                'spend' => round($spend, 2),
                'revenue' => round($revenue, 2),
                'roas' => round($revenue / max($spend, 1), 2),
                'cpm' => round(($spend / max($impressions, 1)) * 1000, 2),
                'ctr' => round(($clicks / max($impressions, 1)) * 100, 2),
                'cpc' => round($spend / max($clicks, 1), 2),
                'cac' => round($spend / max($conversions, 1), 2),
            ],
        ]);
    }

    public function campaigns(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);

        $campaigns = Campaign::query()
            ->with(['platform', 'dailyMetrics' => fn ($query) => $query->whereBetween('metric_date', [$from, $to])])
            ->get()
            ->map(function (Campaign $campaign) {
                $spend = $campaign->dailyMetrics->sum('spend');
                $revenue = $campaign->dailyMetrics->sum('revenue');

                return [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'status' => $campaign->status,
                    'platform' => $campaign->platform?->slug ?? 'unknown',
                    'spend' => round($spend, 2),
                    'roas' => round($revenue / max($spend, 1), 2),
                ];
            })
            ->sortByDesc('spend')
            ->values();

        return response()->json([
            'filters' => compact('from', 'to'),
            'data' => $campaigns,
        ]);
    }

    public function trends(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);

        $trends = $this->metricsQuery($from, $to)
            ->get()
            ->groupBy(fn (CampaignDailyMetric $metric) => $metric->metric_date->toDateString())
            ->map(function ($metrics, string $date) {
                $spend = $metrics->sum('spend');
                $revenue = $metrics->sum('revenue');

                return [
                    'metric_date' => $date,
                    'spend' => round($spend, 2),
                    'revenue' => round($revenue, 2),
                    'roas' => round($revenue / max($spend, 1), 2),
                ];
            })
            ->sortKeys()
            ->values();

        return response()->json([
            'filters' => compact('from', 'to'),
            'data' => $trends,
        ]);
    }

    private function metricsQuery(string $from, string $to)
    {
        return CampaignDailyMetric::query()
            ->with('campaign.platform')
            ->whereBetween('metric_date', [$from, $to]);
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
