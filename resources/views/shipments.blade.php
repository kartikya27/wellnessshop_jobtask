@extends('layouts.dashboard', [
    'title' => $title,
    'subtitle' => $subtitle,
    'eyebrow' => 'Operations',
    'assistantDepartment' => $assistantDepartment,
])

@section('content')
    <form method="GET" class="mb-6 rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 md:grid-cols-[1fr_0.7fr_0.7fr_0.7fr_0.7fr_auto]">
            <input name="search" value="{{ $filters['search'] }}" class="field h-11" placeholder="Search tracking, order, courier">
            <select name="courier" class="field h-11">
                <option value="">All couriers</option>
                @foreach ($couriers as $courier)
                    <option value="{{ $courier->code }}" @selected($filters['courier'] === $courier->code)>{{ $courier->name }}</option>
                @endforeach
            </select>
            <select name="status" class="field h-11">
                <option value="">All statuses</option>
                <option value="delivered" @selected($filters['status'] === 'delivered')>Delivered</option>
                <option value="rto" @selected($filters['status'] === 'rto')>RTO</option>
                <option value="lost" @selected($filters['status'] === 'lost')>Lost</option>
            </select>
            <input type="date" name="from" value="{{ $filters['from'] }}" class="field h-11">
            <input type="date" name="to" value="{{ $filters['to'] }}" class="field h-11">
            <button type="submit" class="btn-primary h-11 self-end">Filter</button>
        </div>
    </form>

    <section class="rounded-xl border border-stone-200 bg-white shadow-sm">
        <div class="border-b border-stone-200 px-4 py-4">
            <h3 class="text-base font-semibold text-stone-950">Shipment Records</h3>
            <p class="mt-1 text-sm text-stone-600">Courier lane, delivery date, and RTO reason labels.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tracking</th>
                        <th>Order</th>
                        <th>Courier</th>
                        <th>Status</th>
                        <th>Shipped</th>
                        <th>Delivered / RTO</th>
                        <th>RTO Reason</th>
                        <th class="text-right">Cost</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($shipments as $shipment)
                        <tr>
                            <td class="font-medium text-stone-950">{{ $shipment['tracking_number'] }}</td>
                            <td>
                                <div class="font-medium text-stone-950">{{ $shipment['order_number'] }}</div>
                                <div class="text-xs text-stone-500">{{ $shipment['customer_city'] }}, {{ $shipment['customer_state'] }}</div>
                            </td>
                            <td>
                                <div class="font-medium text-stone-950">{{ $shipment['courier'] }}</div>
                                <div class="text-xs text-stone-500">{{ $shipment['courier_code'] }}</div>
                            </td>
                            <td>{{ str_replace('_', ' ', $shipment['status']) }}</td>
                            <td>{{ $shipment['shipped_on'] }}</td>
                            <td>{{ $shipment['delivered_on'] !== '-' ? $shipment['delivered_on'] : $shipment['rto_on'] }}</td>
                            <td>{{ $shipment['rto_reason'] }}</td>
                            <td class="text-right">₹{{ number_format($shipment['shipping_cost']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-stone-500">No shipments match the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
