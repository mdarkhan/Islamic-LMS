@if ($paginator->hasPages())
    <nav role="navigation" aria-label="পেজিনেশন" class="flex items-center justify-between gap-3">
        <div class="text-xs text-muted">
            {{ bn($paginator->firstItem() ?? 0) }}–{{ bn($paginator->lastItem() ?? 0) }} / {{ bn($paginator->total()) }}
        </div>
        <div class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="px-3 py-2 rounded-lg text-sm text-muted/50 border border-line cursor-default">পূর্ববর্তী</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="px-3 py-2 rounded-lg text-sm text-ink border border-line hover:border-brand hover:text-brand transition-colors">পূর্ববর্তী</a>
            @endif

            <span class="px-3 py-2 text-sm text-muted">{{ bn($paginator->currentPage()) }} / {{ bn($paginator->lastPage()) }}</span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="px-3 py-2 rounded-lg text-sm text-ink border border-line hover:border-brand hover:text-brand transition-colors">পরবর্তী</a>
            @else
                <span class="px-3 py-2 rounded-lg text-sm text-muted/50 border border-line cursor-default">পরবর্তী</span>
            @endif
        </div>
    </nav>
@endif
