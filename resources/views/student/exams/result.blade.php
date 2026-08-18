@php
    $statusLabels = [
        \App\Models\QuizAttempt::STATUS_SUBMITTED => [__('exams.result_status_submitted'), 'success'],
        \App\Models\QuizAttempt::STATUS_EXPIRED => [__('exams.result_status_expired'), 'warning'],
        \App\Models\QuizAttempt::STATUS_VOIDED => [__('exams.result_status_voided'), 'danger'],
    ];
    [$statusLabel, $statusTone] = $statusLabels[$attempt->status] ?? [$attempt->status, 'neutral'];
@endphp

<x-layout.student :title="__('exams.result_heading')" :heading="__('exams.result_heading')">
    <div class="max-w-2xl mx-auto">
        <x-ui.card class="text-center">
            <h2 class="font-bold text-ink text-lg">{{ $quiz->title }}</h2>
            @if ($quiz->course)
                <p class="text-sm text-muted mt-0.5">{{ $quiz->course->title }}</p>
            @endif

            <div class="mt-4 flex justify-center">
                <x-ui.badge :color="$statusTone">{{ $statusLabel }}</x-ui.badge>
            </div>

            @if ($released)
                {{-- Results are released: show the authoritative server-computed score. --}}
                <div class="mt-6">
                    <p class="text-5xl font-black text-brand tabular-nums mt-1">
                        {{ __('exams.result_score', [
                            'score' => bn($attempt->final_score),
                            'total' => bn($attempt->total_marks_snapshot),
                        ]) }}
                    </p>
                </div>
            @else
                {{-- Not released yet: never leak the score. --}}
                <div class="mt-6 rounded-xl bg-surface-raised border border-line px-4 py-6">
                    <p class="font-semibold text-ink">{{ __('exams.result_pending_title') }}</p>
                    <p class="text-sm text-muted mt-1">{{ __('exams.result_pending_body') }}</p>
                </div>
            @endif

            @if ($attempt->status === \App\Models\QuizAttempt::STATUS_EXPIRED)
                <p class="text-xs text-amber-600 mt-4">{{ __('exams.result_expired_note') }}</p>
            @endif

            <dl class="mt-6 pt-4 border-t border-line grid grid-cols-2 gap-4 text-sm text-start">
                @if ($attempt->submitted_at)
                    <div>
                        <dt class="text-muted">{{ __('exams.result_submitted_at') }}</dt>
                        <dd class="font-semibold text-ink tabular-nums">{{ $attempt->submitted_at->format('d/m/Y H:i') }}</dd>
                    </div>
                @endif
                @if ($attempt->time_taken_seconds !== null)
                    <div>
                        <dt class="text-muted">{{ __('exams.result_time_taken') }}</dt>
                        <dd class="font-semibold text-ink tabular-nums">{{ __('exams.minutes', ['count' => bn(intdiv($attempt->time_taken_seconds, 60))]) }}</dd>
                    </div>
                @endif
            </dl>

            <p class="text-xs text-muted mt-6">{{ __('exams.result_detailed_unavailable') }}</p>

            <div class="mt-6 flex items-center justify-center gap-3">
                <x-ui.button :href="route('student.exams.index')" variant="secondary">
                    {{ __('exams.result_back') }}
                </x-ui.button>
                @if ($released && $attempt->answer_details_available)
                    <x-ui.button :href="route('student.results.show', $attempt)">
                        {{ __('results.view_answer_sheet') }}
                    </x-ui.button>
                @endif
            </div>
        </x-ui.card>
    </div>
</x-layout.student>
