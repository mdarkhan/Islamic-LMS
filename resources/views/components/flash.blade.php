@php
    $flashes = collect(['success' => 'success', 'error' => 'error', 'warning' => 'warning', 'status' => 'info'])
        ->filter(fn ($type, $key) => session()->has($key));
@endphp

@if ($flashes->isNotEmpty())
    <div class="space-y-3 mb-6" x-data="{ show: true }" x-show="show" x-transition>
        @foreach ($flashes as $key => $type)
            <x-ui.alert :type="$type" class="relative pr-10">
                {{ session($key) }}
                <button type="button" @click="show = false" aria-label="বন্ধ করুন"
                        class="absolute top-3 right-3 opacity-60 hover:opacity-100">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </x-ui.alert>
        @endforeach
    </div>
@endif
