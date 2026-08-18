@php
    $percentage = $attempt->total_marks_snapshot > 0
        ? round($attempt->final_score / $attempt->total_marks_snapshot * 100, 2) : 0.0;
@endphp

<x-layout.student :title="__('practice.result_heading')" :heading="__('practice.result_heading')">
    <div class="max-w-3xl mx-auto">
        <div class="flex items-center justify-between gap-3 mb-4 print:hidden">
            <x-ui.button :href="route('student.practice.index')" variant="ghost" size="sm">
                <x-ui.icon name="chevron" class="w-4 h-4 rotate-180" /> {{ __('practice.back_to_practice') }}
            </x-ui.button>
            <form method="POST" action="{{ route('student.practice.start', $quiz) }}">
                @csrf
                <x-ui.button type="submit" size="sm"><x-ui.icon name="practice" class="w-4 h-4" /> {{ __('practice.start_again') }}</x-ui.button>
            </form>
        </div>

        <x-ui.card class="mb-6 text-center">
            <div class="flex items-center justify-center gap-2">
                <x-ui.badge color="brand">{{ __('practice.badge') }}</x-ui.badge>
                <h2 class="text-lg font-bold text-ink">{{ $quiz->title }}</h2>
            </div>
            <p class="text-xs text-muted mt-4">{{ __('practice.your_score') }}</p>
            <p class="text-5xl font-black text-brand tabular-nums mt-1">{{ bn($attempt->final_score) }}<span class="text-2xl text-muted font-normal"> / {{ bn($attempt->total_marks_snapshot) }}</span></p>
            <p class="text-sm text-muted tabular-nums mt-1">{{ bn(number_format($percentage, 2)) }}%</p>
            <p class="text-xs text-muted mt-4 max-w-md mx-auto">{{ __('practice.is_practice_note') }}</p>
        </x-ui.card>

        <x-exams.answer-review :rows="$rows" />
    </div>
</x-layout.student>
