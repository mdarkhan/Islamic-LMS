<x-layout.public :description="__('public.hero_subtitle')" :canonical="route('home')">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 sm:py-12 space-y-12">

        {{-- Hero + date --}}
        <section class="grid gap-6 lg:grid-cols-[1.6fr_1fr] items-start">
            <div class="relative overflow-hidden rounded-3xl border border-line bg-card p-6 sm:p-10">
                <div class="absolute inset-0 geo-accent opacity-40" aria-hidden="true"></div>
                <div class="relative">
                    <p class="text-brand font-arabic text-lg mb-3">بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ</p>
                    <h1 class="text-3xl sm:text-4xl font-black text-ink leading-tight">{{ __('public.hero_title') }}</h1>
                    <p class="mt-3 text-muted max-w-xl leading-relaxed">{{ __('public.hero_subtitle') }}</p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <x-ui.button :href="route('blog.index')"><x-ui.icon name="results" class="w-4 h-4" /> {{ __('public.hero_browse') }}</x-ui.button>
                        <x-ui.button :href="route('login')" variant="secondary">{{ __('public.hero_login') }}</x-ui.button>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <x-ui.date-widget :calendar="$calendar" />
                <x-ui.notice-banner :notices="$notices" />
            </div>
        </section>

        {{-- Courses overview --}}
        <section id="courses" class="scroll-mt-20">
            <div class="flex items-end justify-between mb-4">
                <div>
                    <h2 class="text-xl font-black text-ink">{{ __('public.courses_heading') }}</h2>
                    <p class="text-sm text-muted">{{ __('public.courses_intro') }}</p>
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($courses as $course)
                    <div class="rounded-2xl border border-line bg-card p-5">
                        <div class="w-10 h-10 rounded-xl bg-brand-tint text-brand grid place-items-center mb-3"><x-ui.icon name="courses" /></div>
                        <h3 class="font-bold text-ink">{{ $course->title }}</h3>
                        <p class="text-xs text-muted mt-0.5">{{ __('public.courses_lessons', ['count' => bn($course->lessons_count)]) }}</p>
                    </div>
                @endforeach
            </div>
            <p class="text-xs text-muted mt-3">{{ __('public.courses_login_note') }}</p>
        </section>

        {{-- Recent articles --}}
        @if ($recentPosts->isNotEmpty())
            <section>
                <div class="flex items-end justify-between mb-4">
                    <h2 class="text-xl font-black text-ink">{{ __('public.articles_heading') }}</h2>
                    <a href="{{ route('blog.index') }}" class="text-sm text-brand font-semibold hover:underline">{{ __('public.articles_all') }}</a>
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    @foreach ($recentPosts as $post)
                        <a href="{{ route('blog.show', $post) }}" class="block rounded-2xl border border-line bg-card p-5 hover:border-brand/40 transition-colors">
                            <x-ui.badge color="brand">{{ $post->category->name }}</x-ui.badge>
                            <h3 class="font-bold text-ink mt-2 leading-snug">{{ $post->title }}</h3>
                            <p class="text-sm text-muted mt-1 line-clamp-2">{{ $post->excerptText(120) }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Islamic tools --}}
        <section>
            <h2 class="text-xl font-black text-ink mb-4">{{ __('public.utilities_heading') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <a href="{{ route('zakat.index') }}" class="flex items-center gap-4 rounded-2xl border border-line bg-card p-5 hover:border-brand/40 transition-colors">
                    <div class="w-11 h-11 rounded-xl bg-brand-tint text-brand grid place-items-center shrink-0"><x-ui.icon name="points" /></div>
                    <div><p class="font-bold text-ink">{{ __('public.zakat_cta') }}</p><p class="text-sm text-muted">{{ __('public.zakat_cta_sub') }}</p></div>
                </a>
                <a href="{{ route('ask-ustaz.show') }}" class="flex items-center gap-4 rounded-2xl border border-line bg-card p-5 hover:border-brand/40 transition-colors">
                    <div class="w-11 h-11 rounded-xl bg-brand-tint text-brand grid place-items-center shrink-0"><x-ui.icon name="profile" /></div>
                    <div><p class="font-bold text-ink">{{ __('public.ask_cta') }}</p><p class="text-sm text-muted">{{ __('public.ask_cta_sub') }}</p></div>
                </a>
            </div>
        </section>

        {{-- Student CTA --}}
        <section class="rounded-3xl border border-line bg-brand-tint/40 p-6 sm:p-8 text-center">
            <h2 class="text-xl font-black text-ink">{{ __('public.student_cta_title') }}</h2>
            <p class="text-muted mt-1">{{ __('public.student_cta_sub') }}</p>
            <div class="mt-4"><x-ui.button :href="route('login')">{{ __('public.student_cta_button') }}</x-ui.button></div>
        </section>
    </div>
</x-layout.public>
