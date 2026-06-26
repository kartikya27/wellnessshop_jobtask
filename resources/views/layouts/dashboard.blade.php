<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? 'D2C Metrics Dashboard' }}</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-stone-50 text-stone-950 antialiased">
        <div class="min-h-screen lg:flex">
            <aside class="hidden w-72 shrink-0 border-r border-stone-200 bg-white lg:flex lg:flex-col">
                <div class="border-b border-stone-200 px-6 py-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-700">WellnessShop</p>
                    <h1 class="mt-2 text-lg font-semibold text-stone-950">Metrics Console</h1>
                </div>

                <nav class="grid gap-1 p-4 text-sm font-medium">
                    <a href="{{ route('dashboard.marketing') }}" class="nav-link {{ request()->routeIs('dashboard.marketing') ? 'active' : '' }}">Marketing</a>
                    <a href="{{ route('dashboard.operations') }}" class="nav-link {{ request()->routeIs('dashboard.operations') ? 'active' : '' }}">Operations</a>
                    <a href="{{ route('campaigns.index') }}" class="nav-link {{ request()->routeIs('campaigns.index') ? 'active' : '' }}">Campaigns</a>
                    <a href="{{ route('orders.index') }}" class="nav-link {{ request()->routeIs('orders.index') ? 'active' : '' }}">Orders</a>
                    <a href="{{ route('shipments.index') }}" class="nav-link {{ request()->routeIs('shipments.index') ? 'active' : '' }}">Shipments</a>
                    <a href="{{ route('rto-reasons.index') }}" class="nav-link {{ request()->routeIs('rto-reasons.index') ? 'active' : '' }}">RTO Reasons</a>
                    <a href="{{ route('lost-cases.index') }}" class="nav-link {{ request()->routeIs('lost-cases.index') ? 'active' : '' }}">Lost Cases</a>
                    <a href="{{ route('assistant.index') }}" class="nav-link {{ request()->routeIs('assistant.index') ? 'active' : '' }}">AI Assistant</a>
                </nav>
            </aside>

            <div class="min-w-0 flex-1">
                <header class="border-b border-stone-200 bg-white/90 backdrop-blur">
                    <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                        <div class="flex flex-wrap items-end justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-700">{{ $eyebrow ?? 'D2C Brand Metrics' }}</p>
                                <h2 class="mt-1 text-2xl font-semibold tracking-tight text-stone-950 sm:text-3xl">{{ $title ?? 'Dashboard' }}</h2>
                                @if (! empty($subtitle))
                                    <p class="mt-2 max-w-3xl text-sm leading-6 text-stone-600">{{ $subtitle }}</p>
                                @endif
                            </div>

                            <div class="flex flex-wrap gap-2 lg:hidden">
                                <a href="{{ route('dashboard.marketing') }}" class="nav-link {{ request()->routeIs('dashboard.marketing') ? 'active' : '' }}">Marketing</a>
                                <a href="{{ route('dashboard.operations') }}" class="nav-link {{ request()->routeIs('dashboard.operations') ? 'active' : '' }}">Operations</a>
                                <a href="{{ route('assistant.index') }}" class="nav-link {{ request()->routeIs('assistant.index') ? 'active' : '' }}">AI Assistant</a>
                            </div>
                        </div>
                    </div>
                </header>

                <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    @if (session('status'))
                        <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {{ session('status') }}
                        </div>
                    @endif

                    @yield('content')
                </main>
            </div>
        </div>

        @if (! empty($assistantContext))
            <script id="assistant-context" type="application/json">@json($assistantContext)</script>
        @endif

        @stack('scripts')

        @if (! empty($assistantDepartment))
            @include('partials.assistant-widget', ['department' => $assistantDepartment])
        @endif
    </body>
</html>
