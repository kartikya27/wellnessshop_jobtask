<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title }} | D2C Metrics Dashboard</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-stone-50 text-stone-950 antialiased">
        <main id="dashboard-app" data-department="{{ $department }}" class="min-h-screen">
            <aside class="fixed inset-y-0 left-0 hidden w-64 border-r border-stone-200 bg-white lg:block">
                <div class="flex h-full flex-col">
                    <div class="border-b border-stone-200 px-6 py-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">WellnessShop</p>
                        <h1 class="mt-2 text-lg font-semibold text-stone-950">Metrics Console</h1>
                    </div>
                    <nav class="grid gap-1 p-4 text-sm font-medium">
                        <a href="{{ route('dashboard.marketing') }}" class="nav-link {{ $department === 'marketing' ? 'active' : '' }}">Marketing</a>
                        <a href="{{ route('dashboard.operations') }}" class="nav-link {{ $department === 'operations' ? 'active' : '' }}">Operations</a>
                        <a href="{{ route('campaigns.index') }}" class="nav-link">Campaigns</a>
                        <a href="{{ route('orders.index') }}" class="nav-link">Orders</a>
                        <a href="{{ route('shipments.index') }}" class="nav-link">Shipments</a>
                        <a href="{{ route('rto-reasons.index') }}" class="nav-link">RTO Reasons</a>
                        <a href="{{ route('lost-cases.index') }}" class="nav-link">Lost Cases</a>
                        <a href="{{ route('assistant.index') }}" class="nav-link">AI Assistant</a>
                    </nav>
                    <div class="mt-auto border-t border-stone-200 p-4 text-xs leading-5 text-stone-500">
                        Data is seeded for the last three months and updates through the dashboard APIs.
                    </div>
                </div>
            </aside>

            <section class="lg:pl-64">
                <header class="sticky top-0 z-20 border-b border-stone-200 bg-stone-50/90 backdrop-blur">
                    <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-4 sm:px-6 lg:px-8">
                        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                            <div>
                                <div class="flex flex-wrap gap-2 lg:hidden">
                                    <a href="{{ route('dashboard.marketing') }}" class="nav-link {{ $department === 'marketing' ? 'active' : '' }}">Marketing</a>
                                    <a href="{{ route('dashboard.operations') }}" class="nav-link {{ $department === 'operations' ? 'active' : '' }}">Operations</a>
                                    <a href="{{ route('campaigns.index') }}" class="nav-link">Campaigns</a>
                                    <a href="{{ route('orders.index') }}" class="nav-link">Orders</a>
                                    <a href="{{ route('shipments.index') }}" class="nav-link">Shipments</a>
                                    <a href="{{ route('assistant.index') }}" class="nav-link">AI Assistant</a>
                                </div>
                                <p class="mt-3 text-xs font-semibold uppercase tracking-[0.22em] text-emerald-700 lg:mt-0">{{ ucfirst($department) }}</p>
                                <h2 class="mt-1 text-2xl font-semibold tracking-normal text-stone-950 sm:text-3xl">{{ $title }}</h2>
                                <p class="mt-2 max-w-3xl text-sm leading-6 text-stone-600">{{ $subtitle }}</p>
                            </div>

                            <form id="date-filter" class="grid gap-3 rounded-lg border border-stone-200 bg-white p-3 shadow-sm sm:grid-cols-[1fr_1fr_auto]">
                                <label class="grid gap-1 text-xs font-semibold text-stone-500">
                                    From
                                    <input id="from-date" type="date" class="field h-10">
                                </label>
                                <label class="grid gap-1 text-xs font-semibold text-stone-500">
                                    To
                                    <input id="to-date" type="date" class="field h-10">
                                </label>
                                <button class="btn-primary mt-auto h-10" type="submit">Apply</button>
                            </form>
                        </div>
                    </div>
                </header>

                <div class="mx-auto grid max-w-7xl gap-6 px-4 py-6 sm:px-6 lg:px-8">
                    <div id="alert-strip" class="hidden rounded-lg border px-4 py-3 text-sm"></div>

                    <section id="marketing-panel" data-panel="marketing" class="{{ $department === 'marketing' ? 'grid' : 'hidden' }} gap-6">
                        <div id="marketing-cards" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5"></div>

                        <div class="grid gap-6 xl:grid-cols-[1.3fr_0.7fr]">
                            <section class="panel">
                                <div class="panel-header">
                                    <div>
                                        <h3 class="panel-title">Spend and Revenue Trend</h3>
                                        <p class="panel-copy">Daily media investment, revenue, and ROAS.</p>
                                    </div>
                                </div>
                                <div class="h-80"><canvas id="marketing-trend-chart"></canvas></div>
                            </section>

                            <section class="panel">
                                <div class="panel-header">
                                    <div>
                                        <h3 class="panel-title">Platform Efficiency</h3>
                                        <p class="panel-copy">Meta vs Google for the selected period.</p>
                                    </div>
                                </div>
                                <div id="platform-cards" class="grid gap-3"></div>
                            </section>
                        </div>

                        <section class="panel overflow-hidden p-0">
                            <div class="panel-header border-b border-stone-200 p-4">
                                <div>
                                    <h3 class="panel-title">Campaign Performance</h3>
                                    <p class="panel-copy">Spend, ROAS, platform, and status.</p>
                                </div>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Campaign</th>
                                            <th>Platform</th>
                                            <th>Status</th>
                                            <th class="text-right">Spend</th>
                                            <th class="text-right">ROAS</th>
                                        </tr>
                                    </thead>
                                    <tbody id="campaign-table"></tbody>
                                </table>
                            </div>
                        </section>
                    </section>

                    <section id="operations-panel" data-panel="operations" class="{{ $department === 'operations' ? 'grid' : 'hidden' }} gap-6">
                        <div id="ops-cards" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6"></div>

                        <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
                            <section class="panel">
                                <div class="panel-header">
                                    <div>
                                        <h3 class="panel-title">Orders, OTD, and RTO Trend</h3>
                                        <p class="panel-copy">Daily fulfillment performance.</p>
                                    </div>
                                    <a href="{{ route('orders.index') }}" class="btn-secondary">Open Orders</a>
                                </div>
                                <div class="h-80"><canvas id="ops-trend-chart"></canvas></div>
                            </section>

                            <section class="panel">
                                <div class="panel-header">
                                    <div>
                                        <h3 class="panel-title">RTO Breakdown</h3>
                                        <p class="panel-copy">Reasons contributing to returns.</p>
                                    </div>
                                </div>
                                <div class="h-80"><canvas id="rto-chart"></canvas></div>
                            </section>
                        </div>

                        <section class="panel overflow-hidden p-0">
                            <div class="panel-header border-b border-stone-200 p-4">
                                <div>
                                    <h3 class="panel-title">Courier Performance</h3>
                                    <p class="panel-copy">Scorecard with OTD %, RTO %, lost count, and performance score.</p>
                                </div>
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
                                    <tbody id="courier-table"></tbody>
                                </table>
                            </div>
                        </section>

                        <section class="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
                            <div class="panel">
                                <div class="panel-header">
                                    <div>
                                        <h3 class="panel-title">RTO Reason Controls</h3>
                                        <p class="panel-copy">Add, edit, or remove reason labels used by shipment analysis.</p>
                                    </div>
                                </div>
                                <form id="rto-reason-form" class="grid gap-3">
                                    <input type="hidden" id="rto-reason-id">
                                    <input id="rto-reason-name" class="field" placeholder="Reason name">
                                    <input id="rto-reason-category" class="field" placeholder="Category">
                                    <label class="flex items-center gap-2 text-sm text-stone-600">
                                        <input id="rto-reason-controllable" type="checkbox" class="h-4 w-4 rounded border-stone-300 text-emerald-700">
                                        Controllable by operations
                                    </label>
                                    <div class="flex gap-2">
                                        <button class="btn-primary" type="submit">Save Reason</button>
                                        <button id="rto-reason-reset" class="btn-secondary" type="button">Clear</button>
                                    </div>
                                </form>
                                <div id="rto-reason-list" class="mt-5 grid gap-2"></div>
                            </div>

                            <div class="panel overflow-hidden p-0">
                                <div class="panel-header border-b border-stone-200 p-4">
                                    <div>
                                        <h3 class="panel-title">Lost Cases</h3>
                                        <p class="panel-copy">Claim filed status and recovered amount.</p>
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
                                        <tbody id="lost-case-table"></tbody>
                                    </table>
                                </div>
                            </div>
                        </section>
                    </section>

                </div>
            </section>
        </main>
        @include('partials.assistant-widget', ['department' => $department])
    </body>
</html>
