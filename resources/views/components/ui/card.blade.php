@props(['padded' => true])
<div {{ $attributes->merge(['class' => 'bg-card border border-line rounded-[--radius-card] shadow-[--shadow-soft] ' . ($padded ? 'p-5 sm:p-6' : '')]) }}>
    {{ $slot }}
</div>
