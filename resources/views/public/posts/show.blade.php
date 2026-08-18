@php
    $preview = $preview ?? false;
    $en = app()->getLocale() === 'en';
    $date = $post->published_at ? ($en ? $post->published_at->format('j F Y') : bn($post->published_at->format('d/m/Y'))) : '';
    $canonical = route('blog.show', $post);
@endphp

<x-layout.public
    :title="$post->metaTitle()"
    :description="$post->metaDescription()"
    :canonical="$canonical"
    :og-image="$post->featured_image">

    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-8 sm:py-12">
        @if ($preview)
            <x-ui.alert type="warning" class="mb-6">{{ __('posts.preview_banner') }}</x-ui.alert>
        @endif

        {{-- Breadcrumbs --}}
        <nav class="flex items-center gap-1.5 text-xs text-muted mb-4 flex-wrap" aria-label="breadcrumb">
            <a href="{{ route('home') }}" class="hover:text-ink">{{ __('nav.home') }}</a>
            <span>/</span>
            <a href="{{ route('blog.index') }}" class="hover:text-ink">{{ __('posts.back_to_articles') }}</a>
            <span>/</span>
            <a href="{{ route('blog.index', ['category' => $post->category->slug]) }}" class="hover:text-ink">{{ $post->category->name }}</a>
        </nav>

        <article>
            <header class="mb-6">
                <x-ui.badge color="brand">{{ $post->category->name }}</x-ui.badge>
                <h1 class="text-3xl font-black text-ink leading-tight mt-3">{{ $post->title }}</h1>
                <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted">
                    <span>{{ __('posts.published_on') }} {{ $date }}</span>
                    @if ($post->author)<span>· {{ __('posts.by') }} {{ $post->author->name }}</span>@endif
                    <button type="button" onclick="navigator.clipboard && navigator.clipboard.writeText('{{ $canonical }}')"
                            class="ms-auto inline-flex items-center gap-1.5 text-brand hover:underline print:hidden">
                        <x-ui.icon name="import" class="w-4 h-4" /> {{ __('posts.copy_link') }}
                    </button>
                </div>
            </header>

            {{-- Rich-text body, reduced to a safe allow-list server-side (HtmlSanitizer). --}}
            <div class="text-ink leading-loose space-y-4 text-[1.05rem]
                        [&_h2]:text-2xl [&_h2]:font-bold [&_h2]:text-ink [&_h2]:mt-8
                        [&_h3]:text-xl [&_h3]:font-bold [&_h3]:text-ink [&_h3]:mt-6
                        [&_p]:leading-loose
                        [&_a]:text-brand [&_a]:underline [&_a]:underline-offset-2
                        [&_ul]:list-disc [&_ul]:ps-6 [&_ul]:space-y-1
                        [&_ol]:list-decimal [&_ol]:ps-6 [&_ol]:space-y-1
                        [&_blockquote]:border-s-4 [&_blockquote]:border-brand/40 [&_blockquote]:ps-4 [&_blockquote]:text-muted [&_blockquote]:italic
                        [&_strong]:font-bold [&_code]:bg-surface-raised [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:rounded">
                {!! $post->renderedBody() !!}
            </div>
        </article>

        {{-- Related --}}
        @if ($related->isNotEmpty())
            <section class="mt-10 pt-8 border-t border-line print:hidden">
                <h2 class="text-sm font-bold uppercase tracking-wider text-muted mb-3">{{ __('posts.related') }}</h2>
                <div class="grid gap-3 sm:grid-cols-3">
                    @foreach ($related as $rel)
                        <a href="{{ route('blog.show', $rel) }}" class="block rounded-xl border border-line bg-card p-4 hover:border-brand/40 transition-colors">
                            <p class="font-semibold text-ink text-sm leading-snug">{{ $rel->title }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layout.public>
