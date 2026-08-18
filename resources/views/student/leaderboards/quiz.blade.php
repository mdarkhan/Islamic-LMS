@php
    $fmtTime = fn ($seconds) => $seconds === null ? '—' : __('exams.minutes', ['count' => bn(intdiv((int) $seconds, 60))]);
@endphp

<x-layout.student :title="__('leaderboards.quiz_heading')" :heading="$quiz->title">
    <x-leaderboards.picker :quizzes="$quizzes" :active-quiz-id="$quiz->id" />

    <div class="flex items-center justify-between gap-3 mb-3">
        <div class="flex items-center gap-2">
            @if ($me)<x-ui.badge color="brand">{{ __('leaderboards.your_rank', ['rank' => bn($me['rank'])]) }}</x-ui.badge>@endif
            <span class="text-xs text-muted">{{ __('leaderboards.ranking_note') }}</span>
        </div>
        <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 text-sm text-muted hover:text-ink print:hidden">
            <x-ui.icon name="download" class="w-4 h-4" /> {{ __('leaderboards.print') }}
        </button>
    </div>

    @if ($rows->total() === 0)
        <x-ui.card><x-ui.empty :title="__('leaderboards.none')" /></x-ui.card>
    @else
        <div class="overflow-x-auto rounded-2xl border border-line">
            <table class="w-full text-sm">
                <thead class="bg-surface-raised text-muted">
                    <tr>
                        <th scope="col" class="px-4 py-2.5 text-end font-semibold">{{ __('leaderboards.col_rank') }}</th>
                        <th scope="col" class="px-4 py-2.5 text-start font-semibold">{{ __('leaderboards.col_roll') }}</th>
                        <th scope="col" class="px-4 py-2.5 text-start font-semibold">{{ __('leaderboards.col_name') }}</th>
                        <th scope="col" class="px-4 py-2.5 text-end font-semibold">{{ __('leaderboards.col_score') }}</th>
                        <th scope="col" class="px-4 py-2.5 text-end font-semibold">{{ __('leaderboards.col_percentage') }}</th>
                        <th scope="col" class="px-4 py-2.5 text-end font-semibold">{{ __('leaderboards.col_time') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($rows as $row)
                        <tr @class(['bg-brand-tint/60' => $me && $row['user_id'] === $me['user_id']])>
                            <td class="px-4 py-3 text-end font-black text-brand tabular-nums">{{ bn($row['rank']) }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ $row['roll'] ? bn($row['roll']) : '—' }}</td>
                            <td class="px-4 py-3 font-medium text-ink">
                                {{ $row['name'] }}
                                @if ($me && $row['user_id'] === $me['user_id'])<span class="ms-1 text-xs text-brand">({{ __('leaderboards.you') }})</span>@endif
                            </td>
                            <td class="px-4 py-3 text-end font-semibold text-ink tabular-nums whitespace-nowrap">{{ bn($row['obtained']) }} / {{ bn($row['total']) }}</td>
                            <td class="px-4 py-3 text-end tabular-nums">{{ bn(number_format($row['percentage'], 2)) }}%</td>
                            <td class="px-4 py-3 text-end text-muted tabular-nums whitespace-nowrap">{{ $fmtTime($row['time_taken_seconds']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4 print:hidden">{{ $rows->links() }}</div>
    @endif
</x-layout.student>
