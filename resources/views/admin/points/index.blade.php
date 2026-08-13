<x-layout.admin :title="__('nav.points')" :heading="__('points.admin_heading')">
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-6">
        <form method="GET" class="relative flex-1">
            <x-ui.icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
            <input name="q" value="{{ $search }}" placeholder="{{ __('students.search_placeholder') }}"
                   class="w-full rounded-xl bg-surface-raised border border-line pl-9 pr-4 py-2.5 text-sm outline-none focus:border-brand">
        </form>
        @if (auth()->user()->hasPermission('points.grant'))
            <x-ui.button :href="route('admin.points.bulk.form')"><x-ui.icon name="points" class="w-4 h-4" /> {{ __('points.bulk_points') }}</x-ui.button>
        @endif
    </div>

    @if ($students->isEmpty())
        <x-ui.card><x-ui.empty :title="__('points.no_students')" /></x-ui.card>
    @else
        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-3">{{ __('students.name_col') }}</th>
                <th class="px-4 py-3">{{ __('students.roll_col') }}</th>
                <th class="px-4 py-3">{{ __('ui.status') }}</th>
                <th class="px-4 py-3 text-right">{{ __('points.balance') }}</th>
                <th class="px-4 py-3"></th>
            </x-slot:head>
            @foreach ($students as $student)
                <tr class="hover:bg-ink/[0.02]">
                    <td class="px-4 py-3 font-semibold text-ink">{{ $student->name }}</td>
                    <td class="px-4 py-3 text-muted tabular-nums">{{ bn($student->roll) }}</td>
                    <td class="px-4 py-3"><x-ui.status-badge :status="$student->status" /></td>
                    <td class="px-4 py-3 text-right font-bold text-brand tabular-nums">{{ bn($student->points_balance) }}</td>
                    <td class="px-4 py-3 text-right"><a href="{{ route('admin.points.show', $student) }}" class="text-brand font-semibold hover:underline">{{ __('points.ledger') }}</a></td>
                </tr>
            @endforeach
        </x-ui.table>
        <div class="mt-6">{{ $students->links('components.pagination') }}</div>
    @endif
</x-layout.admin>
