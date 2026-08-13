<x-layout.admin title="কোর্স" heading="কোর্স ব্যবস্থাপনা">
    <div class="flex justify-end mb-6">
        <x-ui.button :href="route('admin.courses.create')"><x-ui.icon name="plus" class="w-4 h-4" /> নতুন কোর্স</x-ui.button>
    </div>

    @if ($courses->isEmpty())
        <x-ui.card><x-ui.empty title="কোনো কোর্স নেই।">প্রথম কোর্স তৈরি করুন।</x-ui.empty></x-ui.card>
    @else
        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-3">ক্রম</th>
                <th class="px-4 py-3">শিরোনাম</th>
                <th class="px-4 py-3 hidden sm:table-cell">স্লাগ</th>
                <th class="px-4 py-3 text-right">ক্লাস</th>
                <th class="px-4 py-3">অবস্থা</th>
                <th class="px-4 py-3"></th>
            </x-slot:head>
            @foreach ($courses as $course)
                <tr>
                    <td class="px-4 py-3 text-muted tabular-nums">{{ bn($course->sort_order) }}</td>
                    <td class="px-4 py-3 font-semibold text-ink">{{ $course->title }}</td>
                    <td class="px-4 py-3 text-muted hidden sm:table-cell font-mono text-xs">{{ $course->slug }}</td>
                    <td class="px-4 py-3 text-right text-ink tabular-nums">{{ bn($course->lessons_count) }}</td>
                    <td class="px-4 py-3">
                        <x-ui.badge :color="$course->is_published ? 'success' : 'neutral'">{{ $course->is_published ? 'প্রকাশিত' : 'খসড়া' }}</x-ui.badge>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-2">
                            <form method="POST" action="{{ route('admin.courses.publish', $course) }}">
                                @csrf @method('PUT')
                                <button class="text-xs font-semibold text-muted hover:text-brand">{{ $course->is_published ? 'আড়াল' : 'প্রকাশ' }}</button>
                            </form>
                            <a href="{{ route('admin.courses.edit', $course) }}" class="text-brand font-semibold hover:underline text-sm">সম্পাদনা</a>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif
</x-layout.admin>
