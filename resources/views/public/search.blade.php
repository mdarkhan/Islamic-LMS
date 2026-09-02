<x-layout.public :title="__('public.search_heading')" :canonical="route('search.index')">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-8 sm:py-12">
        <header class="mb-6">
            <h1 class="text-2xl sm:text-3xl font-black text-ink">{{ __('public.search_heading') }}</h1>
            <form method="GET" action="{{ route('search.index') }}" class="mt-4 flex gap-2">
                <x-ui.input type="search" name="q" value="{{ $q }}" placeholder="{{ __('public.search_placeholder') }}" class="flex-1" autofocus />
                <x-ui.button type="submit"><x-ui.icon name="search" class="w-4 h-4" /></x-ui.button>
            </form>
        </header>

        @if ($q === '')
            {{-- Empty query: nothing to show yet. --}}
        @elseif ($courses->isEmpty() && $books->isEmpty() && $posts->isEmpty())
            <x-ui.card><x-ui.empty :title="__('public.search_no_results')" /></x-ui.card>
        @else
            <div class="space-y-8">
                @if ($courses->isNotEmpty())
                    <section>
                        <h2 class="text-sm font-bold text-muted uppercase tracking-wider mb-3">{{ __('public.search_courses') }}</h2>
                        <div class="space-y-2">
                            @foreach ($courses as $course)
                                <a href="{{ route('home') }}#courses" class="block rounded-xl border border-line bg-card p-4 hover:border-brand/40 transition-colors">
                                    <p class="font-semibold text-ink">{{ $course->title }}</p>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($books->isNotEmpty())
                    <section>
                        <h2 class="text-sm font-bold text-muted uppercase tracking-wider mb-3">{{ __('public.search_books') }}</h2>
                        <div class="space-y-2">
                            @foreach ($books as $book)
                                <a href="{{ route('books.show', $book) }}" class="block rounded-xl border border-line bg-card p-4 hover:border-brand/40 transition-colors">
                                    <p class="font-semibold text-ink">{{ $book->title }}</p>
                                    <p class="text-sm text-muted">{{ __('public.books_by') }}: {{ $book->author }}</p>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($posts->isNotEmpty())
                    <section>
                        <h2 class="text-sm font-bold text-muted uppercase tracking-wider mb-3">{{ __('public.search_articles') }}</h2>
                        <div class="space-y-2">
                            @foreach ($posts as $post)
                                <a href="{{ route('blog.show', $post) }}" class="block rounded-xl border border-line bg-card p-4 hover:border-brand/40 transition-colors">
                                    <p class="font-semibold text-ink">{{ $post->title }}</p>
                                    <p class="text-sm text-muted line-clamp-1">{{ $post->excerptText(120) }}</p>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>
        @endif
    </div>
</x-layout.public>
