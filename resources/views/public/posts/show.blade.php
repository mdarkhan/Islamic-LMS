@php
    $preview = $preview ?? false;
    $hijri = $hijri ?? null;
    $en = app()->getLocale() === 'en';
    $date = $post->published_at ? ($en ? $post->published_at->format('j F Y') : bn($post->published_at->format('d/m/Y'))) : '';
    $canonical = route('blog.show', $post);
    $shareUrl = rawurlencode($canonical);
    $shareTitle = rawurlencode($post->title);
    $networks = [
        'Facebook' => 'https://www.facebook.com/sharer/sharer.php?u='.$shareUrl,
        'WhatsApp' => 'https://wa.me/?text='.$shareTitle.'%20'.$shareUrl,
        'Telegram' => 'https://t.me/share/url?url='.$shareUrl.'&text='.$shareTitle,
        'X' => 'https://twitter.com/intent/tweet?url='.$shareUrl.'&text='.$shareTitle,
    ];
    $icons = [
        'Facebook' => '<path d="M13 22v-8h3l1-4h-4V8c0-1 .5-1.5 1.7-1.5H17V3.1C16.4 3 15.4 3 14.5 3 12 3 10.5 4.4 10.5 7v3H7v4h3.5v8H13z"/>',
        'WhatsApp' => '<path d="M12 2a10 10 0 0 0-8.5 15.2L2 22l4.9-1.3A10 10 0 1 0 12 2zm0 2a8 8 0 1 1-4.2 14.8l-.3-.2-2.9.8.8-2.8-.2-.3A8 8 0 0 1 12 4zm4.5 10.3c-.2-.1-1.4-.7-1.6-.8s-.4-.1-.5.1-.6.8-.8.9-.3.2-.5.1a6.5 6.5 0 0 1-3.2-2.8c-.2-.4.2-.4.6-1.2 0-.2 0-.3 0-.4l-.7-1.7c-.2-.4-.4-.4-.5-.4h-.5a1 1 0 0 0-.7.3A2.8 2.8 0 0 0 6.5 12a4.9 4.9 0 0 0 1 2.6 11 11 0 0 0 4.2 3.7c2 .8 2 .6 2.4.5s1.4-.6 1.6-1.1.2-1 .1-1.1-.2-.2-.4-.3z"/>',
        'Telegram' => '<path d="M21.9 4.3 2.8 11.6c-1 .4-1 1.3-.2 1.5l4.9 1.5 1.9 5.7c.2.5.4.6.8.6s.6-.2.9-.5l2.5-2.4 4.6 3.4c.8.5 1.5.2 1.7-.8l3-14c.2-1-.5-1.5-1.2-1.2zM8.3 14.2l9.1-5.7c.4-.3.8.1.4.4l-7.3 6.6-.3 3.2-1.4-4.2z"/>',
        'X' => '<path d="M18.9 2H22l-7.3 8.3L23 22h-6.6l-5.2-6.7L5.3 22H2l7.8-8.9L1.5 2h6.8l4.7 6.2L18.9 2zm-1.2 18h1.8L7.4 4H5.5l12.2 16z"/>',
    ];
@endphp

