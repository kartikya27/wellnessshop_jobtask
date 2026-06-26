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
                    <option value="open" @selected(($filters['status'] ?? null) === 'open')>Open</option>
                    <option value="under_review" @selected(($filters['status'] ?? null) === 'under_review')>Under review</option>
                    <option value="approved" @selected(($filters['status'] ?? null) === 'approved')>Approved</option>
                    <option value="recovered" @selected(($filters['status'] ?? null) === 'recovered')>Recovered</option>
                </select>
            </label>
            <label class="grid gap-1 text-sm font-medium text-stone-700">
                Search
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="field h-11" placeholder="Case, order, courier">
            </label>
            <button type="submit" class="btn-primary h-11 self-end">Apply</button>
        </div>
    </form>

    <section class="rounded-xl border border-stone-200 bg-white shadow-sm">
        <div class="border-b border-stone-200 px-4 py-4">
            <h3 class="text-base font-semibold text-stone-950">Lost Cases</h3>
            <p class="mt-1 text-sm text-stone-600">Claim filed status, amount recovered, and shipment context.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Case</th>
                        <th>Order</th>
                        <th>Courier</th>
                        <th>Status</th>
                        <th>Claim Filed</th>
                        <th class="text-right">Claim</th>
                        <th class="text-right">Recovered</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lostCases as $case)
                        <tr>
                            <td>
                                <div class="font-medium text-stone-950">{{ $case['case_number'] }}</div>
                                <div class="text-xs text-stone-500">{{ $case['reported_on'] }}</div>
                            </td>
                            <td>
                                <div class="font-medium text-stone-950">{{ $case['order_number'] }}</div>
                                <div class="text-xs text-stone-500">{{ $case['tracking_number'] }}</div>
                            </td>
                            <td>{{ $case['courier'] }}</td>
                            <td>{{ str_replace('_', ' ', $case['status']) }}</td>
                            <td>{{ $case['claim_filed'] ? 'Yes' : 'No' }}</td>
                            <td class="text-right">₹{{ number_format($case['claim_amount']) }}</td>
                            <td class="text-right text-emerald-700">₹{{ number_format($case['amount_recovered']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-stone-500">No lost cases match the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
