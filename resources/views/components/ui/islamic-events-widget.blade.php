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
        <div class="space-y-3.5">
            @foreach ($events as $event)
                {{-- A rough "how close is it" bar derived from days_until (max ~355 days in
                     a civil Islamic year) — a visual cue, not a precise claim. --}}
                @php $closeness = 100 - min(100, (int) round($event['days_until'] / 355 * 100)); @endphp
                <div>
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="text-ink font-medium">{{ $en ? $event['label_en'] : __('public.'.$event['key']) }}</span>
                        <span class="text-xs text-muted whitespace-nowrap">
                            {{ $en ? $event['date']->format('j M') : bn($event['date']->format('d')).' '.\App\Services\Calendar\CalendarService::GREGORIAN_MONTHS_BN[(int) $event['date']->format('n')] }}
                        </span>
                    </div>
                    <div class="mt-1.5 flex items-center gap-2">
                        <div class="flex-1 bg-brand-tint rounded-full h-1.5 overflow-hidden">
                            <div class="bg-brand h-1.5 rounded-full" style="width: {{ $closeness }}%"></div>
                        </div>
                        <span class="text-[11px] text-muted whitespace-nowrap">{{ __('public.days_remaining', ['count' => bn($event['days_until'])]) }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
