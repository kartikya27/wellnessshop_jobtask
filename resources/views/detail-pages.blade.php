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
        <main id="detail-page-app" data-page="{{ $page }}" class="min-h-screen">
            <aside class="fixed inset-y-0 left-0 hidden w-64 border-r border-stone-200 bg-white lg:block">
                <div class="border-b border-stone-200 px-6 py-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">WellnessShop</p>
                    <h1 class="mt-2 text-lg font-semibold">Metrics Console</h1>
                </div>
                <nav class="grid gap-1 p-4 text-sm font-medium">
                    <a href="{{ route('dashboard.marketing') }}" class="nav-link">Marketing</a>
                    <a href="{{ route('dashboard.operations') }}" class="nav-link">Operations</a>
                    <a href="{{ route('campaigns.index') }}" class="nav-link {{ $page === 'campaigns' ? 'active' : '' }}">Campaigns</a>
                    <a href="{{ route('orders.index') }}" class="nav-link">Orders</a>
                    <a href="{{ route('shipments.index') }}" class="nav-link {{ $page === 'shipments' ? 'active' : '' }}">Shipments</a>
                    <a href="{{ route('rto-reasons.index') }}" class="nav-link {{ $page === 'rto-reasons' ? 'active' : '' }}">RTO Reasons</a>
                    <a href="{{ route('lost-cases.index') }}" class="nav-link {{ $page === 'lost-cases' ? 'active' : '' }}">Lost Cases</a>
                    <a href="{{ route('assistant.index') }}" class="nav-link">AI Assistant</a>
                </nav>
            </aside>

            <section class="lg:pl-64">
                <header class="border-b border-stone-200 bg-white">
                    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        <div class="flex flex-wrap gap-2 lg:hidden">
                            <a href="{{ route('dashboard.marketing') }}" class="nav-link">Marketing</a>
                            <a href="{{ route('dashboard.operations') }}" class="nav-link">Operations</a>
                            <a href="{{ route('campaigns.index') }}" class="nav-link {{ $page === 'campaigns' ? 'active' : '' }}">Campaigns</a>
                            <a href="{{ route('orders.index') }}" class="nav-link">Orders</a>
                            <a href="{{ route('shipments.index') }}" class="nav-link {{ $page === 'shipments' ? 'active' : '' }}">Shipments</a>
                            <a href="{{ route('assistant.index') }}" class="nav-link">AI Assistant</a>
                        </div>
                        <p class="mt-3 text-xs font-semibold uppercase tracking-[0.22em] text-emerald-700 lg:mt-0">{{ $eyebrow }}</p>
                        <h2 class="mt-1 text-2xl font-semibold tracking-normal sm:text-3xl">{{ $title }}</h2>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-stone-600">{{ $subtitle }}</p>
                    </div>
                </header>

                <div class="mx-auto grid max-w-7xl gap-6 px-4 py-6 sm:px-6 lg:px-8">
                    <form id="detail-filter" class="panel grid gap-3 md:grid-cols-[1fr_1fr_1fr_auto]">
                        <input id="detail-search" class="field" placeholder="Search records">
                        <input id="detail-from-date" type="date" class="field">
                        <input id="detail-to-date" type="date" class="field">
                        <button class="btn-primary" type="submit">Filter</button>
                    </form>

                    @if ($page === 'rto-reasons')
                        <section class="panel">
                            <div class="panel-header">
                                <div>
                                    <h3 class="panel-title">Reason Editor</h3>
                                    <p class="panel-copy">Create and maintain reason labels used in shipment RTO analytics.</p>
                                </div>
                            </div>
                            <form id="detail-rto-reason-form" class="grid gap-3 md:grid-cols-[1fr_1fr_auto_auto]">
                                <input type="hidden" id="detail-rto-reason-id">
                                <input id="detail-rto-reason-name" class="field" placeholder="Reason name">
                                <input id="detail-rto-reason-category" class="field" placeholder="Category">
                                <label class="flex items-center gap-2 text-sm text-stone-600">
                                    <input id="detail-rto-reason-controllable" type="checkbox" class="h-4 w-4 rounded border-stone-300 text-emerald-700">
                                    Controllable
                                </label>
                                <button class="btn-primary" type="submit">Save</button>
                            </form>
                        </section>
                    @endif

                    <section class="panel overflow-hidden p-0">
                        <div class="panel-header border-b border-stone-200 p-4">
                            <div>
                                <h3 id="detail-table-title" class="panel-title">{{ $title }}</h3>
                                <p id="detail-table-copy" class="panel-copy">Live records from the seeded dashboard database.</p>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="data-table">
                                <thead id="detail-table-head"></thead>
                                <tbody id="detail-table-body"></tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </section>
        </main>
        @include('partials.assistant-widget', ['department' => $eyebrow === 'Marketing' ? 'marketing' : 'operations'])
    </body>
</html>
