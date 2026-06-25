<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OperationsMetricsController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);

        $metrics = $this->shipmentsBaseQuery($from, $to)
            ->selectRaw('
                COUNT(shipments.id) as total_orders,
                SUM(CASE WHEN shipments.status = "delivered" THEN 1 ELSE 0 END) as delivered,
                ROUND(100.0 * SUM(CASE WHEN shipments.status = "rto" THEN 1 ELSE 0 END) / NULLIF(COUNT(shipments.id), 0), 2) as rto_rate,
                ROUND(100.0 * SUM(CASE WHEN shipments.delivered_on <= shipments.expected_delivery_on THEN 1 ELSE 0 END) / NULLIF(COUNT(shipments.id), 0), 2) as otd_percent,
                SUM(CASE WHEN shipments.status = "lost" THEN 1 ELSE 0 END) as lost_cases,
                ROUND(AVG(shipments.ship_time_hours), 2) as avg_ship_time_hours
            ')
            ->first();

        return response()->json([
            'filters' => compact('from', 'to'),
            'data' => $metrics,
            'alerts' => [
                'rto_above_threshold' => (float) $metrics->rto_rate > 10.0,
                'otd_below_threshold' => (float) $metrics->otd_percent < 90.0,
            ],
        ]);
    }

    public function couriers(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);

        $couriers = $this->shipmentsBaseQuery($from, $to)
            ->join('couriers', 'couriers.id', '=', 'shipments.courier_id')
            ->selectRaw('
                couriers.id,
                couriers.name,
                couriers.code,
                COUNT(shipments.id) as orders,
                ROUND(100.0 * SUM(CASE WHEN shipments.delivered_on <= shipments.expected_delivery_on THEN 1 ELSE 0 END) / NULLIF(COUNT(shipments.id), 0), 2) as otd_percent,
                ROUND(100.0 * SUM(CASE WHEN shipments.status = "rto" THEN 1 ELSE 0 END) / NULLIF(COUNT(shipments.id), 0), 2) as rto_percent,
                SUM(CASE WHEN shipments.status = "lost" THEN 1 ELSE 0 END) as lost_count,
                ROUND(
                    (100.0 * SUM(CASE WHEN shipments.delivered_on <= shipments.expected_delivery_on THEN 1 ELSE 0 END) / NULLIF(COUNT(shipments.id), 0)) -
                    (100.0 * SUM(CASE WHEN shipments.status = "rto" THEN 1 ELSE 0 END) / NULLIF(COUNT(shipments.id), 0)) -
                    (SUM(CASE WHEN shipments.status = "lost" THEN 1 ELSE 0 END) * 1.5),
                    2
                ) as performance_score
            ')
            ->groupBy('couriers.id', 'couriers.name', 'couriers.code')
            ->orderByDesc('performance_score')
            ->get();

        return response()->json([
            'filters' => compact('from', 'to'),
            'data' => $couriers,
        ]);
    }

    public function rto(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string'],
        ]);
        [$from, $to] = $this->dateRange($request);

        $query = $this->shipmentsBaseQuery($from, $to)
            ->join('rto_reasons', 'rto_reasons.id', '=', 'shipments.rto_reason_id')
            ->where('shipments.status', 'rto');

        if (! empty($validated['reason'])) {
            $query->where('rto_reasons.reason', 'like', '%' . $validated['reason'] . '%');
        }

        $breakdown = $query
            ->selectRaw('
                rto_reasons.reason,
                rto_reasons.category,
                rto_reasons.is_controllable,
                COUNT(shipments.id) as rto_count
            ')
            ->groupBy('rto_reasons.id', 'rto_reasons.reason', 'rto_reasons.category', 'rto_reasons.is_controllable')
            ->orderByDesc('rto_count')
            ->get();

        return response()->json([
            'filters' => ['reason' => $validated['reason'] ?? null, 'from' => $from, 'to' => $to],
            'data' => $breakdown,
        ]);
    }

    public function lostCases(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);

        $cases = DB::table('lost_cases')
            ->join('shipments', 'shipments.id', '=', 'lost_cases.shipment_id')
            ->join('orders', 'orders.id', '=', 'shipments.order_id')
            ->join('couriers', 'couriers.id', '=', 'shipments.courier_id')
            ->whereBetween('lost_cases.reported_on', [$from, $to])
            ->select([
                'lost_cases.case_number',
                'orders.order_number',
                'couriers.name as courier',
                'lost_cases.reported_on',
                'lost_cases.status',
                'lost_cases.claim_filed',
                'lost_cases.claim_amount',
                'lost_cases.amount_recovered',
            ])
            ->orderByDesc('lost_cases.reported_on')
            ->get();

        return response()->json([
            'filters' => compact('from', 'to'),
            'data' => $cases,
        ]);
    }

    public function trends(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);

        $trends = $this->shipmentsBaseQuery($from, $to)
            ->selectRaw('
                shipments.shipped_on as metric_date,
                COUNT(shipments.id) as orders,
                ROUND(100.0 * SUM(CASE WHEN shipments.status = "rto" THEN 1 ELSE 0 END) / NULLIF(COUNT(shipments.id), 0), 2) as rto_rate,
                ROUND(100.0 * SUM(CASE WHEN shipments.delivered_on <= shipments.expected_delivery_on THEN 1 ELSE 0 END) / NULLIF(COUNT(shipments.id), 0), 2) as otd_percent
            ')
            ->groupBy('shipments.shipped_on')
            ->orderBy('shipments.shipped_on')
            ->get();

        return response()->json([
            'filters' => compact('from', 'to'),
            'data' => $trends,
        ]);
    }

    public function orders(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['delivered', 'rto', 'lost'])],
            'courier' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:80'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        [$from, $to] = $this->dateRange($request);

        $query = DB::table('orders')
            ->join('shipments', 'shipments.order_id', '=', 'orders.id')
            ->join('couriers', 'couriers.id', '=', 'shipments.courier_id')
            ->leftJoin('rto_reasons', 'rto_reasons.id', '=', 'shipments.rto_reason_id')
            ->whereBetween('orders.order_date', [$from, $to]);

        if (! empty($validated['status'])) {
            $query->where('orders.status', $validated['status']);
        }

        if (! empty($validated['courier'])) {
            $query->where('couriers.code', $validated['courier']);
        }

        if (! empty($validated['search'])) {
            $query->where(function (Builder $query) use ($validated): void {
                $query->where('orders.order_number', 'like', '%' . $validated['search'] . '%')
                    ->orWhere('shipments.tracking_number', 'like', '%' . $validated['search'] . '%')
                    ->orWhere('orders.customer_city', 'like', '%' . $validated['search'] . '%');
            });
        }

        $orders = $query
            ->select([
                'orders.id',
                'orders.order_number',
                'orders.order_date',
                'orders.customer_city',
                'orders.customer_state',
                'orders.product_category',
                'orders.payment_method',
                'orders.order_value',
                'orders.status',
                'couriers.name as courier',
                'couriers.code as courier_code',
                'shipments.tracking_number',
                'shipments.expected_delivery_on',
                'shipments.delivered_on',
                'shipments.rto_on',
                'rto_reasons.reason as rto_reason',
            ])
            ->orderByDesc('orders.order_date')
            ->limit(80)
            ->get();

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
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['delivered', 'rto', 'lost'])],
            'courier' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:80'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        [$from, $to] = $this->dateRange($request);

        $query = DB::table('shipments')
            ->join('orders', 'orders.id', '=', 'shipments.order_id')
            ->join('couriers', 'couriers.id', '=', 'shipments.courier_id')
            ->leftJoin('rto_reasons', 'rto_reasons.id', '=', 'shipments.rto_reason_id')
            ->whereBetween('shipments.shipped_on', [$from, $to]);

        if (! empty($validated['status'])) {
            $query->where('shipments.status', $validated['status']);
        }

        if (! empty($validated['courier'])) {
            $query->where('couriers.code', $validated['courier']);
        }

        if (! empty($validated['search'])) {
            $query->where(function (Builder $query) use ($validated): void {
                $query->where('shipments.tracking_number', 'like', '%' . $validated['search'] . '%')
                    ->orWhere('orders.order_number', 'like', '%' . $validated['search'] . '%')
                    ->orWhere('couriers.name', 'like', '%' . $validated['search'] . '%');
            });
        }

        $shipments = $query
            ->select([
                'shipments.id',
                'shipments.tracking_number',
                'shipments.shipped_on',
                'shipments.expected_delivery_on',
                'shipments.delivered_on',
                'shipments.rto_on',
                'shipments.status',
                'shipments.ship_time_hours',
                'shipments.shipping_cost',
                'orders.order_number',
                'orders.customer_city',
                'orders.customer_state',
                'couriers.name as courier',
                'couriers.code as courier_code',
                'rto_reasons.reason as rto_reason',
            ])
            ->orderByDesc('shipments.shipped_on')
            ->limit(100)
            ->get();

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

    public function updateOrder(Request $request, int $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['delivered', 'rto', 'lost'])],
        ]);

        DB::table('orders')->where('id', $order)->update([
            'status' => $validated['status'],
            'updated_at' => now(),
        ]);

        DB::table('shipments')->where('order_id', $order)->update([
            'status' => $validated['status'],
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Order status updated.']);
    }

    public function rtoReasons(): JsonResponse
    {
        return response()->json([
            'data' => DB::table('rto_reasons')
                ->select(['id', 'reason', 'category', 'is_controllable'])
                ->orderBy('category')
                ->orderBy('reason')
                ->get(),
        ]);
    }

    public function storeRtoReason(Request $request): JsonResponse
    {
        $validated = $this->validateRtoReason($request);

        $id = DB::table('rto_reasons')->insertGetId([
            ...$validated,
            'is_controllable' => $request->boolean('is_controllable'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'RTO reason created.',
            'id' => $id,
        ], 201);
    }

    public function updateRtoReason(Request $request, int $reason): JsonResponse
    {
        $validated = $this->validateRtoReason($request, $reason);

        DB::table('rto_reasons')->where('id', $reason)->update([
            ...$validated,
            'is_controllable' => $request->boolean('is_controllable'),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'RTO reason updated.']);
    }

    public function deleteRtoReason(int $reason): JsonResponse
    {
        DB::table('rto_reasons')->where('id', $reason)->delete();

        return response()->json(['message' => 'RTO reason deleted.']);
    }

    private function shipmentsBaseQuery(string $from, string $to): Builder
    {
        return DB::table('shipments')
            ->whereBetween('shipments.shipped_on', [$from, $to]);
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
