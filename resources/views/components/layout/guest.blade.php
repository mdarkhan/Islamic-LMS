@props(['title' => null])

<x-layout.base :title="$title">
    <div class="min-h-screen flex flex-col">
        <header class="p-5 flex items-center justify-between">
            <x-ui.brand :sub="false" />
            <div class="flex items-center gap-2">
                <x-ui.locale-toggle />
                <x-ui.theme-toggle />
            </div>
        </header>

        <main class="flex-1 grid place-items-center px-4 py-8">
            <div class="w-full max-w-md animate-rise">
                {{ $slot }}
            </div>
        </main>

        <footer class="py-6 text-center text-xs text-muted">
            সর্বস্বত্ব সংরক্ষিত © {{ date('Y') }} · মাসউদ আলিমী
        </footer>
    </div>
</x-layout.base>
