@if (session('temp_password'))
    <div x-data="{ copied: false }" class="mb-6 rounded-xl border border-amber-300 bg-amber-50 dark:bg-amber-500/10 dark:border-amber-500/30 p-4">
        <div class="flex items-start gap-3">
            <x-ui.icon name="key" class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
            <div class="min-w-0 flex-1">
                <p class="font-semibold text-amber-900 dark:text-amber-200">{{ __('admin.temp_password_title') }}</p>
                <p class="text-xs text-amber-800/80 dark:text-amber-300/80 mt-0.5">{{ __('admin.temp_password_body') }}</p>
                <div class="mt-2 flex items-center gap-2">
                    <code class="px-3 py-1.5 rounded-lg bg-white dark:bg-slate-900 border border-amber-300 dark:border-amber-500/30 font-mono text-base tracking-wider text-ink select-all">{{ session('temp_password') }}</code>
                    <button type="button" @click="navigator.clipboard.writeText('{{ session('temp_password') }}'); copied = true"
                            class="text-xs font-semibold text-amber-700 hover:text-amber-900">
                        <span x-show="!copied">{{ __('admin.copy') }}</span><span x-show="copied" x-cloak>{{ __('admin.copied') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
