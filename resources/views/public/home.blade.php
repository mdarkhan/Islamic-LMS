<x-layout.public :description="__('public.hero_subtitle')" :canonical="route('home')">

    {{-- Hero: full-bleed, so it reads as the page's visual anchor rather than another
         bordered card in a column of identical bordered cards. --}}
    <section class="relative overflow-hidden border-b border-line">
        <div class="absolute inset-0 bg-gradient-to-br from-brand-tint via-surface to-surface" aria-hidden="true"></div>
        <div class="absolute inset-0 geo-accent opacity-60" aria-hidden="true"></div>

        <div class="relative max-w-6xl mx-auto px-4 sm:px-6 pt-10 sm:pt-16 pb-16 sm:pb-20">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start">
                <div class="lg:col-span-7">
                    {{-- Bismillah badge --}}
                    <div class="inline-flex items-center gap-2.5 px-4 py-1.5 rounded-full bg-card border border-line w-fit mb-5 shadow-[--shadow-soft]">
                        <span class="w-2 h-2 rounded-full bg-brand animate-pulse" aria-hidden="true"></span>
                        <span class="font-arabic text-brand text-lg sm:text-xl font-bold" dir="rtl">بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ</span>
                    </div>

                    <h1 class="text-4xl sm:text-5xl font-black text-ink leading-[1.15] tracking-tight">
                        {{ __('public.hero_title_pre') }}<span class="text-brand relative inline-block">{{ __('public.hero_title_emphasis') }}
                            <svg class="absolute -bottom-1.5 left-0 w-full text-brand/40" viewBox="0 0 200 8" fill="none" aria-hidden="true"><path d="M1 5.5C40 2 160 2 199 5.5" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                        </span>
                    </h1>
                    <p class="mt-5 text-base sm:text-lg text-muted max-w-xl leading-relaxed">{{ __('public.hero_subtitle') }}</p>

                    <form method="GET" action="{{ route('search.index') }}" class="mt-7 max-w-lg">
                        <div class="relative flex items-center">
                            <input type="search" name="q" placeholder="{{ __('public.search_placeholder') }}"
                                   class="w-full bg-card pl-5 pr-14 py-3.5 rounded-2xl border border-line shadow-[--shadow-soft] text-ink placeholder-muted text-base outline-none focus:border-brand focus:ring-4 focus:ring-brand/10 transition">
                            <button type="submit" aria-label="{{ __('public.search_heading') }}"
                                    class="absolute right-2 bg-brand hover:bg-brand-strong text-brand-ink p-2.5 rounded-xl transition-colors">
                                <x-ui.icon name="search" class="w-5 h-5" />
                            </button>
                        </div>
                    </form>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <x-ui.button :href="route('blog.index')" size="lg"><x-ui.icon name="results" class="w-4 h-4" /> {{ __('public.hero_browse') }}</x-ui.button>
                        <x-ui.button :href="route('login')" variant="secondary" size="lg"><x-ui.icon name="profile" class="w-4 h-4" /> {{ __('public.hero_login') }}</x-ui.button>
                    </div>
                </div>

                <div class="lg:col-span-5 flex flex-col gap-4">
                    <x-ui.date-widget :calendar="$calendar" class="shadow-[--shadow-soft]" />
                    <x-ui.islamic-events-widget :events="$upcomingEvents" class="shadow-[--shadow-soft]" />
                </div>
            </div>

            {{-- Notices: the mockup this hero follows has no slot for them, but they're a
                 real, tested feature (server-windowed, priority-ordered) — a slim strip
                 under the two-column layout keeps them visible without crowding either
                 card. --}}
            @if ($notices->isNotEmpty())
                <div class="relative mt-6 max-w-3xl">
                    <x-ui.notice-banner :notices="$notices" />
                </div>
            @endif
        </div>
    </section>

    {{-- Stats: one floating bar overlapping the hero's bottom edge, ties the two
         sections together instead of sitting as a fifth identical card. --}}
    <div class="max-w-6xl mx-auto px-4 sm:px-6 -mt-8 sm:-mt-10 relative z-10">
        <div class="rounded-2xl border border-line bg-card shadow-[--shadow-soft] overflow-hidden grid grid-cols-2 sm:grid-cols-4 divide-x divide-y sm:divide-y-0 divide-line">
            @foreach ([
                ['icon' => 'students', 'value' => $stats['students'], 'label' => __('public.stats_students')],
                ['icon' => 'courses', 'value' => $stats['courses'], 'label' => __('public.stats_courses')],
                ['icon' => 'lessons', 'value' => $stats['lessons'], 'label' => __('public.stats_lessons')],
                ['icon' => 'book', 'value' => $stats['books'], 'label' => __('public.stats_books')],
            ] as $stat)
                <div class="group p-5 sm:p-6 text-center hover:bg-brand-tint/40 transition-colors">
                    <div class="mx-auto w-10 h-10 rounded-full bg-brand-tint text-brand grid place-items-center mb-2.5 group-hover:scale-110 transition-transform">
                        <x-ui.icon :name="$stat['icon']" class="w-4 h-4" />
                    </div>
                    {{-- data-count-up: app.js animates 0 → value (locale-aware digits) the
                         first time this scrolls into view. The server-rendered {{ bn(...) }}
                         is the real, correct value from the start — JS only replaces it with
                         an animated version; without JS (or before it runs) the right number
                         is already there, just static. --}}
                    <p class="text-2xl sm:text-3xl font-black text-ink tabular-nums" data-count-up="{{ $stat['value'] }}">{{ bn($stat['value']) }}</p>
                    <p class="text-xs sm:text-sm text-muted mt-0.5">{{ $stat['label'] }}</p>
                </div>
            @endforeach
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 pt-12 sm:pt-16 pb-12 sm:pb-16 space-y-16 sm:space-y-20">

        {{-- About the Ustaz --}}
        @if (filled($about['about_bio']))
            <section data-reveal class="rounded-3xl border border-line bg-card p-6 sm:p-8">
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
            <section data-reveal @class([
                'rounded-2xl border p-5 sm:p-6 flex flex-wrap items-center justify-between gap-4',
                'border-emerald-300/60 bg-emerald-50 dark:border-emerald-500/30 dark:bg-emerald-500/10' => $isOpen,
                'border-brand/30 bg-brand-tint/40' => ! $isOpen,
            ])>
                <div class="flex items-center gap-4">
                    <div class="w-11 h-11 rounded-xl bg-card text-brand grid place-items-center shrink-0 border border-line">
                        <x-ui.icon name="exam" class="w-5 h-5" />
                    </div>
                    <div>
                        <x-ui.badge :color="$isOpen ? 'success' : 'info'">{{ $isOpen ? __('public.exam_banner_live') : __('public.exam_banner_soon') }}</x-ui.badge>
                        <p class="font-bold text-ink mt-1.5">{{ $upcomingQuiz->title }}</p>
                        @if ($upcomingQuiz->course)
                            <p class="text-xs text-muted">{{ $upcomingQuiz->course->title }}</p>
                        @endif
                    </div>
                </div>
                <x-ui.button :href="route('login')" size="sm">{{ __('public.exam_banner_cta') }}</x-ui.button>
            </section>
        @endif

        {{-- Courses overview --}}
        <section id="courses" data-reveal class="scroll-mt-20">
            <div class="flex items-center gap-3 mb-6">
                <span class="w-1.5 h-8 rounded-full bg-brand shrink-0" aria-hidden="true"></span>
                <div>
                    <h2 class="text-2xl sm:text-3xl font-black text-ink">{{ __('public.courses_heading') }}</h2>
                    <p class="text-sm text-muted mt-0.5">{{ __('public.courses_intro') }}</p>
                </div>
            </div>
            {{-- Per-course icon, matched to the reference design's choice for each of the
                 institution's actual courses (book / document / microphone / group) — keyed
                 by slug since that's the stable identifier, not the Bengali title. A course
                 outside this set (none exist today) just falls back to the book icon. --}}
            @php $courseIcons = ['seerat' => 'courses', 'tafsir' => 'document', 'jummah' => 'microphone', 'halakah' => 'group']; @endphp
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($courses as $course)
                    <div class="group rounded-2xl border border-line bg-card p-5 sm:p-6 hover:border-brand/40 hover:shadow-[--shadow-soft] transition-all flex flex-col justify-between">
                        <div>
                            <div class="w-11 h-11 rounded-xl bg-brand-tint text-brand grid place-items-center mb-5 group-hover:bg-brand group-hover:text-brand-ink transition-colors">
                                <x-ui.icon :name="$courseIcons[$course->slug] ?? 'courses'" class="w-6 h-6" />
                            </div>
                            <h3 class="font-bold text-ink text-lg leading-snug group-hover:text-brand-strong transition-colors">{{ $course->title }}</h3>
                            @if ($course->description)
                                <p class="text-sm text-muted mt-1.5 leading-relaxed line-clamp-2">{{ $course->description }}</p>
                            @endif
                        </div>
                        <div class="mt-6 pt-4 border-t border-line flex items-center justify-between gap-2 text-xs">
                            <span class="flex items-center gap-1.5 font-medium text-muted">
                                <x-ui.icon name="lessons" class="w-3.5 h-3.5" /> {{ __('public.courses_lessons', ['count' => bn($course->lessons_count)]) }}
                            </span>
                            <span class="text-brand font-semibold flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                                {{ __('public.courses_cta') }} →
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="text-xs text-muted mt-5 flex items-center gap-1.5">
                <x-ui.icon name="key" class="w-3.5 h-3.5 shrink-0" /> {{ __('public.courses_login_note') }}
            </p>
        </section>

        {{-- Books --}}
        <section id="books" data-reveal class="scroll-mt-20">
            <div class="flex items-center gap-3 mb-6">
                <span class="w-1.5 h-8 rounded-full bg-brand shrink-0" aria-hidden="true"></span>
                <div>
                    <h2 class="text-2xl sm:text-3xl font-black text-ink">{{ __('public.books_heading') }}</h2>
                    <p class="text-sm text-muted mt-0.5">{{ __('public.books_intro') }}</p>
                </div>
            </div>
            <div class="grid gap-5 sm:gap-6 grid-cols-2 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ($books as $book)
                    {{-- The cover/title link and the store links are siblings, not nested — an
                         <a> inside an <a> is invalid HTML and browsers split it unpredictably. --}}
                    <div class="flex flex-col items-center text-center">
                        <a href="{{ route('books.show', $book) }}" class="group flex flex-col items-center w-full">
                            <div class="book-spine-effect w-full aspect-[3/4.2] rounded-2xl border border-brand/25 shadow-[--shadow-soft] overflow-hidden relative bg-brand-tint">
                                @if ($book->coverUrl())
                                    <img src="{{ $book->coverUrl() }}" alt="{{ $book->title }}" class="absolute inset-0 w-full h-full object-cover">
                                @else
                                    <div class="absolute inset-0 geo-accent opacity-60" aria-hidden="true"></div>
                                    <div class="absolute inset-0 grid place-items-center text-brand/60">
                                        <x-ui.icon name="book" class="w-10 h-10" />
                                    </div>
                                @endif
                            </div>
                            <h4 class="mt-3 text-xs sm:text-sm font-bold text-ink group-hover:text-brand-strong transition-colors leading-snug line-clamp-2">{{ $book->title }}</h4>
                            <p class="text-xs text-muted mt-0.5 truncate max-w-full">{{ $book->author }}</p>
                        </a>
                        <x-ui.buy-links :links="$book->purchaseLinks" size="sm" class="mt-2.5 justify-center" />
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Featured Fatwa --}}
        @if ($featuredPost)
            <section data-reveal>
                <div class="flex items-center gap-3 mb-6">
                    <span class="w-1.5 h-8 rounded-full bg-brand shrink-0" aria-hidden="true"></span>
                    <h2 class="text-2xl sm:text-3xl font-black text-ink">{{ __('public.featured_fatwa_heading') }}</h2>
                </div>
                <a href="{{ route('blog.show', $featuredPost) }}" class="block rounded-2xl border border-brand/30 bg-brand-tint/30 p-6 sm:p-8 hover:border-brand/50 hover:shadow-[--shadow-soft] transition-all">
                    <x-ui.badge color="brand">{{ $featuredPost->category->name }}</x-ui.badge>
                    <h3 class="font-bold text-ink mt-3 text-lg sm:text-xl leading-snug">{{ $featuredPost->question ?: $featuredPost->title }}</h3>
                    <p class="text-sm text-muted mt-2 leading-relaxed line-clamp-2">{{ $featuredPost->excerptText(160) }}</p>
                </a>
            </section>
        @endif

        {{-- Recent articles --}}
        @if ($recentPosts->isNotEmpty())
            <section data-reveal>
                <div class="flex items-center justify-between gap-4 mb-6">
                    <div class="flex items-center gap-3">
                        <span class="w-1.5 h-8 rounded-full bg-brand shrink-0" aria-hidden="true"></span>
                        <h2 class="text-2xl sm:text-3xl font-black text-ink">{{ __('public.articles_heading') }}</h2>
                    </div>
                    <a href="{{ route('blog.index') }}" class="text-sm text-brand font-semibold hover:underline shrink-0">{{ __('public.articles_all') }}</a>
                </div>
                <div class="grid gap-5 sm:grid-cols-3">
                    @foreach ($recentPosts as $post)
                        <a href="{{ route('blog.show', $post) }}" class="block rounded-2xl border border-line bg-card p-5 sm:p-6 hover:border-brand/40 hover:shadow-[--shadow-soft] transition-all">
                            <x-ui.badge color="brand">{{ $post->category->name }}</x-ui.badge>
                            <h3 class="font-bold text-ink mt-3 leading-snug">{{ $post->title }}</h3>
                            <p class="text-sm text-muted mt-1.5 leading-relaxed line-clamp-2">{{ $post->excerptText(120) }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- FAQ --}}
        @if ($faqs->isNotEmpty())
            <section x-data="{ open: null }" data-reveal>
                <div class="flex items-center gap-3 mb-6">
                    <span class="w-1.5 h-8 rounded-full bg-brand shrink-0" aria-hidden="true"></span>
                    <h2 class="text-2xl sm:text-3xl font-black text-ink">{{ __('public.faq_heading') }}</h2>
                </div>
                <div class="rounded-2xl border border-line bg-card divide-y divide-line overflow-hidden">
                    @foreach ($faqs as $i => $faq)
                        <div>
                            <button type="button" @click="open = (open === {{ $i }} ? null : {{ $i }})"
                                    class="w-full flex items-center justify-between gap-3 text-start px-5 py-4 hover:bg-ink/[0.02] transition-colors">
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
        <section data-reveal>
            <div class="flex items-center gap-3 mb-6">
                <span class="w-1.5 h-8 rounded-full bg-brand shrink-0" aria-hidden="true"></span>
                <h2 class="text-2xl sm:text-3xl font-black text-ink">{{ __('public.utilities_heading') }}</h2>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <a href="{{ route('zakat.index') }}" class="group flex items-center gap-5 rounded-2xl border border-line bg-card p-5 sm:p-6 hover:border-brand/40 hover:shadow-[--shadow-soft] transition-all">
                    <div class="w-14 h-14 rounded-2xl bg-brand-tint text-brand grid place-items-center shrink-0 group-hover:bg-brand group-hover:text-brand-ink transition-colors"><x-ui.icon name="points" class="w-7 h-7" /></div>
                    <div><p class="font-bold text-ink">{{ __('public.zakat_cta') }}</p><p class="text-sm text-muted mt-0.5">{{ __('public.zakat_cta_sub') }}</p></div>
                </a>
                <a href="{{ route('ask-ustaz.show') }}" class="group flex items-center gap-5 rounded-2xl border border-line bg-card p-5 sm:p-6 hover:border-brand/40 hover:shadow-[--shadow-soft] transition-all">
                    <div class="w-14 h-14 rounded-2xl bg-brand-tint text-brand grid place-items-center shrink-0 group-hover:bg-brand group-hover:text-brand-ink transition-colors"><x-ui.icon name="profile" class="w-7 h-7" /></div>
                    <div><p class="font-bold text-ink">{{ __('public.ask_cta') }}</p><p class="text-sm text-muted mt-0.5">{{ __('public.ask_cta_sub') }}</p></div>
                </a>
            </div>
        </section>

        {{-- Telegram CTA — a solid brand block so the page has a second strong colour
             anchor besides the hero, rather than yet another white bordered card. --}}
        @if ($telegramUrl)
            <section data-reveal class="relative overflow-hidden rounded-3xl bg-brand text-brand-ink p-6 sm:p-8 flex flex-wrap items-center justify-between gap-4">
                <div class="absolute -right-10 -bottom-10 w-48 h-48 rounded-full bg-brand-ink/10 blur-2xl pointer-events-none" aria-hidden="true"></div>
                <div class="relative flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-brand-ink/15 grid place-items-center shrink-0">
                        <x-ui.icon name="send" class="w-5 h-5" />
                    </div>
                    <div>
                        <p class="font-black text-lg">{{ __('public.telegram_cta') }}</p>
                        <p class="text-sm opacity-90 mt-1">{{ __('public.telegram_cta_sub') }}</p>
                    </div>
                </div>
                <x-ui.button :href="$telegramUrl" target="_blank" rel="noopener" variant="secondary" class="relative shrink-0">{{ __('public.telegram_cta_button') }} <x-ui.icon name="chevron" class="w-4 h-4" /></x-ui.button>
            </section>
        @endif

        {{-- Student CTA --}}
        <section data-reveal class="relative overflow-hidden rounded-3xl border border-brand/30 bg-brand-tint/50 p-8 sm:p-10 text-center">
            <div class="absolute inset-0 geo-accent opacity-40" aria-hidden="true"></div>
            <div class="relative">
                <h2 class="text-2xl font-black text-ink">{{ __('public.student_cta_title') }}</h2>
                <p class="text-muted mt-2">{{ __('public.student_cta_sub') }}</p>
                <div class="mt-5"><x-ui.button :href="route('login')" size="lg">{{ __('public.student_cta_button') }}</x-ui.button></div>
            </div>
        </section>
    </div>
</x-layout.public>
