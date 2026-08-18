<x-layout.base :title="$title" :noindex="true">
    <div class="min-h-screen grid place-items-center px-4 text-center bg-surface text-ink">
        <div class="max-w-md">
            <p class="text-6xl sm:text-7xl font-black text-brand tabular-nums">{{ bn($code) }}</p>
            <h1 class="mt-3 text-xl font-bold text-ink">{{ $title }}</h1>
            <p class="mt-2 text-muted leading-relaxed">{{ $message }}</p>
            <div class="mt-6">
                <x-ui.button :href="url('/')"><x-ui.icon name="chevron" class="w-4 h-4 rotate-180" /> {{ __('errors.home') }}</x-ui.button>
            </div>
        </div>
    </div>
</x-layout.base>
