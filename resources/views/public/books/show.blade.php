@php
    $previewPageCount = 5;
@endphp

<x-layout.public :title="$book->title" :canonical="route('books.show', $book)">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 py-8 sm:py-12 space-y-8">

        {{-- `flex w-fit`, not an inline link: the parent's space-y spacing is a vertical
             margin, which an inline element ignores — that's what left this glued to the
             cover before. --}}
        <a href="{{ route('home') }}#books"
           class="group flex w-fit items-center gap-2 rounded-full border border-line bg-card py-2 pl-2.5 pr-4 text-sm font-semibold text-brand-strong shadow-[--shadow-soft] transition-colors hover:border-brand/40 hover:bg-brand-tint">
            <x-ui.icon name="chevron" class="w-5 h-5 rotate-180 transition-transform group-hover:-translate-x-0.5" />
            {{ __('public.book_back') }}
        </a>

        {{-- Header --}}
        <section class="grid gap-6 sm:grid-cols-[auto_1fr]">
            <div class="w-32 sm:w-40 aspect-[3/4] rounded-2xl overflow-hidden bg-brand-tint border border-line shrink-0">
                @if ($book->coverUrl())
                    <img src="{{ $book->coverUrl() }}" alt="{{ $book->title }}" class="w-full h-full object-cover">
                @else
                    <div class="relative w-full h-full">
                        <div class="absolute inset-0 geo-accent opacity-60" aria-hidden="true"></div>
                        <div class="absolute inset-0 grid place-items-center text-brand/70"><x-ui.icon name="book" class="w-12 h-12" /></div>
                    </div>
                @endif
            </div>
            <div>
                <h1 class="text-2xl sm:text-3xl font-black text-ink leading-tight">{{ $book->title }}</h1>
                <p class="text-muted mt-1">{{ __('public.books_by') }}: {{ $book->author }}</p>

                <dl class="grid grid-cols-2 gap-x-4 gap-y-2 mt-4 text-sm max-w-xs">
                    @if ($book->publisher)
                        <dt class="text-muted">{{ __('public.book_publisher') }}</dt>
                        <dd class="text-ink font-semibold">{{ $book->publisher }}</dd>
                    @endif
                    @if ($book->page_count)
                        <dt class="text-muted">{{ __('public.book_pages') }}</dt>
                        <dd class="text-ink font-semibold tabular-nums">{{ bn($book->page_count) }}</dd>
                    @endif
                </dl>

                @if ($book->purchaseLinks->isNotEmpty())
                    <p class="text-xs font-semibold text-muted mt-5 mb-2">{{ __('public.book_buy_heading') }}</p>
                    <x-ui.buy-links :links="$book->purchaseLinks" />
                @endif
            </div>
        </section>

        {{-- Details --}}
        @if ($book->details)
            <section class="rounded-2xl border border-line bg-card p-5 sm:p-6">
                <h2 class="font-bold text-ink mb-2">{{ __('public.book_details_heading') }}</h2>
                <p class="text-sm text-muted leading-relaxed whitespace-pre-line">{{ $book->details }}</p>
            </section>
        @endif

        {{-- Demo preview + buy popup --}}
        <section x-data="{ notified: false }">
            <h2 class="font-bold text-ink mb-1">{{ __('public.book_preview_heading') }}</h2>
            <p class="text-xs text-muted mb-4">{{ __('public.book_preview_note') }}</p>

            <div class="max-h-[70vh] overflow-y-auto rounded-2xl border border-line bg-ink/[0.03] p-4 sm:p-6 space-y-4"
                 @scroll="if (! notified && $el.scrollTop + $el.clientHeight >= $el.scrollHeight - 24) { notified = true; $dispatch('open-modal', 'buy-book') }">
                @for ($page = 1; $page <= $previewPageCount; $page++)
                    <div class="aspect-[3/4] rounded-xl bg-card border border-line shadow-sm p-6 sm:p-8 flex flex-col">
                        <div class="space-y-2.5 flex-1">
                            @for ($line = 0; $line < 9; $line++)
                                <div class="h-2 rounded-full bg-ink/[0.06]" style="width: {{ [95, 88, 92, 60, 90, 85, 40, 93, 70][$line] }}%"></div>
                            @endfor
                        </div>
                        <p class="text-center text-xs text-muted mt-4">{{ __('public.book_preview_page', ['current' => bn($page), 'total' => bn($previewPageCount)]) }}</p>
                    </div>
                @endfor
            </div>
        </section>
    </div>

    <x-ui.modal name="buy-book" :title="__('public.book_buy_heading')">
        @if ($book->purchaseLinks->isEmpty())
            <p class="text-sm text-muted">{{ __('public.book_buy_none') }}</p>
        @else
            <p class="text-sm text-muted mb-4">{{ __('public.book_buy_body') }}</p>
            <x-ui.buy-links :links="$book->purchaseLinks" class="flex-col items-stretch [&>a]:justify-between" />
        @endif
    </x-ui.modal>
</x-layout.public>
