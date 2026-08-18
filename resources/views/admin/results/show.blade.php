@php
    $statusTone = ['submitted' => 'success', 'expired' => 'warning', 'in_progress' => 'info', 'voided' => 'danger'][$attempt->status] ?? 'neutral';
    $fmt = fn ($d) => $d?->format('d/m/Y H:i');
    $canAdjust = auth()->user()->hasPermission('results.adjust') && $attempt->isTerminal() && $attempt->isOfficial();
@endphp

<x-layout.admin :title="__('results_admin.detail')" :heading="__('results_admin.detail')">
    <div class="mb-4">
        <x-ui.button :href="route('admin.results.index')" variant="ghost" size="sm">
            <x-ui.icon name="chevron" class="w-4 h-4 rotate-180" /> {{ __('results_admin.heading') }}
        </x-ui.button>
    </div>

    <div class="grid gap-5 lg:grid-cols-3">
        {{-- Left: identity + scores + adjustment --}}
        <div class="space-y-5">
            <x-ui.card>
                <h2 class="font-bold text-ink">{{ $attempt->user->name }}</h2>
                <p class="text-sm text-muted tabular-nums">{{ __('results_admin.col_roll') }}: {{ $attempt->user->roll ? bn($attempt->user->roll) : '—' }}</p>
                <p class="text-sm text-ink mt-1">{{ $quiz->title }}</p>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <x-ui.badge :color="$attempt->kind === 'practice' ? 'neutral' : 'brand'">{{ __('results_admin.kind_'.$attempt->kind) }}</x-ui.badge>
                    <x-ui.badge :color="$statusTone">{{ __('results_admin.status_'.$attempt->status) }}</x-ui.badge>
                    @if ($attempt->manual_adjustment !== 0)<x-ui.badge color="info">{{ __('results_admin.adjusted_badge') }}</x-ui.badge>@endif
                    @if ($attempt->wasRegraded())<x-ui.badge color="warning">{{ __('results_admin.regraded_badge') }}</x-ui.badge>@endif
                </div>
                <dl class="mt-4 space-y-1.5 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">{{ __('results_admin.started') }}</dt><dd class="tabular-nums">{{ $fmt($attempt->started_at) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">{{ __('results_admin.col_submitted') }}</dt><dd class="tabular-nums">{{ $fmt($attempt->submitted_at) ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">{{ __('results_admin.time_taken') }}</dt><dd class="tabular-nums">{{ $attempt->time_taken_seconds !== null ? bn(intdiv($attempt->time_taken_seconds, 60)).'m '.bn($attempt->time_taken_seconds % 60).'s' : '—' }}</dd></div>
                </dl>
            </x-ui.card>

            <x-ui.card>
                <h3 class="text-sm font-bold uppercase tracking-wider text-muted mb-3">{{ __('results_admin.scores') }}</h3>
                <dl class="space-y-1.5 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">{{ __('results_admin.calculated_score') }}</dt><dd class="font-semibold tabular-nums">{{ bn($attempt->calculated_score) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">{{ __('results_admin.manual_adjustment') }}</dt><dd class="font-semibold tabular-nums">{{ $attempt->manual_adjustment >= 0 ? '+' : '' }}{{ bn($attempt->manual_adjustment) }}</dd></div>
                    <div class="flex justify-between border-t border-line pt-1.5"><dt class="text-ink font-semibold">{{ __('results_admin.final_score') }}</dt><dd class="font-black text-brand tabular-nums">{{ bn($attempt->final_score) }} / {{ bn($attempt->total_marks_snapshot) }}</dd></div>
                </dl>
            </x-ui.card>

            {{-- Manual adjustment --}}
            @if ($canAdjust)
                <x-ui.card x-data="{ manual: {{ (int) $attempt->manual_adjustment }}, calc: {{ (int) $attempt->calculated_score }} }">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-muted mb-3">{{ __('results_admin.adjust_heading') }}</h3>
                    <form method="POST" action="{{ route('admin.results.adjust', $attempt) }}" class="space-y-3">
                        @csrf
                        <x-ui.field :label="__('results_admin.adjust_new_manual')" name="manual_adjustment">
                            <x-ui.input type="number" name="manual_adjustment" x-model.number="manual" value="{{ old('manual_adjustment', $attempt->manual_adjustment) }}" />
                        </x-ui.field>
                        <x-ui.field :label="__('results_admin.adjust_reason')" name="reason">
                            <x-ui.input name="reason" value="{{ old('reason') }}" required />
                        </x-ui.field>
                        <p class="text-sm text-muted tabular-nums">
                            {{ __('results_admin.calculated_score') }}: <span x-text="calc"></span> ·
                            {{ __('results_admin.manual_adjustment') }}: <span x-text="(manual>=0?'+':'')+manual"></span> ·
                            <span class="text-ink font-semibold">{{ __('results_admin.final_score') }}: <span x-text="calc + (manual||0)"></span></span>
                        </p>
                        <x-ui.button type="submit" size="sm">{{ __('results_admin.adjust_save') }}</x-ui.button>
                    </form>
                </x-ui.card>
            @endif

            {{-- Adjustment history --}}
            @if ($attempt->scoreAdjustments->isNotEmpty())
                <x-ui.card>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-muted mb-3">{{ __('results_admin.adjustment_history') }}</h3>
                    <ul class="space-y-2 text-sm">
                        @foreach ($attempt->scoreAdjustments->sortByDesc('created_at') as $adj)
                            <li class="border-b border-line pb-2 last:border-0">
                                <p class="text-ink">{{ __('results_admin.adjusted_by', ['name' => $adj->admin->name, 'old' => bn($adj->old_final), 'new' => bn($adj->new_final)]) }}</p>
                                <p class="text-xs text-muted">{{ $adj->reason }} · {{ $fmt($adj->created_at) }}</p>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif
        </div>

        {{-- Right: answers --}}
        <div class="lg:col-span-2">
            <h3 class="text-sm font-bold uppercase tracking-wider text-muted mb-3">{{ __('results_admin.answers_heading') }}</h3>
            @if ($legacy)
                <x-ui.alert type="info">{{ __('results.legacy_no_answers') }}</x-ui.alert>
            @else
                <x-exams.answer-review :rows="$rows" :regraded="$attempt->wasRegraded()" />
            @endif
        </div>
    </div>
</x-layout.admin>
