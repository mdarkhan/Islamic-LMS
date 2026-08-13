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

{{-- One-time credential download after a student import. Shown once; the file is
     deleted the moment it is downloaded and swept by a short TTL otherwise. --}}
@if (session('credentials_download'))
    <div class="mb-6 rounded-xl border border-amber-300 bg-amber-50 dark:bg-amber-500/10 dark:border-amber-500/30 p-4">
        <div class="flex items-start gap-3">
            <x-ui.icon name="key" class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
            <div class="min-w-0 flex-1">
                <p class="font-semibold text-amber-900 dark:text-amber-200">অস্থায়ী পাসওয়ার্ড ফাইল (একবারই ডাউনলোড করা যাবে)</p>
                <p class="text-xs text-amber-800/80 dark:text-amber-300/80 mt-0.5">
                    প্রতিটি ইমপোর্ট করা শিক্ষার্থীর নতুন অস্থায়ী পাসওয়ার্ড এই CSV ফাইলে আছে। ডাউনলোড করার সাথে সাথে ফাইলটি মুছে যাবে — নিরাপদ স্থানে সংরক্ষণ করুন।
                </p>
                <x-ui.button :href="session('credentials_download')" size="sm" class="mt-3">
                    <x-ui.icon name="download" class="w-4 h-4" /> ক্রেডেনশিয়াল CSV ডাউনলোড
                </x-ui.button>
            </div>
        </div>
    </div>
@endif
