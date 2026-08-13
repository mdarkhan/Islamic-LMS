<x-layout.public title="হোম">
    @if ($notice)
        <div class="bg-brand text-brand-ink">
            <div class="max-w-6xl mx-auto px-4 py-2.5 text-sm font-medium flex items-center gap-2 justify-center text-center">
                <x-ui.icon name="dot" class="w-4 h-4 shrink-0" />
                <span>{{ $notice->body }}</span>
            </div>
        </div>
    @endif

    {{-- Hero --}}
    <section class="relative overflow-hidden border-b border-line">
        <div class="absolute inset-0 geo-accent opacity-60" aria-hidden="true"></div>
        <div class="relative max-w-6xl mx-auto px-4 sm:px-6 py-16 sm:py-24 text-center animate-rise">
            <p class="font-arabic text-2xl sm:text-3xl text-brand mb-6">بِسْمِ ٱللَّٰهِ ٱلرَّحْمَٰنِ ٱلرَّحِيمِ</p>
            <h1 class="text-3xl sm:text-5xl font-black text-ink leading-tight max-w-3xl mx-auto">
                কুরআন তাফসির ও <span class="text-brand">সীরাত</span> কোর্স
            </h1>
            <p class="mt-5 text-muted max-w-xl mx-auto leading-relaxed">
                কুরআনের গভীর জ্ঞান এবং রাসূল ﷺ-এর জীবনের শিক্ষণীয় ঘটনাবলী জানতে আমাদের এই আয়োজন।
                নিয়মিত ক্লাস, পরীক্ষা ও মেধাতালিকার মাধ্যমে নিজেকে যাচাই করুন।
            </p>
            <div class="mt-8 flex items-center justify-center gap-3">
                @guest
                    <x-ui.button :href="route('login')" size="lg">প্রবেশ করুন</x-ui.button>
                @else
                    <x-ui.button :href="auth()->user()->isAdmin() ? route('admin.dashboard') : route('student.dashboard')" size="lg">
                        ড্যাশবোর্ডে যান
                    </x-ui.button>
                @endguest
            </div>
            <p class="mt-6 text-sm text-muted">
                <span class="font-bold text-ink">{{ bn($lessonCount) }}</span> টি ক্লাস · <span class="font-bold text-ink">{{ bn($courses->count()) }}</span> টি কোর্স
            </p>
        </div>
    </section>

    {{-- Courses --}}
    <section class="max-w-6xl mx-auto px-4 sm:px-6 py-14">
        <h2 class="text-xl font-bold text-ink mb-6">কোর্সসমূহ</h2>
        @if ($courses->isEmpty())
            <x-ui.empty title="এখনো কোনো কোর্স প্রকাশ করা হয়নি।" />
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($courses as $course)
                    <x-ui.card class="hover:border-brand/40 transition-colors">
                        <div class="flex items-start justify-between gap-3">
                            <div class="w-11 h-11 rounded-xl bg-brand-tint text-brand grid place-items-center shrink-0">
                                <x-ui.icon name="courses" />
                            </div>
                            <x-ui.badge color="brand">{{ bn($course->lessons_count) }} টি ক্লাস</x-ui.badge>
                        </div>
                        <h3 class="mt-4 font-bold text-ink text-lg">{{ $course->title }}</h3>
                        @if ($course->description)
                            <p class="mt-1 text-sm text-muted line-clamp-2">{{ $course->description }}</p>
                        @endif
                    </x-ui.card>
                @endforeach
            </div>
            <p class="mt-6 text-sm text-muted text-center">
                ক্লাসের অডিও ও রিসোর্স দেখতে <a href="{{ route('login') }}" class="text-brand font-semibold hover:underline">প্রবেশ করুন</a>।
            </p>
        @endif
    </section>
</x-layout.public>
