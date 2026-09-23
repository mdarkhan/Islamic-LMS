@props([
    'label',
    'isDone' => false,
    'editable' => false,
    'toggleUrl' => null,
])

{{-- One deed's row. Always a <button> — when not $editable it is simply `disabled`,
     which already makes it unclickable and unfocusable, so a past day's checklist is
     inert by construction rather than by a click handler that could be forgotten. --}}
<button type="button"
        data-done="{{ $isDone ? '1' : '0' }}"
        @if ($editable) data-url="{{ $toggleUrl }}" @else disabled @endif
        {{ $attributes->merge(['class' =>
            'amol-row flex w-full items-center gap-3 rounded-xl border px-4 py-3 text-start transition-colors '
            . ($editable ? 'cursor-pointer hover:border-brand/40 ' : 'cursor-default ')
            . ($isDone ? 'border-brand/40 bg-brand-tint/40' : 'border-line bg-surface')
        ]) }}>
    <span class="amol-check grid place-items-center w-6 h-6 rounded-md border-2 shrink-0 transition-colors {{ $isDone ? 'bg-brand border-brand text-brand-ink' : 'border-line text-transparent' }}">
        <x-ui.icon name="check" class="w-4 h-4" />
    </span>
    <span class="flex-1 text-sm font-medium text-ink">{{ $label }}</span>
</button>
