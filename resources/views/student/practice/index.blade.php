<x-layout.student :title="__('practice.heading')" :heading="__('practice.heading')">

    @if ($byCourse->isEmpty())
        <x-ui.card><x-ui.empty :title="__('practice.library_none')">{{ __('practice.library_none_hint') }}</x-ui.empty></x-ui.card>
    @endif

    @foreach ($byCourse as $courseTitle => $quizzes)
        <section class="mb-8">
            <h2 class="text-sm font-bold uppercase tracking-wider text-muted mb-3">{{ $courseTitle }}</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($quizzes as $quiz)
                    <x-ui.card class="flex flex-col">
                        <div class="flex items-start justify-between gap-2 mb-1">
                            <h3 class="font-bold text-ink">{{ $quiz->title }}</h3>
                            <x-ui.badge color="brand">{{ __('practice.badge') }}</x-ui.badge>
                        </div>

                        <dl class="mt-2 space-y-1.5 text-sm text-muted flex-1">
                            <div class="flex items-center gap-4">
                                <span class="flex items-center gap-1.5"><x-ui.icon name="exam" class="w-4 h-4" /> {{ __('practice.total_questions') }}: {{ bn($quiz->active_questions_count) }}</span>
                                <span class="flex items-center gap-1.5"><x-ui.icon name="results" class="w-4 h-4" /> {{ __('practice.total_marks') }}: {{ bn($quiz->total_marks) }}</span>
                            </div>
                            @if ($quiz->practiceTimerActive())
                                <div class="flex items-center gap-1.5"><x-ui.icon name="clock" class="w-4 h-4" /> {{ __('exams.minutes', ['count' => bn(intdiv($quiz->duration_seconds, 60))]) }}</div>
                            @endif
                        </dl>

                        <form method="POST" action="{{ route('student.practice.start', $quiz) }}" class="mt-4">
                            @csrf
                            <x-ui.button type="submit" size="sm" class="w-full">
                                <x-ui.icon name="practice" class="w-4 h-4" /> {{ __('practice.start') }}
                            </x-ui.button>
                        </form>
                        <p class="text-xs text-muted text-center mt-1.5">{{ $quiz->practiceTimerActive() ? __('practice.timed_note') : __('practice.untimed_note') }}</p>
                    </x-ui.card>
                @endforeach
            </div>
        </section>
    @endforeach
</x-layout.student>
