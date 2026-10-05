@props([
    'calendar' => [],   // CalendarService::all() output
])

@php
    $g = $calendar['gregorian'];
    $b = $calendar['bangla'];
    $h = $calendar['hijri'];
    $sunset = $calendar['sunset'];
    $en = app()->getLocale() === 'en';

    $gregorian = $en
        ? $g['carbon']->format('j F Y')
        : bn($g['day']).' '.$g['month_bn'].' '.bn($g['year']);
    $bangla = bn($b['day']).' '.$b['month_name'].' '.bn($b['year']);
    $hijri = bn($h['day']).' '.$h['month_name'].' '.bn($h['year']).' '.__('calendar.ah');
@endphp

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-line bg-card p-4 sm:p-5']) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="flex items-center gap-2 text-xs text-muted">
            <x-ui.icon name="calendar" class="w-4 h-4" />
            <span>{{ __('calendar.today') }}</span>
        </div>
        @if (filled($calendar['location'] ?? null))
            <x-ui.badge color="brand">{{ $calendar['location'] }}</x-ui.badge>
        @endif
    </div>

    {{-- Hijri prominent --}}
    <p class="mt-2 text-xl sm:text-2xl font-black text-brand leading-tight">{{ $hijri }}</p>

    {{-- Bangla + Gregorian secondary --}}
    <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-sm text-ink">
        <span>{{ $bangla }}</span>
        <span class="text-muted">·</span>
        <span class="text-muted">{{ $gregorian }}</span>
    </div>

    {{-- Sunset note subtle --}}
    <div class="mt-3 pt-3 border-t border-line flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-xs text-muted">
        <span class="flex items-center gap-1.5 text-ink font-medium">
            <x-ui.icon name="sun" class="w-4 h-4 text-amber-500" />
            {{ __('calendar.sunset', ['time' => $en ? $sunset->format('g:i A') : bn($sunset->format('g:i')).' '.$sunset->format('A')]) }}
        </span>
        <span>{{ __('calendar.sunset_note') }}</span>
    </div>
</div>
