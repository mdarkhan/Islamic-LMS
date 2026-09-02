@props([
    'events' => [],   // CalendarService::upcomingIslamicOccasions() output
])

@php
    $en = app()->getLocale() === 'en';
@endphp

@if (! empty($events))
    <div {{ $attributes->merge(['class' => 'rounded-2xl border border-line bg-card p-4 sm:p-5']) }}>
        <div class="flex items-center gap-2 text-xs text-muted mb-3">
            <x-ui.icon name="calendar" class="w-4 h-4" />
            <span>{{ __('public.upcoming_events_heading') }}</span>
        </div>
        <ul class="space-y-2.5">
            @foreach ($events as $event)
                <li class="flex items-center justify-between gap-3 text-sm">
                    <span class="text-ink font-medium">{{ $en ? $event['label_en'] : __('public.'.$event['key']) }}</span>
                    <span class="text-xs text-muted whitespace-nowrap">
                        {{ $en ? $event['date']->format('j M') : bn($event['date']->format('d')).' '.\App\Services\Calendar\CalendarService::GREGORIAN_MONTHS_BN[(int) $event['date']->format('n')] }}
                        · {{ __('public.days_remaining', ['count' => bn($event['days_until'])]) }}
                    </span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
