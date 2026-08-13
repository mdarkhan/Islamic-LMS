<x-layout.student title="পয়েন্ট হিস্ট্রি" heading="পয়েন্ট হিস্ট্রি">
    <div class="flex items-center justify-between mb-6">
        <p class="text-sm text-muted">আপনার বর্তমান ব্যালেন্স</p>
        <span class="text-2xl font-black text-brand tabular-nums">{{ bn($user->points_balance) }}</span>
    </div>

    @if ($transactions->isEmpty())
        <x-ui.card><x-ui.empty title="এখনো কোনো পয়েন্ট লেনদেন নেই।" /></x-ui.card>
    @else
        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-3">তারিখ</th>
                <th class="px-4 py-3">বিবরণ</th>
                <th class="px-4 py-3 text-right">পরিমাণ</th>
                <th class="px-4 py-3 text-right">ব্যালেন্স</th>
            </x-slot:head>
            @foreach ($transactions as $tx)
                <tr>
                    <td class="px-4 py-3 text-muted whitespace-nowrap">{{ $tx->created_at->format('d/m/Y') }}</td>
                    <td class="px-4 py-3 text-ink">{{ $tx->reason ?? 'পয়েন্ট সমন্বয়' }}</td>
                    <td class="px-4 py-3 text-right font-bold tabular-nums {{ $tx->amount >= 0 ? 'text-brand' : 'text-rose-500' }}">
                        {{ $tx->amount >= 0 ? '+' : '' }}{{ bn($tx->amount) }}
                    </td>
                    <td class="px-4 py-3 text-right text-muted tabular-nums">{{ bn($tx->balance_after) }}</td>
                </tr>
            @endforeach
        </x-ui.table>
        <div class="mt-6">{{ $transactions->links('components.pagination') }}</div>
    @endif
</x-layout.student>
