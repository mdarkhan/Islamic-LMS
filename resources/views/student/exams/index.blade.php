@php
    $sections = [
        'open' => [__('exams.group_open'), 'success'],
        'upcoming' => [__('exams.group_upcoming'), 'info'],
        'completed' => [__('exams.group_completed'), 'neutral'],
        'previous' => [__('exams.group_previous'), 'warning'],
    ];
@endphp
<x-layout.student :title="__('exams.heading')" :heading="__('exams.heading')">
    <div class="flex items-center justify-between mb-6">
        <p class="text-sm text-muted">{{ __('exams.your_points') }}</p>
        <span class="text-2xl font-black text-brand tabular-nums">{{ bn($user->points_balance) }}</span>
    </div>

    @php $anything = collect($groups)->flatten(1)->isNotEmpty(); @endphp
    @unless ($anything)
        <x-ui.card><x-ui.empty :title="__('exams.none_yet')">{{ __('exams.none_yet_hint') }}</x-ui.empty></x-ui.card>
    @endunless

    @foreach ($sections as $key => [$title, $tone])
        @if (! empty($groups[$key]))
            <section class="mb-8">
                <h2 class="text-sm font-bold uppercase tracking-wider text-muted mb-3">{{ $title }}</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($groups[$key] as $card)
                        @php $quiz = $card['quiz']; @endphp
                        <x-ui.card class="flex flex-col">
                            <div class="flex items-start justify-between gap-2 mb-2">
                                <x-ui.badge :color="$tone">{{ $title }}</x-ui.badge>
                                @if ($card['practice_available'])<x-ui.badge color="brand">{{ __('exams.practice_available') }}</x-ui.badge>@endif
                            </div>
                            <h3 class="font-bold text-ink">{{ $quiz->title }}</h3>
                            @if ($quiz->course)<p class="text-xs text-muted mt-0.5">{{ $quiz->course->title }}</p>@endif

                            <dl class="mt-3 space-y-1.5 text-sm text-muted">
                                @if ($quiz->starts_at)
                                    <div class="flex items-center gap-2"><x-ui.icon name="calendar" class="w-4 h-4" /> {{ $quiz->starts_at->format('d/m/Y H:i') }}</div>
                                @endif
                                <div class="flex items-center gap-4">
                                    @if ($quiz->duration_seconds)<span class="flex items-center gap-1.5"><x-ui.icon name="clock" class="w-4 h-4" /> {{ __('exams.minutes', ['count' => bn(intdiv($quiz->duration_seconds, 60))]) }}</span>@endif
                                    <span class="flex items-center gap-1.5"><x-ui.icon name="results" class="w-4 h-4" /> {{ __('exams.marks', ['count' => bn($quiz->total_marks)]) }}</span>
                                </div>
                                <div class="flex items-center gap-1.5"><x-ui.icon name="points" class="w-4 h-4" /> {{ __('exams.points', ['count' => bn($quiz->point_cost)]) }}</div>
                            </dl>

                            <div class="mt-4 pt-3 border-t border-line text-sm">
                                @if ($card['completed'])
                                    @if ($card['results_pending'])
                                        <span class="text-muted">{{ __('exams.results_pending') }}</span>
                                    @else
                                        <span class="font-semibold text-brand">{{ __('exams.obtained', ['score' => bn($card['score']), 'total' => bn($quiz->total_marks)]) }}</span>
                                    @endif
                                @elseif ($card['resume'])
                                    <span class="font-semibold text-brand">{{ __('exams.ready_to_resume') }}</span>
                                @elseif ($key === 'open')
                                    @if ($card['enough_points'])
                                        <span class="text-muted">{{ __('exams.ready_to_start') }} <span class="text-xs">({{ __('exams.coming_soon') }})</span></span>
                                    @else
                                        <span class="text-rose-600">{{ __('exams.insufficient_points') }}</span>
                                    @endif
                                @elseif ($key === 'upcoming')
                                    <span class="text-muted">{{ __('exams.awaiting_start') }}</span>
                                @else
                                    <span class="text-muted">{{ $card['state'] === 'archived' ? __('exams.archived') : __('exams.finished') }}</span>
                                @endif
                            </div>
                        </x-ui.card>
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach
</x-layout.student>
