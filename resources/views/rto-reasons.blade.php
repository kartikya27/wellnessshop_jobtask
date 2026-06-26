@extends('layouts.dashboard', [
    'title' => $title,
    'subtitle' => $subtitle,
    'eyebrow' => 'Operations',
    'assistantDepartment' => $assistantDepartment,
])

@section('content')
    <section class="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
        <div class="rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
            <h3 class="text-base font-semibold text-stone-950">Add RTO Reason</h3>
            <p class="mt-1 text-sm text-stone-600">Create a new reason label for operations reporting.</p>

            <form method="POST" action="{{ route('rto-reasons.store') }}" class="mt-4 grid gap-3">
                @csrf
                <input name="reason" class="field" placeholder="Reason name">
                <input name="category" class="field" placeholder="Category">
                <label class="flex items-center gap-2 text-sm text-stone-600">
                    <input type="checkbox" name="is_controllable" value="1" class="h-4 w-4 rounded border-stone-300 text-emerald-700">
                    Controllable by operations
                </label>
                <button type="submit" class="btn-primary">Save reason</button>
            </form>
        </div>

        <div class="rounded-xl border border-stone-200 bg-white shadow-sm">
            <div class="border-b border-stone-200 px-4 py-4">
                <h3 class="text-base font-semibold text-stone-950">Existing Reasons</h3>
                <p class="mt-1 text-sm text-stone-600">Edit or remove reasons using the inline forms.</p>
            </div>
            <div class="divide-y divide-stone-200">
                @forelse ($reasons as $reason)
                    <div class="p-4">
                        <div class="mb-3 flex items-start justify-between gap-4">
                            <div>
                                <p class="font-semibold text-stone-950">{{ $reason['reason'] }}</p>
                                <p class="mt-1 text-xs uppercase tracking-[0.16em] text-stone-500">{{ $reason['category'] }} · {{ $reason['rto_count'] }} RTOs</p>
                            </div>
                            <span class="inline-flex rounded-md px-2 py-1 text-xs font-semibold {{ $reason['is_controllable'] ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-200 text-stone-700' }}">
                                {{ $reason['is_controllable'] ? 'Controllable' : 'External' }}
                            </span>
                        </div>

                        <form method="POST" action="{{ route('rto-reasons.update', $reason['id']) }}" class="grid gap-3 md:grid-cols-[1fr_1fr_auto_auto]">
                            @csrf
                            @method('PATCH')
                            <input name="reason" value="{{ $reason['reason'] }}" class="field">
                            <input name="category" value="{{ $reason['category'] }}" class="field">
                            <label class="flex items-center gap-2 text-sm text-stone-600">
                                <input type="checkbox" name="is_controllable" value="1" class="h-4 w-4 rounded border-stone-300 text-emerald-700" @checked($reason['is_controllable'])>
                                Controllable
                            </label>
                            <div class="flex gap-2">
                                <button type="submit" class="btn-primary">Update</button>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('rto-reasons.destroy', $reason['id']) }}" class="mt-3">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-secondary" onclick="return confirm('Delete this reason?')">Delete</button>
                        </form>
                    </div>
                @empty
                    <div class="p-4 text-sm text-stone-500">No reasons available yet.</div>
                @endforelse
            </div>
        </div>
    </section>
@endsection
