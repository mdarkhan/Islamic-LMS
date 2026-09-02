<x-layout.admin :title="__('ui.search')" :heading="__('ui.search')">
    <form method="GET" action="{{ route('admin.search.index') }}" class="relative mb-8 max-w-xl">
        <x-ui.icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
        <input name="q" value="{{ $q }}" autofocus placeholder="{{ __('admin.search_placeholder') }}"
               class="w-full rounded-xl bg-surface-raised border border-line pl-9 pr-4 py-2.5 text-sm outline-none focus:border-brand">
    </form>

    @if ($q === '')
        <x-ui.card><x-ui.empty :title="__('admin.search_prompt')" /></x-ui.card>
    @elseif (collect($results)->every(fn ($r) => $r->isEmpty()))
        <x-ui.card><x-ui.empty :title="__('ui.nothing_found')" /></x-ui.card>
    @else
        <div class="space-y-8">
            @if (($results['students'] ?? collect())->isNotEmpty())
                <section>
                    <h2 class="text-sm font-bold uppercase tracking-wider text-muted mb-3">{{ __('nav.students') }}</h2>
                    <div class="space-y-2">
                        @foreach ($results['students'] as $student)
                            <a href="{{ route('admin.students.show', $student) }}" class="flex items-center justify-between p-3 rounded-xl border border-line bg-card hover:border-brand/40">
                                <span class="font-semibold text-ink">{{ $student->name }}</span>
                                <span class="text-xs text-muted tabular-nums">{{ bn($student->roll) }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            @if (($results['quizzes'] ?? collect())->isNotEmpty())
                <section>
                    <h2 class="text-sm font-bold uppercase tracking-wider text-muted mb-3">{{ __('nav.quizzes') }}</h2>
                    <div class="space-y-2">
                        @foreach ($results['quizzes'] as $quiz)
                            <a href="{{ route('admin.quizzes.edit', $quiz) }}" class="block p-3 rounded-xl border border-line bg-card hover:border-brand/40 font-semibold text-ink">
                                {{ $quiz->title }}
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            @if (($results['courses'] ?? collect())->isNotEmpty())
                <section>
                    <h2 class="text-sm font-bold uppercase tracking-wider text-muted mb-3">{{ __('nav.courses') }}</h2>
                    <div class="space-y-2">
                        @foreach ($results['courses'] as $course)
                            <a href="{{ route('admin.courses.edit', $course) }}" class="block p-3 rounded-xl border border-line bg-card hover:border-brand/40 font-semibold text-ink">
                                {{ $course->title }}
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            @if (($results['books'] ?? collect())->isNotEmpty())
                <section>
                    <h2 class="text-sm font-bold uppercase tracking-wider text-muted mb-3">{{ __('nav.books') }}</h2>
                    <div class="space-y-2">
                        @foreach ($results['books'] as $book)
                            <a href="{{ route('admin.books.edit', $book) }}" class="block p-3 rounded-xl border border-line bg-card hover:border-brand/40 font-semibold text-ink">
                                {{ $book->title }}
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            @if (($results['posts'] ?? collect())->isNotEmpty())
                <section>
                    <h2 class="text-sm font-bold uppercase tracking-wider text-muted mb-3">{{ __('nav.posts') }}</h2>
                    <div class="space-y-2">
                        @foreach ($results['posts'] as $post)
                            <a href="{{ route('admin.posts.edit', $post) }}" class="block p-3 rounded-xl border border-line bg-card hover:border-brand/40 font-semibold text-ink">
                                {{ $post->title }}
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    @endif
</x-layout.admin>