<x-layout.public
    :title="$post->metaTitle()"
    :description="$post->metaDescription()"
    :canonical="$canonical"
    :og-image="$post->featured_image">

    {{-- Clean print / PDF output: only the article, no chrome. --}}
    <style>
        @media print {
            body header, body > div > header, body footer, nav[aria-label="breadcrumb"], .no-print { display: none !important; }
            main, article { max-width: 100% !important; }
            body { background: #fff !important; color: #000 !important; }
            .article-body a { text-decoration: underline; color: #000 !important; }
        }
    </style>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-8 sm:py-12">
        @if ($preview)
            <x-ui.alert type="warning" class="mb-6 no-print">{{ __('posts.preview_banner') }}</x-ui.alert>
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
                <h1 class="text-3xl sm:text-4xl font-black text-ink leading-tight mt-3">{{ $post->title }}</h1>

                {{-- Meta: date · Hijri · views · author --}}
                <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted">
                    <span class="inline-flex items-center gap-1.5">
                        <x-ui.icon name="calendar" class="w-4 h-4 opacity-70" />
                        <span>{{ $date }}</span>
                        @if ($hijri)
                            <span class="text-muted/70">·</span>
                            <span>{{ bn($hijri['day']) }} {{ $en ? $hijri['month_name_en'] : $hijri['month_name'] }} {{ bn($hijri['year']) }} {{ __('posts.hijri_suffix') }}</span>
                        @endif
                    </span>
                    <span class="inline-flex items-center gap-1.5" title="{{ __('posts.views') }}">
                        <svg class="w-4 h-4 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S5.5 5.5 12 5.5 21.5 12 21.5 12 18.5 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="2.5"/>
                        </svg>
                        <span class="tabular-nums">{{ bn($post->views_count) }}</span>
                    </span>
                    @if ($post->author)<span>· {{ __('posts.by') }} {{ $post->author->name }}</span>@endif
                </div>
            </header>

            @php
                $hasQuestion = $post->question && trim($post->question) !== '';
                $hasSummary = $post->excerpt && trim($post->excerpt) !== '';
            @endphp

            {{-- Question callout (amber) --}}
            @if ($hasQuestion)
                <div class="rounded-2xl border border-amber-300/70 bg-amber-50 dark:bg-amber-500/10 dark:border-amber-500/30 p-5 mb-4">
                    <p class="text-sm font-bold text-amber-700 dark:text-amber-300 mb-1.5">{{ __('posts.question_label') }} {{ bn($post->id) }}</p>
                    <p class="text-ink/90 leading-relaxed">{{ $post->question }}</p>
                </div>
            @endif

            {{-- Answer-summary callout (green) --}}
            @if ($hasSummary)
                <div class="rounded-2xl border border-brand/30 bg-brand-tint/70 dark:bg-brand-tint/20 p-5 mb-6">
                    <p class="text-sm font-bold text-brand-strong mb-1.5">{{ __('posts.summary') }}</p>
                    <p class="text-ink/90 leading-relaxed">{{ $post->excerpt }}</p>
                </div>
            @endif

            {{-- "Answer" section header when there is a question; otherwise a decorative divider --}}
            @if ($hasQuestion)
                <div class="flex items-center gap-3 my-6">
                    <h2 class="shrink-0 text-lg font-bold text-brand-strong">{{ __('posts.answer_label') }}</h2>
                    <span class="h-px flex-1 bg-brand/30"></span>
                </div>
            @else
                <div class="flex items-center gap-3 my-7" aria-hidden="true">
                    <span class="h-px flex-1 bg-line"></span>
                    <svg class="w-3.5 h-3.5 text-brand/60" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.5 7.5H22l-6 4.5 2.3 7.5L12 17l-6.3 4.5L8 14 2 9.5h7.5L12 2z"/></svg>
                    <span class="h-px flex-1 bg-line"></span>
                </div>
            @endif

            {{-- Rich-text body (reduced to a safe allow-list server-side — HtmlSanitizer). --}}
            <div class="article-body text-ink leading-loose space-y-4 text-[1.075rem]
                        [&_h2]:text-2xl [&_h2]:font-bold [&_h2]:text-ink [&_h2]:mt-8 [&_h2]:mb-2 [&_h2]:pb-1.5 [&_h2]:border-b [&_h2]:border-line
                        [&_h3]:text-xl [&_h3]:font-bold [&_h3]:text-ink [&_h3]:mt-6 [&_h3]:mb-1.5
                        [&_h4]:text-lg [&_h4]:font-semibold [&_h4]:text-ink [&_h4]:mt-4
                        [&_p]:leading-loose
                        [&_a]:text-brand [&_a]:underline [&_a]:underline-offset-2 [&_a]:break-words
                        [&_ul]:list-disc [&_ul]:ps-6 [&_ul]:space-y-1.5 [&_ul]:my-3
                        [&_ol]:list-decimal [&_ol]:ps-6 [&_ol]:space-y-1.5 [&_ol]:my-3
                        [&_li]:leading-relaxed [&_li]:ps-1
                        [&_blockquote]:border-s-4 [&_blockquote]:border-brand/50 [&_blockquote]:bg-surface-raised [&_blockquote]:rounded-e-lg [&_blockquote]:ps-4 [&_blockquote]:pe-3 [&_blockquote]:py-2 [&_blockquote]:text-ink/80 [&_blockquote]:italic
                        [&_strong]:font-bold [&_b]:font-bold [&_u]:underline
                        [&_code]:bg-surface-raised [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:rounded [&_code]:text-[0.95em]
                        [&_pre]:bg-surface-raised [&_pre]:p-4 [&_pre]:rounded-xl [&_pre]:overflow-x-auto [&_pre]:text-sm">
                {!! $post->renderedBody() !!}
            </div>

            {{-- Tags --}}
            @if ($post->tags->isNotEmpty())
                <div class="mt-8 flex flex-wrap items-center gap-2">
                    <span class="text-sm font-semibold text-muted">{{ __('posts.filed_under') }}:</span>
                    @foreach ($post->tags as $tag)
                        <a href="{{ route('blog.index', ['tag' => $tag->slug]) }}"
                           class="inline-flex items-center rounded-full border border-line px-3 py-1 text-sm text-ink hover:border-brand/50 hover:text-brand transition-colors">
                            {{ $tag->name }}
                        </a>
                    @endforeach
                </div>
            @endif

            {{-- Action bar: share + print + copy --}}
            <div class="mt-8 pt-6 border-t border-line flex flex-wrap items-center gap-3 no-print">
                <span class="text-sm font-semibold text-ink">{{ __('posts.share') }}:</span>
                @foreach ($networks as $name => $href)
                    <a href="{{ $href }}" target="_blank" rel="noopener noreferrer"
                       title="{{ __('posts.share_on', ['network' => $name]) }}" aria-label="{{ __('posts.share_on', ['network' => $name]) }}"
                       class="grid place-items-center w-9 h-9 rounded-full border border-line text-muted hover:text-brand-ink hover:bg-brand hover:border-brand transition-colors">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">{!! $icons[$name] !!}</svg>
                    </a>
                @endforeach

                <button type="button" x-data
                        @click="navigator.clipboard && navigator.clipboard.writeText('{{ $canonical }}').then(() => { $el.dataset.copied = '1'; setTimeout(() => $el.dataset.copied = '', 1500); })"
                        class="grid place-items-center w-9 h-9 rounded-full border border-line text-muted hover:text-brand transition-colors"
                        title="{{ __('posts.copy_link') }}" aria-label="{{ __('posts.copy_link') }}">
                    <x-ui.icon name="import" class="w-4 h-4" />
                </button>

                <button type="button" onclick="window.print()"
                        class="ms-auto inline-flex items-center gap-1.5 rounded-lg border border-line px-3 py-2 text-sm text-ink hover:border-brand/50 hover:text-brand transition-colors"
                        title="{{ __('posts.print') }}">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 9V4h12v5M6 18H4a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2M6 14h12v6H6v-6Z"/>
                    </svg>
                    {{ __('posts.print') }}
                </button>
            </div>
        </article>

        {{-- Related --}}
        @if ($related->isNotEmpty())
            <section class="mt-10 pt-8 border-t border-line no-print">
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
