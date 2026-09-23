<x-layout.student :title="__('points.heading')" :heading="__('points.heading')">
    <div class="flex items-center justify-between mb-6">
        <p class="text-sm text-muted">{{ __('points.current_balance') }}</p>
        <span class="text-2xl font-black text-brand tabular-nums">{{ bn($user->points_balance) }}</span>
    </div>

    @if ($transactions->isEmpty())
        <x-ui.card><x-ui.empty :title="__('points.none')" /></x-ui.card>
    @else
        {{-- Mobile: 4 columns still push the balance column past the fold on a
             narrow screen (the reason text is unpredictable length), so it becomes
             a stacked list instead of a table that needs a sideways scroll. --}}
        <div class="sm:hidden space-y-2">
            @foreach ($transactions as $tx)
                <div class="bg-card border border-line rounded-[--radius-card] shadow-[--shadow-soft] p-4 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm text-ink">{{ $tx->reason ?? __('points.adjustment') }}</p>
                        <p class="text-xs text-muted tabular-nums mt-0.5">{{ $tx->created_at->format('d/m/Y') }}</p>
                    </div>
                    <div class="text-end shrink-0">
                        <p class="font-bold tabular-nums {{ $tx->amount >= 0 ? 'text-brand' : 'text-rose-500' }}">
                            {{ $tx->amount >= 0 ? '+' : '' }}{{ bn($tx->amount) }}
                        </p>
                        <p class="text-xs text-muted tabular-nums">{{ __('points.balance') }}: {{ bn($tx->balance_after) }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="hidden sm:block">
            <x-ui.table>
                <x-slot:head>
                    <th class="px-4 py-3">{{ __('points.date') }}</th>
                    <th class="px-4 py-3">{{ __('points.description') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('points.amount') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('points.balance') }}</th>
                </x-slot:head>
                @foreach ($transactions as $tx)
                    <tr>
                        <td class="px-4 py-3 text-muted whitespace-nowrap">{{ $tx->created_at->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-ink">{{ $tx->reason ?? __('points.adjustment') }}</td>
                        <td class="px-4 py-3 text-right font-bold tabular-nums {{ $tx->amount >= 0 ? 'text-brand' : 'text-rose-500' }}">
                            {{ $tx->amount >= 0 ? '+' : '' }}{{ bn($tx->amount) }}
                        </td>
                        <td class="px-4 py-3 text-right text-muted tabular-nums">{{ bn($tx->balance_after) }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        </div>
        <div class="mt-6">{{ $transactions->links('components.pagination') }}</div>
    @endif
</x-layout.student>
