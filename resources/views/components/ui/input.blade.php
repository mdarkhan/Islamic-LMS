@props(['name' => null, 'error' => null])
@php
    $hasError = $name ? $errors->has($name) : (bool) $error;
    $ring = $hasError ? 'border-rose-400 focus:border-rose-500' : 'border-line focus:border-brand';
@endphp
<input
    @if ($name) name="{{ $name }}" id="{{ $attributes->get('id', $name) }}" @endif
    {{ $attributes->merge(['class' => "w-full rounded-xl bg-surface-raised text-ink placeholder-muted/70 border $ring px-4 py-2.5 text-sm outline-none transition-colors"]) }}
/>
