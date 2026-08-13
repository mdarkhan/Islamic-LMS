@php $placeholder = 'এই ক্লাসের বিস্তারিত তথ্য ও কুইজ শীঘ্রই আপডেট করা হবে।'; @endphp
<x-layout.admin title="ক্লাস" heading="ক্লাস ব্যবস্থাপনা">
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-6">
        <form method="GET" class="flex-1">
            <x-ui.select name="course" onchange="this.form.submit()" class="sm:w-64">
                <option value="">সব কোর্স</option>
                @foreach ($courses as $course)
                    <option value="{{ $course->slug }}" @selected($courseFilter === $course->slug)>{{ $course->title }}</option>
                @endforeach
            </x-ui.select>
        </form>
        <x-ui.button :href="route('admin.lessons.create')"><x-ui.icon name="plus" class="w-4 h-4" /> নতুন ক্লাস</x-ui.button>
    </div>

    @if ($lessons->isEmpty())
        <x-ui.card><x-ui.empty title="কোনো ক্লাস নেই।" /></x-ui.card>
    @else
        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-3">শিরোনাম</th>
                <th class="px-4 py-3 hidden md:table-cell">কোর্স</th>
                <th class="px-4 py-3 hidden sm:table-cell">তারিখ</th>
                <th class="px-4 py-3">অবস্থা</th>
                <th class="px-4 py-3"></th>
            </x-slot:head>
            @foreach ($lessons as $lesson)
                @php
                    $flags = [];
                    if (! $lesson->description || $lesson->description === $placeholder) $flags[] = 'বিবরণ নেই';
                    if (! $lesson->held_on) $flags[] = 'তারিখ নেই';
                    if ($lesson->resources_count === 0) $flags[] = 'রিসোর্স নেই';
                @endphp
                <tr>
                    <td class="px-4 py-3">
                        <p class="font-semibold text-ink">{{ $lesson->title }}</p>
                        @if ($flags)
                            <div class="flex flex-wrap gap-1 mt-1">
                                @foreach ($flags as $flag)<x-ui.badge color="warning">{{ $flag }}</x-ui.badge>@endforeach
                            </div>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-muted hidden md:table-cell">{{ $lesson->course->title }}</td>
                    <td class="px-4 py-3 text-muted hidden sm:table-cell">{{ $lesson->date_label ?? '—' }}</td>
                    <td class="px-4 py-3"><x-ui.badge :color="$lesson->is_published ? 'success' : 'neutral'">{{ $lesson->is_published ? 'প্রকাশিত' : 'খসড়া' }}</x-ui.badge></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-2">
                            <form method="POST" action="{{ route('admin.lessons.publish', $lesson) }}">
                                @csrf @method('PUT')
                                <button class="text-xs font-semibold text-muted hover:text-brand">{{ $lesson->is_published ? 'আড়াল' : 'প্রকাশ' }}</button>
                            </form>
                            <a href="{{ route('admin.lessons.edit', $lesson) }}" class="text-brand font-semibold hover:underline text-sm">সম্পাদনা</a>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
        <div class="mt-6">{{ $lessons->links('components.pagination') }}</div>
    @endif
</x-layout.admin>
