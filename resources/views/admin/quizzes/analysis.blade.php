<x-layout.admin :title="__('results_admin.analysis_heading')" :heading="__('results_admin.analysis_heading')">
    <div class="mb-4">
        <x-ui.button :href="route('admin.quizzes.edit', $quiz)" variant="ghost" size="sm">
            <x-ui.icon name="chevron" class="w-4 h-4 rotate-180" /> {{ $quiz->title }}
        </x-ui.button>
    </div>

    @php
        $questions = $report['questions'];
        $tone = ['easy' => 'text-brand', 'medium' => 'text-amber-600', 'hard' => 'text-rose-600'];
        $bar = ['easy' => 'bg-brand', 'medium' => 'bg-amber-500', 'hard' => 'bg-rose-500'];
    @endphp

    @if ($report['students'] === 0)
        <x-ui.alert>{{ __('results_admin.analysis_empty') }}</x-ui.alert>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-6">
            <x-ui.card class="text-center">
                <p class="text-2xl font-extrabold text-ink tabular-nums">{{ bn($report['students']) }}</p>
                <p class="text-xs text-muted">{{ __('results_admin.analysis_students') }}</p>
            </x-ui.card>
            <x-ui.card class="text-center">
                <p class="text-2xl font-extrabold text-ink tabular-nums">{{ $report['average_percent'] === null ? '—' : bn($report['average_percent']).'%' }}</p>
                <p class="text-xs text-muted">{{ __('results_admin.analysis_average') }}</p>
            </x-ui.card>
            <x-ui.card class="text-center col-span-2 sm:col-span-1">
                <p class="text-2xl font-extrabold text-rose-600 tabular-nums">{{ bn(collect($questions)->where('level', 'hard')->count()) }}</p>
                <p class="text-xs text-muted">{{ __('results_admin.analysis_hard_count') }}</p>
            </x-ui.card>
        </div>

        <p class="text-sm text-muted mb-3">{{ __('results_admin.analysis_note') }}</p>

        <div class="space-y-3" x-data="{ sort: 'hardest' }">
            <div class="flex gap-2 text-sm">
                <button type="button" @click="sort = 'hardest'" :class="sort === 'hardest' ? 'bg-brand text-brand-ink' : 'border border-line text-muted'" class="px-3 py-1.5 rounded-lg font-semibold">{{ __('results_admin.analysis_sort_hardest') }}</button>
                <button type="button" @click="sort = 'order'" :class="sort === 'order' ? 'bg-brand text-brand-ink' : 'border border-line text-muted'" class="px-3 py-1.5 rounded-lg font-semibold">{{ __('results_admin.analysis_sort_order') }}</button>
            </div>

            <div class="flex flex-col gap-3">
                @foreach ($questions as $q)
                    <x-ui.card class="!p-0 overflow-hidden" x-bind:style="'order: ' + (sort === 'hardest' ? {{ $q['percent'] * 1000 + $q['number'] }} : {{ $q['number'] }})">
                        <details class="group">
                            <summary class="cursor-pointer list-none [&::-webkit-details-marker]:hidden px-4 py-3 hover:bg-ink/5">
                                <div class="flex items-start gap-3">
                                    <span class="shrink-0 w-8 h-8 rounded-lg bg-surface-raised border border-line grid place-items-center text-sm font-bold text-ink tabular-nums">{{ bn($q['number']) }}</span>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm text-ink leading-relaxed line-clamp-2">{{ $q['body'] }}</p>
                                        <div class="mt-2 flex items-center gap-3">
                                            <div class="flex-1 h-2 rounded-full bg-line overflow-hidden"><div class="h-full {{ $bar[$q['level']] }}" style="width: {{ $q['percent'] }}%"></div></div>
                                            <span class="text-sm font-bold tabular-nums {{ $tone[$q['level']] }}">{{ bn($q['percent']) }}%</span>
                                        </div>
                                        <p class="text-xs text-muted mt-1.5 tabular-nums">
                                            {{ __('results_admin.analysis_counts', ['correct' => bn($q['correct']), 'wrong' => bn($q['wrong']), 'skipped' => bn($q['skipped'])]) }}
                                            · <span class="font-semibold {{ $tone[$q['level']] }}">{{ __('results_admin.analysis_level_'.$q['level']) }}</span>
                                        </p>
                                    </div>
                                    <x-ui.icon name="chevron" class="w-4 h-4 text-muted mt-1 transition-transform group-open:rotate-90" />
                                </div>
                            </summary>
                            <div class="px-4 pb-4 pt-1 border-t border-line space-y-2">
                                @foreach ($q['options'] as $o)
                                    <div class="flex items-center gap-3 text-sm">
                                        <span class="w-5 shrink-0">@if ($o['is_correct'])<x-ui.icon name="check" class="w-4 h-4 text-brand" />@endif</span>
                                        <span @class(['flex-1 min-w-0 truncate', 'font-semibold text-ink' => $o['is_correct'], 'text-muted' => ! $o['is_correct']])>{{ $o['body'] }}</span>
                                        <div class="w-24 sm:w-40 h-2 rounded-full bg-line overflow-hidden shrink-0"><div class="h-full {{ $o['is_correct'] ? 'bg-brand' : 'bg-rose-400' }}" style="width: {{ $o['percent'] }}%"></div></div>
                                        <span class="w-16 text-right tabular-nums text-xs text-muted shrink-0">{{ bn($o['picked']) }} ({{ bn($o['percent']) }}%)</span>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    </x-ui.card>
                @endforeach
            </div>
        </div>
    @endif
</x-layout.admin>
