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
        : 'text-muted hover:text-ink hover:bg-ink/5';
@endphp

@if ($disabled)
    <span class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm text-muted/50 cursor-not-allowed select-none">
        <x-ui.icon :name="$icon" />
        <span class="truncate">{{ $label }}</span>
        <span class="ml-auto text-[10px] font-medium px-1.5 py-0.5 rounded bg-ink/5 text-muted">পরবর্তী ধাপ</span>
    </span>
@else
    <a href="{{ $href }}" @if ($active) aria-current="page" @endif
       class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-colors {{ $cls }}">
        <x-ui.icon :name="$icon" />
        <span class="truncate">{{ $label }}</span>
        @if ($badge)<span class="ml-auto text-xs font-semibold">{{ $badge }}</span>@endif
    </a>
@endif
