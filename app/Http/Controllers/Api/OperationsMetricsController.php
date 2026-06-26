<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\LostCase;
use App\Models\Order;
use App\Models\RtoReason;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class OperationsMetricsController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);
        $shipments = $this->shipmentsQuery($from, $to)->get();
        $metrics = $this->overviewFromShipments($shipments);

        return response()->json([
            'filters' => compact('from', 'to'),
            'data' => $metrics['data'],
            'alerts' => $metrics['alerts'],
        ]);
    }

    public function couriers(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);
        $shipments = $this->shipmentsQuery($from, $to)->get();
        $data = $this->courierScorecards($shipments);

        return response()->json([
            'filters' => compact('from', 'to'),
            'data' => $data,
        ]);
    }

    public function rto(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);
        $validated = $request->validate([
            'reason' => ['nullable', 'string'],
        ]);

        $shipments = $this->shipmentsQuery($from, $to)
            ->where('status', 'rto')
            ->when(! empty($validated['reason']), function ($query) use ($validated): void {
                $query->whereHas('rtoReason', fn ($reasonQuery) => $reasonQuery->where('reason', 'like', '%' . $validated['reason'] . '%'));
            })
            ->get();

        $data = $shipments
            ->groupBy(fn (Shipment $shipment) => $shipment->rtoReason?->id ?? 0)
            ->map(function (Collection $group): array {
                $reason = $group->first()?->rtoReason;

                return [
                    'reason' => $reason?->reason ?? 'Unassigned',
                    'category' => $reason?->category ?? 'Unknown',
                    'is_controllable' => (bool) ($reason?->is_controllable ?? false),
                    'rto_count' => $group->count(),
                ];
            })
            ->sortByDesc('rto_count')
            ->values();

        return response()->json([
            'filters' => ['reason' => $validated['reason'] ?? null, 'from' => $from, 'to' => $to],
            'data' => $data,
        ]);
    }

    public function lostCases(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);

        $cases = LostCase::query()
            ->with(['shipment.order', 'shipment.courier'])
            ->whereBetween('reported_on', [$from, $to])
            ->latest('reported_on')
            ->get()
            ->map(fn (LostCase $case): array => [
                'case_number' => $case->case_number,
                'order_number' => $case->shipment?->order?->order_number ?? '-',
                'courier' => $case->shipment?->courier?->name ?? '-',
                'reported_on' => $case->reported_on?->toDateString(),
                'status' => $case->status,
                'claim_filed' => $case->claim_filed,
                'claim_amount' => (float) $case->claim_amount,
                'amount_recovered' => (float) $case->amount_recovered,
            ]);

        return response()->json([
            'filters' => compact('from', 'to'),
            'data' => $cases,
        ]);
    }

    public function trends(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);
        $shipments = $this->shipmentsQuery($from, $to)->get();

        $data = $shipments
            ->groupBy(fn (Shipment $shipment) => $shipment->shipped_on->toDateString())
            ->map(function (Collection $group, string $date): array {
                $total = $group->count();
                $rto = $group->where('status', 'rto')->count();
                $onTime = $group->filter(fn (Shipment $shipment) => $shipment->delivered_on && $shipment->expected_delivery_on && $shipment->delivered_on->lessThanOrEqualTo($shipment->expected_delivery_on))->count();

                return [
                    'metric_date' => $date,
                    'orders' => $total,
                    'rto_rate' => $total > 0 ? round(($rto / $total) * 100, 2) : 0,
                    'otd_percent' => $total > 0 ? round(($onTime / $total) * 100, 2) : 0,
                ];
            })
            ->sortKeys()
            ->values();

        return response()->json([
            'filters' => compact('from', 'to'),
            'data' => $data,
        ]);
    }

    public function orders(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['delivered', 'rto', 'lost'])],
            'courier' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:80'],
        ]);

        $orders = Order::query()
            ->with(['shipment.courier', 'shipment.rtoReason'])
            ->whereBetween('order_date', [$from, $to])
            ->when(! empty($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->when(! empty($validated['courier']), function ($query) use ($validated): void {
                $query->whereHas('shipment.courier', fn ($courierQuery) => $courierQuery->where('code', $validated['courier']));
            })
            ->when(! empty($validated['search']), function ($query) use ($validated): void {
                $search = $validated['search'];
                $query->where(function ($nested) use ($search): void {
                    $nested->where('order_number', 'like', '%' . $search . '%')
                        ->orWhere('customer_city', 'like', '%' . $search . '%')
                        ->orWhere('customer_state', 'like', '%' . $search . '%')
                        ->orWhereHas('shipment', function ($shipmentQuery) use ($search): void {
                            $shipmentQuery->where('tracking_number', 'like', '%' . $search . '%')
                                ->orWhereHas('courier', fn ($courierQuery) => $courierQuery->where('name', 'like', '%' . $search . '%'));
                        });
                });
            })
            ->latest('order_date')
            ->limit(80)
            ->get()
            ->map(fn (Order $order): array => $this->orderRow($order));

        return response()->json([
            'filters' => [
                'from' => $from,
                'to' => $to,
                'status' => $validated['status'] ?? null,
                'courier' => $validated['courier'] ?? null,
                'search' => $validated['search'] ?? null,
            ],
            'data' => $orders,
        ]);
    }

    public function shipments(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['delivered', 'rto', 'lost'])],
            'courier' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:80'],
        ]);

        $shipments = $this->shipmentsQuery($from, $to)
            ->when(! empty($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->when(! empty($validated['courier']), function ($query) use ($validated): void {
                $query->whereHas('courier', fn ($courierQuery) => $courierQuery->where('code', $validated['courier']));
            })
            ->when(! empty($validated['search']), function ($query) use ($validated): void {
                $search = $validated['search'];
                $query->where(function ($nested) use ($search): void {
                    $nested->where('tracking_number', 'like', '%' . $search . '%')
                        ->orWhereHas('order', fn ($orderQuery) => $orderQuery->where('order_number', 'like', '%' . $search . '%'))
                        ->orWhereHas('courier', fn ($courierQuery) => $courierQuery->where('name', 'like', '%' . $search . '%'));
                });
            })
            ->latest('shipped_on')
            ->limit(100)
            ->get()
            ->map(fn (Shipment $shipment): array => $this->shipmentRow($shipment));

        return response()->json([
            'filters' => [
                'from' => $from,
                'to' => $to,
                'status' => $validated['status'] ?? null,
                'courier' => $validated['courier'] ?? null,
                'search' => $validated['search'] ?? null,
            ],
            'data' => $shipments,
        ]);
    }

    public function updateOrder(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['delivered', 'rto', 'lost'])],
        ]);

        $order->update(['status' => $validated['status']]);
        $order->shipment()->update(['status' => $validated['status']]);

        return response()->json(['message' => 'Order status updated.']);
    }

    public function rtoReasons(): JsonResponse
    {
        $data = RtoReason::query()
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
            ]);

        return response()->json(['data' => $data]);
    }

    public function storeRtoReason(Request $request): JsonResponse
    {
        $validated = $this->validateRtoReason($request);

        $reason = RtoReason::query()->create([
            'reason' => $validated['reason'],
            'category' => $validated['category'],
            'is_controllable' => $request->boolean('is_controllable'),
        ]);

        return response()->json([
            'message' => 'RTO reason created.',
            'id' => $reason->id,
        ], 201);
    }

    public function updateRtoReason(Request $request, int $reason): JsonResponse
    {
        $validated = $this->validateRtoReason($request, $reason);

        RtoReason::query()->whereKey($reason)->update([
            'reason' => $validated['reason'],
            'category' => $validated['category'],
            'is_controllable' => $request->boolean('is_controllable'),
        ]);

        return response()->json(['message' => 'RTO reason updated.']);
    }

    public function deleteRtoReason(int $reason): JsonResponse
    {
        RtoReason::query()->whereKey($reason)->delete();

        return response()->json(['message' => 'RTO reason deleted.']);
    }

    private function shipmentsQuery(string $from, string $to)
    {
        return Shipment::query()
            ->with(['order', 'courier', 'rtoReason'])
            ->whereBetween('shipped_on', [$from, $to]);
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

    /**
     * @return array{data: array<string, float|int>, alerts: array<string, bool>}
     */
    private function overviewFromShipments(Collection $shipments): array
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
            'data' => [
                'total_orders' => $totalOrders,
                'delivered' => $delivered,
                'rto_rate' => $rtoRate,
                'otd_percent' => $otd,
                'lost_cases' => $lost,
                'avg_ship_time_hours' => round((float) $avgShipTime, 2),
            ],
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
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
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
     * @return array{reason: string, category: string}
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
