<x-layout.student :title="__('nav.dashboard')" :heading="__('nav.dashboard')">
    @if ($notices->isNotEmpty())
        <div class="mb-6"><x-ui.notice-banner :notices="$notices" /></div>
    @endif

    {{-- Welcome --}}
    <x-ui.card class="relative overflow-hidden mb-6">
        <div class="absolute inset-0 geo-accent opacity-50" aria-hidden="true"></div>
        <div class="relative">
            <p class="text-sm text-muted">{{ __('dashboard.greeting') }}</p>
            <h2 class="text-2xl font-black text-ink mt-1">{{ $user->name }}</h2>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <x-ui.badge color="neutral">{{ __('dashboard.roll') }}: {{ bn($user->roll) }}</x-ui.badge>
                <x-ui.badge color="success">{{ __('dashboard.active') }}</x-ui.badge>
            </div>
        </div>
    </x-ui.card>

    {{-- Action items: exam banner, resume, unseen results, practice suggestions --}}
    <div class="space-y-3 mb-6">
        @if ($upcomingExam)
            @php $q = $upcomingExam['quiz']; @endphp
            <x-ui.alert :type="$upcomingExam['state'] === 'open' ? 'success' : 'info'" :title="$upcomingExam['state'] === 'open' ? __('dashboard.exam_open_title') : __('dashboard.exam_upcoming_title')">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span>{{ $upcomingExam['state'] === 'open'
                        ? __('dashboard.exam_open_body', ['title' => $q->title])
                        : __('dashboard.exam_upcoming_body', ['title' => $q->title, 'when' => $q->starts_at?->format('d/m/Y H:i')]) }}</span>
                    <x-ui.button :href="route('student.exams.index')" size="sm" variant="secondary">{{ __('dashboard.exam_view') }}</x-ui.button>
                </div>
            </x-ui.alert>
        @endif

        @foreach ($resumableAttempts as $attempt)
            <x-ui.alert type="warning" :title="__('dashboard.resume_heading')">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span>{{ $attempt->isOfficial() ? __('dashboard.resume_official') : __('dashboard.resume_practice') }} — {{ $attempt->quiz->title }}</span>
                    <x-ui.button :href="$attempt->isOfficial() ? route('student.attempts.show', $attempt) : route('student.practice.show', $attempt)" size="sm">
                        {{ __('dashboard.resume_button') }}
                    </x-ui.button>
                </div>
            </x-ui.alert>
        @endforeach

        @foreach ($unseenResults as $attempt)
            <x-ui.alert type="success" :title="__('dashboard.new_result_heading')">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span>{{ __('dashboard.new_result_body', ['title' => $attempt->quiz->title]) }}</span>
                    <x-ui.button :href="route('student.results.index')" size="sm" variant="secondary">{{ __('dashboard.new_result_view') }}</x-ui.button>
                </div>
            </x-ui.alert>
        @endforeach

        @if ($practiceSuggestions->isNotEmpty())
            <x-ui.alert type="info" :title="__('dashboard.practice_suggestion_heading')">
                <p class="mb-2">{{ __('dashboard.practice_suggestion_hint') }}</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($practiceSuggestions as $quiz)
                        <form method="POST" action="{{ route('student.practice.start', $quiz) }}">
                            @csrf
                            <x-ui.button type="submit" size="sm" variant="secondary">
                                <x-ui.icon name="practice" class="w-4 h-4" /> {{ $quiz->title }}
                            </x-ui.button>
                        </form>
                    @endforeach
                </div>
            </x-ui.alert>
        @endif
    </div>

    {{-- Metrics — real figures, matching the leaderboard exactly. --}}
    <div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mb-8">
        <x-ui.stat :label="__('dashboard.points')" :value="bn($user->points_balance)" tone="brand" />
        <x-ui.stat :label="__('dashboard.completed_exams')" :value="bn($completed)" tone="ink" />
        <x-ui.stat
            :label="__('dashboard.obtained_marks')"
            :value="$overall ? bn($overall['obtained']) : '—'"
            :sub="$overall ? __('dashboard.total_of', ['total' => bn($overall['possible'])]).' · '.bn(number_format($overall['percentage'], 1)).'%' : null"
            tone="ink" />
        <div class="bg-card border border-line rounded-[--radius-card] shadow-[--shadow-soft] p-5">
            <p class="text-xs font-semibold uppercase tracking-wider text-muted">{{ __('dashboard.overall_rank') }}</p>
            <div class="mt-2 flex items-center gap-2">
                <p class="text-3xl font-black text-amber-500 tabular-nums">{{ $overall ? bn($overall['rank']) : '—' }}</p>
                @if ($rankMovement === 'up')
                    <span class="flex items-center gap-0.5 text-xs font-semibold text-emerald-600" title="{{ __('dashboard.rank_up') }}"><x-ui.icon name="trend-up" class="w-4 h-4" /></span>
                @elseif ($rankMovement === 'down')
                    <span class="flex items-center gap-0.5 text-xs font-semibold text-rose-500" title="{{ __('dashboard.rank_down') }}"><x-ui.icon name="trend-down" class="w-4 h-4" /></span>
                @endif
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Courses shortcut --}}
        <div class="lg:col-span-2 space-y-4">
            @if ($continueLesson || $nextLesson)
                <a href="{{ route('student.courses.show', ($continueLesson ?? $nextLesson)->slug) }}"
                   class="flex items-center gap-4 p-4 bg-brand-tint border border-brand/30 rounded-xl hover:border-brand/60 transition-colors">
                    <div class="w-10 h-10 rounded-lg bg-brand text-brand-ink grid place-items-center shrink-0"><x-ui.icon name="practice" /></div>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-brand">{{ $continueLesson ? __('dashboard.continue_heading') : __('dashboard.next_lesson_heading') }}</p>
                        <p class="font-semibold text-ink truncate">{{ ($continueLesson ?? $nextLesson)->title }}</p>
                    </div>
                    <span class="ml-auto text-sm font-semibold text-brand shrink-0">{{ $continueLesson ? __('dashboard.continue_button') : __('dashboard.next_lesson_button') }}</span>
                </a>
            @endif

            <div class="flex items-center justify-between">
                <h3 class="font-bold text-ink">{{ __('dashboard.courses') }}</h3>
                <a href="{{ route('student.courses.index') }}" class="text-sm text-brand font-semibold hover:underline">{{ __('dashboard.see_all') }}</a>
            </div>
            @forelse ($courses as $course)
                @php $progress = $courseProgress[$course->id]; @endphp
                <a href="{{ route('student.courses.index', ['course' => $course->slug]) }}"
                   class="flex items-center gap-4 p-4 bg-card border border-line rounded-xl hover:border-brand/40 transition-colors">
                    <div class="w-10 h-10 rounded-lg bg-brand-tint text-brand grid place-items-center shrink-0"><x-ui.icon name="courses" /></div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <p class="font-semibold text-ink truncate">{{ $course->title }}</p>
                            @if ($progress['completed'])
                                <x-ui.badge color="success"><x-ui.icon name="flag" class="w-3 h-3" /> {{ __('dashboard.course_completed') }}</x-ui.badge>
                            @endif
                        </div>
                        <p class="text-xs text-muted">{{ __('dashboard.lessons_count', ['count' => bn($course->lessons_count)]) }}</p>
                        @if ($progress['total'] > 0)
                            <div class="mt-2 flex items-center gap-2">
                                <div class="h-1.5 flex-1 rounded-full bg-surface-raised overflow-hidden">
                                    <div class="h-full rounded-full bg-brand" style="width: {{ round($progress['viewed'] / $progress['total'] * 100) }}%"></div>
                                </div>
                                <span class="text-[11px] text-muted shrink-0 tabular-nums">{{ __('dashboard.course_progress', ['viewed' => bn($progress['viewed']), 'total' => bn($progress['total'])]) }}</span>
                            </div>
                        @endif
                    </div>
                    <x-ui.icon name="chevron" class="w-5 h-5 text-muted ml-auto shrink-0" />
                </a>
            @empty
                <x-ui.card><x-ui.empty :title="__('dashboard.no_courses')" /></x-ui.card>
            @endforelse
        </div>

        {{-- Recent results + points --}}
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-ink">{{ __('dashboard.recent_results') }}</h3>
                <a href="{{ route('student.results.index') }}" class="text-sm text-brand font-semibold hover:underline">{{ __('ui.all') }}</a>
            </div>
            <x-ui.card :padded="false" class="divide-y divide-line">
                @forelse ($recent as $attempt)
                    @php $released = $attempt->quiz->resultsReleasedAt(now()); @endphp
                    <a href="{{ $released && $attempt->answer_details_available ? route('student.results.show', $attempt) : route('student.results.index') }}"
                       class="flex items-center justify-between px-4 py-3 hover:bg-surface-raised/50">
                        <div class="min-w-0">
                            <p class="text-sm text-ink truncate">{{ $attempt->quiz->title }}</p>
                            <p class="text-xs text-muted">{{ $attempt->submitted_at?->format('d/m/Y') }}</p>
                        </div>
                        @if ($released)
                            <span class="font-bold text-brand tabular-nums">{{ bn($attempt->final_score) }}/{{ bn($attempt->total_marks_snapshot) }}</span>
                        @else
                            <span class="text-xs text-muted">{{ __('results.group_pending') }}</span>
                        @endif
                    </a>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-muted">{{ __('results.none_yet') }}</div>
                @endforelse
            </x-ui.card>

            <div class="flex items-center justify-between">
                <h3 class="font-bold text-ink">{{ __('dashboard.recent_points') }}</h3>
                <a href="{{ route('student.points') }}" class="text-sm text-brand font-semibold hover:underline">{{ __('ui.all') }}</a>
            </div>
            <x-ui.card :padded="false" class="divide-y divide-line">
                @forelse ($recentPoints as $tx)
                    <div class="flex items-center justify-between px-4 py-3">
                        <div class="min-w-0">
                            <p class="text-sm text-ink truncate">{{ $tx->reason ?? __('dashboard.point_adjustment') }}</p>
                            <p class="text-xs text-muted">{{ $tx->created_at->format('d/m/Y') }}</p>
                        </div>
                        <span class="font-bold {{ $tx->amount >= 0 ? 'text-brand' : 'text-rose-500' }} tabular-nums">
                            {{ $tx->amount >= 0 ? '+' : '' }}{{ bn($tx->amount) }}
                        </span>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-muted">{{ __('dashboard.no_transactions') }}</div>
                @endforelse
            </x-ui.card>
        </div>
    </div>
</x-layout.student>
