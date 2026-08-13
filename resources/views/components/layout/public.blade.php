@props(['title' => null])

<x-layout.base :title="$title">
    <div class="min-h-screen flex flex-col">
        <header class="sticky top-0 z-30 bg-surface/80 backdrop-blur border-b border-line">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
                <x-ui.brand />
                <div class="flex items-center gap-2">
                    <x-ui.theme-toggle />
                    @auth
                        <x-ui.button :href="auth()->user()->isAdmin() ? route('admin.dashboard') : route('student.dashboard')" size="sm">
                            ড্যাশবোর্ড
                        </x-ui.button>
                    @else
                        <x-ui.button :href="route('login')" size="sm">প্রবেশ করুন</x-ui.button>
                    @endauth
                </div>
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
