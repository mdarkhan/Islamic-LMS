@props(['name', 'title' => null])
<div x-data="{ open: false }"
     x-on:open-modal.window="if ($event.detail === '{{ $name }}') open = true"
     x-on:close-modal.window="open = false"
     x-on:keydown.escape.window="open = false"
     style="display:none" x-show="open" x-cloak
     class="fixed inset-0 z-50 grid place-items-center p-4">
    <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="open = false"></div>
    <div x-show="open" x-transition
         role="dialog" aria-modal="true" @if ($title) aria-label="{{ $title }}" @endif
         class="relative w-full max-w-lg bg-card border border-line rounded-2xl shadow-xl p-6">
        @if ($title)
            <h2 class="text-lg font-bold text-ink mb-4">{{ $title }}</h2>
        @endif
        {{ $slot }}
    </div>
</div>
