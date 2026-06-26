@extends('layouts.dashboard', [
    'title' => $title,
    'subtitle' => $subtitle,
    'eyebrow' => 'Operations',
    'assistantDepartment' => $assistantDepartment,
])

@section('content')
    <form method="GET" class="mb-6 rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 md:grid-cols-[1.2fr_0.7fr_0.7fr_0.7fr_0.7fr_auto]">
            <input name="search" value="{{ $filters['search'] }}" class="field h-11" placeholder="Search order, city, tracking, courier">
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
            <h3 class="text-base font-semibold text-stone-950">Orders</h3>
            <p class="mt-1 text-sm text-stone-600">Latest matching records from the seeded shipment data.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Courier</th>
                        <th>Status</th>
                        <th>RTO Reason</th>
                        <th class="text-right">Value</th>
                        <th class="text-right">Update</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td>
                                <div class="font-medium text-stone-950">{{ $order['order_number'] }}</div>
                                <div class="text-xs text-stone-500">{{ $order['order_date'] }}</div>
                            </td>
                            <td>
                                <div class="font-medium text-stone-950">{{ $order['customer_city'] }}</div>
                                <div class="text-xs text-stone-500">{{ $order['customer_state'] }}</div>
                            </td>
                            <td>
                                <div class="font-medium text-stone-950">{{ $order['courier'] }}</div>
                                <div class="text-xs text-stone-500">{{ $order['courier_code'] }}</div>
                            </td>
                            <td>
                                <span class="inline-flex rounded-md px-2 py-1 text-xs font-semibold capitalize {{ $order['status'] === 'delivered' ? 'bg-emerald-100 text-emerald-700' : ($order['status'] === 'rto' ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700') }}">
                                    {{ str_replace('_', ' ', $order['status']) }}
                                </span>
                            </td>
                            <td>{{ $order['rto_reason'] }}</td>
                            <td class="text-right">₹{{ number_format($order['order_value']) }}</td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('orders.status.update', $order['id']) }}" class="inline-flex items-center gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <select name="status" class="field !w-36 !py-2">
                                        <option value="delivered" @selected($order['status'] === 'delivered')>Delivered</option>
                                        <option value="rto" @selected($order['status'] === 'rto')>RTO</option>
                                        <option value="lost" @selected($order['status'] === 'lost')>Lost</option>
                                    </select>
                                    <button class="btn-secondary" type="submit">Save</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-stone-500">No orders match the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
