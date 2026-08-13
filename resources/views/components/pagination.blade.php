@if ($paginator->hasPages())
    <nav role="navigation" aria-label="pagination" class="flex items-center justify-between gap-3">
        <div class="text-xs text-muted">
            {{ bn($paginator->firstItem() ?? 0) }}–{{ bn($paginator->lastItem() ?? 0) }} / {{ bn($paginator->total()) }}
        </div>
        <div class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="px-3 py-2 rounded-lg text-sm text-muted/50 border border-line cursor-default">{{ __('ui.previous') }}</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="px-3 py-2 rounded-lg text-sm text-ink border border-line hover:border-brand hover:text-brand transition-colors">{{ __('ui.previous') }}</a>
            @endif

            <span class="px-3 py-2 text-sm text-muted">{{ bn($paginator->currentPage()) }} / {{ bn($paginator->lastPage()) }}</span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="px-3 py-2 rounded-lg text-sm text-ink border border-line hover:border-brand hover:text-brand transition-colors">{{ __('ui.next') }}</a>
            @else
                <span class="px-3 py-2 rounded-lg text-sm text-muted/50 border border-line cursor-default">{{ __('ui.next') }}</span>
            @endif
        </div>
    </nav>
@endif
