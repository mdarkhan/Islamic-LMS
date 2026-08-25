<x-layout.student :title="__('leaderboards.quiz_heading')" :heading="__('leaderboards.quiz_heading')">
    <x-leaderboards.picker :quizzes="$quizzes" :active-quiz-id="$quiz->id" />

    <div class="mb-3 flex items-center justify-between gap-3">
        @if ($me)
            <div class="inline-flex items-center rounded-xl bg-brand-tint px-4 py-2 text-lg font-bold text-brand-strong">
                {{ __('leaderboards.your_rank', ['rank' => bn($me['rank'])]) }}
            </div>
        @else
            <span></span>
        @endif
        <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 text-sm text-muted hover:text-ink print:hidden">
            <x-ui.icon name="download" class="w-4 h-4" /> {{ __('leaderboards.print') }}
        </button>
    </div>

    <x-leaderboards.table :rows="$rows" :me="$me" />
</x-layout.student>
