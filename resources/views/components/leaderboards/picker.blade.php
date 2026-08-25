@props([
    'quizzes' => collect(),
    'activeQuizId' => null,
])

@php
    $activeQuiz = $activeQuizId === null
        ? null
        : $quizzes->first(fn ($quiz) => (int) $quiz->id === (int) $activeQuizId);
    $activeCourseId = $activeQuiz
        ? ($activeQuiz->course_id === null ? '__none' : (string) $activeQuiz->course_id)
        : '';
    $quizOptions = $quizzes->map(fn ($quiz) => [
        'courseId' => $quiz->course_id === null ? '__none' : (string) $quiz->course_id,
        'title' => $quiz->title,
        'url' => route('student.leaderboards.quiz', $quiz),
    ])->values();
    $courses = $quizzes
        ->filter(fn ($quiz) => $quiz->course !== null)
        ->unique('course_id')
        ->map(fn ($quiz) => ['id' => (string) $quiz->course_id, 'title' => $quiz->course->title])
        ->sortBy('title')
        ->values();
    $hasUncategorised = $quizzes->contains(fn ($quiz) => $quiz->course_id === null);
@endphp

<nav class="mb-5 rounded-2xl border border-line bg-surface-raised p-3 print:hidden"
     aria-label="{{ __('leaderboards.heading') }}"
     x-data="{
         courseId: @js($activeCourseId),
         quizUrl: @js($activeQuiz ? route('student.leaderboards.quiz', $activeQuiz) : ''),
         quizzes: @js($quizOptions),
         get filteredQuizzes() {
             return this.quizzes.filter((quiz) => quiz.courseId === this.courseId);
         },
         changeCourse() {
             this.quizUrl = '';
         },
         openQuiz() {
             if (this.quizUrl) window.location.assign(this.quizUrl);
         },
     }">
    <div class="grid gap-3 sm:grid-cols-[auto_minmax(0,1fr)_minmax(0,1fr)] sm:items-end">
        <a href="{{ route('student.leaderboards.overall') }}"
           @class([
               'inline-flex min-h-11 items-center justify-center rounded-xl border px-5 py-2.5 text-sm font-bold transition-colors',
               'border-brand bg-brand text-brand-ink' => $activeQuizId === null,
               'border-line bg-surface text-muted hover:border-brand/50 hover:text-ink' => $activeQuizId !== null,
           ])>
            {{ __('leaderboards.tab_overall') }}
        </a>

        <label class="block min-w-0">
            <span class="mb-1 block text-xs font-semibold text-muted">{{ __('leaderboards.filter_course') }}</span>
            <select x-model="courseId" @change="changeCourse()"
                    class="w-full rounded-xl border border-line bg-surface px-3 py-2.5 text-sm text-ink outline-none transition-colors focus:border-brand">
                <option value="">{{ __('leaderboards.pick_course') }}</option>
                @foreach ($courses as $course)
                    <option value="{{ $course['id'] }}">{{ $course['title'] }}</option>
                @endforeach
                @if ($hasUncategorised)
                    <option value="__none">{{ __('leaderboards.no_course') }}</option>
                @endif
            </select>
        </label>

        <label class="block min-w-0">
            <span class="mb-1 block text-xs font-semibold text-muted">{{ __('leaderboards.filter_quiz') }}</span>
            <select x-model="quizUrl" @change="openQuiz()" :disabled="!courseId"
                    class="w-full rounded-xl border border-line bg-surface px-3 py-2.5 text-sm text-ink outline-none transition-colors focus:border-brand disabled:cursor-not-allowed disabled:opacity-50">
                <option value="">{{ __('leaderboards.pick_quiz') }}</option>
                <template x-for="quiz in filteredQuizzes" :key="quiz.url">
                    <option :value="quiz.url" x-text="quiz.title"></option>
                </template>
            </select>
        </label>
    </div>
</nav>
