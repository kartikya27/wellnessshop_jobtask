@extends('layouts.dashboard', [
    'title' => $title,
    'subtitle' => $subtitle,
    'eyebrow' => 'Operations',
    'assistantDepartment' => $assistantDepartment,
])

@section('content')
    <form method="GET" class="mb-6 rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 md:grid-cols-[1fr_1fr_0.9fr_0.9fr_1fr_auto]">
            <label class="grid gap-1 text-sm font-medium text-stone-700">
                From
                <input type="date" name="from" value="{{ $filters['from'] }}" class="field h-11">
            </label>
            <label class="grid gap-1 text-sm font-medium text-stone-700">
                To
                <input type="date" name="to" value="{{ $filters['to'] }}" class="field h-11">
            </label>
            <label class="grid gap-1 text-sm font-medium text-stone-700">
                Courier
                <select name="courier" class="field h-11">
                    <option value="">All couriers</option>
                    <option value="dlv" @selected(($filters['courier'] ?? null) === 'dlv')>Delhivery</option>
                    <option value="bdp" @selected(($filters['courier'] ?? null) === 'bdp')>Blue Dart</option>
                    <option value="srx" @selected(($filters['courier'] ?? null) === 'srx')>Shiprocket</option>
                    <option value="ecx" @selected(($filters['courier'] ?? null) === 'ecx')>Ecom Express</option>
                </select>
            </label>
            <label class="grid gap-1 text-sm font-medium text-stone-700">
                Status
                <select name="status" class="field h-11">
                    <option value="">All statuses</option>
                    <option value="delivered" @selected(($filters['status'] ?? null) === 'delivered')>Delivered</option>
                    <option value="rto" @selected(($filters['status'] ?? null) === 'rto')>RTO</option>
                    <option value="lost" @selected(($filters['status'] ?? null) === 'lost')>Lost</option>
                </select>
            </label>
            <label class="grid gap-1 text-sm font-medium text-stone-700">
                Search
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="field h-11" placeholder="Order, courier, tracking">
            </label>
            <button type="submit" class="btn-primary h-11 self-end">Apply</button>
        </div>
    </form>

    @if ($overview['alerts']['rto_above_threshold'] || $overview['alerts']['otd_below_threshold'])
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <span class="font-semibold">Attention:</span>
            @if ($overview['alerts']['rto_above_threshold'])
                RTO is above the 10% threshold.
            @endif
            @if ($overview['alerts']['otd_below_threshold'])
                OTD is below the 90% threshold.
            @endif
        </div>
    @endif

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        @include('partials.metric-card', ['label' => 'Orders', 'value' => number_format($overview['total_orders']), 'helper' => 'Shipped orders'])
        @include('partials.metric-card', ['label' => 'Delivered', 'value' => number_format($overview['delivered']), 'helper' => 'Completed deliveries'])
        @include('partials.metric-card', ['label' => 'RTO Rate', 'value' => $overview['rto_rate'] . '%', 'helper' => 'Return to origin rate'])
        @include('partials.metric-card', ['label' => 'OTD', 'value' => $overview['otd_percent'] . '%', 'helper' => 'On-time delivery'])
        @include('partials.metric-card', ['label' => 'Lost', 'value' => number_format($overview['lost_cases']), 'helper' => 'Lost shipments'])
        @include('partials.metric-card', ['label' => 'Avg Ship', 'value' => $overview['avg_ship_time_hours'] . 'h', 'helper' => 'Order to shipment'])
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <div class="rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-base font-semibold text-stone-950">Order Trend</h3>
                    <p class="mt-1 text-sm text-stone-600">Daily orders with OTD and RTO context.</p>
                </div>
                <div class="flex flex-wrap gap-4 text-xs font-medium text-stone-500">
                    <span class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-sky-500"></span>Orders</span>
                    <span class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>OTD</span>
                    <span class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-rose-500"></span>RTO</span>
                </div>
            </div>

            <script id="operations-trend-data" type="application/json">@json($trendChart)</script>
            <div class="mt-5 h-80">
                <canvas id="operations-trend-chart"></canvas>
            </div>
        </div>

        <div class="rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-stone-950">RTO Breakdown</h3>
                    <p class="mt-1 text-sm text-stone-600">Reasons driving returns.</p>
                </div>
                <a href="{{ route('rto-reasons.index') }}" class="btn-secondary">Manage reasons</a>
            </div>

            <div class="mt-4 grid gap-3">
                @forelse ($rtoBreakdown as $item)
                    <div class="rounded-lg border border-stone-200 bg-stone-50 p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="font-semibold text-stone-950">{{ $item['reason'] }}</p>
                                <p class="mt-1 text-xs text-stone-500">{{ $item['category'] }}</p>
                            </div>
                            <span class="inline-flex rounded-md px-2 py-1 text-xs font-semibold {{ $item['is_controllable'] ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-200 text-stone-700' }}">
                                {{ $item['is_controllable'] ? 'Controllable' : 'External' }}
                            </span>
                        </div>
                        <p class="mt-3 text-2xl font-semibold text-stone-950">{{ $item['rto_count'] }}</p>
                    </div>
                @empty
                    <p class="text-sm text-stone-500">No RTO reasons found.</p>
                @endforelse
            </div>
        </div>
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-[1fr_1fr]">
        <div class="rounded-xl border border-stone-200 bg-white shadow-sm">
            <div class="border-b border-stone-200 px-4 py-4">
                <h3 class="text-base font-semibold text-stone-950">Courier Performance</h3>
                <p class="mt-1 text-sm text-stone-600">OTD %, RTO %, lost count, and performance score.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Courier</th>
                            <th class="text-right">Orders</th>
                            <th class="text-right">OTD %</th>
                            <th class="text-right">RTO %</th>
                            <th class="text-right">Lost</th>
                            <th class="text-right">Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($couriers as $courier)
                            <tr>
                                <td>
                                    <div class="font-medium text-stone-950">{{ $courier['name'] }}</div>
                                    <div class="text-xs text-stone-500">{{ $courier['code'] }}</div>
                                </td>
                                <td class="text-right">{{ number_format($courier['orders']) }}</td>
                                <td class="text-right">{{ $courier['otd_percent'] }}%</td>
                                <td class="text-right">{{ $courier['rto_percent'] }}%</td>
                                <td class="text-right">{{ number_format($courier['lost_count']) }}</td>
                                <td class="text-right {{ $courier['performance_score'] < 60 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $courier['performance_score'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-stone-500">No courier data available.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-xl border border-stone-200 bg-white shadow-sm">
            <div class="border-b border-stone-200 px-4 py-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-stone-950">Recent Lost Cases</h3>
                        <p class="mt-1 text-sm text-stone-600">Claim filed status and recovered amounts.</p>
                    </div>
                    <a href="{{ route('lost-cases.index') }}" class="btn-secondary">Open page</a>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Case</th>
                            <th>Order</th>
                            <th>Courier</th>
                            <th>Status</th>
                            <th class="text-right">Claim</th>
                            <th class="text-right">Recovered</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (array_slice($lostCases, 0, 8) as $case)
                            <tr>
                                <td>{{ $case['case_number'] }}</td>
                                <td>
                                    <div class="font-medium text-stone-950">{{ $case['order_number'] }}</div>
                                    <div class="text-xs text-stone-500">{{ $case['tracking_number'] }}</div>
                                </td>
                                <td>{{ $case['courier'] }}</td>
                                <td>
                                    <span class="inline-flex rounded-md px-2 py-1 text-xs font-semibold capitalize {{ $case['status'] === 'recovered' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                        {{ str_replace('_', ' ', $case['status']) }}
                                    </span>
                                </td>
                                <td class="text-right">₹{{ number_format($case['claim_amount']) }}</td>
                                <td class="text-right text-emerald-700">₹{{ number_format($case['amount_recovered']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-stone-500">No lost cases available.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="mt-6 rounded-xl border border-stone-200 bg-white shadow-sm">
        <div class="border-b border-stone-200 px-4 py-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-stone-950">RTO Reason Summary</h3>
                    <p class="mt-1 text-sm text-stone-600">Use the dedicated page to add, edit, or remove reasons.</p>
                </div>
                <a href="{{ route('rto-reasons.index') }}" class="btn-secondary">Open reason library</a>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Reason</th>
                        <th>Category</th>
                        <th>Type</th>
                        <th class="text-right">RTO Count</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rtoReasons as $reason)
                        <tr>
                            <td class="font-medium text-stone-950">{{ $reason['reason'] }}</td>
                            <td>{{ $reason['category'] }}</td>
                            <td>{{ $reason['is_controllable'] ? 'Controllable' : 'External' }}</td>
                            <td class="text-right">{{ number_format($reason['rto_count']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-stone-500">No RTO reasons available.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @push('scripts')
        @vite(['resources/js/dashboard-charts.js'])
    @endpush
@endsection
