@props(['items' => []])
<nav aria-label="breadcrumb" {{ $attributes->merge(['class' => 'flex items-center gap-1.5 text-sm text-muted flex-wrap']) }}>
    @foreach ($items as $label => $href)
        @if (! $loop->last && $href)
            <a href="{{ $href }}" class="hover:text-brand transition-colors">{{ $label }}</a>
            <svg class="w-4 h-4 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
        @else
            <span class="text-ink font-medium">{{ $label }}</span>
        @endif
    @endforeach
</nav>
