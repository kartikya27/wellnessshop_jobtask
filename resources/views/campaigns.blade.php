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

    <section class="rounded-xl border border-stone-200 bg-white shadow-sm">
        <div class="border-b border-stone-200 px-4 py-4">
            <h3 class="text-base font-semibold text-stone-950">Campaign Performance</h3>
            <p class="mt-1 text-sm text-stone-600">Spend, ROAS, platform, and status for the selected range.</p>
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
                            <td>{{ str_replace('_', ' ', $campaign['status']) }}</td>
                            <td class="text-right">₹{{ number_format($campaign['daily_budget']) }}</td>
                            <td class="text-right">₹{{ number_format($campaign['spend']) }}</td>
                            <td class="text-right {{ $campaign['roas'] < 2 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $campaign['roas'] }}x</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-stone-500">No campaigns available.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
