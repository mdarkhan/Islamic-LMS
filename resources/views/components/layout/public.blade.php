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
        ['label' => __('nav.fatwa'), 'href' => route('blog.index'), 'active' => request()->routeIs('blog.*')],
        ['label' => __('nav.zakat'), 'href' => route('zakat.index'), 'active' => request()->routeIs('zakat.*')],
        ['label' => __('nav.ask_ustaz'), 'href' => route('ask-ustaz.show'), 'active' => request()->routeIs('ask-ustaz.*')],
    ];
@endphp

<x-layout.base :title="$title" :description="$description" :canonical="$canonical" :og-image="$ogImage">
    <div x-data="{ open: false }" class="min-h-screen flex flex-col">
        <header class="sticky top-0 z-30 bg-surface/85 backdrop-blur border-b border-line">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-4">
                <x-ui.brand />

                <nav class="hidden md:flex items-center gap-1" aria-label="{{ __('nav.home') }}">
                    @foreach ($nav as $item)
                        <a href="{{ $item['href'] }}"
                           @class([
                               'px-3 py-2 rounded-lg text-sm font-medium transition-colors',
                               'text-brand-strong bg-brand-tint' => $item['active'] ?? false,
                               'text-muted hover:text-ink hover:bg-ink/5' => ! ($item['active'] ?? false),
                           ])>{{ $item['label'] }}</a>
                    @endforeach
                </nav>

                <div class="flex items-center gap-1 sm:gap-2">
                    <x-ui.locale-toggle />
                    <x-ui.theme-toggle />
                    @auth
                        <x-ui.button :href="auth()->user()->isAdmin() ? route('admin.dashboard') : route('student.dashboard')" size="sm">{{ __('nav.dashboard') }}</x-ui.button>
                    @else
                        <x-ui.button :href="route('login')" size="sm" class="hidden sm:inline-flex">{{ __('nav.login') }}</x-ui.button>
                    @endauth
                    <button @click="open = !open" class="md:hidden p-2 text-muted hover:text-ink" aria-label="{{ __('nav.open_menu') }}">
                        <x-ui.icon name="menu" />
                    </button>
                </div>
            </div>

            {{-- Mobile menu --}}
            <div x-show="open" x-cloak class="md:hidden border-t border-line bg-surface" style="display:none">
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

        <footer class="border-t border-line py-8 text-center text-sm text-muted">
            <div class="max-w-6xl mx-auto px-4">
                সর্বস্বত্ব সংরক্ষিত © {{ date('Y') }} · মাসউদ আলিমী
            </div>
        </footer>
    </div>
</x-layout.base>
