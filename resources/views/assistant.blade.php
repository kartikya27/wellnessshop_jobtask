<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>AI Assistant | D2C Metrics Dashboard</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-stone-50 text-stone-950 antialiased">
        <main id="assistant-page-app" data-default-department="operations" class="min-h-screen">
            <aside class="fixed inset-y-0 left-0 hidden w-64 border-r border-stone-200 bg-white lg:block">
                <div class="border-b border-stone-200 px-6 py-5">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">WellnessShop</p>
                    <h1 class="mt-2 text-lg font-semibold">Metrics Console</h1>
                </div>
                <nav class="grid gap-1 p-4 text-sm font-medium">
                    <a href="{{ route('dashboard.marketing') }}" class="nav-link">Marketing</a>
                    <a href="{{ route('dashboard.operations') }}" class="nav-link">Operations</a>
                    <a href="{{ route('campaigns.index') }}" class="nav-link">Campaigns</a>
                    <a href="{{ route('orders.index') }}" class="nav-link">Orders</a>
                    <a href="{{ route('assistant.index') }}" class="nav-link active">AI Assistant</a>
                </nav>
            </aside>

            <section class="lg:pl-64">
                <header class="border-b border-stone-200 bg-white">
                    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-emerald-700">AI Analyst</p>
                        <h2 class="mt-1 text-2xl font-semibold tracking-normal sm:text-3xl">Assistant Workspace</h2>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-stone-600">Create chats, revisit prior answers, and ask questions using dashboard data as context.</p>
                    </div>
                </header>

                <div class="mx-auto grid max-w-7xl gap-6 px-4 py-6 sm:px-6 lg:grid-cols-[280px_1fr] lg:px-8">
                    <section class="panel">
                        <div class="panel-header">
                            <div>
                                <h3 class="panel-title">Chats</h3>
                                <p class="panel-copy">Recent conversations.</p>
                            </div>
                        </div>
                        <button id="assistant-page-new-chat" class="btn-primary w-full" type="button">New chat</button>
                        <div id="assistant-session-list" class="mt-4 grid gap-2"></div>
                    </section>

                    <section class="panel flex min-h-[70vh] flex-col p-0">
                        <div class="flex items-center justify-between border-b border-stone-200 p-4">
                            <div>
                                <h3 class="panel-title">Conversation</h3>
                                <p class="panel-copy">Responses are formatted for quick reading.</p>
                            </div>
                            <select id="assistant-page-department" class="field !w-44">
                                <option value="operations">Operations</option>
                                <option value="marketing">Marketing</option>
                            </select>
                        </div>
                        <div id="assistant-page-messages" class="grid flex-1 content-start gap-3 overflow-y-auto p-4 text-sm"></div>
                        <div class="border-t border-stone-200 bg-stone-50 p-4">
                            <form id="assistant-page-form" class="flex gap-2">
                                <input id="assistant-page-question" class="field flex-1" placeholder="Ask a question about Marketing or Operations data">
                                <button class="btn-primary" type="submit">Send</button>
                            </form>
                        </div>
                    </section>
                </div>
            </section>
        </main>
    </body>
</html>
