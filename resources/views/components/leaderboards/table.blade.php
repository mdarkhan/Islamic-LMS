@props([
    'rows',
    'me' => null,
])

@php
    $fmtTime = fn ($seconds) => $seconds === null
        ? '—'
        : __('leaderboards.time_format', [
            'minutes' => bn(intdiv((int) $seconds, 60)),
            'seconds' => bn((int) $seconds % 60),
        ]);
@endphp

@if ($rows->total() === 0)
    <x-ui.card><x-ui.empty :title="__('leaderboards.none')" /></x-ui.card>
@else
    {{-- Mobile: 7 columns don't fit below sm without hiding obtained/possible/
         percentage — the very numbers a student opens this page to see — behind a
         scroll they might never discover. Stacked cards instead, same data. --}}
    <div class="sm:hidden space-y-2">
        @foreach ($rows as $row)
            <div @class([
                'rounded-2xl border p-4',
                'border-brand/40 bg-brand-tint/60' => $me && $row['user_id'] === $me['user_id'],
                'border-line bg-card' => ! ($me && $row['user_id'] === $me['user_id']),
            ])>
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="shrink-0 w-9 h-9 rounded-full bg-brand-tint text-brand grid place-items-center font-black tabular-nums">{{ bn($row['rank']) }}</span>
                        <div class="min-w-0">
                            <p class="font-semibold text-ink truncate">
                                {{ $row['name'] }}
                                @if ($me && $row['user_id'] === $me['user_id'])<span class="text-xs text-brand">({{ __('leaderboards.you') }})</span>@endif
                            </p>
                            <p class="text-xs text-muted tabular-nums">{{ $row['roll'] ? bn($row['roll']) : '—' }}</p>
                        </div>
                    </div>
                    <div class="text-end shrink-0">
                        <p class="font-black text-ink tabular-nums">{{ bn($row['obtained']) }}<span class="text-xs text-muted font-normal">/{{ bn($row['possible'] ?? $row['total']) }}</span></p>
                        <p class="text-xs text-muted tabular-nums">{{ bn(number_format($row['percentage'], 2)) }}%</p>
                    </div>
                </div>
                <p class="text-xs text-muted mt-2 tabular-nums">{{ __('leaderboards.col_time') }}: {{ $fmtTime($row['time_taken_seconds']) }}</p>
            </div>
        @endforeach
    </div>

    <div class="hidden sm:block overflow-x-auto rounded-2xl border border-line">
        <table class="w-full text-sm">
            <thead class="bg-surface-raised text-muted">
                <tr>
                    <th scope="col" class="px-4 py-2.5 text-center align-middle font-semibold">{{ __('leaderboards.col_rank') }}</th>
                    <th scope="col" class="px-4 py-2.5 text-center align-middle font-semibold">{{ __('leaderboards.col_roll') }}</th>
                    <th scope="col" class="px-4 py-2.5 text-center align-middle font-semibold">{{ __('leaderboards.col_name') }}</th>
                    <th scope="col" class="px-4 py-2.5 text-center align-middle font-semibold">{{ __('leaderboards.col_time') }}</th>
                    <th scope="col" class="px-4 py-2.5 text-center align-middle font-semibold">{{ __('leaderboards.col_obtained') }}</th>
                    <th scope="col" class="px-4 py-2.5 text-center align-middle font-semibold">{{ __('leaderboards.col_possible') }}</th>
                    <th scope="col" class="px-4 py-2.5 text-center align-middle font-semibold">{{ __('leaderboards.col_percentage') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach ($rows as $row)
                    <tr @class(['bg-brand-tint/60' => $me && $row['user_id'] === $me['user_id']])>
                        <td class="px-4 py-3 text-center align-middle font-black text-brand tabular-nums">{{ bn($row['rank']) }}</td>
                        <td class="px-4 py-3 text-center align-middle tabular-nums">{{ $row['roll'] ? bn($row['roll']) : '—' }}</td>
                        <td class="px-4 py-3 text-center align-middle font-medium text-ink">
                            {{ $row['name'] }}
                            @if ($me && $row['user_id'] === $me['user_id'])<span class="ms-1 text-xs text-brand">({{ __('leaderboards.you') }})</span>@endif
                        </td>
                        <td class="px-4 py-3 text-center align-middle text-muted tabular-nums whitespace-nowrap">{{ $fmtTime($row['time_taken_seconds']) }}</td>
                        <td class="px-4 py-3 text-center align-middle font-semibold text-ink tabular-nums">{{ bn($row['obtained']) }}</td>
                        <td class="px-4 py-3 text-center align-middle text-muted tabular-nums">{{ bn($row['possible'] ?? $row['total']) }}</td>
                        <td class="px-4 py-3 text-center align-middle tabular-nums">{{ bn(number_format($row['percentage'], 2)) }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4 print:hidden">{{ $rows->links() }}</div>
@endif
