@php
    $statuses = ['submitted', 'expired', 'in_progress', 'voided'];
    $statusTone = ['submitted' => 'success', 'expired' => 'warning', 'in_progress' => 'info', 'voided' => 'danger'];
@endphp

<x-layout.admin :title="__('results_admin.heading')" :heading="__('results_admin.heading')">

    {{-- Filters (official attempts only — practice is not retained) --}}
    <form method="GET" action="{{ route('admin.results.index') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mb-5">
        <x-ui.select name="quiz_id">
            <option value="">{{ __('results_admin.filter_quiz') }}: {{ __('results_admin.all') }}</option>
            @foreach ($quizzes as $q)
                <option value="{{ $q->id }}" @selected($filters['quiz_id'] == $q->id)>{{ $q->title }}</option>
            @endforeach
        </x-ui.select>
        <x-ui.select name="status">
            <option value="">{{ __('results_admin.filter_status') }}: {{ __('results_admin.all') }}</option>
            @foreach ($statuses as $s)
                <option value="{{ $s }}" @selected($filters['status'] === $s)>{{ __('results_admin.status_'.$s) }}</option>
            @endforeach
        </x-ui.select>
        <x-ui.input name="q" value="{{ $filters['q'] }}" placeholder="{{ __('results_admin.search_placeholder') }}" />
        <div class="flex gap-2">
            <x-ui.button type="submit" variant="secondary" class="flex-1">{{ __('results_admin.apply') }}</x-ui.button>
            <x-ui.button :href="route('admin.results.export', request()->query())" variant="ghost">
                <x-ui.icon name="download" class="w-4 h-4" /> {{ __('results_admin.export_csv') }}
            </x-ui.button>
        </div>
    </form>

    @if ($attempts->isEmpty())
        <x-ui.card><x-ui.empty :title="__('results_admin.none')" /></x-ui.card>
    @else
        <div class="overflow-x-auto rounded-2xl border border-line">
            <table class="w-full text-sm">
                <thead class="bg-surface-raised text-muted">
                    <tr>
                        <th scope="col" class="px-4 py-2.5 text-center align-middle font-semibold">{{ __('results_admin.col_student') }}</th>
                        <th scope="col" class="px-4 py-2.5 text-center align-middle font-semibold">{{ __('results_admin.col_quiz') }}</th>
                        <th scope="col" class="px-4 py-2.5 text-center align-middle font-semibold">{{ __('results_admin.col_status') }}</th>
                        <th scope="col" class="px-4 py-2.5 text-center align-middle font-semibold">{{ __('results_admin.col_final') }}</th>
                        <th scope="col" class="px-4 py-2.5 text-center align-middle font-semibold">{{ __('results_admin.col_total') }}</th>
                        <th scope="col" class="px-4 py-2.5 text-center align-middle font-semibold">{{ __('results_admin.col_submitted') }}</th>
                        <th scope="col" class="px-4 py-2.5 text-center align-middle"><span class="sr-only">{{ __('results_admin.view') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($attempts as $a)
                        <tr class="hover:bg-surface-raised/50">
                            <td class="px-4 py-3 text-center align-middle">
                                <div class="font-medium text-ink">{{ $a->user->name }}</div>
                                <div class="text-xs text-muted tabular-nums">{{ $a->user->roll ? bn($a->user->roll) : '—' }}</div>
                            </td>
                            <td class="px-4 py-3 text-center align-middle">{{ $a->quiz->title }}</td>
                            <td class="px-4 py-3 text-center align-middle"><x-ui.badge :color="$statusTone[$a->status] ?? 'neutral'">{{ __('results_admin.status_'.$a->status) }}</x-ui.badge></td>
                            <td class="px-4 py-3 text-center align-middle font-semibold text-ink tabular-nums">{{ bn($a->final_score) }}</td>
                            <td class="px-4 py-3 text-center align-middle text-muted tabular-nums">{{ bn($a->total_marks_snapshot) }}</td>
                            <td class="px-4 py-3 text-center align-middle text-muted tabular-nums whitespace-nowrap">{{ $a->submitted_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="px-4 py-3 text-center align-middle">
                                <x-ui.button :href="route('admin.results.show', $a)" variant="secondary" size="sm">{{ __('results_admin.view') }}</x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $attempts->links() }}</div>
    @endif
</x-layout.admin>
