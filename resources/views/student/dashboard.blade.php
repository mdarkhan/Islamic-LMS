<x-layout.student title="ড্যাশবোর্ড" heading="ড্যাশবোর্ড">
    {{-- Welcome --}}
    <x-ui.card class="relative overflow-hidden mb-6">
        <div class="absolute inset-0 geo-accent opacity-50" aria-hidden="true"></div>
        <div class="relative">
            <p class="text-sm text-muted">আসসালামু আলাইকুম</p>
            <h2 class="text-2xl font-black text-ink mt-1">{{ $user->name }}</h2>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <x-ui.badge color="neutral">রোল: {{ bn($user->roll) }}</x-ui.badge>
                <x-ui.badge color="success">সক্রিয়</x-ui.badge>
            </div>
        </div>
    </x-ui.card>

    {{-- Metrics --}}
    <div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mb-8">
        <x-ui.stat label="পয়েন্ট" :value="bn($user->points_balance)" tone="brand" />
        <x-ui.stat label="সম্পন্ন পরীক্ষা" :value="bn($completed)" tone="ink" />
        <x-ui.stat label="প্রাপ্ত নম্বর" :value="bn($obtained)" sub="মোট {{ bn($possible) }}" tone="ink" />
        <x-ui.stat label="গড় শতকরা" :value="$percentage !== null ? bn($percentage).'%' : '—'" tone="amber" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Courses shortcut --}}
        <div class="lg:col-span-2 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-ink">কোর্সসমূহ</h3>
                <a href="{{ route('student.courses.index') }}" class="text-sm text-brand font-semibold hover:underline">সব দেখুন</a>
            </div>
            @forelse ($courses as $course)
                <a href="{{ route('student.courses.index', ['course' => $course->slug]) }}"
                   class="flex items-center gap-4 p-4 bg-card border border-line rounded-xl hover:border-brand/40 transition-colors">
                    <div class="w-10 h-10 rounded-lg bg-brand-tint text-brand grid place-items-center shrink-0"><x-ui.icon name="courses" /></div>
                    <div class="min-w-0">
                        <p class="font-semibold text-ink truncate">{{ $course->title }}</p>
                        <p class="text-xs text-muted">{{ bn($course->lessons_count) }} টি ক্লাস</p>
                    </div>
                    <x-ui.icon name="chevron" class="w-5 h-5 text-muted ml-auto" />
                </a>
            @empty
                <x-ui.card><x-ui.empty title="এখনো কোনো কোর্স নেই।" /></x-ui.card>
            @endforelse
        </div>

        {{-- Recent points --}}
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-ink">সাম্প্রতিক পয়েন্ট</h3>
                <a href="{{ route('student.points') }}" class="text-sm text-brand font-semibold hover:underline">সব</a>
            </div>
            <x-ui.card :padded="false" class="divide-y divide-line">
                @forelse ($recentPoints as $tx)
                    <div class="flex items-center justify-between px-4 py-3">
                        <div class="min-w-0">
                            <p class="text-sm text-ink truncate">{{ $tx->reason ?? 'পয়েন্ট সমন্বয়' }}</p>
                            <p class="text-xs text-muted">{{ $tx->created_at->format('d/m/Y') }}</p>
                        </div>
                        <span class="font-bold {{ $tx->amount >= 0 ? 'text-brand' : 'text-rose-500' }} tabular-nums">
                            {{ $tx->amount >= 0 ? '+' : '' }}{{ bn($tx->amount) }}
                        </span>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-muted">এখনো কোনো লেনদেন নেই।</div>
                @endforelse
            </x-ui.card>
        </div>
    </div>
</x-layout.student>
