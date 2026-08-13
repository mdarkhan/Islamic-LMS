@php
    $banglaIndices = ['ক', 'খ', 'গ', 'ঘ', 'ঙ', 'চ', 'ছ', 'জ', 'ঝ', 'ঞ', 'ট', 'ঠ'];
@endphp
<x-layout.admin :title="$quiz->title.' — '.__('quizzes.preview')" :heading="__('quizzes.preview_heading')">
    <x-ui.breadcrumbs :items="[__('nav.quizzes') => route('admin.quizzes.index'), $quiz->title => route('admin.quizzes.edit', $quiz), __('quizzes.preview') => null]" class="mb-5" />

    <x-ui.alert type="info" :title="__('quizzes.preview_banner_title')" class="mb-6">
        {{ __('quizzes.preview_banner_body') }}
    </x-ui.alert>

    <div class="max-w-3xl mx-auto">
        <x-ui.card class="mb-6 text-center">
            <p class="font-arabic text-xl text-brand mb-2">بِسْمِ ٱللَّٰهِ ٱلرَّحْمَٰنِ ٱلرَّحِيمِ</p>
            <h1 class="text-2xl font-black text-ink">{{ $quiz->title }}</h1>
            <div class="mt-3 flex flex-wrap items-center justify-center gap-2 text-sm">
                <x-ui.badge color="brand">{{ bn($quiz->questions->count()) }} {{ __('quizzes.questions_suffix') }}</x-ui.badge>
                <x-ui.badge color="neutral">{{ __('quizzes.total_suffix', ['count' => bn($quiz->total_marks)]) }}</x-ui.badge>
                @if ($quiz->duration_seconds)<x-ui.badge color="neutral">{{ bn(intdiv($quiz->duration_seconds, 60)) }} {{ __('quizzes.minutes_suffix') }}</x-ui.badge>@endif
                <x-ui.badge color="warning">{{ bn($quiz->point_cost) }} {{ __('quizzes.points_suffix') }}</x-ui.badge>
            </div>
        </x-ui.card>

        <div class="space-y-6">
            @foreach ($quiz->questions as $i => $question)
                <x-ui.card>
                    <div class="flex items-start gap-3">
                        <span class="shrink-0 w-8 h-8 rounded-full bg-brand text-brand-ink grid place-items-center font-bold text-sm">{{ bn($i + 1) }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="font-bold text-ink">{{ $question->body }}</h3>
                                <div class="flex gap-1.5 shrink-0">
                                    <x-ui.badge :color="$question->type === 'multiple' ? 'info' : 'neutral'">{{ $question->type === 'multiple' ? __('quizzes.type_multiple') : __('quizzes.type_single') }}</x-ui.badge>
                                    <x-ui.badge color="brand">{{ bn($question->marks) }} {{ __('quizzes.marks_suffix') }}</x-ui.badge>
                                </div>
                            </div>
                            <div class="mt-3 space-y-2">
                                @foreach ($question->options as $oi => $option)
                                    <div class="flex items-center gap-3 p-3 rounded-xl border {{ $option->is_correct ? 'border-brand bg-brand-tint' : 'border-line' }}">
                                        <span class="shrink-0 w-8 h-8 rounded-lg grid place-items-center text-sm font-bold {{ $option->is_correct ? 'bg-brand text-brand-ink' : 'bg-ink/5 text-muted' }}">{{ $banglaIndices[$oi] ?? ($oi + 1) }}</span>
                                        <span class="text-sm {{ $option->is_correct ? 'text-brand-strong font-medium' : 'text-ink' }}">{{ $option->body }}</span>
                                        @if ($option->is_correct)<x-ui.icon name="check" class="w-4 h-4 text-brand ml-auto" />@endif
                                    </div>
                                @endforeach
                            </div>
                            @if ($question->explanation)
                                <div class="mt-3 text-sm text-muted bg-ink/[0.03] rounded-lg p-3">
                                    <span class="font-semibold text-ink">{{ __('quizzes.explanation_label') }}</span> {{ $question->explanation }}
                                </div>
                            @endif
                        </div>
                    </div>
                </x-ui.card>
            @endforeach
        </div>
    </div>
</x-layout.admin>
