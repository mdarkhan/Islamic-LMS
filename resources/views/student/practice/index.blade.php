@php
    $fmtDate = fn ($d) => $d?->format('d/m/Y H:i');
@endphp

<x-layout.student :title="__('practice.heading')" :heading="__('practice.heading')">

    @if ($byCourse->isEmpty())
        <x-ui.card><x-ui.empty :title="__('practice.library_none')">{{ __('practice.library_none_hint') }}</x-ui.empty></x-ui.card>
    @endif

    @foreach ($byCourse as $courseTitle => $quizzes)
        <section class="mb-8">
            <h2 class="text-sm font-bold uppercase tracking-wider text-muted mb-3">{{ $courseTitle }}</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($quizzes as $quiz)
                    @php $stat = $stats->get($quiz->id); @endphp
                    <x-ui.card class="flex flex-col">
                        <div class="flex items-start justify-between gap-2 mb-1">
                            <h3 class="font-bold text-ink">{{ $quiz->title }}</h3>
                            <x-ui.badge color="brand">{{ __('practice.badge') }}</x-ui.badge>
                        </div>

                        <dl class="mt-2 space-y-1.5 text-sm text-muted flex-1">
                            <div class="flex items-center gap-4">
                                <span class="flex items-center gap-1.5"><x-ui.icon name="exam" class="w-4 h-4" /> {{ __('practice.total_questions') }}: {{ bn($quiz->active_questions_count) }}</span>
                                <span class="flex items-center gap-1.5"><x-ui.icon name="results" class="w-4 h-4" /> {{ __('practice.total_marks') }}: {{ bn($quiz->total_marks) }}</span>
                            </div>
                            @if ($stat)
                                <div class="flex items-center gap-4">
                                    <span>{{ __('practice.attempts_taken') }}: {{ bn($stat->attempts) }}</span>
                                    @if ($stat->best !== null)<span>{{ __('practice.best_score') }}: {{ bn((int) $stat->best) }}</span>@endif
                                </div>
                            @endif
                        </dl>

                        <form method="POST" action="{{ route('student.practice.start', $quiz) }}" class="mt-4">
                            @csrf
                            <x-ui.button type="submit" size="sm" class="w-full">
                                <x-ui.icon name="practice" class="w-4 h-4" /> {{ $stat ? __('practice.start_again') : __('practice.start') }}
                            </x-ui.button>
                        </form>
                        <p class="text-xs text-muted text-center mt-1.5">{{ __('practice.untimed_note') }}</p>
                    </x-ui.card>
                @endforeach
            </div>
        </section>
    @endforeach

    {{-- Practice history (kept visually distinct from official Results) --}}
    @if ($history->isNotEmpty())
        <section class="mb-8">
            <h2 class="text-sm font-bold uppercase tracking-wider text-muted mb-3">{{ __('practice.history_heading') }}</h2>
            <div class="overflow-x-auto rounded-2xl border border-line">
                <table class="w-full text-sm">
                    <thead class="bg-surface-raised text-muted">
                        <tr>
                            <th scope="col" class="px-4 py-2.5 text-start font-semibold">{{ __('results.col_quiz') }}</th>
                            <th scope="col" class="px-4 py-2.5 text-start font-semibold">{{ __('results.col_date') }}</th>
                            <th scope="col" class="px-4 py-2.5 text-end font-semibold">{{ __('results.col_score') }}</th>
                            <th scope="col" class="px-4 py-2.5 print:hidden"><span class="sr-only">{{ __('practice.review') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($history as $attempt)
                            <tr class="hover:bg-surface-raised/50">
                                <td class="px-4 py-3 font-semibold text-ink">{{ $attempt->quiz->title }}</td>
                                <td class="px-4 py-3 text-muted tabular-nums whitespace-nowrap">{{ $fmtDate($attempt->submitted_at) }}</td>
                                <td class="px-4 py-3 text-end font-semibold text-ink tabular-nums">{{ bn($attempt->final_score) }} / {{ bn($attempt->total_marks_snapshot) }}</td>
                                <td class="px-4 py-3 text-end print:hidden">
                                    <x-ui.button :href="route('student.practice.result', $attempt)" variant="secondary" size="sm">{{ __('practice.review') }}</x-ui.button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</x-layout.student>
