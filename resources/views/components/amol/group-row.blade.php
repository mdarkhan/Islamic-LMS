@props([
    'group',        // 'fajr' | 'zuhr' | 'asr' | 'maghrib' | 'isha'
    'items',        // Collection<array{amol:Amol, is_done:bool}> — this prayer's sub-items
    'editable' => false,
])

@php
    $done = $items->where('is_done', true)->count();
    $total = $items->count();
@endphp

{{-- A native <details> accordion — no JS needed to open/close. `group-open:` (Tailwind's
     built-in variant for an ancestor's [open] attribute) rotates the chevron. --}}
<details class="amol-group group rounded-xl border border-line bg-surface overflow-hidden" data-group="{{ $group }}">
    <summary class="flex items-center justify-between gap-3 px-4 py-3 cursor-pointer select-none list-none [&::-webkit-details-marker]:hidden hover:bg-ink/5 transition-colors">
        <span class="font-semibold text-ink flex items-center gap-2">
            <x-ui.icon name="chevron" class="w-4 h-4 text-muted transition-transform group-open:rotate-90" />
            {{ __('amol.group_'.$group) }}
        </span>
        <span class="amol-group-progress text-sm font-semibold text-muted tabular-nums">
            {{ __('amol.group_progress', ['done' => bn($done), 'total' => bn($total)]) }}
        </span>
    </summary>
    <div class="px-3 pb-3 pt-1 grid gap-2 sm:grid-cols-2 border-t border-line">
        @foreach ($items as $item)
            <x-amol.item-row
                :label="$item['amol']->label"
                :is-done="$item['is_done']"
                :editable="$editable"
                :toggle-url="$editable ? route('student.amol.toggle', $item['amol']) : null" />
        @endforeach
    </div>
</details>
