<x-layout.student :title="__('nav.courses')" :heading="__('courses.heading')">
    {{-- Filters --}}
    <form method="GET" class="flex flex-col sm:flex-row gap-3 mb-6">
        <div class="flex gap-2 overflow-x-auto hide-scrollbar pb-1">
            <a href="{{ route('student.courses.index', ['q' => $search]) }}"
               class="px-4 py-2 rounded-full text-sm font-semibold whitespace-nowrap border transition-colors {{ ! $courseFilter ? 'bg-brand text-brand-ink border-brand' : 'bg-card text-muted border-line hover:text-ink' }}">
                {{ __('courses.all') }}
            </a>
            @foreach ($courses as $course)
                <a href="{{ route('student.courses.index', ['course' => $course->slug, 'q' => $search]) }}"
                   class="px-4 py-2 rounded-full text-sm font-semibold whitespace-nowrap border transition-colors {{ $courseFilter === $course->slug ? 'bg-brand text-brand-ink border-brand' : 'bg-card text-muted border-line hover:text-ink' }}">
                    {{ $course->title }}
                </a>
            @endforeach
        </div>
        <div class="relative sm:ml-auto sm:w-64 shrink-0">
            <x-ui.icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
            <input name="q" value="{{ $search }}" placeholder="{{ __('courses.search_placeholder') }}"
                   class="w-full rounded-xl bg-surface-raised border border-line pl-9 pr-4 py-2.5 text-sm outline-none focus:border-brand">
            @if ($courseFilter)<input type="hidden" name="course" value="{{ $courseFilter }}">@endif
        </div>
    </form>

    @if ($lessons->isEmpty())
        <x-ui.card><x-ui.empty :title="__('courses.none_found')">{{ __('courses.none_found_hint') }}</x-ui.empty></x-ui.card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($lessons as $lesson)
                <a href="{{ route('student.courses.show', $lesson->slug) }}"
                   class="group bg-card border border-line rounded-[--radius-card] shadow-[--shadow-soft] p-5 hover:border-brand/40 hover:-translate-y-0.5 transition-all">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-1.5 min-w-0">
                            <x-ui.badge color="neutral">{{ $lesson->course->title }}</x-ui.badge>
                            @if ($lesson->video_url)
                                <x-ui.badge color="brand"><x-ui.icon name="video" class="w-3 h-3" /></x-ui.badge>
                            @endif
                        </div>
                        <span class="text-xs text-muted flex items-center gap-1 shrink-0"><x-ui.icon name="clock" class="w-3.5 h-3.5" />{{ $lesson->duration_label ?? '—' }}</span>
                    </div>
                    <h3 class="font-bold text-ink group-hover:text-brand transition-colors">{{ $lesson->title }}</h3>
                    @if ($lesson->description)<p class="mt-1.5 text-sm text-muted line-clamp-2">{{ $lesson->description }}</p>@endif
                    <p class="mt-3 text-xs text-muted flex items-center gap-1"><x-ui.icon name="calendar" class="w-3.5 h-3.5" />{{ $lesson->date_label ?? '—' }}</p>
                </a>
            @endforeach
        </div>
        <div class="mt-6">{{ $lessons->links('components.pagination') }}</div>
    @endif
</x-layout.student>
