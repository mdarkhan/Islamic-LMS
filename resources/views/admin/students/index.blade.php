<x-layout.admin :title="__('nav.students')" :heading="__('students.admin_heading')">
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-6">
        <form method="GET" class="flex-1 flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <x-ui.icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
                <input name="q" value="{{ $search }}" placeholder="{{ __('students.search_placeholder') }}"
                       class="w-full rounded-xl bg-surface-raised border border-line pl-9 pr-4 py-2.5 text-sm outline-none focus:border-brand">
            </div>
            <x-ui.select name="status" class="sm:w-44" onchange="this.form.submit()">
                <option value="">{{ __('admin.all_statuses') }}</option>
                <option value="active" @selected($status === 'active')>{{ __('students.status_active') }}</option>
                <option value="suspended" @selected($status === 'suspended')>{{ __('students.status_suspended') }}</option>
                <option value="archived" @selected($status === 'archived')>{{ __('students.status_archived') }}</option>
            </x-ui.select>
        </form>
        @if (auth()->user()->hasPermission('students.create'))
            <div class="flex gap-2">
                <x-ui.button :href="route('admin.students.import.form')" variant="secondary"><x-ui.icon name="import" class="w-4 h-4" /> {{ __('dashboard.import') }}</x-ui.button>
                <x-ui.button :href="route('admin.students.create')"><x-ui.icon name="plus" class="w-4 h-4" /> {{ __('students.new') }}</x-ui.button>
            </div>
        @endif
    </div>

    @if ($students->isEmpty())
        <x-ui.card><x-ui.empty :title="__('students.no_students')" /></x-ui.card>
    @else
        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-3"><x-ui.sort-link sort="name" :current="$sort" :dir="$dir" :label="__('students.name_col')" /></th>
                <th class="px-4 py-3"><x-ui.sort-link sort="roll" :current="$sort" :dir="$dir" :label="__('students.roll_col')" /></th>
                <th class="px-4 py-3 hidden sm:table-cell">{{ __('students.guardian_col') }}</th>
                <th class="px-4 py-3 text-right"><x-ui.sort-link sort="points" :current="$sort" :dir="$dir" :label="__('students.points_col')" class="justify-end" /></th>
                <th class="px-4 py-3">{{ __('students.status_col') }}</th>
                <th class="px-4 py-3"></th>
            </x-slot:head>
            @foreach ($students as $student)
                <tr class="hover:bg-ink/[0.02]">
                    <td class="px-4 py-3 font-semibold text-ink">{{ $student->name }}</td>
                    <td class="px-4 py-3 text-muted tabular-nums">{{ bn($student->roll) }}</td>
                    <td class="px-4 py-3 text-muted hidden sm:table-cell">{{ $student->guardian_name ?? '—' }}</td>
                    <td class="px-4 py-3 text-right font-semibold text-ink tabular-nums">{{ bn($student->points_balance) }}</td>
                    <td class="px-4 py-3"><x-ui.status-badge :status="$student->status" /></td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.students.show', $student) }}" class="text-brand font-semibold hover:underline whitespace-nowrap">{{ __('students.details') }}</a>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
        <div class="mt-6">{{ $students->links('components.pagination') }}</div>
    @endif
</x-layout.admin>
