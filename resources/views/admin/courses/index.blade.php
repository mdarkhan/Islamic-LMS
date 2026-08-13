<x-layout.admin :title="__('nav.courses')" :heading="__('courses.admin_heading')">
    <div class="flex justify-end mb-6">
        <x-ui.button :href="route('admin.courses.create')"><x-ui.icon name="plus" class="w-4 h-4" /> {{ __('courses.new_course') }}</x-ui.button>
    </div>

    @if ($courses->isEmpty())
        <x-ui.card><x-ui.empty :title="__('courses.no_courses_admin')">{{ __('courses.create_first_course') }}</x-ui.empty></x-ui.card>
    @else
        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-3">{{ __('admin.order') }}</th>
                <th class="px-4 py-3">{{ __('courses.title') }}</th>
                <th class="px-4 py-3 hidden sm:table-cell">{{ __('admin.slug') }}</th>
                <th class="px-4 py-3 text-right">{{ __('nav.lessons') }}</th>
                <th class="px-4 py-3">{{ __('ui.status') }}</th>
                <th class="px-4 py-3"></th>
            </x-slot:head>
            @foreach ($courses as $course)
                <tr>
                    <td class="px-4 py-3 text-muted tabular-nums">{{ bn($course->sort_order) }}</td>
                    <td class="px-4 py-3 font-semibold text-ink">{{ $course->title }}</td>
                    <td class="px-4 py-3 text-muted hidden sm:table-cell font-mono text-xs">{{ $course->slug }}</td>
                    <td class="px-4 py-3 text-right text-ink tabular-nums">{{ bn($course->lessons_count) }}</td>
                    <td class="px-4 py-3">
                        <x-ui.badge :color="$course->is_published ? 'success' : 'neutral'">{{ $course->is_published ? __('admin.published') : __('admin.draft') }}</x-ui.badge>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-2">
                            <form method="POST" action="{{ route('admin.courses.publish', $course) }}">
                                @csrf @method('PUT')
                                <button class="text-xs font-semibold text-muted hover:text-brand">{{ $course->is_published ? __('admin.unpublish') : __('admin.publish') }}</button>
                            </form>
                            <a href="{{ route('admin.courses.edit', $course) }}" class="text-brand font-semibold hover:underline text-sm">{{ __('ui.edit') }}</a>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif
</x-layout.admin>
