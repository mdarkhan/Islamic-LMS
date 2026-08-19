{{-- The "congratulations" screen: shown once, after login, for bonus points a student
     has just earned. Dismissing it marks the rewards seen so it never repeats. --}}
@php use App\Models\RewardGrant; @endphp

<div x-data="{ open: true }" x-show="open" x-cloak
     class="fixed inset-0 z-50 grid place-items-center bg-black/60 p-4"
     @keydown.escape.window="open = false">
    <div class="w-full max-w-md overflow-hidden rounded-2xl border border-line bg-card shadow-2xl">
        <div class="bg-brand px-6 py-6 text-center text-brand-ink">
            <div class="text-5xl leading-none">🎉</div>
            <h2 class="mt-2 text-xl font-black">{{ __('rewards.congrats_title') }}</h2>
            <p class="mt-1 text-sm opacity-90">{{ __('rewards.congrats_sub') }}</p>
        </div>

        <div class="max-h-72 space-y-2.5 overflow-y-auto p-5">
            @foreach ($rewards as $reward)
                <div class="flex items-start gap-3 rounded-xl border border-line bg-surface-raised p-3">
                    <div class="text-2xl leading-none">{{ $reward->type === RewardGrant::TYPE_COURSE_TOPPER ? '🏆' : '⭐' }}</div>
                    <div class="flex-1 text-sm">
                        <p class="text-ink leading-snug">
                            @if ($reward->type === RewardGrant::TYPE_COURSE_TOPPER)
                                {{ __('rewards.topper_line', ['course' => $reward->course?->title, 'position' => bn($reward->position)]) }}
                            @else
                                {{ __('rewards.quiz_line', ['quiz' => $reward->quiz?->title]) }}
                            @endif
                        </p>
                        <p class="mt-0.5 font-bold text-brand-strong tabular-nums">+{{ bn($reward->points) }} {{ __('rewards.points') }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="border-t border-line p-5">
            <form method="POST" action="{{ route('student.rewards.seen') }}">
                @csrf
                <x-ui.button type="submit" class="w-full">{{ __('rewards.dismiss') }}</x-ui.button>
            </form>
        </div>
    </div>
</div>
