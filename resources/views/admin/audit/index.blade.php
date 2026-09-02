<x-layout.admin :title="__('nav.audit')" :heading="__('audit.heading')">
    <form method="GET" class="mb-6 grid gap-3 sm:grid-cols-3 sm:max-w-xl">
        <x-ui.select name="action" onchange="this.form.submit()">
            <option value="">{{ __('admin.all_activity') }}</option>
            @foreach ($actions as $a)
                <option value="{{ $a }}" @selected($action === $a)>{{ $a }}</option>
            @endforeach
        </x-ui.select>
        <x-ui.input type="date" name="date_from" :value="$dateFrom" :placeholder="__('admin.date_from')" onchange="this.form.submit()" />
        <x-ui.input type="date" name="date_to" :value="$dateTo" :placeholder="__('admin.date_to')" onchange="this.form.submit()" />
    </form>

    @if ($logs->isEmpty())
        <x-ui.card><x-ui.empty :title="__('audit.no_logs')" /></x-ui.card>
    @else
        <div class="space-y-2">
            @foreach ($logs as $log)
                <div x-data="{ open: false }" class="rounded-xl border border-line bg-card overflow-hidden">
                    <div class="grid grid-cols-1 sm:grid-cols-[auto_auto_1fr_auto] items-center gap-2 sm:gap-4 px-4 py-3 text-sm">
                        <span class="text-muted whitespace-nowrap text-xs">{{ $log->created_at->format('d/m/Y H:i') }}</span>
                        <span class="text-ink">{{ $log->user?->name ?? __('dashboard.system') }}</span>
                        <code class="text-xs font-mono text-brand-strong">{{ $log->action }}</code>
                        <span class="text-muted text-xs">
                            {{ $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : '—' }}
                        </span>
                    </div>
                    @if ($log->before || $log->after)
                        <button type="button" @click="open = !open" class="w-full text-left px-4 py-2 text-xs font-semibold text-brand hover:underline border-t border-line">
                            <span x-show="!open">{{ __('admin.view_full') }}</span>
                            <span x-show="open" x-cloak>{{ __('ui.close') }}</span>
                        </button>
                        <div x-show="open" x-cloak class="px-4 pb-3 grid gap-3 sm:grid-cols-2">
                            @if ($log->before)
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted mb-1">before</p>
                                    <pre class="text-xs bg-surface-raised rounded-lg p-3 overflow-x-auto">{{ json_encode($log->before, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                                </div>
                            @endif
                            @if ($log->after)
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted mb-1">after</p>
                                    <pre class="text-xs bg-surface-raised rounded-lg p-3 overflow-x-auto">{{ json_encode($log->after, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="mt-6">{{ $logs->links('components.pagination') }}</div>
    @endif
</x-layout.admin>
