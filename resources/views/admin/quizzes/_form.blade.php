@php
    $lessonsByCourse = $courses->mapWithKeys(fn ($c) => [
        $c->id => $c->lessons->map(fn ($l) => ['id' => $l->id, 'title' => $l->title])->values(),
    ]);
    $dt = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('Y-m-d\TH:i') : '';
@endphp

<div x-data="{ course: '{{ old('course_id', $quiz->course_id ?? '') }}', lessonsByCourse: {{ \Illuminate\Support\Js::from($lessonsByCourse) }} }"
     class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-5">
        <x-ui.card>
            <div class="space-y-5">
                <x-ui.field :label="__('quizzes.title')" name="title" required>
                    <x-ui.input name="title" :value="old('title', $quiz->title ?? '')" />
                </x-ui.field>
                <x-ui.field :label="__('quizzes.slug_optional')" name="slug" :hint="__('quizzes.slug_hint')">
                    <x-ui.input name="slug" :value="old('slug', $quiz->slug ?? '')" :placeholder="__('quizzes.slug_placeholder')" />
                </x-ui.field>
                <div class="grid sm:grid-cols-2 gap-4">
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
                                <option :value="lesson.id" x-text="lesson.title"
                                        :selected="lesson.id == {{ old('lesson_id', $quiz->lesson_id ?? 'null') ?: 'null' }}"></option>
                            </template>
                        </select>
                    </x-ui.field>
                </div>
                <x-ui.field :label="__('admin.description')" name="description">
                    <x-ui.textarea name="description">{{ old('description', $quiz->description ?? '') }}</x-ui.textarea>
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card>
            <h3 class="font-bold text-ink mb-4">{{ __('quizzes.schedule_heading') }}</h3>
            <div class="grid sm:grid-cols-2 gap-4">
                <x-ui.field :label="__('quizzes.starts_at')" name="starts_at">
                    <x-ui.input type="datetime-local" name="starts_at" :value="old('starts_at', $dt($quiz->starts_at ?? null))" />
                </x-ui.field>
                <x-ui.field :label="__('quizzes.ends_at')" name="ends_at">
                    <x-ui.input type="datetime-local" name="ends_at" :value="old('ends_at', $dt($quiz->ends_at ?? null))" />
                </x-ui.field>
                <x-ui.field :label="__('quizzes.result_release')" name="result_release_at" :hint="__('quizzes.result_release_hint')" class="sm:col-span-2">
                    <x-ui.input type="datetime-local" name="result_release_at" :value="old('result_release_at', $dt($quiz->result_release_at ?? null))" />
                </x-ui.field>
            </div>
            <p class="text-xs text-muted mt-3">{{ __('quizzes.schedule_note') }}</p>
        </x-ui.card>
    </div>

    <div class="space-y-5">
        <x-ui.card>
            <h3 class="font-bold text-ink mb-4">{{ __('quizzes.settings_heading') }}</h3>
            <div class="space-y-4">
                <x-ui.field :label="__('quizzes.status')" name="status">
                    <x-ui.select name="status">
                        @foreach (['draft' => __('quizzes.status_draft'), 'scheduled' => __('quizzes.status_scheduled'), 'published' => __('quizzes.status_published'), 'archived' => __('quizzes.status_archived')] as $val => $label)
                            <option value="{{ $val }}" @selected(old('status', $quiz->status ?? 'draft') === $val)>{{ $label }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field :label="__('quizzes.point_cost')" name="point_cost" :hint="__('quizzes.point_cost_hint')">
                    <x-ui.input type="number" name="point_cost" min="0" :value="old('point_cost', $quiz->point_cost ?? 1)" />
                </x-ui.field>
                <x-ui.field :label="__('quizzes.duration_minutes')" name="duration_minutes" :hint="__('quizzes.duration_hint')">
                    <x-ui.input type="number" name="duration_minutes" min="1"
                                :value="old('duration_minutes', ($quiz->duration_seconds ?? null) ? intdiv($quiz->duration_seconds, 60) : '')" />
                </x-ui.field>
                <x-ui.field :label="__('quizzes.max_attempts')" name="max_official_attempts">
                    <x-ui.input type="number" name="max_official_attempts" min="1" :value="old('max_official_attempts', $quiz->max_official_attempts ?? 1)" />
                </x-ui.field>
                <label class="flex items-center gap-2 text-sm text-ink">
                    <input type="checkbox" name="practice_enabled" value="1" @checked(old('practice_enabled', $quiz->practice_enabled ?? false)) class="rounded border-line text-brand focus:ring-brand">
                    {{ __('quizzes.practice_enabled') }}
                </label>
                <label class="flex items-center gap-2 text-sm text-ink">
                    <input type="checkbox" name="practice_timer_enabled" value="1" @checked(old('practice_timer_enabled', $quiz->practice_timer_enabled ?? false)) class="rounded border-line text-brand focus:ring-brand">
                    {{ __('quizzes.practice_timer_enabled') }}
                </label>
                <label class="flex items-center gap-2 text-sm text-ink">
                    <input type="checkbox" name="leaderboard_visible" value="1" @checked(old('leaderboard_visible', $quiz->leaderboard_visible ?? true)) class="rounded border-line text-brand focus:ring-brand">
                    {{ __('quizzes.leaderboard_visible') }}
                </label>
            </div>
        </x-ui.card>
    </div>
</div>
