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

                    <form method="GET" action="{{ route('search.index') }}" class="mt-5 max-w-md flex gap-2">
                        <x-ui.input type="search" name="q" placeholder="{{ __('public.search_placeholder') }}" class="flex-1 bg-surface" />
                        <x-ui.button type="submit" variant="secondary"><x-ui.icon name="search" class="w-4 h-4" /></x-ui.button>
                    </form>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <x-ui.button :href="route('blog.index')"><x-ui.icon name="results" class="w-4 h-4" /> {{ __('public.hero_browse') }}</x-ui.button>
                        <x-ui.button :href="route('login')" variant="secondary">{{ __('public.hero_login') }}</x-ui.button>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <x-ui.date-widget :calendar="$calendar" />
                <x-ui.islamic-events-widget :events="$upcomingEvents" />
                <x-ui.notice-banner :notices="$notices" />
            </div>
        </section>

        {{-- Stats strip --}}
        <section class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="rounded-2xl border border-line bg-card p-4 text-center">
                <p class="text-2xl font-black text-brand tabular-nums">{{ bn($stats['students']) }}</p>
                <p class="text-xs text-muted mt-1">{{ __('public.stats_students') }}</p>
            </div>
            <div class="rounded-2xl border border-line bg-card p-4 text-center">
                <p class="text-2xl font-black text-brand tabular-nums">{{ bn($stats['lessons']) }}</p>
                <p class="text-xs text-muted mt-1">{{ __('public.stats_lessons') }}</p>
            </div>
            <div class="rounded-2xl border border-line bg-card p-4 text-center">
                <p class="text-2xl font-black text-brand tabular-nums">{{ bn($stats['articles']) }}</p>
                <p class="text-xs text-muted mt-1">{{ __('public.stats_articles') }}</p>
            </div>
            <div class="rounded-2xl border border-line bg-card p-4 text-center">
                <p class="text-2xl font-black text-brand tabular-nums">{{ bn($stats['books']) }}</p>
                <p class="text-xs text-muted mt-1">{{ __('public.stats_books') }}</p>
            </div>
        </section>

        {{-- About the Ustaz --}}
        @if (filled($about['about_bio']))
            <section class="rounded-3xl border border-line bg-card p-6 sm:p-8">
                <div class="grid gap-6 sm:grid-cols-[auto_1fr] items-start">
                    @if ($about['about_photo'])
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($about['about_photo']) }}" alt=""
                             class="w-24 h-24 sm:w-32 sm:h-32 rounded-2xl object-cover border border-line shrink-0">
                    @endif
                    <div>
                        <h2 class="text-xl font-black text-ink mb-2">{{ __('public.about_heading') }}</h2>
                        <p class="text-muted leading-relaxed whitespace-pre-line">{{ $about['about_bio'] }}</p>
                    </div>
                </div>
            </section>
        @endif

        {{-- Upcoming exam banner --}}
        @if ($upcomingQuiz)
            @php $isOpen = $upcomingQuiz->officialState(now()) === \App\Models\Quiz::STATE_OPEN; @endphp
            <section class="rounded-2xl border border-brand/30 bg-brand-tint/40 p-4 sm:p-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <x-ui.badge :color="$isOpen ? 'success' : 'info'">{{ $isOpen ? __('public.exam_banner_live') : __('public.exam_banner_soon') }}</x-ui.badge>
                    <p class="font-bold text-ink mt-1.5">{{ $upcomingQuiz->title }}</p>
                    @if ($upcomingQuiz->course)
                        <p class="text-xs text-muted">{{ $upcomingQuiz->course->title }}</p>
                    @endif
                </div>
                <x-ui.button :href="route('login')" size="sm">{{ __('public.exam_banner_cta') }}</x-ui.button>
            </section>
        @endif

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

        {{-- Books --}}
        <section id="books" class="scroll-mt-20">
            <div class="flex items-end justify-between mb-4">
                <div>
                    <h2 class="text-xl font-black text-ink">{{ __('public.books_heading') }}</h2>
                    <p class="text-sm text-muted">{{ __('public.books_intro') }}</p>
                </div>
            </div>
            <div class="grid gap-4 grid-cols-2 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ($books as $book)
                    <a href="{{ route('books.show', $book) }}" class="group rounded-2xl border border-line bg-card p-3 hover:border-brand/40 transition-colors">
                        <div class="relative aspect-[3/4] rounded-xl overflow-hidden bg-brand-tint">
                            @if ($book->coverUrl())
                                <img src="{{ $book->coverUrl() }}" alt="{{ $book->title }}" class="absolute inset-0 w-full h-full object-cover">
                            @else
                                <div class="absolute inset-0 geo-accent opacity-60" aria-hidden="true"></div>
                                <div class="absolute inset-0 grid place-items-center text-brand/70">
                                    <x-ui.icon name="book" class="w-10 h-10" />
                                </div>
                                <div class="absolute inset-x-0 bottom-0 bg-brand-strong/90 px-2.5 py-2">
                                    <p class="text-xs font-bold text-white leading-snug line-clamp-3">{{ $book->title }}</p>
                                </div>
                            @endif
                        </div>
                        <p class="text-xs font-semibold text-ink mt-2 truncate">{{ $book->title }}</p>
                        <p class="text-xs text-muted truncate">{{ __('public.books_by') }}: {{ $book->author }}</p>
                    </a>
                @endforeach
            </div>
        </section>

        {{-- Featured Fatwa --}}
        @if ($featuredPost)
            <section>
                <h2 class="text-xl font-black text-ink mb-4">{{ __('public.featured_fatwa_heading') }}</h2>
                <a href="{{ route('blog.show', $featuredPost) }}" class="block rounded-2xl border border-brand/30 bg-brand-tint/30 p-5 sm:p-6 hover:border-brand/50 transition-colors">
                    <x-ui.badge color="brand">{{ $featuredPost->category->name }}</x-ui.badge>
                    <h3 class="font-bold text-ink mt-2 text-lg leading-snug">{{ $featuredPost->question ?: $featuredPost->title }}</h3>
                    <p class="text-sm text-muted mt-1.5 line-clamp-2">{{ $featuredPost->excerptText(160) }}</p>
                </a>
            </section>
        @endif

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

        {{-- FAQ --}}
        @if ($faqs->isNotEmpty())
            <section x-data="{ open: null }">
                <h2 class="text-xl font-black text-ink mb-4">{{ __('public.faq_heading') }}</h2>
                <div class="rounded-2xl border border-line bg-card divide-y divide-line overflow-hidden">
                    @foreach ($faqs as $i => $faq)
                        <div>
                            <button type="button" @click="open = (open === {{ $i }} ? null : {{ $i }})"
                                    class="w-full flex items-center justify-between gap-3 text-start px-5 py-4">
                                <span class="font-semibold text-ink">{{ $faq->question }}</span>
                                <span class="shrink-0 transition-transform" :class="{ 'rotate-90': open === {{ $i }} }">
                                    <x-ui.icon name="chevron" class="w-4 h-4 text-muted" />
                                </span>
                            </button>
                            <div x-show="open === {{ $i }}" x-transition x-cloak class="px-5 pb-4 text-sm text-muted leading-relaxed whitespace-pre-line">
                                {{ $faq->answer }}
                            </div>
                        </div>
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

        {{-- Telegram CTA --}}
        @if ($telegramUrl)
            <section class="rounded-2xl border border-line bg-card p-5 sm:p-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="font-bold text-ink">{{ __('public.telegram_cta') }}</p>
                    <p class="text-sm text-muted">{{ __('public.telegram_cta_sub') }}</p>
                </div>
                <x-ui.button :href="$telegramUrl" variant="secondary">{{ __('public.telegram_cta_button') }}</x-ui.button>
            </section>
        @endif

        {{-- Student CTA --}}
        <section class="rounded-3xl border border-line bg-brand-tint/40 p-6 sm:p-8 text-center">
            <h2 class="text-xl font-black text-ink">{{ __('public.student_cta_title') }}</h2>
            <p class="text-muted mt-1">{{ __('public.student_cta_sub') }}</p>
            <div class="mt-4"><x-ui.button :href="route('login')">{{ __('public.student_cta_button') }}</x-ui.button></div>
        </section>
    </div>
</x-layout.public>
