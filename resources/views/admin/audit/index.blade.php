<x-layout.admin :title="__('nav.audit')" :heading="__('audit.heading')">
    <form method="GET" class="mb-6 sm:w-72">
        <x-ui.select name="action" onchange="this.form.submit()">
            <option value="">{{ __('admin.all_activity') }}</option>
            @foreach ($actions as $a)
                <option value="{{ $a }}" @selected($action === $a)>{{ $a }}</option>
            @endforeach
        </x-ui.select>
    </form>

    @if ($logs->isEmpty())
        <x-ui.card><x-ui.empty :title="__('audit.no_logs')" /></x-ui.card>
    @else
        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-3">{{ __('admin.when') }}</th>
                <th class="px-4 py-3">{{ __('admin.who') }}</th>
                <th class="px-4 py-3">{{ __('quizzes.actions') }}</th>
                <th class="px-4 py-3 hidden md:table-cell">{{ __('admin.object') }}</th>
                <th class="px-4 py-3 hidden lg:table-cell">{{ __('admin.changes') }}</th>
            </x-slot:head>
            @foreach ($logs as $log)
                <tr>
                    <td class="px-4 py-3 text-muted whitespace-nowrap text-xs">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                    <td class="px-4 py-3 text-ink">{{ $log->user?->name ?? __('dashboard.system') }}</td>
                    <td class="px-4 py-3"><code class="text-xs font-mono text-brand-strong">{{ $log->action }}</code></td>
                    <td class="px-4 py-3 text-muted text-xs hidden md:table-cell">
                        {{ $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : '—' }}
                    </td>
                    <td class="px-4 py-3 text-muted text-xs hidden lg:table-cell max-w-xs truncate">
                        @if ($log->before || $log->after)
                            {{ Str::limit(json_encode($log->after ?? $log->before, JSON_UNESCAPED_UNICODE), 80) }}
                        @else — @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
        <div class="mt-6">{{ $logs->links('components.pagination') }}</div>
    @endif
</x-layout.admin>
