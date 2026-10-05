@props([
    'stats',   // AmolStatsService::forStudent() payload
])

@php
    $m = $stats['month'];
    $monthLabel = app()->getLocale() === 'en'
        ? $m->format('F Y')
        : app(\App\Services\Calendar\CalendarService::class)->gregorian($m)['month_bn'].' '.bn($m->format('Y'));
    $isCurrentMonth = $m->isSameMonth(now());
    $tile = fn (string $label, int $value, string $tone) => compact('label', 'value', 'tone');
@endphp

<x-ui.card class="mb-5">
    <h3 class="font-bold text-ink mb-4 flex items-center gap-2">
        <x-ui.icon name="trend-up" class="w-4 h-4 text-brand" /> {{ __('amol.stats_heading') }}
    </h3>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
        @foreach ([
            ['current_full', 'amol.streak_full', true],
            ['best_full', 'amol.streak_best_full', false],
            ['current_active', 'amol.streak_active', true],
            ['best_active', 'amol.streak_best_active', false],
        ] as [$key, $label, $flame])
            <div @class(['rounded-xl border px-3 py-3 text-center', 'border-brand/40 bg-brand-tint/40' => $flame && $stats[$key] > 0, 'border-line bg-surface' => ! ($flame && $stats[$key] > 0)])>
                <p class="text-2xl font-extrabold text-ink tabular-nums">{{ bn($stats[$key]) }}<span class="text-sm font-semibold text-muted"> {{ __('amol.days') }}</span></p>
                <p class="text-xs text-muted mt-0.5">{{ __($label) }}</p>
            </div>
        @endforeach
    </div>

    <div class="flex items-center justify-between mb-3">
        <a href="{{ route('student.amol.index', ['month' => $m->subMonth()->format('Y-m')]) }}" class="p-2 rounded-lg border border-line text-muted hover:text-ink hover:border-brand/40" aria-label="{{ __('amol.prev_month') }}">
            <x-ui.icon name="chevron" class="w-4 h-4 rotate-180" />
        </a>
        <div class="text-center">
            <p class="font-semibold text-ink">{{ $monthLabel }}</p>
            <p class="text-xs text-muted">{{ __('amol.month_summary', ['full' => bn($stats['month_full_days']), 'avg' => bn($stats['month_average'])]) }}</p>
        </div>
        @if ($isCurrentMonth)
            <span class="p-2 w-8"></span>
        @else
            <a href="{{ route('student.amol.index', ['month' => $m->addMonth()->format('Y-m')]) }}" class="p-2 rounded-lg border border-line text-muted hover:text-ink hover:border-brand/40" aria-label="{{ __('amol.next_month') }}">
                <x-ui.icon name="chevron" class="w-4 h-4" />
            </a>
        @endif
    </div>

    {{-- One bar per day; height = share of the checklist completed. Pure CSS. --}}
    <div class="flex items-end gap-[3px] h-32" role="img" aria-label="{{ __('amol.chart_label', ['month' => $monthLabel]) }}">
        @foreach ($stats['days'] as $day)
            @php $full = $stats['total'] > 0 && $day['done'] >= $stats['total']; @endphp
            <a href="{{ route('student.amol.index', ['date' => $day['date']->toDateString()]) }}"
               title="{{ bn($day['date']->format('d')) }} — {{ $day['future'] ? '' : bn($day['done']).'/'.bn($stats['total']) }}"
               class="flex-1 h-full flex items-end group/bar">
                <span @class([
                    'w-full rounded-t-sm transition-opacity group-hover/bar:opacity-80',
                    'bg-brand' => $full,
                    'bg-brand/50' => ! $full && ! $day['future'],
                    'bg-line' => $day['future'],
                    'ring-2 ring-brand/60' => $day['is_today'],
                ]) style="height: {{ $day['future'] ? 4 : max(4, $day['percent']) }}%"></span>
            </a>
        @endforeach
    </div>
    <div class="flex justify-between text-[10px] text-muted tabular-nums mt-1">
        <span>{{ bn(1) }}</span><span>{{ bn(count($stats['days'])) }}</span>
    </div>
    <p class="text-[11px] text-muted mt-2 flex items-center gap-3">
        <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-brand"></span>{{ __('amol.legend_full') }}</span>
        <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-brand/50"></span>{{ __('amol.legend_partial') }}</span>
    </p>
</x-ui.card>
