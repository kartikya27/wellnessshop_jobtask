<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Orders | D2C Metrics Dashboard</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-stone-50 text-stone-950 antialiased">
        <main id="orders-app" class="min-h-screen">
            <aside class="fixed inset-y-0 left-0 hidden w-64 border-r border-stone-200 bg-white lg:block">
                <div class="border-b border-stone-200 px-6 py-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">WellnessShop</p>
                    <h1 class="mt-2 text-lg font-semibold">Metrics Console</h1>
                </div>
                <nav class="grid gap-1 p-4 text-sm font-medium">
                    <a href="{{ route('dashboard.marketing') }}" class="nav-link">Marketing</a>
                    <a href="{{ route('dashboard.operations') }}" class="nav-link">Operations</a>
                    <a href="{{ route('campaigns.index') }}" class="nav-link">Campaigns</a>
                    <a href="{{ route('orders.index') }}" class="nav-link active">Orders</a>
                    <a href="{{ route('shipments.index') }}" class="nav-link">Shipments</a>
                    <a href="{{ route('rto-reasons.index') }}" class="nav-link">RTO Reasons</a>
                    <a href="{{ route('lost-cases.index') }}" class="nav-link">Lost Cases</a>
                    <a href="{{ route('assistant.index') }}" class="nav-link">AI Assistant</a>
                </nav>
            </aside>

            <section class="lg:pl-64">
                <header class="border-b border-stone-200 bg-white">
                    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-700">Operations</p>
                        <h2 class="mt-1 text-2xl font-semibold tracking-normal sm:text-3xl">Order Control Room</h2>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-stone-600">Filter seeded orders, inspect courier and RTO details, and update operational status.</p>
                    </div>
                </header>

                <div class="mx-auto grid max-w-7xl gap-6 px-4 py-6 sm:px-6 lg:px-8">
                    <form id="order-filter" class="panel grid gap-3 md:grid-cols-[1fr_1fr_1fr_1fr_auto]">
                        <input id="order-search" class="field" placeholder="Search order, tracking, city">
                        <select id="order-status" class="field">
                            <option value="">All statuses</option>
                            <option value="delivered">Delivered</option>
                            <option value="rto">RTO</option>
                            <option value="lost">Lost</option>
                        </select>
                        <input id="orders-from-date" type="date" class="field">
                        <input id="orders-to-date" type="date" class="field">
                        <button class="btn-primary" type="submit">Filter</button>
                    </form>

                    <section class="panel overflow-hidden p-0">
                        <div class="panel-header border-b border-stone-200 p-4">
                            <div>
                                <h3 class="panel-title">Orders</h3>
                                <p class="panel-copy">Latest 80 matching records from seeded shipment data.</p>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Customer</th>
                                        <th>Courier</th>
                                        <th>Status</th>
                                        <th>RTO reason</th>
                                        <th class="text-right">Value</th>
                                        <th class="text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="orders-table"></tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </section>
        </main>
        @include('partials.assistant-widget', ['department' => 'operations'])
    </body>
</html>
