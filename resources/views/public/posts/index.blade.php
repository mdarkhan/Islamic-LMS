@php
    $en = app()->getLocale() === 'en';
    $fmtDate = fn ($d) => $d === null ? '' : ($en ? $d->format('j M Y') : bn($d->format('d/m/Y')));
@endphp

<x-layout.public :title="__('posts.public_heading')" :description="__('posts.public_intro')" :canonical="route('blog.index')">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 sm:py-12">
        <header class="mb-6">
            <h1 class="text-2xl sm:text-3xl font-black text-ink">{{ __('posts.public_heading') }}</h1>
            <p class="text-muted mt-1">{{ __('posts.public_intro') }}</p>
        </header>

        {{-- Filters --}}
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-6">
            <nav class="flex items-center gap-2 overflow-x-auto pb-1" aria-label="{{ __('posts.categories') }}">
                <a href="{{ route('blog.index') }}" @class(['shrink-0 rounded-full px-3.5 py-1.5 text-sm font-semibold border', 'border-brand bg-brand text-brand-ink' => ! $activeCategory, 'border-line text-muted hover:border-brand/50' => $activeCategory])>{{ __('posts.all_categories') }}</a>
                @foreach ($categories as $cat)
                    <a href="{{ route('blog.index', ['category' => $cat->slug]) }}" @class(['shrink-0 whitespace-nowrap rounded-full px-3.5 py-1.5 text-sm font-semibold border', 'border-brand bg-brand text-brand-ink' => $activeCategory && $activeCategory->id === $cat->id, 'border-line text-muted hover:border-brand/50' => ! ($activeCategory && $activeCategory->id === $cat->id)])>{{ $cat->name }}</a>
                @endforeach
            </nav>
            <form method="GET" action="{{ route('blog.index') }}" class="sm:ms-auto flex gap-2">
                @if ($activeCategory)<input type="hidden" name="category" value="{{ $activeCategory->slug }}">@endif
                <x-ui.input name="q" value="{{ $q }}" placeholder="{{ __('posts.search_placeholder') }}" class="sm:w-56" />
                <x-ui.button type="submit" variant="secondary"><x-ui.icon name="search" class="w-4 h-4" /></x-ui.button>
            </form>
        </div>

        @if ($posts->isEmpty())
            <x-ui.card><x-ui.empty :title="__('posts.no_results')" /></x-ui.card>
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    <article class="flex flex-col rounded-2xl border border-line bg-card p-5 hover:border-brand/40 transition-colors">
                        <x-ui.badge color="brand">{{ $post->category->name }}</x-ui.badge>
                        <h2 class="font-bold text-ink mt-2 leading-snug">
                            <a href="{{ route('blog.show', $post) }}" class="hover:text-brand">{{ $post->title }}</a>
                        </h2>
                        <p class="text-sm text-muted mt-1.5 flex-1 line-clamp-3">{{ $post->excerptText(160) }}</p>
                        <div class="mt-3 pt-3 border-t border-line flex items-center justify-between text-xs text-muted">
                            <span>{{ $fmtDate($post->published_at) }}</span>
                            <a href="{{ route('blog.show', $post) }}" class="text-brand font-semibold hover:underline">{{ __('posts.read_more') }} →</a>
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="mt-8">{{ $posts->links() }}</div>
        @endif
    </div>
</x-layout.public>
