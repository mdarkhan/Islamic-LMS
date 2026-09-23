@props([
    'title' => null,
    'description' => null,
    'canonical' => null,
    'ogImage' => null,
])

@php
    $nav = [
        ['label' => __('nav.home'), 'href' => route('home')],
        ['label' => __('nav.courses'), 'href' => route('home').'#courses'],
        ['label' => __('nav.books'), 'href' => route('home').'#books'],
        ['label' => __('nav.fatwa'), 'href' => route('blog.index'), 'active' => request()->routeIs('blog.*')],
        ['label' => __('nav.zakat'), 'href' => route('zakat.index'), 'active' => request()->routeIs('zakat.*')],
        ['label' => __('nav.ask_ustaz'), 'href' => route('ask-ustaz.show'), 'active' => request()->routeIs('ask-ustaz.*')],
        ['label' => __('nav.contact'), 'href' => route('contact.show'), 'active' => request()->routeIs('contact.*')],
    ];

    // Footer social links — only channels the admin has configured are shown
    // (Admin → Settings → General → সোসাল মিডিয়া লিংক).
    $socialSettings = app(\App\Services\Settings\SettingService::class)->group('general');
    $socialLinks = collect([
        ['label' => __('public.social_whatsapp'), 'url' => $socialSettings['whatsapp_url'] ?? null],
        ['label' => __('public.social_facebook_page'), 'url' => $socialSettings['facebook_page_url'] ?? null],
        ['label' => __('public.social_facebook_group'), 'url' => $socialSettings['facebook_group_url'] ?? null],
        ['label' => __('public.social_telegram_group'), 'url' => $socialSettings['telegram_url'] ?? null],
    ])->filter(fn ($link) => filled($link['url']));
@endphp

<x-layout.base :title="$title" :description="$description" :canonical="$canonical" :og-image="$ogImage">
    <div x-data="{ open: false }" class="min-h-screen flex flex-col">
        <header class="sticky top-0 z-30 bg-surface/85 backdrop-blur border-b border-line">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-4">
                <x-ui.brand />

                {{-- Seven items don't fit beside the brand + action icons until quite
                     wide (measured overflow up to ~1100px), so the full horizontal nav
                     only appears at xl; the hamburger menu covers everything below
                     that instead of letting it wrap or overflow. --}}
                <nav class="hidden xl:flex items-center gap-1" aria-label="{{ __('nav.home') }}">
                    @foreach ($nav as $item)
                        <a href="{{ $item['href'] }}"
                           @class([
                               'px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors',
                               'text-brand-strong bg-brand-tint' => $item['active'] ?? false,
                               'text-muted hover:text-ink hover:bg-ink/5' => ! ($item['active'] ?? false),
                           ])>{{ $item['label'] }}</a>
                    @endforeach
                </nav>

                <div class="flex items-center gap-1 sm:gap-2">
                    <a href="{{ route('search.index') }}" class="p-2 rounded-lg text-muted hover:text-ink hover:bg-ink/5" aria-label="{{ __('public.search_heading') }}">
                        <x-ui.icon name="search" class="w-5 h-5" />
                    </a>
                    <x-ui.locale-toggle />
                    <x-ui.theme-toggle />
                    @auth
                        <x-ui.button :href="auth()->user()->isAdmin() ? route('admin.dashboard') : route('student.dashboard')" size="sm">{{ __('nav.dashboard') }}</x-ui.button>
                    @else
                        <x-ui.button :href="route('login')" size="sm" class="hidden sm:inline-flex">{{ __('nav.login') }}</x-ui.button>
                    @endauth
                    <button @click="open = !open" class="xl:hidden p-2 text-muted hover:text-ink" aria-label="{{ __('nav.open_menu') }}">
                        <x-ui.icon name="menu" />
                    </button>
                </div>
            </div>

            {{-- Mobile/tablet menu (below lg) --}}
            <div x-show="open" x-cloak class="xl:hidden border-t border-line bg-surface" style="display:none">
                <nav class="max-w-6xl mx-auto px-4 py-2 space-y-1">
                    @foreach ($nav as $item)
                        <a href="{{ $item['href'] }}" class="block px-3 py-2.5 rounded-lg text-sm font-medium text-ink hover:bg-ink/5">{{ $item['label'] }}</a>
                    @endforeach
                    @guest
                        <a href="{{ route('login') }}" class="block px-3 py-2.5 rounded-lg text-sm font-semibold text-brand-strong bg-brand-tint">{{ __('nav.login') }}</a>
                    @endguest
                </nav>
            </div>
        </header>

        <main class="flex-1">
            {{ $slot }}
        </main>

        <footer class="border-t border-line bg-surface-raised">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 py-10 sm:py-12">
                <div class="grid gap-8 sm:grid-cols-[1.4fr_1fr_1fr]">
                    <div>
                        <x-ui.brand />
                        <p class="text-sm text-muted mt-3 max-w-xs leading-relaxed">{{ __('public.hero_subtitle') }}</p>
                    </div>

                    <div>
                        <p class="text-xs font-bold text-ink uppercase tracking-wider mb-3">{{ __('public.quick_links_heading') }}</p>
                        <nav class="flex flex-col gap-2 text-sm text-muted">
                            @foreach ($nav as $item)
                                <a href="{{ $item['href'] }}" class="hover:text-brand transition-colors w-fit">{{ $item['label'] }}</a>
                            @endforeach
                        </nav>
                    </div>

                    <div>
                        @if ($socialLinks->isNotEmpty())
                            <p class="text-xs font-bold text-ink uppercase tracking-wider mb-3">{{ __('public.social_heading') }}</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($socialLinks as $link)
                                    <a href="{{ $link['url'] }}" target="_blank" rel="noopener"
                                       class="px-3 py-1.5 rounded-full border border-line text-xs font-semibold text-ink hover:border-brand/40 hover:text-brand transition-colors">
                                        {{ $link['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <div class="mt-10 pt-6 border-t border-line text-center text-sm text-muted">
                    সর্বস্বত্ব সংরক্ষিত © {{ date('Y') }} · মাসউদ আলিমী
                </div>
            </div>
        </footer>
    </div>
</x-layout.base>
