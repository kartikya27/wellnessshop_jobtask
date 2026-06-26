<article class="rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">{{ $label }}</p>
    <div class="mt-2 text-2xl font-semibold tracking-tight text-stone-950">{{ $value }}</div>
    @if (! empty($helper))
        <p class="mt-2 text-sm leading-6 text-stone-600">{{ $helper }}</p>
    @endif
</article>
