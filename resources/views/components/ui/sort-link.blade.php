@props(['sort', 'current' => 'roll', 'dir' => 'asc', 'label'])

@php
    $active = $current === $sort;
    // Clicking the active column flips direction; a new column starts ascending.
    $next = ($active && $dir === 'asc') ? 'desc' : 'asc';
    // Preserve search/status filters; reset pagination.
    $params = array_merge(request()->except('page'), ['sort' => $sort, 'dir' => $next]);
@endphp

<a href="?{{ http_build_query($params) }}"
   {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 hover:text-ink transition-colors '.($active ? 'text-ink font-semibold' : '')]) }}>
    {{ $label }}
    @if ($active)
        <span class="text-brand text-[10px] leading-none">{{ $dir === 'asc' ? '▲' : '▼' }}</span>
    @else
        <span class="text-muted/30 text-[10px] leading-none">↕</span>
    @endif
</a>
