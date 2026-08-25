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
    <div class="overflow-x-auto rounded-2xl border border-line">
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
