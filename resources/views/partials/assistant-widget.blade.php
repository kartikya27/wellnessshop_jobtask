<section id="assistant-widget" data-default-department="{{ $department ?? 'operations' }}" class="fixed bottom-5 right-5 z-50">
    <button id="assistant-toggle" class="flex items-center gap-2 rounded-full bg-emerald-700 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-900/20 transition hover:bg-emerald-800" type="button">
        <span class="grid h-6 w-6 place-items-center rounded-full bg-white/15">AI</span>
        Ask analyst
    </button>

    <aside id="assistant-drawer" class="hidden w-[min(420px,calc(100vw-2rem))] overflow-hidden rounded-xl border border-stone-200 bg-white shadow-2xl shadow-stone-900/20">
        <div class="flex items-center justify-between border-b border-stone-200 bg-stone-50 px-4 py-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-700">AI Analyst</p>
                <h2 class="text-sm font-semibold text-stone-950">Dashboard chat</h2>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('assistant.index') }}" class="btn-secondary !px-2 !py-1 text-xs">Full page</a>
                <button id="assistant-new-chat" class="btn-secondary !px-2 !py-1 text-xs" type="button">New</button>
                <button id="assistant-close" class="btn-secondary !px-2 !py-1 text-xs" type="button">Close</button>
            </div>
        </div>

        <div id="assistant-messages" class="grid max-h-[55vh] min-h-80 gap-3 overflow-y-auto bg-white p-4 text-sm"></div>

        <div class="border-t border-stone-200 bg-stone-50 p-3">
            <div class="mb-3 flex flex-wrap gap-2">
                <button class="ai-suggestion" type="button">Summarize this page</button>
                <button class="ai-suggestion" type="button">What needs attention?</button>
                <button class="ai-suggestion" type="button">Suggest next actions</button>
            </div>
            <form id="assistant-form" class="flex gap-2">
                <input id="assistant-question" class="field flex-1" placeholder="Ask about current dashboard data">
                <button class="btn-primary" type="submit">Send</button>
            </form>
        </div>
    </aside>
</section>
