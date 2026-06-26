<?php

namespace App\Http\Controllers;

use App\Models\AdPlatform;
use App\Models\Campaign;
use App\Models\CampaignDailyMetric;
use App\Models\Courier;
use App\Models\LostCase;
use App\Models\Order;
use App\Models\RtoReason;
use App\Models\Shipment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function marketing(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);
        $platform = $request->string('platform')->toString() ?: null;
        $search = trim($request->string('search')->toString());
        $metrics = $this->marketingMetrics($from, $to, $platform, $search);
        $overview = $this->marketingOverview($metrics);
        $platforms = $this->marketingPlatforms($metrics);
        $campaigns = $this->marketingCampaigns($from, $to, $platform, $search);
        $trends = $this->marketingTrends($metrics);
        $trendChart = $this->chartSeries($trends, 30);

        return view('marketing', [
            'title' => 'Marketing Dashboard',
            'subtitle' => 'Spend, revenue, ROAS, CAC, platform performance, and campaign trends.',
            'assistantDepartment' => 'marketing',
            'filters' => compact('from', 'to', 'platform', 'search'),
            'overview' => $overview,
            'platforms' => $platforms,
            'campaigns' => $campaigns,
            'trends' => $trends,
            'trendChart' => $trendChart,
            'assistantContext' => [
                'department' => 'marketing',
                'filters' => compact('from', 'to', 'platform', 'search'),
                'overview' => $overview,
                'platforms' => $platforms,
                'campaigns' => $campaigns,
                'trends' => $trends,
            ],
        ]);
    }

    public function operations(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);
        $courier = $request->string('courier')->toString() ?: null;
        $status = $request->string('status')->toString() ?: null;
        $search = trim($request->string('search')->toString());
        $shipments = $this->operationsShipments($from, $to, $courier, $status, $search);
        $overview = $this->operationsOverview($shipments);
        $couriers = $this->courierScorecards($shipments);
        $rtoBreakdown = $this->rtoBreakdown($shipments);
        $lostCases = $this->lostCaseRows($from, $to, $courier, $status, $search);
        $trends = $this->operationsTrends($shipments);
        $rtoReasons = $this->rtoReasonRows();
        $trendChart = $this->chartSeries($trends, 30);

        return view('operations', [
            'title' => 'Operations Dashboard',
            'subtitle' => 'Orders, delivery health, courier scorecards, RTO reasons, and lost cases.',
            'assistantDepartment' => 'operations',
            'filters' => compact('from', 'to', 'courier', 'status', 'search'),
            'overview' => $overview,
            'couriers' => $couriers,
            'rtoBreakdown' => $rtoBreakdown,
            'lostCases' => $lostCases,
            'trends' => $trends,
            'trendChart' => $trendChart,
            'rtoReasons' => $rtoReasons,
            'assistantContext' => [
                'department' => 'operations',
                'filters' => compact('from', 'to', 'courier', 'status', 'search'),
                'overview' => $overview,
                'couriers' => $couriers,
                'rtoBreakdown' => $rtoBreakdown,
                'lostCases' => $lostCases,
                'trends' => $trends,
                'rtoReasons' => $rtoReasons,
            ],
        ]);
    }

    public function orders(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);
        $status = $request->string('status')->toString() ?: null;
        $search = trim($request->string('search')->toString());
        $courier = trim($request->string('courier')->toString());

        $orders = Order::query()
            ->with(['shipment.courier', 'shipment.rtoReason'])
            ->whereBetween('order_date', [$from, $to])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($courier, fn ($query) => $query->whereHas('shipment.courier', fn ($courierQuery) => $courierQuery->where('code', $courier)))
            ->when($search, function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('order_number', 'like', "%{$search}%")
                        ->orWhere('customer_city', 'like', "%{$search}%")
                        ->orWhere('customer_state', 'like', "%{$search}%")
                        ->orWhereHas('shipment', function ($shipmentQuery) use ($search): void {
                            $shipmentQuery->where('tracking_number', 'like', "%{$search}%")
                                ->orWhereHas('courier', fn ($courierQuery) => $courierQuery->where('name', 'like', "%{$search}%"));
                        });
                });
            })
            ->latest('order_date')
            ->limit(80)
            ->get()
            ->map(fn (Order $order) => $this->orderRow($order));

        return view('orders', [
            'title' => 'Order Control Room',
            'subtitle' => 'Filter seeded orders, inspect shipment details, and update operational status.',
            'assistantDepartment' => 'operations',
            'filters' => compact('from', 'to', 'status', 'search', 'courier'),
            'orders' => $orders,
            'couriers' => Courier::query()->orderBy('name')->get(),
            'assistantContext' => [
                'department' => 'operations',
                'page' => 'orders',
                'filters' => compact('from', 'to', 'status', 'search', 'courier'),
                'orders' => $orders,
            ],
        ]);
    }

    public function updateOrderStatus(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:delivered,rto,lost'],
        ]);

        $order->update(['status' => $validated['status']]);
        $order->shipment()->update([
            'status' => $validated['status'],
        ]);

        return back()->with('status', 'Order status updated.');
    }

    public function campaigns(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);
        $platform = $request->string('platform')->toString() ?: null;
        $search = trim($request->string('search')->toString());
        $campaigns = $this->marketingCampaigns($from, $to, $platform, $search);

        return view('campaigns', [
            'title' => 'Campaign Details',
            'subtitle' => 'Campaign spend, ROAS, platform, and status for the selected period.',
            'assistantDepartment' => 'marketing',
            'filters' => compact('from', 'to', 'platform', 'search'),
            'campaigns' => $campaigns,
            'assistantContext' => [
                'department' => 'marketing',
                'page' => 'campaigns',
                'filters' => compact('from', 'to', 'platform', 'search'),
                'campaigns' => $campaigns,
            ],
        ]);
    }

    public function shipments(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);
        $status = $request->string('status')->toString() ?: null;
        $search = trim($request->string('search')->toString());
        $courier = trim($request->string('courier')->toString());

        $shipments = Shipment::query()
            ->with(['order', 'courier', 'rtoReason'])
            ->whereBetween('shipped_on', [$from, $to])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($courier, fn ($query) => $query->whereHas('courier', fn ($courierQuery) => $courierQuery->where('code', $courier)))
            ->when($search, function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('tracking_number', 'like', "%{$search}%")
                        ->orWhereHas('order', fn ($orderQuery) => $orderQuery->where('order_number', 'like', "%{$search}%"))
                        ->orWhereHas('courier', fn ($courierQuery) => $courierQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest('shipped_on')
            ->limit(100)
            ->get()
            ->map(fn (Shipment $shipment) => $this->shipmentRow($shipment));

        return view('shipments', [
            'title' => 'Shipment Details',
            'subtitle' => 'Track courier, delivery date, shipment status, and RTO reason labels.',
            'assistantDepartment' => 'operations',
            'filters' => compact('from', 'to', 'status', 'search', 'courier'),
            'shipments' => $shipments,
            'couriers' => Courier::query()->orderBy('name')->get(),
            'assistantContext' => [
                'department' => 'operations',
                'page' => 'shipments',
                'filters' => compact('from', 'to', 'status', 'search', 'courier'),
                'shipments' => $shipments,
            ],
        ]);
    }

    public function rtoReasonsPage(Request $request): View
    {
        $reasons = $this->rtoReasonRows();

        return view('rto-reasons', [
            'title' => 'RTO Reason Library',
            'subtitle' => 'Maintain controllable and courier-driven RTO reasons used in the dashboard.',
            'assistantDepartment' => 'operations',
            'reasons' => $reasons,
            'assistantContext' => [
                'department' => 'operations',
                'page' => 'rto-reasons',
                'reasons' => $reasons,
            ],
        ]);
    }

    public function storeRtoReason(Request $request): RedirectResponse
    {
        $data = $this->validateRtoReason($request);

        RtoReason::query()->create([
            'reason' => $data['reason'],
            'category' => $data['category'],
            'is_controllable' => $request->boolean('is_controllable'),
        ]);

        return back()->with('status', 'RTO reason created.');
    }

    public function updateRtoReason(Request $request, RtoReason $reason): RedirectResponse
    {
        $data = $this->validateRtoReason($request, $reason->id);

        $reason->update([
            'reason' => $data['reason'],
            'category' => $data['category'],
            'is_controllable' => $request->boolean('is_controllable'),
        ]);

        return back()->with('status', 'RTO reason updated.');
    }

    public function deleteRtoReason(RtoReason $reason): RedirectResponse
    {
        $reason->delete();

        return back()->with('status', 'RTO reason deleted.');
    }

    public function lostCasesPage(Request $request): View
    {
        [$from, $to] = $this->dateRange($request);
        $courier = $request->string('courier')->toString() ?: null;
        $status = $request->string('status')->toString() ?: null;
        $search = trim($request->string('search')->toString());
        $lostCases = $this->lostCaseRows($from, $to, $courier, $status, $search);

        return view('lost-cases', [
            'title' => 'Lost Case Details',
            'subtitle' => 'Review shipment loss cases, claim filing status, and recovered amounts.',
            'assistantDepartment' => 'operations',
            'filters' => compact('from', 'to', 'courier', 'status', 'search'),
            'lostCases' => $lostCases,
            'assistantContext' => [
                'department' => 'operations',
                'page' => 'lost-cases',
                'filters' => compact('from', 'to', 'courier', 'status', 'search'),
                'lostCases' => $lostCases,
            ],
        ]);
    }

    public function assistant(): View
    {
        return view('assistant', [
            'title' => 'AI Assistant',
            'subtitle' => 'Ask questions about the latest marketing and operations data.',
            'assistantDepartment' => null,
            'assistantContext' => [
                'source' => 'assistant_page',
            ],
        ]);
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
            $validated['from'] ?? now()->subMonthsNoOverflow(3)->toDateString(),
            $validated['to'] ?? now()->toDateString(),
        ];
    }

    private function marketingMetrics(string $from, string $to, ?string $platform = null, ?string $search = null): Collection
    {
        return CampaignDailyMetric::query()
            ->with('campaign.platform')
            ->whereBetween('metric_date', [$from, $to])
            ->when($platform, function ($query) use ($platform): void {
                $query->whereHas('campaign.platform', fn ($platformQuery) => $platformQuery->where('slug', $platform));
            })
            ->when($search, function ($query) use ($search): void {
                $query->whereHas('campaign', fn ($campaignQuery) => $campaignQuery->where('name', 'like', '%' . $search . '%'));
            })
            ->get();
    }

    /**
     * @return array<string, float|int>
     */
    private function marketingOverview(Collection $metrics): array
    {
        $spend = (float) $metrics->sum('spend');
        $revenue = (float) $metrics->sum('revenue');
        $conversions = (int) $metrics->sum('conversions');
        $roas = $spend > 0 ? round($revenue / $spend, 2) : 0;
        $cac = $conversions > 0 ? round($spend / $conversions, 2) : 0;

        return [
            'total_spend' => round($spend, 2),
            'revenue' => round($revenue, 2),
            'blended_roas' => $roas,
            'blended_cac' => $cac,
            'conversions' => $conversions,
            'alerts' => [
                'roas_below_threshold' => $roas < 2.0,
                'cac_above_threshold' => $cac > 800,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function marketingPlatforms(Collection $metrics): array
    {
        return AdPlatform::query()
            ->whereIn('slug', ['meta', 'google'])
            ->orderBy('name')
            ->get()
            ->map(function (AdPlatform $platform) use ($metrics): array {
                $platformMetrics = $metrics->filter(fn (CampaignDailyMetric $metric) => $metric->campaign?->platform?->slug === $platform->slug);
                $spend = (float) $platformMetrics->sum('spend');
                $revenue = (float) $platformMetrics->sum('revenue');
                $conversions = (int) $platformMetrics->sum('conversions');
                $impressions = (int) $platformMetrics->sum('impressions');
                $clicks = (int) $platformMetrics->sum('clicks');

                return [
                    'platform' => $platform->slug,
                    'platform_name' => $platform->name,
                    'spend' => round($spend, 2),
                    'revenue' => round($revenue, 2),
                    'roas' => $spend > 0 ? round($revenue / $spend, 2) : 0,
                    'cpm' => $impressions > 0 ? round(($spend / $impressions) * 1000, 2) : 0,
                    'ctr' => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0,
                    'cpc' => $clicks > 0 ? round($spend / $clicks, 2) : 0,
                    'cac' => $conversions > 0 ? round($spend / $conversions, 2) : 0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function marketingCampaigns(string $from, string $to, ?string $platform = null, ?string $search = null): array
    {
        return Campaign::query()
            ->with(['platform', 'dailyMetrics' => fn ($query) => $query->whereBetween('metric_date', [$from, $to])])
            ->when($platform, fn ($query) => $query->whereHas('platform', fn ($platformQuery) => $platformQuery->where('slug', $platform)))
            ->when($search, fn ($query) => $query->where('name', 'like', '%' . $search . '%'))
            ->get()
            ->map(function (Campaign $campaign): array {
                $spend = (float) $campaign->dailyMetrics->sum('spend');
                $revenue = (float) $campaign->dailyMetrics->sum('revenue');

                return [
                    'name' => $campaign->name,
                    'platform' => $campaign->platform?->name ?? 'Unknown',
                    'status' => $campaign->status,
                    'objective' => $campaign->objective,
                    'daily_budget' => (float) $campaign->daily_budget,
                    'spend' => round($spend, 2),
                    'roas' => $spend > 0 ? round($revenue / $spend, 2) : 0,
                ];
            })
            ->sortByDesc('spend')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function marketingTrends(Collection $metrics): array
    {
        $rows = $metrics
            ->groupBy(fn (CampaignDailyMetric $metric) => $metric->metric_date->toDateString())
            ->map(function (Collection $group, string $date): array {
                $spend = (float) $group->sum('spend');
                $revenue = (float) $group->sum('revenue');

                return [
                    'date' => $date,
                    'spend' => round($spend, 2),
                    'revenue' => round($revenue, 2),
                    'roas' => $spend > 0 ? round($revenue / $spend, 2) : 0,
                ];
            })
            ->sortKeys()
            ->values();

        $maxSpend = max((float) $rows->max('spend'), 1);
        $maxRevenue = max((float) $rows->max('revenue'), 1);

        return $rows->map(function (array $row) use ($maxSpend, $maxRevenue): array {
            $row['spend_height'] = (int) round(($row['spend'] / $maxSpend) * 100);
            $row['revenue_height'] = (int) round(($row['revenue'] / $maxRevenue) * 100);

            return $row;
        })->all();
    }

    private function operationsShipments(string $from, string $to, ?string $courier = null, ?string $status = null, ?string $search = null): Collection
    {
        return Shipment::query()
            ->with(['order', 'courier', 'rtoReason'])
            ->whereBetween('shipped_on', [$from, $to])
            ->when($courier, fn ($query) => $query->whereHas('courier', fn ($courierQuery) => $courierQuery->where('code', $courier)))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($search, function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('tracking_number', 'like', '%' . $search . '%')
                        ->orWhereHas('order', fn ($orderQuery) => $orderQuery->where('order_number', 'like', '%' . $search . '%'))
                        ->orWhereHas('courier', fn ($courierQuery) => $courierQuery->where('name', 'like', '%' . $search . '%'));
                });
            })
            ->get();
    }

    /**
     * @return array<string, float|int>
     */
    private function operationsOverview(Collection $shipments): array
    {
        $totalOrders = $shipments->count();
        $delivered = $shipments->where('status', 'delivered')->count();
        $rto = $shipments->where('status', 'rto')->count();
        $lost = $shipments->where('status', 'lost')->count();
        $onTime = $shipments->filter(fn (Shipment $shipment) => $shipment->delivered_on && $shipment->expected_delivery_on && $shipment->delivered_on->lessThanOrEqualTo($shipment->expected_delivery_on))->count();
        $avgShipTime = $shipments->avg('ship_time_hours') ?: 0;

        $rtoRate = $totalOrders > 0 ? round(($rto / $totalOrders) * 100, 2) : 0;
        $otd = $totalOrders > 0 ? round(($onTime / $totalOrders) * 100, 2) : 0;

        return [
            'total_orders' => $totalOrders,
            'delivered' => $delivered,
            'rto_rate' => $rtoRate,
            'otd_percent' => $otd,
            'lost_cases' => $lost,
            'avg_ship_time_hours' => round((float) $avgShipTime, 2),
            'alerts' => [
                'rto_above_threshold' => $rtoRate > 10.0,
                'otd_below_threshold' => $otd < 90.0,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function courierScorecards(Collection $shipments): array
    {
        return Courier::query()
            ->orderBy('name')
            ->get()
            ->map(function (Courier $courier) use ($shipments): array {
                $courierShipments = $shipments->where('courier_id', $courier->id);
                $total = $courierShipments->count();
                $onTime = $courierShipments->filter(fn (Shipment $shipment) => $shipment->delivered_on && $shipment->expected_delivery_on && $shipment->delivered_on->lessThanOrEqualTo($shipment->expected_delivery_on))->count();
                $rto = $courierShipments->where('status', 'rto')->count();
                $lost = $courierShipments->where('status', 'lost')->count();
                $otd = $total > 0 ? round(($onTime / $total) * 100, 2) : 0;
                $rtoPercent = $total > 0 ? round(($rto / $total) * 100, 2) : 0;
                $score = round($otd - $rtoPercent - ($lost * 1.5), 2);

                return [
                    'name' => $courier->name,
                    'code' => $courier->code,
                    'orders' => $total,
                    'otd_percent' => $otd,
                    'rto_percent' => $rtoPercent,
                    'lost_count' => $lost,
                    'performance_score' => $score,
                ];
            })
            ->sortByDesc('performance_score')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rtoBreakdown(Collection $shipments): array
    {
        return $shipments
            ->where('status', 'rto')
            ->groupBy(fn (Shipment $shipment) => $shipment->rtoReason?->reason ?? 'Unassigned')
            ->map(function (Collection $group, string $reason): array {
                $first = $group->first();

                return [
                    'reason' => $reason,
                    'category' => $first?->rtoReason?->category ?? 'Unknown',
                    'is_controllable' => (bool) ($first?->rtoReason?->is_controllable ?? false),
                    'rto_count' => $group->count(),
                ];
            })
            ->sortByDesc('rto_count')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function lostCaseRows(string $from, string $to, ?string $courier = null, ?string $status = null, ?string $search = null): array
    {
        return LostCase::query()
            ->with(['shipment.order', 'shipment.courier'])
            ->whereBetween('reported_on', [$from, $to])
            ->when($courier, fn ($query) => $query->whereHas('shipment.courier', fn ($courierQuery) => $courierQuery->where('code', $courier)))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($search, function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('case_number', 'like', '%' . $search . '%')
                        ->orWhereHas('shipment.order', fn ($orderQuery) => $orderQuery->where('order_number', 'like', '%' . $search . '%'))
                        ->orWhereHas('shipment.courier', fn ($courierQuery) => $courierQuery->where('name', 'like', '%' . $search . '%'));
                });
            })
            ->latest('reported_on')
            ->get()
            ->map(fn (LostCase $case): array => [
                'case_number' => $case->case_number,
                'reported_on' => $case->reported_on?->toDateString(),
                'status' => $case->status,
                'claim_filed' => $case->claim_filed,
                'claim_amount' => (float) $case->claim_amount,
                'amount_recovered' => (float) $case->amount_recovered,
                'order_number' => $case->shipment?->order?->order_number ?? '-',
                'tracking_number' => $case->shipment?->tracking_number ?? '-',
                'courier' => $case->shipment?->courier?->name ?? '-',
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function operationsTrends(Collection $shipments): array
    {
        $rows = $shipments
            ->groupBy(fn (Shipment $shipment) => $shipment->shipped_on->toDateString())
            ->map(function (Collection $group, string $date): array {
                $total = $group->count();
                $rto = $group->where('status', 'rto')->count();
                $onTime = $group->filter(fn (Shipment $shipment) => $shipment->delivered_on && $shipment->expected_delivery_on && $shipment->delivered_on->lessThanOrEqualTo($shipment->expected_delivery_on))->count();

                return [
                    'date' => $date,
                    'orders' => $total,
                    'rto_rate' => $total > 0 ? round(($rto / $total) * 100, 2) : 0,
                    'otd_percent' => $total > 0 ? round(($onTime / $total) * 100, 2) : 0,
                ];
            })
            ->sortKeys()
            ->values();

        $maxOrders = max((float) $rows->max('orders'), 1);

        return $rows->map(function (array $row) use ($maxOrders): array {
            $row['orders_height'] = (int) round(($row['orders'] / $maxOrders) * 100);

            return $row;
        })->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rtoReasonRows(): array
    {
        return RtoReason::query()
            ->withCount([
                'shipments as rto_count' => fn ($query) => $query->where('status', 'rto'),
            ])
            ->orderBy('category')
            ->orderBy('reason')
            ->get()
            ->map(fn (RtoReason $reason): array => [
                'id' => $reason->id,
                'reason' => $reason->reason,
                'category' => $reason->category,
                'is_controllable' => $reason->is_controllable,
                'rto_count' => $reason->rto_count,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function orderRow(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'order_date' => $order->order_date?->toDateString(),
            'customer_city' => $order->customer_city,
            'customer_state' => $order->customer_state,
            'status' => $order->status,
            'order_value' => (float) $order->order_value,
            'courier' => $order->shipment?->courier?->name ?? '-',
            'courier_code' => $order->shipment?->courier?->code ?? '-',
            'tracking_number' => $order->shipment?->tracking_number ?? '-',
            'rto_reason' => $order->shipment?->rtoReason?->reason ?? '-',
            'expected_delivery_on' => $order->shipment?->expected_delivery_on?->toDateString() ?? '-',
            'delivered_on' => $order->shipment?->delivered_on?->toDateString() ?? '-',
            'rto_on' => $order->shipment?->rto_on?->toDateString() ?? '-',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shipmentRow(Shipment $shipment): array
    {
        return [
            'id' => $shipment->id,
            'tracking_number' => $shipment->tracking_number,
            'shipped_on' => $shipment->shipped_on?->toDateString(),
            'expected_delivery_on' => $shipment->expected_delivery_on?->toDateString(),
            'delivered_on' => $shipment->delivered_on?->toDateString() ?? '-',
            'rto_on' => $shipment->rto_on?->toDateString() ?? '-',
            'status' => $shipment->status,
            'ship_time_hours' => $shipment->ship_time_hours,
            'shipping_cost' => (float) $shipment->shipping_cost,
            'order_number' => $shipment->order?->order_number ?? '-',
            'customer_city' => $shipment->order?->customer_city ?? '-',
            'customer_state' => $shipment->order?->customer_state ?? '-',
            'courier' => $shipment->courier?->name ?? '-',
            'courier_code' => $shipment->courier?->code ?? '-',
            'rto_reason' => $shipment->rtoReason?->reason ?? '-',
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function chartSeries(array $rows, int $limit = 30): array
    {
        return array_slice($rows, max(count($rows) - $limit, 0));
    }

    /**
     * @return array{reason: string, category: string, is_controllable: bool}
     */
    private function validateRtoReason(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'reason' => ['required', 'string', 'max:120', Rule::unique('rto_reasons', 'reason')->ignore($ignoreId)],
            'category' => ['required', 'string', 'max:80'],
            'is_controllable' => ['nullable', 'boolean'],
        ]);
    }
}
