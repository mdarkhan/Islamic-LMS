<x-layout.student :title="__('nav.dashboard')" :heading="__('nav.dashboard')">
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

    {{-- Metrics --}}
    <div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mb-8">
        <x-ui.stat :label="__('dashboard.points')" :value="bn($user->points_balance)" tone="brand" />
        <x-ui.stat :label="__('dashboard.completed_exams')" :value="bn($completed)" tone="ink" />
        <x-ui.stat :label="__('dashboard.obtained_marks')" :value="bn($obtained)" :sub="__('dashboard.total_of', ['total' => bn($possible)])" tone="ink" />
        <x-ui.stat :label="__('dashboard.average_percentage')" :value="$percentage !== null ? bn($percentage).'%' : '—'" tone="amber" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Courses shortcut --}}
        <div class="lg:col-span-2 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-ink">{{ __('dashboard.courses') }}</h3>
                <a href="{{ route('student.courses.index') }}" class="text-sm text-brand font-semibold hover:underline">{{ __('dashboard.see_all') }}</a>
            </div>
            @forelse ($courses as $course)
                <a href="{{ route('student.courses.index', ['course' => $course->slug]) }}"
                   class="flex items-center gap-4 p-4 bg-card border border-line rounded-xl hover:border-brand/40 transition-colors">
                    <div class="w-10 h-10 rounded-lg bg-brand-tint text-brand grid place-items-center shrink-0"><x-ui.icon name="courses" /></div>
                    <div class="min-w-0">
                        <p class="font-semibold text-ink truncate">{{ $course->title }}</p>
                        <p class="text-xs text-muted">{{ __('dashboard.lessons_count', ['count' => bn($course->lessons_count)]) }}</p>
                    </div>
                    <x-ui.icon name="chevron" class="w-5 h-5 text-muted ml-auto" />
                </a>
            @empty
                <x-ui.card><x-ui.empty :title="__('dashboard.no_courses')" /></x-ui.card>
            @endforelse
        </div>

        {{-- Recent points --}}
        <div class="space-y-4">
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
