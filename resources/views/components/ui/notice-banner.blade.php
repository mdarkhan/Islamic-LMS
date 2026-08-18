@props([
    'notices' => [],   // collection of active Notice models
])

@if (count($notices) > 0)
    {{-- Accessible, static banner (no auto-scrolling marquee). --}}
    <div class="space-y-2" role="region" aria-label="{{ __('notices.heading') }}">
        @foreach ($notices as $notice)
            <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0zM12 9v4m0 4h.01" />
                </svg>
                <p class="leading-relaxed">{{ $notice->body }}</p>
            </div>
        @endforeach
    </div>
@endif
