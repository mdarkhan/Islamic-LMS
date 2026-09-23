<x-layout.admin :title="__('amol.inbox_heading')" :heading="__('amol.inbox_heading')">
    <form method="GET" class="mb-6">
        <div class="relative max-w-md">
            <x-ui.icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
            <input name="q" value="{{ $search }}" placeholder="{{ __('amol.search_placeholder') }}"
                   class="w-full rounded-xl bg-surface-raised border border-line pl-9 pr-4 py-2.5 text-sm outline-none focus:border-brand">
        </div>
    </form>

    @if ($students->isEmpty())
        <x-ui.card><x-ui.empty :title="__('amol.no_students')" /></x-ui.card>
    @else
        <div class="space-y-2">
            @foreach ($students as $student)
                <a href="{{ route('admin.amol.show', $student) }}"
                   class="flex items-center justify-between gap-4 p-4 rounded-2xl border border-line bg-surface hover:border-brand/40 transition-colors">
                    <div class="min-w-0">
                        <p class="font-semibold text-ink truncate">{{ $student->name }}</p>
                        <p class="text-xs text-muted tabular-nums">{{ $student->roll ? bn($student->roll) : '—' }}</p>
                    </div>
                    <span class="shrink-0 inline-flex items-center gap-1.5 text-sm font-semibold text-brand">
                        {{ __('amol.view_amol') }} <x-ui.icon name="chevron" class="w-4 h-4" />
                    </span>
                </a>
            @endforeach
        </div>
        <div class="mt-6">{{ $students->links('components.pagination') }}</div>
    @endif
</x-layout.admin>
