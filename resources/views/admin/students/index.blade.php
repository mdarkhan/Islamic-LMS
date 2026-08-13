<x-layout.admin title="শিক্ষার্থী" heading="শিক্ষার্থী ব্যবস্থাপনা">
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-6">
        <form method="GET" class="flex-1 flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <x-ui.icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
                <input name="q" value="{{ $search }}" placeholder="নাম বা রোল দিয়ে খুঁজুন..."
                       class="w-full rounded-xl bg-surface-raised border border-line pl-9 pr-4 py-2.5 text-sm outline-none focus:border-brand">
            </div>
            <x-ui.select name="status" class="sm:w-44" onchange="this.form.submit()">
                <option value="">সব স্ট্যাটাস</option>
                <option value="active" @selected($status === 'active')>সক্রিয়</option>
                <option value="suspended" @selected($status === 'suspended')>স্থগিত</option>
                <option value="archived" @selected($status === 'archived')>সংরক্ষণাগার</option>
            </x-ui.select>
        </form>
        @if (auth()->user()->hasPermission('students.create'))
            <div class="flex gap-2">
                <x-ui.button :href="route('admin.students.import.form')" variant="secondary"><x-ui.icon name="import" class="w-4 h-4" /> ইমপোর্ট</x-ui.button>
                <x-ui.button :href="route('admin.students.create')"><x-ui.icon name="plus" class="w-4 h-4" /> নতুন</x-ui.button>
            </div>
        @endif
    </div>

    @if ($students->isEmpty())
        <x-ui.card><x-ui.empty title="কোনো শিক্ষার্থী পাওয়া যায়নি।" /></x-ui.card>
    @else
        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-3">নাম</th>
                <th class="px-4 py-3">রোল</th>
                <th class="px-4 py-3 hidden sm:table-cell">অভিভাবক</th>
                <th class="px-4 py-3 text-right">পয়েন্ট</th>
                <th class="px-4 py-3">স্ট্যাটাস</th>
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
                        <a href="{{ route('admin.students.show', $student) }}" class="text-brand font-semibold hover:underline whitespace-nowrap">বিস্তারিত</a>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
        <div class="mt-6">{{ $students->links('components.pagination') }}</div>
    @endif
</x-layout.admin>
