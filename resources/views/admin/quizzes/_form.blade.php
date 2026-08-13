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
                <x-ui.field label="শিরোনাম" name="title" required>
                    <x-ui.input name="title" :value="old('title', $quiz->title ?? '')" />
                </x-ui.field>
                <x-ui.field label="স্লাগ (ঐচ্ছিক)" name="slug" hint="খালি রাখলে শিরোনাম থেকে তৈরি হবে">
                    <x-ui.input name="slug" :value="old('slug', $quiz->slug ?? '')" placeholder="যেমনঃ সীরাত-২৮" />
                </x-ui.field>
                <div class="grid sm:grid-cols-2 gap-4">
                    <x-ui.field label="কোর্স" name="course_id">
                        <select name="course_id" x-model="course"
                                class="w-full rounded-xl bg-surface-raised text-ink border border-line focus:border-brand px-4 py-2.5 text-sm outline-none">
                            <option value="">— কোনোটি নয় —</option>
                            @foreach ($courses as $course)
                                <option value="{{ $course->id }}">{{ $course->title }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>
                    <x-ui.field label="ক্লাস (ঐচ্ছিক)" name="lesson_id">
                        <select name="lesson_id"
                                class="w-full rounded-xl bg-surface-raised text-ink border border-line focus:border-brand px-4 py-2.5 text-sm outline-none">
                            <option value="">— কোনোটি নয় —</option>
                            <template x-for="lesson in (lessonsByCourse[course] || [])" :key="lesson.id">
                                <option :value="lesson.id" x-text="lesson.title"
                                        :selected="lesson.id == {{ old('lesson_id', $quiz->lesson_id ?? 'null') ?: 'null' }}"></option>
                            </template>
                        </select>
                    </x-ui.field>
                </div>
                <x-ui.field label="বিবরণ" name="description">
                    <x-ui.textarea name="description">{{ old('description', $quiz->description ?? '') }}</x-ui.textarea>
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card>
            <h3 class="font-bold text-ink mb-4">সময়সূচি</h3>
            <div class="grid sm:grid-cols-2 gap-4">
                <x-ui.field label="শুরু" name="starts_at">
                    <x-ui.input type="datetime-local" name="starts_at" :value="old('starts_at', $dt($quiz->starts_at ?? null))" />
                </x-ui.field>
                <x-ui.field label="শেষ" name="ends_at">
                    <x-ui.input type="datetime-local" name="ends_at" :value="old('ends_at', $dt($quiz->ends_at ?? null))" />
                </x-ui.field>
                <x-ui.field label="ফলাফল প্রকাশ" name="result_release_at" hint="খালি রাখলে পরীক্ষা শেষে" class="sm:col-span-2">
                    <x-ui.input type="datetime-local" name="result_release_at" :value="old('result_release_at', $dt($quiz->result_release_at ?? null))" />
                </x-ui.field>
            </div>
            <p class="text-xs text-muted mt-3">সময় Asia/Dhaka অনুযায়ী। scheduled ও published — দুই অবস্থাতেই এই সময়সীমা অনুযায়ী পরীক্ষা খোলে ও বন্ধ হয়।</p>
        </x-ui.card>
    </div>

    <div class="space-y-5">
        <x-ui.card>
            <h3 class="font-bold text-ink mb-4">সেটিংস</h3>
            <div class="space-y-4">
                <x-ui.field label="অবস্থা" name="status">
                    <x-ui.select name="status">
                        @foreach (['draft' => 'খসড়া', 'scheduled' => 'নির্ধারিত', 'published' => 'প্রকাশিত', 'archived' => 'সংরক্ষণাগার'] as $val => $label)
                            <option value="{{ $val }}" @selected(old('status', $quiz->status ?? 'draft') === $val)>{{ $label }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field label="পয়েন্ট খরচ" name="point_cost" hint="০ হলে বিনামূল্যে">
                    <x-ui.input type="number" name="point_cost" min="0" :value="old('point_cost', $quiz->point_cost ?? 1)" />
                </x-ui.field>
                <x-ui.field label="সময়সীমা (মিনিট)" name="duration_minutes" hint="খালি রাখলে সময়সীমা নেই">
                    <x-ui.input type="number" name="duration_minutes" min="1"
                                :value="old('duration_minutes', ($quiz->duration_seconds ?? null) ? intdiv($quiz->duration_seconds, 60) : '')" />
                </x-ui.field>
                <x-ui.field label="সর্বোচ্চ অফিসিয়াল অ্যাটেম্পট" name="max_official_attempts">
                    <x-ui.input type="number" name="max_official_attempts" min="1" :value="old('max_official_attempts', $quiz->max_official_attempts ?? 1)" />
                </x-ui.field>
                <label class="flex items-center gap-2 text-sm text-ink">
                    <input type="checkbox" name="practice_enabled" value="1" @checked(old('practice_enabled', $quiz->practice_enabled ?? false)) class="rounded border-line text-brand focus:ring-brand">
                    অনুশীলন চালু
                </label>
                <label class="flex items-center gap-2 text-sm text-ink">
                    <input type="checkbox" name="leaderboard_visible" value="1" @checked(old('leaderboard_visible', $quiz->leaderboard_visible ?? true)) class="rounded border-line text-brand focus:ring-brand">
                    মেধাতালিকা দৃশ্যমান
                </label>
            </div>
        </x-ui.card>
    </div>
</div>
