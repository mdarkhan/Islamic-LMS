@php
    $fmtTime = fn ($seconds) => $seconds === null ? '—' : __('exams.minutes', ['count' => bn(intdiv((int) $seconds, 60))]);
    $statusLabel = fn ($status) => $status === \App\Models\QuizAttempt::STATUS_EXPIRED
        ? __('results.status_expired') : __('results.status_submitted');
    $statusTone = fn ($status) => $status === \App\Models\QuizAttempt::STATUS_EXPIRED ? 'warning' : 'success';
    $anything = collect($groups)->flatten(1)->isNotEmpty();
@endphp

<x-layout.student :title="__('results.heading')" :heading="__('results.heading')">

    {{-- Overall standing strip --}}
    @if ($overall)
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
            <x-ui.card class="text-center">
                <p class="text-xs text-muted">{{ __('results.overall_rank') }}</p>
                <p class="text-2xl font-black text-brand tabular-nums mt-1">{{ bn($overall['rank']) }}</p>
            </x-ui.card>
            <x-ui.card class="text-center">
                <p class="text-xs text-muted">{{ __('results.exams_counted') }}</p>
                <p class="text-2xl font-black text-ink tabular-nums mt-1">{{ bn($overall['exams_counted']) }}</p>
            </x-ui.card>
            <x-ui.card class="text-center">
                <p class="text-xs text-muted">{{ __('results.total_obtained') }}</p>
                <p class="text-2xl font-black text-ink tabular-nums mt-1">{{ bn($overall['obtained']) }}<span class="text-sm text-muted font-normal"> / {{ bn($overall['possible']) }}</span></p>
            </x-ui.card>
            <x-ui.card class="text-center">
                <p class="text-xs text-muted">{{ __('results.overall_percentage') }}</p>
                <p class="text-2xl font-black text-ink tabular-nums mt-1">{{ bn(number_format($overall['percentage'], 2)) }}%</p>
            </x-ui.card>
        </div>
    @endif

    @unless ($anything)
        <x-ui.card><x-ui.empty :title="__('results.none_yet')">{{ __('results.none_hint') }}</x-ui.empty></x-ui.card>
    @endunless

    {{-- Released --}}
    @if (! empty($groups['released']))
        <section class="mb-8">
            <h2 class="text-sm font-bold uppercase tracking-wider text-muted mb-3">{{ __('results.group_released') }}</h2>

            {{-- Mobile: the table's 8 columns don't fit below sm without hiding the
                 score/percentage/rank behind a scroll a student might never discover,
                 so it becomes a stacked card list instead — same data, no scrolling. --}}
            <div class="sm:hidden space-y-3">
                @foreach ($groups['released'] as $row)
                    <x-ui.card>
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold text-ink">{{ $row['quiz']->title }}</p>
                                @if ($row['quiz']->course)<p class="text-xs text-muted">{{ $row['quiz']->course->title }}</p>@endif
                            </div>
                            <x-ui.badge :color="$statusTone($row['attempt']->status)" class="shrink-0">{{ $statusLabel($row['attempt']->status) }}</x-ui.badge>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-y-3 text-sm">
                            <div>
                                <p class="text-xs text-muted">{{ __('results.col_score') }}</p>
                                <p class="font-semibold text-ink tabular-nums">{{ bn($row['attempt']->final_score) }} / {{ bn($row['attempt']->total_marks_snapshot) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted">{{ __('results.col_percentage') }}</p>
                                <p class="tabular-nums">{{ bn(number_format($row['percentage'], 2)) }}%</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted">{{ __('results.col_time') }}</p>
                                <p class="tabular-nums">{{ $fmtTime($row['attempt']->time_taken_seconds) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted">{{ __('results.col_rank') }}</p>
                                <p class="tabular-nums">{{ $row['rank'] ? bn($row['rank']) : '—' }}</p>
                            </div>
                        </div>
                        <p class="text-xs text-muted mt-3 tabular-nums">{{ $row['attempt']->submitted_at?->format('d/m/Y H:i') }}</p>
                        @if ($row['reviewable'])
                            <x-ui.button :href="route('student.results.show', $row['attempt'])" variant="secondary" size="sm" class="w-full mt-3">{{ __('results.view_answer_sheet') }}</x-ui.button>
                        @else
                            <p class="text-xs text-muted mt-3">{{ __('results.legacy_no_answers') }}</p>
                        @endif
                    </x-ui.card>
                @endforeach
            </div>

            <div class="hidden sm:block overflow-x-auto rounded-2xl border border-line">
                <table class="w-full text-sm">
                    <thead class="bg-surface-raised text-muted">
                        <tr class="text-start">
                            <th scope="col" class="px-4 py-2.5 text-start font-semibold">{{ __('results.col_quiz') }}</th>
                            <th scope="col" class="px-4 py-2.5 text-start font-semibold">{{ __('results.col_date') }}</th>
                            <th scope="col" class="px-4 py-2.5 text-start font-semibold">{{ __('results.col_status') }}</th>
                            <th scope="col" class="px-4 py-2.5 text-end font-semibold">{{ __('results.col_score') }}</th>
                            <th scope="col" class="px-4 py-2.5 text-end font-semibold">{{ __('results.col_percentage') }}</th>
                            <th scope="col" class="px-4 py-2.5 text-end font-semibold">{{ __('results.col_time') }}</th>
                            <th scope="col" class="px-4 py-2.5 text-end font-semibold">{{ __('results.col_rank') }}</th>
                            <th scope="col" class="px-4 py-2.5 print:hidden"><span class="sr-only">{{ __('results.view_answer_sheet') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($groups['released'] as $row)
                            <tr class="hover:bg-surface-raised/50">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-ink">{{ $row['quiz']->title }}</div>
                                    @if ($row['quiz']->course)<div class="text-xs text-muted">{{ $row['quiz']->course->title }}</div>@endif
                                </td>
                                <td class="px-4 py-3 text-muted tabular-nums whitespace-nowrap">{{ $row['attempt']->submitted_at?->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3"><x-ui.badge :color="$statusTone($row['attempt']->status)">{{ $statusLabel($row['attempt']->status) }}</x-ui.badge></td>
                                <td class="px-4 py-3 text-end font-semibold text-ink tabular-nums whitespace-nowrap">{{ bn($row['attempt']->final_score) }} / {{ bn($row['attempt']->total_marks_snapshot) }}</td>
                                <td class="px-4 py-3 text-end tabular-nums">{{ bn(number_format($row['percentage'], 2)) }}%</td>
                                <td class="px-4 py-3 text-end text-muted tabular-nums whitespace-nowrap">{{ $fmtTime($row['attempt']->time_taken_seconds) }}</td>
                                <td class="px-4 py-3 text-end tabular-nums">{{ $row['rank'] ? bn($row['rank']) : '—' }}</td>
                                <td class="px-4 py-3 text-end print:hidden">
                                    @if ($row['reviewable'])
                                        <x-ui.button :href="route('student.results.show', $row['attempt'])" variant="secondary" size="sm">{{ __('results.view_answer_sheet') }}</x-ui.button>
                                    @else
                                        <span class="text-xs text-muted">{{ __('results.legacy_no_answers') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    {{-- Expired, awaiting release --}}
    @foreach (['expired' => __('results.group_expired'), 'pending' => __('results.group_pending')] as $key => $title)
        @if (! empty($groups[$key]))
            <section class="mb-8">
                <h2 class="text-sm font-bold uppercase tracking-wider text-muted mb-3">{{ $title }}</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($groups[$key] as $row)
                        <x-ui.card>
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <div class="font-semibold text-ink">{{ $row['quiz']->title }}</div>
                                    @if ($row['quiz']->course)<div class="text-xs text-muted">{{ $row['quiz']->course->title }}</div>@endif
                                </div>
                                <x-ui.badge :color="$statusTone($row['attempt']->status)">{{ $statusLabel($row['attempt']->status) }}</x-ui.badge>
                            </div>
                            <p class="text-xs text-muted mt-2 tabular-nums">{{ $row['attempt']->submitted_at?->format('d/m/Y H:i') }}</p>
                            <p class="text-sm text-muted mt-2">{{ __('results.pending_note') }}</p>
                        </x-ui.card>
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach

    {{-- Legacy records --}}
    @if (! empty($groups['legacy']))
        <section class="mb-8">
            <h2 class="text-sm font-bold uppercase tracking-wider text-muted mb-3">{{ __('results.group_legacy') }}</h2>

            <div class="sm:hidden space-y-3">
                @foreach ($groups['legacy'] as $row)
                    <x-ui.card>
                        <p class="font-semibold text-ink">{{ $row['quiz']->title }}</p>
                        <div class="mt-3 grid grid-cols-2 gap-y-3 text-sm">
                            <div>
                                <p class="text-xs text-muted">{{ __('results.col_score') }}</p>
                                <p class="font-semibold text-ink tabular-nums">{{ bn($row['attempt']->final_score) }} / {{ bn($row['attempt']->total_marks_snapshot) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted">{{ __('results.col_percentage') }}</p>
                                <p class="tabular-nums">{{ $row['percentage'] !== null ? bn(number_format($row['percentage'], 2)).'%' : '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted">{{ __('results.col_time') }}</p>
                                <p class="tabular-nums">{{ $fmtTime($row['attempt']->time_taken_seconds) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted">{{ __('results.col_date') }}</p>
                                <p class="tabular-nums">{{ $row['attempt']->submitted_at?->format('d/m/Y') }}</p>
                            </div>
                        </div>
                    </x-ui.card>
                @endforeach
            </div>

            <div class="hidden sm:block overflow-x-auto rounded-2xl border border-line">
                <table class="w-full text-sm">
                    <thead class="bg-surface-raised text-muted">
                        <tr>
                            <th scope="col" class="px-4 py-2.5 text-start font-semibold">{{ __('results.col_quiz') }}</th>
                            <th scope="col" class="px-4 py-2.5 text-start font-semibold">{{ __('results.col_date') }}</th>
                            <th scope="col" class="px-4 py-2.5 text-end font-semibold">{{ __('results.col_score') }}</th>
                            <th scope="col" class="px-4 py-2.5 text-end font-semibold">{{ __('results.col_percentage') }}</th>
                            <th scope="col" class="px-4 py-2.5 text-end font-semibold">{{ __('results.col_time') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($groups['legacy'] as $row)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-ink">{{ $row['quiz']->title }}</td>
                                <td class="px-4 py-3 text-muted tabular-nums whitespace-nowrap">{{ $row['attempt']->submitted_at?->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-end font-semibold text-ink tabular-nums">{{ bn($row['attempt']->final_score) }} / {{ bn($row['attempt']->total_marks_snapshot) }}</td>
                                <td class="px-4 py-3 text-end tabular-nums">{{ $row['percentage'] !== null ? bn(number_format($row['percentage'], 2)).'%' : '—' }}</td>
                                <td class="px-4 py-3 text-end text-muted tabular-nums">{{ $fmtTime($row['attempt']->time_taken_seconds) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-muted mt-2">{{ __('results.legacy_no_answers') }}</p>
        </section>
    @endif
</x-layout.student>
