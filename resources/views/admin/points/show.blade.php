<x-layout.admin :title="$student->name.' — '.__('nav.points')" :heading="__('points.ledger_heading')">
    <x-ui.breadcrumbs :items="[__('nav.points') => route('admin.points.index'), $student->name => null]" class="mb-5" />

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Adjust form --}}
        <div class="space-y-6">
            <x-ui.card>
                <div class="text-center pb-4 border-b border-line">
                    <p class="text-sm text-muted">{{ $student->name }} · {{ __('points.roll_prefix') }} {{ bn($student->roll) }}</p>
                    <p class="text-3xl font-black text-brand mt-1">{{ bn($student->points_balance) }}</p>
                    <p class="text-xs text-muted">{{ __('points.current_balance_short') }}</p>
                </div>

                @if (auth()->user()->hasPermission('points.grant'))
                    <form method="POST" action="{{ route('admin.points.store', $student) }}" class="space-y-4 mt-5"
                          x-data="{ dir: '{{ old('direction', 'credit') }}' }">
                        @csrf
                        <div class="grid grid-cols-2 gap-2">
                            <label class="cursor-pointer">
                                <input type="radio" name="direction" value="credit" x-model="dir" class="peer sr-only">
                                <span class="block text-center px-3 py-2 rounded-xl border border-line text-sm font-semibold peer-checked:bg-brand-tint peer-checked:border-brand peer-checked:text-brand-strong">{{ __('points.credit') }}</span>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="direction" value="deduct" x-model="dir" class="peer sr-only">
                                <span class="block text-center px-3 py-2 rounded-xl border border-line text-sm font-semibold peer-checked:bg-rose-50 dark:peer-checked:bg-rose-500/10 peer-checked:border-rose-400 peer-checked:text-rose-600">{{ __('points.debit') }}</span>
                            </label>
                        </div>
                        <x-ui.field :label="__('ui.amount')" name="amount" required>
                            <x-ui.input name="amount" type="number" min="1" :value="old('amount')" />
                        </x-ui.field>
                        <x-ui.field :label="__('ui.reason')" name="reason" required>
                            <x-ui.textarea name="reason" rows="2" :placeholder="__('points.reason_placeholder')">{{ old('reason') }}</x-ui.textarea>
                        </x-ui.field>
                        <x-ui.button type="submit" class="w-full">{{ __('points.apply') }}</x-ui.button>
                    </form>
                @endif
            </x-ui.card>
        </div>

        {{-- Ledger --}}
        <div class="lg:col-span-2">
            @if ($transactions->isEmpty())
                <x-ui.card><x-ui.empty :title="__('points.none')" /></x-ui.card>
            @else
                <x-ui.table>
                    <x-slot:head>
                        <th class="px-4 py-3">{{ __('points.date') }}</th>
                        <th class="px-4 py-3">{{ __('ui.type') }}</th>
                        <th class="px-4 py-3">{{ __('ui.reason') }}</th>
                        <th class="px-4 py-3 hidden sm:table-cell">{{ __('points.who_col') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('points.amount') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('points.balance') }}</th>
                    </x-slot:head>
                    @foreach ($transactions as $tx)
                        <tr>
                            <td class="px-4 py-3 text-muted whitespace-nowrap">{{ $tx->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3"><x-ui.badge :color="$tx->amount >= 0 ? 'success' : 'danger'">{{ $tx->type }}</x-ui.badge></td>
                            <td class="px-4 py-3 text-ink">{{ $tx->reason ?? '—' }}</td>
                            <td class="px-4 py-3 text-muted hidden sm:table-cell">{{ $tx->performedBy?->name ?? __('dashboard.system') }}</td>
                            <td class="px-4 py-3 text-right font-bold tabular-nums {{ $tx->amount >= 0 ? 'text-brand' : 'text-rose-500' }}">{{ $tx->amount >= 0 ? '+' : '' }}{{ bn($tx->amount) }}</td>
                            <td class="px-4 py-3 text-right text-muted tabular-nums">{{ bn($tx->balance_after) }}</td>
                        </tr>
                    @endforeach
                </x-ui.table>
                <div class="mt-6">{{ $transactions->links('components.pagination') }}</div>
            @endif
        </div>
    </div>
</x-layout.admin>
