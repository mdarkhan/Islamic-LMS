@php
    $config = $parsed['config'];
    $lessonsByCourse = $lessons->groupBy('course_id')->map(fn ($g) => $g->map(fn ($l) => ['id' => $l->id, 'title' => $l->title])->values());
    $banglaIndices = ['ক', 'খ', 'গ', 'ঘ', 'ঙ', 'চ', 'ছ', 'জ', 'ঝ', 'ঞ', 'ট', 'ঠ'];
@endphp
<x-layout.admin :title="__('quizzes.import_preview_heading')" :heading="__('quizzes.import_preview_heading')">
    <x-ui.breadcrumbs :items="[__('nav.quizzes') => route('admin.quizzes.index'), __('dashboard.import') => route('admin.quizzes.import.form'), __('ui.view') => null]" class="mb-5" />

    {{-- Sheet-level errors / warnings --}}
    @foreach ($parsed['errors'] as $error)
        <x-ui.alert type="error" class="mb-3">{{ $error }}</x-ui.alert>
    @endforeach
    @foreach ($parsed['warnings'] as $warning)
        <x-ui.alert type="warning" class="mb-3">{{ $warning }}</x-ui.alert>
    @endforeach
    @if ($config['schedule_error'])
        <x-ui.alert type="warning" class="mb-3">{{ __('quizzes.schedule_prefix') }} {{ $config['schedule_error'] }}</x-ui.alert>
    @endif
    @foreach ($suggestion['warnings'] as $warning)
        <x-ui.alert type="info" class="mb-3">{{ $warning }}</x-ui.alert>
    @endforeach
    @if ($duplicate)
        <x-ui.alert type="warning" :title="__('quizzes.possible_duplicate')" class="mb-3">
            {{ __('quizzes.duplicate_body', ['title' => $config['title']]) }}
        </x-ui.alert>
    @endif

    {{-- Summary --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <x-ui.stat :label="__('quizzes.valid_questions')" :value="bn($parsed['valid_count'])" tone="brand" />
        <x-ui.stat :label="__('quizzes.total_marks')" :value="bn($parsed['total_marks'])" tone="ink" />
        <x-ui.stat :label="__('quizzes.timer')" :value="$config['duration_seconds'] ? bn(intdiv($config['duration_seconds'], 60)).' '.__('quizzes.minutes_suffix') : '—'" tone="ink" />
        <x-ui.stat :label="__('quizzes.schedule_col')" :value="$config['starts_at'] ? $config['starts_at']->format('d/m H:i') : '—'" tone="amber" />
    </div>

    <form method="POST" action="{{ route('admin.quizzes.import.confirm') }}"
          x-data="{ course: '{{ old('course_id', $suggestion['course_id'] ?? '') }}', lessonsByCourse: {{ \Illuminate\Support\Js::from($lessonsByCourse) }} }">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="sheet" value="{{ $sheet }}">

        <x-ui.card class="mb-6">
            <h3 class="font-bold text-ink mb-4">{{ __('quizzes.quiz_info') }}</h3>
            <div class="grid sm:grid-cols-2 gap-4">
                <x-ui.field :label="__('quizzes.title')" name="title" required class="sm:col-span-2">
                    <x-ui.input name="title" :value="old('title', $config['title'])" />
                </x-ui.field>
                <x-ui.field :label="__('admin.course')" name="course_id">
                    <select name="course_id" x-model="course"
                            class="w-full rounded-xl bg-surface-raised text-ink border border-line focus:border-brand px-4 py-2.5 text-sm outline-none">
                        <option value="">{{ __('admin.none_option') }}</option>
                        @foreach ($courses as $course)
                            <option value="{{ $course->id }}">{{ $course->title }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
                <x-ui.field :label="__('admin.lesson').' ('.__('admin.optional').')'" name="lesson_id">
                    <select name="lesson_id"
                            class="w-full rounded-xl bg-surface-raised text-ink border border-line focus:border-brand px-4 py-2.5 text-sm outline-none">
                        <option value="">{{ __('admin.none_option') }}</option>
                        <template x-for="lesson in (lessonsByCourse[course] || [])" :key="lesson.id">
                            <option :value="lesson.id" x-text="lesson.title" :selected="lesson.id == {{ old('lesson_id', $suggestion['lesson_id'] ?? 'null') ?: 'null' }}"></option>
                        </template>
                    </select>
                </x-ui.field>
                <x-ui.field :label="__('quizzes.point_cost_required')" name="point_cost" required>
                    <x-ui.input type="number" name="point_cost" min="0" :value="old('point_cost', 1)" />
                </x-ui.field>
            </div>
            <p class="text-xs text-muted mt-3">{{ __('quizzes.imports_as_draft_note', ['draft' => __('admin.draft')]) }}</p>
        </x-ui.card>

        {{-- Questions --}}
        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-3">{{ __('quizzes.row_col') }}</th>
                <th class="px-4 py-3">{{ __('quizzes.question_col') }}</th>
                <th class="px-4 py-3 text-center">{{ __('quizzes.option_count_col') }}</th>
                <th class="px-4 py-3">{{ __('quizzes.correct_col') }}</th>
                <th class="px-4 py-3 text-center">{{ __('quizzes.marks_col') }}</th>
                <th class="px-4 py-3">{{ __('ui.status') }}</th>
            </x-slot:head>
            @foreach ($parsed['questions'] as $q)
                <tr class="{{ $q['errors'] ? 'bg-rose-500/[0.03]' : '' }}">
                    <td class="px-4 py-3 text-muted tabular-nums">{{ bn($q['row']) }}</td>
                    <td class="px-4 py-3 text-ink">{{ \Illuminate\Support\Str::limit($q['body'], 60) ?: '—' }}</td>
                    <td class="px-4 py-3 text-center tabular-nums">{{ bn(count($q['options'])) }}</td>
                    <td class="px-4 py-3 text-sm text-muted">
                        {{ collect($q['correct_positions'])->map(fn ($p) => $banglaIndices[$p - 1] ?? $p)->implode(', ') ?: '—' }}
                        @if (count($q['correct_positions']) > 1)<x-ui.badge color="info" class="ml-1">{{ __('quizzes.multi_badge') }}</x-ui.badge>@endif
                    </td>
                    <td class="px-4 py-3 text-center tabular-nums">{{ bn($q['marks']) }}</td>
                    <td class="px-4 py-3">
                        @if ($q['errors'])
                            <x-ui.badge color="danger">{{ __('quizzes.error_badge') }}</x-ui.badge>
                            <p class="text-xs text-rose-600 mt-1">{{ implode(' ', $q['errors']) }}</p>
                        @else
                            <x-ui.badge color="success">{{ __('quizzes.ok_badge') }}</x-ui.badge>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <div class="mt-6 flex items-center justify-between gap-3">
            <x-ui.button :href="route('admin.quizzes.import.form')" variant="ghost">{{ __('ui.cancel') }}</x-ui.button>
            <x-ui.button type="submit" :disabled="$parsed['has_fatal']">
                @if ($duplicate) {{ __('quizzes.import_as_copy') }} @else {{ __('quizzes.confirm_import') }} @endif
            </x-ui.button>
        </div>
        @if ($parsed['has_fatal'])
            <p class="text-right text-xs text-rose-600 mt-2">{{ __('quizzes.fatal_error_note') }}</p>
        @endif
    </form>
</x-layout.admin>
