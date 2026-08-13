@props(['color' => 'neutral'])
@php
    $map = [
        'neutral' => 'bg-ink/8 text-ink',
        'brand'   => 'bg-brand-tint text-brand-strong',
        'success' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
        'warning' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
        'danger'  => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
        'info'    => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
    ];
    $cls = $map[$color] ?? $map['neutral'];
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold $cls"]) }}>{{ $slot }}</span>
