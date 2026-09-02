<x-layout.admin :title="__('nav.lessons')" :heading="__('lessons.admin_heading')">
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-6">
        <form method="GET" class="flex-1">
            <x-ui.select name="course" onchange="this.form.submit()" class="sm:w-64">
                <option value="">{{ __('admin.all_courses') }}</option>
                @foreach ($courses as $course)
                    <option value="{{ $course->slug }}" @selected($courseFilter === $course->slug)>{{ $course->title }}</option>
                @endforeach
            </x-ui.select>
        </form>
        <x-ui.button :href="route('admin.lessons.create')"><x-ui.icon name="plus" class="w-4 h-4" /> {{ __('lessons.new_lesson') }}</x-ui.button>
    </div>

    @if ($lessons->isEmpty())
        <x-ui.card><x-ui.empty :title="__('lessons.no_lessons')" /></x-ui.card>
    @else
        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-3">{{ __('courses.title') }}</th>
                <th class="px-4 py-3 hidden md:table-cell">{{ __('admin.course') }}</th>
                <th class="px-4 py-3 hidden sm:table-cell">{{ __('lessons.date') }}</th>
                <th class="px-4 py-3 text-right hidden lg:table-cell">{{ __('lessons.viewed_by_col') }}</th>
                <th class="px-4 py-3">{{ __('ui.status') }}</th>
                <th class="px-4 py-3"></th>
            </x-slot:head>
            @foreach ($lessons as $lesson)
                <tr>
                    <td class="px-4 py-3">
                        <p class="font-semibold text-ink">{{ $lesson->title }}</p>
                        <div class="flex items-center gap-2 mt-0.5">
                            @if ($lesson->video_url)<span class="text-xs text-muted flex items-center gap-0.5"><x-ui.icon name="video" class="w-3 h-3" /> {{ __('lessons.video_url') }}</span>@endif
                            @if ($lesson->embedUrl())<span class="text-xs text-muted flex items-center gap-0.5"><x-ui.icon name="volume" class="w-3 h-3" /> {{ __('lessons.audio') }}</span>@endif
                        </div>
                    </td>
                    <td class="px-4 py-3 text-muted hidden md:table-cell">{{ $lesson->course->title }}</td>
                    <td class="px-4 py-3 text-muted hidden sm:table-cell">{{ $lesson->date_label ?? '—' }}</td>
                    <td class="px-4 py-3 text-right text-muted tabular-nums hidden lg:table-cell">{{ bn($lesson->views_count) }}/{{ bn($activeStudentCount) }}</td>
                    <td class="px-4 py-3"><x-ui.badge :color="$lesson->is_published ? 'success' : 'neutral'">{{ $lesson->is_published ? __('admin.published') : __('admin.draft') }}</x-ui.badge></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-2">
                            <form method="POST" action="{{ route('admin.lessons.publish', $lesson) }}">
                                @csrf @method('PUT')
                                <button class="text-xs font-semibold text-muted hover:text-brand">{{ $lesson->is_published ? __('admin.unpublish') : __('admin.publish') }}</button>
                            </form>
                            <a href="{{ route('admin.lessons.edit', $lesson) }}" class="text-brand font-semibold hover:underline text-sm">{{ __('ui.edit') }}</a>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
        <div class="mt-6">{{ $lessons->links('components.pagination') }}</div>
    @endif
</x-layout.admin>
