<x-layout.admin :title="__('amol.heading')" :heading="$student->name">
    <div class="flex items-center gap-3 mb-4">
        <x-ui.button :href="route('admin.amol.index')" variant="ghost" size="sm">
            <x-ui.icon name="chevron" class="w-4 h-4 rotate-180" /> {{ __('messages.back_to_inbox') }}
        </x-ui.button>
        @if ($student->roll)
            <span class="text-sm text-muted tabular-nums">{{ bn($student->roll) }}</span>
        @endif
    </div>

    <x-amol.date-nav :date="$date" :today="$today" route="admin.amol.show" :route-params="['student' => $student]" />

    @php $parts = \App\Models\Amol::partition($checklist); @endphp

    <x-ui.card>
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-ink">{{ bn($date->format('d/m/Y')) }}</h3>
            <span class="text-sm font-semibold text-muted tabular-nums">
                {{ __('amol.progress', ['done' => bn($checklist->where('is_done', true)->count()), 'total' => bn($checklist->count())]) }}
            </span>
        </div>

        {{-- Always read-only here: only the student's own screen may check an item off,
             and only for today (AmolService::toggle). The ustaz side never mutates it. --}}
        <div class="space-y-2 mb-2">
            @foreach ($parts['grouped'] as $group => $items)
                @continue($items->isEmpty())
                <x-amol.group-row :group="$group" :items="$items" :editable="false" />
            @endforeach
        </div>

        <div class="grid gap-2 sm:grid-cols-2">
            @foreach ($parts['ungrouped'] as $item)
                <x-amol.item-row :label="$item['amol']->label" :is-done="$item['is_done']" :editable="false" />
            @endforeach
        </div>
    </x-ui.card>

    <x-ui.card class="mt-5">
        <h3 class="font-bold text-ink mb-3">{{ $note ? __('amol.edit_note') : __('amol.note_heading') }}</h3>

        @if ($note && $note->note !== '')
            <p class="text-xs text-muted mb-3">
                {{ __('amol.note_by', ['name' => $note->commentedBy?->name ?? '—', 'at' => $note->commented_at?->format('d/m/Y H:i') ?? '']) }}
            </p>
        @endif

        <form method="POST" action="{{ route('admin.amol.note', $student) }}">
            @csrf
            <input type="hidden" name="date" value="{{ $date->toDateString() }}">
            <x-ui.textarea name="note" rows="3" placeholder="{{ __('amol.note_placeholder') }}">{{ old('note', $note?->note) }}</x-ui.textarea>
            @error('note')<p class="text-xs font-medium text-rose-600 mt-1">{{ $message }}</p>@enderror

            <div class="mt-3 flex justify-end">
                <x-ui.button type="submit">{{ __('amol.save_note') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layout.admin>
