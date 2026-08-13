@props(['title' => null, 'icon' => 'inbox'])
@php $title ??= __('ui.nothing_found'); @endphp
<div {{ $attributes->merge(['class' => 'text-center py-16 px-6']) }}>
    <div class="mx-auto w-14 h-14 rounded-2xl bg-ink/5 grid place-items-center text-muted mb-4">
        <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v7m16 0-2.5 5A2 2 0 0 1 15.7 21H8.3a2 2 0 0 1-1.8-3L4 13m16 0h-4.6a1 1 0 0 0-.9.6 3 3 0 0 1-5 0 1 1 0 0 0-.9-.6H4"/></svg>
    </div>
    <p class="font-semibold text-ink">{{ $title }}</p>
    @if (trim($slot) !== '')<p class="text-sm text-muted mt-1 max-w-sm mx-auto">{{ $slot }}</p>@endif
</div>
