<button type="button" x-data @click="window.theme.toggle()" aria-label="{{ __('nav.toggle_theme') }}"
    {{ $attributes->merge(['class' => 'p-2 rounded-lg text-muted hover:text-ink hover:bg-ink/5 transition-colors']) }}>
    <svg class="w-5 h-5 hidden dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.36 6.36-.7-.7M6.34 6.34l-.7-.7m12.72 0-.7.7M6.34 17.66l-.7.7M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8z"/></svg>
    <svg class="w-5 h-5 block dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
</button>
