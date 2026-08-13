<x-layout.admin title="অডিট লগ" heading="অডিট লগ">
    <form method="GET" class="mb-6 sm:w-72">
        <x-ui.select name="action" onchange="this.form.submit()">
            <option value="">সব কার্যক্রম</option>
            @foreach ($actions as $a)
                <option value="{{ $a }}" @selected($action === $a)>{{ $a }}</option>
            @endforeach
        </x-ui.select>
    </form>

    @if ($logs->isEmpty())
        <x-ui.card><x-ui.empty title="কোনো লগ নেই।" /></x-ui.card>
    @else
        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-3">সময়</th>
                <th class="px-4 py-3">কে</th>
                <th class="px-4 py-3">কার্যক্রম</th>
                <th class="px-4 py-3 hidden md:table-cell">অবজেক্ট</th>
                <th class="px-4 py-3 hidden lg:table-cell">পরিবর্তন</th>
            </x-slot:head>
            @foreach ($logs as $log)
                <tr>
                    <td class="px-4 py-3 text-muted whitespace-nowrap text-xs">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                    <td class="px-4 py-3 text-ink">{{ $log->user?->name ?? 'সিস্টেম' }}</td>
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
