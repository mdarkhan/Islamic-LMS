@php
    $statusMeta = [
        'draft' => ['neutral', __('quizzes.status_draft')],
        'scheduled' => ['info', __('quizzes.status_scheduled')],
        'published' => ['success', __('quizzes.status_published')],
        'archived' => ['warning', __('quizzes.status_archived')],
    ];
    $me = auth()->user();
@endphp
<x-layout.admin :title="__('nav.quizzes')" :heading="__('quizzes.admin_heading')">
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-6">
        <form method="GET" class="flex-1 flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <x-ui.icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
                <input name="q" value="{{ $search }}" placeholder="{{ __('quizzes.title_search_placeholder') }}"
                       class="w-full rounded-xl bg-surface-raised border border-line pl-9 pr-4 py-2.5 text-sm outline-none focus:border-brand">
            </div>
            <x-ui.select name="status" class="sm:w-40" onchange="this.form.submit()">
                <option value="">{{ __('admin.all_statuses') }}</option>
                @foreach ($statusMeta as $val => [$c, $label])
                    <option value="{{ $val }}" @selected($status === $val)>{{ $label }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.select name="course" class="sm:w-44" onchange="this.form.submit()">
                <option value="">{{ __('admin.all_courses') }}</option>
                @foreach ($courses as $course)
                    <option value="{{ $course->slug }}" @selected($courseFilter === $course->slug)>{{ $course->title }}</option>
                @endforeach
            </x-ui.select>
        </form>
        <div class="flex gap-2">
            @if ($me->hasPermission('quizzes.import'))
                <x-ui.button :href="route('admin.quizzes.import.form')" variant="secondary"><x-ui.icon name="import" class="w-4 h-4" /> {{ __('dashboard.import') }}</x-ui.button>
            @endif
            @if ($me->hasPermission('quizzes.create'))
                <x-ui.button :href="route('admin.quizzes.create')"><x-ui.icon name="plus" class="w-4 h-4" /> {{ __('quizzes.new_quiz') }}</x-ui.button>
            @endif
        </div>
    </div>

    @if ($quizzes->isEmpty())
        <x-ui.card><x-ui.empty :title="__('quizzes.no_quizzes')">{{ __('quizzes.no_quizzes_hint') }}</x-ui.empty></x-ui.card>
    @else
        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-3">{{ __('quizzes.title_col') }}</th>
                <th class="px-4 py-3 hidden md:table-cell">{{ __('quizzes.course_lesson') }}</th>
                <th class="px-4 py-3 text-center align-middle">{{ __('ui.status') }}</th>
                <th class="px-4 py-3 text-center hidden sm:table-cell">{{ __('quizzes.questions_col') }}</th>
                <th class="px-4 py-3 text-center hidden sm:table-cell">{{ __('quizzes.marks_col') }}</th>
                <th class="px-4 py-3 text-center hidden lg:table-cell">{{ __('quizzes.attempts_col') }}</th>
                <th class="px-4 py-3"></th>
            </x-slot:head>
            @foreach ($quizzes as $quiz)
                @php [$color, $label] = $statusMeta[$quiz->status]; @endphp
                <tr>
                    <td class="px-4 py-3">
                        <p class="font-semibold text-ink">{{ $quiz->title }}</p>
                        <p class="text-xs text-muted">
                            {{ bn($quiz->point_cost) }} {{ __('quizzes.points_suffix') }}
                            @if ($quiz->duration_seconds) · {{ bn(intdiv($quiz->duration_seconds, 60)) }} {{ __('quizzes.minutes_suffix') }} @endif
                            @if ($quiz->practice_enabled) · {{ __('quizzes.practice_suffix') }} @endif
                        </p>
                        @if ($quiz->starts_at)
                            <p class="text-xs text-muted mt-0.5">{{ $quiz->starts_at->format('d/m/Y H:i') }}@if ($quiz->ends_at) – {{ $quiz->ends_at->format('d/m H:i') }}@endif</p>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-muted text-sm hidden md:table-cell">
                        {{ $quiz->course?->title ?? '—' }}
                        @if ($quiz->lesson)<span class="block text-xs">{{ $quiz->lesson->title }}</span>@endif
                    </td>
                    <td class="px-4 py-3 text-center align-middle"><x-ui.badge :color="$color">{{ $label }}</x-ui.badge></td>
                    <td class="px-4 py-3 text-center tabular-nums hidden sm:table-cell">{{ bn($quiz->questions_count) }}</td>
                    <td class="px-4 py-3 text-center tabular-nums hidden sm:table-cell">{{ bn($quiz->total_marks) }}</td>
                    <td class="px-4 py-3 text-center tabular-nums hidden lg:table-cell">
                        {{ bn($quiz->official_attempts_count) }}
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end" x-data="{ open: false }">
                            <button @click="open = !open" class="p-2 rounded-lg text-muted hover:text-ink hover:bg-ink/5" aria-label="{{ __('quizzes.actions') }}">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
                            </button>
                            <div x-show="open" x-cloak @click.outside="open = false" x-transition
                                 class="absolute mt-2 right-6 z-20 w-48 bg-card border border-line rounded-xl shadow-lg py-1 text-sm">
                                <a href="{{ route('admin.quizzes.edit', $quiz) }}" class="block px-4 py-2 hover:bg-ink/5 text-ink">{{ __('quizzes.edit') }}</a>
                                <a href="{{ route('admin.quizzes.preview', $quiz) }}" class="block px-4 py-2 hover:bg-ink/5 text-ink">{{ __('quizzes.preview') }}</a>
                                @if ($me->hasPermission('quizzes.create'))
                                    <form method="POST" action="{{ route('admin.quizzes.duplicate', $quiz) }}">@csrf<button class="block w-full text-left px-4 py-2 hover:bg-ink/5 text-ink">{{ __('quizzes.duplicate') }}</button></form>
                                @endif
                                @if ($me->hasPermission('quizzes.publish'))
                                    <div class="border-t border-line my-1"></div>
                                    @foreach (['published' => __('quizzes.publish'), 'scheduled' => __('quizzes.schedule'), 'archived' => __('quizzes.archive')] as $st => $lbl)
                                        @if ($quiz->status !== $st)
                                            <form method="POST" action="{{ route('admin.quizzes.status', $quiz) }}">@csrf @method('PUT')<input type="hidden" name="status" value="{{ $st }}"><button class="block w-full text-left px-4 py-2 hover:bg-ink/5 text-ink">{{ __('quizzes.do_action', ['label' => $lbl]) }}</button></form>
                                        @endif
                                    @endforeach
                                @endif
                                @if (! $quiz->official_attempts_count)
                                    <div class="border-t border-line my-1"></div>
                                    <form method="POST" action="{{ route('admin.quizzes.destroy', $quiz) }}" data-confirm="{{ __('admin.confirm_delete_quiz') }}">@csrf @method('DELETE')<button class="block w-full text-left px-4 py-2 hover:bg-rose-500/10 text-rose-600">{{ __('quizzes.delete') }}</button></form>
                                @endif
                            </div>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
        <div class="mt-6">{{ $quizzes->links('components.pagination') }}</div>
    @endif
</x-layout.admin>
