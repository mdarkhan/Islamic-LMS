@php
    $statusTone = $attempt->status === \App\Models\QuizAttempt::STATUS_EXPIRED ? 'warning' : 'success';
    $statusLabel = $attempt->status === \App\Models\QuizAttempt::STATUS_EXPIRED
        ? __('results.status_expired') : __('results.status_submitted');
@endphp

<x-layout.student :title="__('results.sheet_heading')" :heading="__('results.sheet_heading')">
    <div class="max-w-3xl mx-auto">
        <div class="flex items-center justify-between gap-3 mb-4 print:hidden">
            <x-ui.button :href="route('student.results.index')" variant="ghost" size="sm">
                <x-ui.icon name="chevron" class="w-4 h-4 rotate-180" /> {{ __('results.back_to_results') }}
            </x-ui.button>
            <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 text-sm text-muted hover:text-ink">
                <x-ui.icon name="download" class="w-4 h-4" /> {{ __('results.print') }}
            </button>
        </div>

        {{-- Summary --}}
        <x-ui.card class="mb-6">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h2 class="text-lg font-bold text-ink">{{ $quiz->title }}</h2>
                    @if ($quiz->course)<p class="text-sm text-muted">{{ $quiz->course->title }}</p>@endif
                    <div class="mt-2 flex items-center gap-2">
                        <x-ui.badge :color="$statusTone">{{ $statusLabel }}</x-ui.badge>
                        @if ($rank)<x-ui.badge color="brand">{{ __('results.col_rank') }}: {{ bn($rank) }}</x-ui.badge>@endif
                    </div>
                </div>
                <div class="text-end">
                    <p class="text-xs text-muted">{{ __('results.score_summary') }}</p>
                    <p class="text-3xl font-black text-brand tabular-nums">{{ bn($attempt->final_score) }}<span class="text-lg text-muted font-normal"> / {{ bn($attempt->total_marks_snapshot) }}</span></p>
                    <p class="text-sm text-muted tabular-nums">{{ bn(number_format($percentage, 2)) }}%</p>
                </div>
            </div>
            @if ($attempt->status === \App\Models\QuizAttempt::STATUS_EXPIRED)
                <p class="text-xs text-amber-600 mt-3">{{ __('results.expired_note') }}</p>
            @endif
        </x-ui.card>

        @if ($legacy)
            <x-ui.alert type="info">{{ __('results.legacy_no_answers') }}</x-ui.alert>
        @else
            <x-exams.answer-review :rows="$rows" :regraded="$regraded" />
        @endif
    </div>
</x-layout.student>
