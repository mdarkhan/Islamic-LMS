@props([
    'quizzes' => [],
    'activeQuizId' => null,   // null → the Overall tab is active
])

<nav class="flex items-center gap-2 overflow-x-auto pb-2 mb-4 print:hidden" aria-label="{{ __('leaderboards.heading') }}">
    <a href="{{ route('student.leaderboards.overall') }}"
       @class([
           'shrink-0 rounded-full px-4 py-1.5 text-sm font-semibold border transition-colors',
           'border-brand bg-brand text-brand-ink' => $activeQuizId === null,
           'border-line text-muted hover:border-brand/50' => $activeQuizId !== null,
       ])>
        {{ __('leaderboards.tab_overall') }}
    </a>
    @foreach ($quizzes as $quiz)
        <a href="{{ route('student.leaderboards.quiz', $quiz) }}"
           @class([
               'shrink-0 rounded-full px-4 py-1.5 text-sm font-semibold border transition-colors whitespace-nowrap',
               'border-brand bg-brand text-brand-ink' => (int) $activeQuizId === (int) $quiz->id,
               'border-line text-muted hover:border-brand/50' => (int) $activeQuizId !== (int) $quiz->id,
           ])>
            {{ $quiz->title }}
        </a>
    @endforeach
</nav>
