@extends('layouts.dashboard', [
    'title' => $title,
    'subtitle' => $subtitle,
    'eyebrow' => 'AI Analyst',
    'assistantDepartment' => null,
])

@section('content')
    <div id="assistant-page-app" data-default-department="operations" class="grid gap-6 lg:grid-cols-[280px_1fr]">
        <section class="rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
            <div>
                <h3 class="text-base font-semibold text-stone-950">Chats</h3>
                <p class="mt-1 text-sm text-stone-600">Recent conversations.</p>
            </div>
            <button id="assistant-page-new-chat" class="btn-primary mt-4 w-full" type="button">New chat</button>
            <div id="assistant-session-list" class="mt-4 grid gap-2"></div>
        </section>

        <section class="flex min-h-[72vh] flex-col rounded-xl border border-stone-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-stone-200 px-4 py-4">
                <div>
                    <h3 class="text-base font-semibold text-stone-950">Conversation</h3>
                    <p class="mt-1 text-sm text-stone-600">Responses are formatted for quick reading.</p>
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
@endsection
