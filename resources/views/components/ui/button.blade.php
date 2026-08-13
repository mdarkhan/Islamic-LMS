@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
])

@php
    $base = 'inline-flex items-center justify-center gap-2 font-semibold rounded-xl transition-colors '
        . 'focus-visible:outline-none disabled:opacity-50 disabled:pointer-events-none whitespace-nowrap select-none';

    $variants = [
        'primary'   => 'bg-brand text-brand-ink hover:bg-brand-strong shadow-sm',
        'secondary' => 'bg-card text-ink border border-line hover:border-brand/50 hover:text-brand',
        'ghost'     => 'text-muted hover:text-ink hover:bg-ink/5',
        'danger'    => 'bg-rose-600 text-white hover:bg-rose-700 shadow-sm',
        'subtle'    => 'bg-brand-tint text-brand-strong hover:bg-brand/15',
    ];

    $sizes = [
        'sm' => 'text-sm px-3 py-1.5',
        'md' => 'text-sm px-4 py-2.5',
        'lg' => 'text-base px-6 py-3',
    ];

    $classes = trim($base . ' ' . ($variants[$variant] ?? $variants['primary']) . ' ' . ($sizes[$size] ?? $sizes['md']));
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
