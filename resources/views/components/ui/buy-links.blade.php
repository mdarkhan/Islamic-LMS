@props([
    'links' => [],        // BookPurchaseLink models
    'size' => 'md',       // sm = compact (book cards), md = book page / modal
])

@php
    // Known stores get their logo; anything else falls back to the plain store name.
    $logos = [
        'rokomari' => 'images/brands/rokomari.png',
        'wafilife' => 'images/brands/wafilife.svg',
    ];

    $pill = $size === 'sm'
        ? 'gap-1.5 px-2 py-1 text-[11px]'
        : 'gap-2.5 px-3.5 py-2 text-sm';
    $logoHeight = $size === 'sm' ? 'h-3.5' : 'h-5';

    $logoFor = function ($link) use ($logos) {
        $haystack = strtolower($link->website_name.' '.parse_url($link->url, PHP_URL_HOST));
        foreach ($logos as $key => $path) {
            if (str_contains($haystack, $key)) {
                return asset($path);
            }
        }

        return null;
    };
@endphp

@if (count($links) > 0)
    <div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2']) }}>
        @foreach ($links as $link)
            {{-- Always a white pill with dark text (not theme tokens): the store logos are
                 designed for a light background and would vanish on a dark card. --}}
            <a href="{{ $link->url }}" target="_blank" rel="noopener nofollow"
               aria-label="{{ $link->website_name }} — {{ __('public.buy_now') }}"
               class="inline-flex items-center rounded-lg border border-stone-200 bg-white font-semibold text-stone-800 shadow-sm hover:border-brand hover:shadow transition {{ $pill }}">
                @if ($logo = $logoFor($link))
                    <img src="{{ $logo }}" alt="{{ $link->website_name }}" class="{{ $logoHeight }} w-auto" loading="lazy">
                @else
                    <span>{{ $link->website_name }}</span>
                @endif
                <span class="text-brand-strong whitespace-nowrap">{{ __('public.buy_now') }}</span>
            </a>
        @endforeach
    </div>
@endif
