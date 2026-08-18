@props([
    'rows' => [],       // AttemptReviewPresenter::questions() output
    'regraded' => false, // show the "answer key later corrected" note
])

@php
    $stateMeta = [
        'correct' => ['tone' => 'success', 'icon' => 'check', 'label' => __('results.correct')],
        'wrong' => ['tone' => 'danger', 'icon' => 'close', 'label' => __('results.wrong')],
        'unanswered' => ['tone' => 'neutral', 'icon' => 'dot', 'label' => __('results.unanswered')],
    ];
@endphp

<div class="space-y-4">
    @if ($regraded)
        <x-ui.alert type="info">{{ __('results.regrade_note') }}</x-ui.alert>
    @endif

    @foreach ($rows as $row)
        @php $meta = $stateMeta[$row['state']]; @endphp
        <x-ui.card class="break-inside-avoid">
            <div class="flex items-start justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold uppercase tracking-wider text-muted">
                        {{ __('results.question_label', ['number' => bn($row['number'])]) }}
                    </span>
                    <x-ui.badge :color="$meta['tone']">
                        <x-ui.icon :name="$meta['icon']" class="w-3.5 h-3.5" /> {{ $meta['label'] }}
                    </x-ui.badge>
                </div>
                <span class="text-sm font-semibold text-ink tabular-nums">
                    {{ __('results.marks_label', ['earned' => bn($row['marks_earned']), 'available' => bn($row['marks_available'])]) }}
                    <span class="text-muted font-normal">{{ __('results.marks_word') }}</span>
                </span>
            </div>

            <p class="mt-2 text-ink font-medium leading-relaxed">{!! resource_label_html($row['body']) !!}</p>

            <ul class="mt-3 space-y-2" role="list">
                @foreach ($row['options'] as $opt)
                    @php
                        // Visual + textual state for each option, never colour alone.
                        $isCorrect = $opt['correct'];
                        $isChosen = $opt['selected'];
                        $tint = match (true) {
                            $isCorrect => 'border-emerald-400/60 bg-emerald-50 dark:bg-emerald-500/10',
                            $isChosen && ! $isCorrect => 'border-rose-400/60 bg-rose-50 dark:bg-rose-500/10',
                            default => 'border-line',
                        };
                    @endphp
                    <li class="flex items-start gap-3 rounded-xl border px-4 py-2.5 {{ $tint }}">
                        <span class="shrink-0 mt-0.5">
                            @if ($isCorrect)
                                <x-ui.icon name="check" class="w-5 h-5 text-emerald-600" />
                            @elseif ($isChosen)
                                <x-ui.icon name="close" class="w-5 h-5 text-rose-600" />
                            @else
                                <span class="block w-5 h-5 rounded-full border border-line"></span>
                            @endif
                        </span>
                        <span class="flex-1 text-ink">{!! resource_label_html($opt['body']) !!}</span>
                        <span class="flex flex-col items-end gap-1 shrink-0">
                            @if ($isChosen)
                                <x-ui.badge :color="$isCorrect ? 'success' : 'danger'">{{ __('results.your_answer') }}</x-ui.badge>
                            @endif
                            @if ($isCorrect)
                                <x-ui.badge color="success">{{ __('results.correct_answer') }}</x-ui.badge>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>

            @if (! empty($row['explanation']))
                <div class="mt-3 rounded-xl bg-surface-raised border border-line px-4 py-3">
                    <p class="text-xs font-semibold text-muted uppercase tracking-wider">{{ __('results.explanation_label') }}</p>
                    <p class="mt-1 text-sm text-ink leading-relaxed">{!! resource_label_html($row['explanation']) !!}</p>
                </div>
            @endif
        </x-ui.card>
    @endforeach
</div>
