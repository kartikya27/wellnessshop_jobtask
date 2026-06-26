@extends('layouts.dashboard', [
    'title' => $title,
    'subtitle' => $subtitle,
    'eyebrow' => 'Marketing',
    'assistantDepartment' => $assistantDepartment,
])

@section('content')
    <form method="GET" class="mb-6 rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 md:grid-cols-[1fr_1fr_0.8fr_1fr_auto]">
            <label class="grid gap-1 text-sm font-medium text-stone-700">
                From
                <input type="date" name="from" value="{{ $filters['from'] }}" class="field h-11">
            </label>
            <label class="grid gap-1 text-sm font-medium text-stone-700">
                To
                <input type="date" name="to" value="{{ $filters['to'] }}" class="field h-11">
            </label>
            <label class="grid gap-1 text-sm font-medium text-stone-700">
                Platform
                <select name="platform" class="field h-11">
                    <option value="">All platforms</option>
                    <option value="meta" @selected(($filters['platform'] ?? null) === 'meta')>Meta</option>
                    <option value="google" @selected(($filters['platform'] ?? null) === 'google')>Google</option>
                </select>
            </label>
            <label class="grid gap-1 text-sm font-medium text-stone-700">
                Search
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="field h-11" placeholder="Campaign name">
            </label>
            <button type="submit" class="btn-primary h-11 self-end">Apply</button>
        </div>
    </form>

    @if ($overview['alerts']['roas_below_threshold'] || $overview['alerts']['cac_above_threshold'])
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <span class="font-semibold">Attention:</span>
            @if ($overview['alerts']['roas_below_threshold'])
                Blended ROAS is below target.
            @endif
            @if ($overview['alerts']['cac_above_threshold'])
                CAC is above target.
            @endif
        </div>
    @endif

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @include('partials.metric-card', ['label' => 'Spend', 'value' => '₹' . number_format($overview['total_spend']), 'helper' => 'Total media investment'])
        @include('partials.metric-card', ['label' => 'Revenue', 'value' => '₹' . number_format($overview['revenue']), 'helper' => 'Attributed sales'])
        @include('partials.metric-card', ['label' => 'ROAS', 'value' => $overview['blended_roas'] . 'x', 'helper' => 'Revenue per rupee spent'])
        @include('partials.metric-card', ['label' => 'CAC', 'value' => '₹' . number_format($overview['blended_cac']), 'helper' => 'Cost per conversion'])
        @include('partials.metric-card', ['label' => 'Conversions', 'value' => number_format($overview['conversions']), 'helper' => 'Total purchases'])
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-[1.25fr_0.75fr]">
        <div class="rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-base font-semibold text-stone-950">Spend and Revenue Trend</h3>
                    <p class="mt-1 text-sm text-stone-600">A quiet view of the last 90 days.</p>
                </div>
                <div class="flex gap-4 text-xs font-medium text-stone-500">
                    <span class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-cyan-500"></span>Spend</span>
                    <span class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>Revenue</span>
                </div>
            </div>

            <script id="marketing-trend-data" type="application/json">@json($trendChart)</script>
            <div class="mt-5 h-80">
                <canvas id="marketing-trend-chart"></canvas>
            </div>
        </div>

        <div class="rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
            <h3 class="text-base font-semibold text-stone-950">Platform Efficiency</h3>
            <div class="mt-4 grid gap-3">
                @foreach ($platforms as $platform)
                    <div class="rounded-lg border border-stone-200 bg-stone-50 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="font-semibold text-stone-950">{{ $platform['platform_name'] }}</p>
                                <p class="text-xs uppercase tracking-[0.16em] text-stone-500">{{ $platform['platform'] }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-stone-950">{{ $platform['roas'] }}x ROAS</p>
                                <p class="text-xs text-stone-500">CAC ₹{{ number_format($platform['cac']) }}</p>
                            </div>
                        </div>

                        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt class="text-stone-500">Spend</dt>
                                <dd class="font-medium text-stone-950">₹{{ number_format($platform['spend']) }}</dd>
                            </div>
                            <div>
                                <dt class="text-stone-500">Revenue</dt>
                                <dd class="font-medium text-stone-950">₹{{ number_format($platform['revenue']) }}</dd>
                            </div>
                            <div>
                                <dt class="text-stone-500">CPM</dt>
                                <dd class="font-medium text-stone-950">₹{{ number_format($platform['cpm'], 2) }}</dd>
                            </div>
                            <div>
                                <dt class="text-stone-500">CTR</dt>
                                <dd class="font-medium text-stone-950">{{ number_format($platform['ctr'], 2) }}%</dd>
                            </div>
                        </dl>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="mt-6 rounded-xl border border-stone-200 bg-white shadow-sm">
        <div class="border-b border-stone-200 px-4 py-4">
            <h3 class="text-base font-semibold text-stone-950">Campaign Performance</h3>
            <p class="mt-1 text-sm text-stone-600">Spend, ROAS, platform, and status.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Campaign</th>
                        <th>Platform</th>
                        <th>Status</th>
                        <th class="text-right">Budget</th>
                        <th class="text-right">Spend</th>
                        <th class="text-right">ROAS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($campaigns as $campaign)
                        <tr>
                            <td>
                                <div class="font-medium text-stone-950">{{ $campaign['name'] }}</div>
                                <div class="text-xs text-stone-500">{{ $campaign['objective'] }}</div>
                            </td>
                            <td>{{ $campaign['platform'] }}</td>
                            <td>
                                <span class="inline-flex rounded-md px-2 py-1 text-xs font-semibold capitalize {{ $campaign['status'] === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-100 text-stone-700' }}">
                                    {{ str_replace('_', ' ', $campaign['status']) }}
                                </span>
                            </td>
                            <td class="text-right">₹{{ number_format($campaign['daily_budget']) }}</td>
                            <td class="text-right">₹{{ number_format($campaign['spend']) }}</td>
                            <td class="text-right {{ $campaign['roas'] < 2 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $campaign['roas'] }}x</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-stone-500">No campaigns available for the selected period.</td>
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
