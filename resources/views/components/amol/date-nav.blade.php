@props([
    'date',          // CarbonImmutable — the date currently shown
    'today',         // CarbonImmutable — the server's current Dhaka date
    'route',         // route name, e.g. 'student.amol.index' or 'admin.amol.show'
    'routeParams' => [],
])

@php
    $urlFor = fn (string $d) => route($route, array_merge($routeParams, ['date' => $d]));
    $isToday = $date->equalTo($today);
    $prevUrl = $urlFor($date->subDay()->toDateString());
    $nextUrl = $isToday ? null : $urlFor($date->addDay()->toDateString());
    $todayUrl = $urlFor($today->toDateString());
    // A template URL with a placeholder, swapped client-side when the date input changes
    // — avoids a form/JS round trip just to read one field back out.
    $template = $urlFor('__DATE__');
@endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div class="flex items-center gap-2">
        <a href="{{ $prevUrl }}" class="p-2 rounded-lg border border-line text-muted hover:text-ink hover:border-brand/40 transition-colors" aria-label="{{ __('amol.prev_day') }}">
            <x-ui.icon name="chevron" class="w-4 h-4 rotate-180" />
        </a>

        <input type="date" value="{{ $date->toDateString() }}" max="{{ $today->toDateString() }}"
               onchange="if(this.value) window.location.href = @js($template).replace('__DATE__', this.value)"
               aria-label="{{ __('amol.jump_to_date') }}"
               class="rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink outline-none focus:border-brand">

        @if ($nextUrl)
            <a href="{{ $nextUrl }}" class="p-2 rounded-lg border border-line text-muted hover:text-ink hover:border-brand/40 transition-colors" aria-label="{{ __('amol.next_day') }}">
                <x-ui.icon name="chevron" class="w-4 h-4" />
            </a>
        @else
            <span class="p-2 rounded-lg border border-line text-muted/30 cursor-not-allowed" aria-hidden="true">
                <x-ui.icon name="chevron" class="w-4 h-4" />
            </span>
        @endif
    </div>

    @unless ($isToday)
        <a href="{{ $todayUrl }}" class="text-sm font-semibold text-brand hover:text-brand-strong">{{ __('amol.back_to_today') }}</a>
    @endunless
</div>
