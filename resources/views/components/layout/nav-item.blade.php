@props(['item'])

@php
    $label = $item['label'];
    $href = $item['href'] ?? '#';
    $icon = $item['icon'] ?? 'dot';
    $active = $item['active'] ?? false;
    $disabled = $item['disabled'] ?? false;
    $badge = $item['badge'] ?? null;

    $cls = $active
        ? 'bg-brand-tint text-brand-strong font-semibold'
        : 'text-muted hover:text-white hover:bg-brand';
@endphp

@if ($disabled)
    <span class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm text-muted/50 cursor-not-allowed select-none">
        <x-ui.icon :name="$icon" />
        <span class="truncate">{{ $label }}</span>
        <span class="ml-auto text-[10px] font-medium px-1.5 py-0.5 rounded bg-ink/5 text-muted">{{ __('nav.later_phase') }}</span>
    </span>
@else
    <a href="{{ $href }}" @if ($active) aria-current="page" @endif
       class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-colors {{ $cls }}">
        <x-ui.icon :name="$icon" />
        <span class="truncate">{{ $label }}</span>
        @if ($badge)
            {{-- Unread count. Numeric badges render in the UI locale's digits. --}}
            <span class="ml-auto shrink-0 min-w-5 px-1.5 py-0.5 rounded-full bg-brand text-brand-ink text-[11px] font-bold text-center tabular-nums">{{ is_numeric($badge) ? bn($badge) : $badge }}</span>
        @endif
    </a>
@endif
