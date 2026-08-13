@php
    $questions = $quiz->questions;   // already ordered by sort_order
    $ids = $questions->pluck('id')->values()->all();
    $banglaIndices = ['ক', 'খ', 'গ', 'ঘ', 'ঙ', 'চ', 'ছ', 'জ', 'ঝ', 'ঞ', 'ট', 'ঠ'];
    // Build swapped id-orders for move up/down (server-side reorder, no JS needed).
    $swap = function (array $a, int $i, int $j) { [$a[$i], $a[$j]] = [$a[$j], $a[$i]]; return $a; };
@endphp
<x-layout.admin :title="$quiz->title" heading="কুইজ সম্পাদনা">
    <x-ui.breadcrumbs :items="['কুইজ' => route('admin.quizzes.index'), $quiz->title => null]" class="mb-5" />

    <div class="flex flex-wrap items-center gap-2 mb-5">
        <x-ui.button :href="route('admin.quizzes.preview', $quiz)" variant="secondary" size="sm"><x-ui.icon name="exam" class="w-4 h-4" /> প্রিভিউ</x-ui.button>
    </div>

    @if ($scoringLocked)
        <x-ui.alert type="warning" title="স্কোরিং লক" class="mb-6">
            এই কুইজে অফিসিয়াল পরীক্ষা জমা পড়েছে। সঠিক উত্তর/নম্বর পরিবর্তনের জন্য Regrade ব্যবস্থা ব্যবহার করতে হবে। এখন শুধু বিবরণ ও সময়সূচির মতো তথ্য পরিবর্তন করা যাবে।
        </x-ui.alert>
    @endif

    {{-- Quiz configuration --}}
    <form method="POST" action="{{ route('admin.quizzes.update', $quiz) }}">
        @csrf @method('PUT')
        @include('admin.quizzes._form')
        <div class="flex justify-end gap-3 mt-6">
            <x-ui.button type="submit">সংরক্ষণ করুন</x-ui.button>
        </div>
    </form>

    {{-- Questions --}}
    <div class="mt-10">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-ink">প্রশ্নসমূহ <span class="text-muted font-normal">({{ bn($questions->count()) }} · মোট {{ bn($quiz->total_marks) }} নম্বর)</span></h2>
            @unless ($scoringLocked)
                <x-ui.button :href="route('admin.quizzes.questions.create', $quiz)" size="sm"><x-ui.icon name="plus" class="w-4 h-4" /> প্রশ্ন যোগ করুন</x-ui.button>
            @endunless
        </div>

        @if ($questions->isEmpty())
            <x-ui.card><x-ui.empty title="এখনো কোনো প্রশ্ন নেই।">উপরের বাটন থেকে প্রশ্ন যোগ করুন।</x-ui.empty></x-ui.card>
        @else
            <div class="space-y-3">
                @foreach ($questions as $i => $question)
                    <x-ui.card class="!p-4">
                        <div class="flex items-start gap-3">
                            <div class="shrink-0 flex flex-col items-center gap-1">
                                <span class="w-7 h-7 rounded-lg bg-brand-tint text-brand-strong grid place-items-center text-sm font-bold">{{ bn($i + 1) }}</span>
                                @unless ($scoringLocked)
                                    @if ($i > 0)
                                        <form method="POST" action="{{ route('admin.quizzes.questions.reorder', $quiz) }}">@csrf @method('PUT')
                                            @foreach ($swap($ids, $i, $i - 1) as $id)<input type="hidden" name="order[]" value="{{ $id }}">@endforeach
                                            <button class="text-muted hover:text-ink p-0.5" aria-label="উপরে">▲</button>
                                        </form>
                                    @endif
                                    @if ($i < count($ids) - 1)
                                        <form method="POST" action="{{ route('admin.quizzes.questions.reorder', $quiz) }}">@csrf @method('PUT')
                                            @foreach ($swap($ids, $i, $i + 1) as $id)<input type="hidden" name="order[]" value="{{ $id }}">@endforeach
                                            <button class="text-muted hover:text-ink p-0.5" aria-label="নিচে">▼</button>
                                        </form>
                                    @endif
                                @endunless
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="font-semibold text-ink">{{ $question->body }}</p>
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <x-ui.badge :color="$question->type === 'multiple' ? 'info' : 'neutral'">{{ $question->type === 'multiple' ? 'বহু-উত্তর' : 'একক' }}</x-ui.badge>
                                        <x-ui.badge color="brand">{{ bn($question->marks) }} নম্বর</x-ui.badge>
                                        @unless ($question->is_active)<x-ui.badge color="warning">নিষ্ক্রিয়</x-ui.badge>@endunless
                                    </div>
                                </div>
                                <ul class="mt-2 space-y-1">
                                    @foreach ($question->options as $oi => $option)
                                        <li class="flex items-center gap-2 text-sm {{ $option->is_correct ? 'text-brand-strong font-medium' : 'text-muted' }}">
                                            <span class="w-5 h-5 rounded grid place-items-center text-xs {{ $option->is_correct ? 'bg-brand text-brand-ink' : 'bg-ink/5' }}">{{ $banglaIndices[$oi] ?? ($oi + 1) }}</span>
                                            {{ $option->body }}
                                            @if ($option->is_correct)<x-ui.icon name="check" class="w-4 h-4" />@endif
                                        </li>
                                    @endforeach
                                </ul>
                                @unless ($scoringLocked)
                                    <div class="flex items-center gap-3 mt-3 text-sm">
                                        <a href="{{ route('admin.quizzes.questions.edit', [$quiz, $question]) }}" class="text-brand font-semibold hover:underline">সম্পাদনা</a>
                                        <form method="POST" action="{{ route('admin.quizzes.questions.duplicate', [$quiz, $question]) }}">@csrf<button class="text-muted hover:text-ink">ডুপ্লিকেট</button></form>
                                        <form method="POST" action="{{ route('admin.quizzes.questions.destroy', [$quiz, $question]) }}" onsubmit="return confirm('এই প্রশ্নটি মুছে ফেলবেন?')">@csrf @method('DELETE')<button class="text-rose-600 hover:underline">মুছুন</button></form>
                                    </div>
                                @endunless
                            </div>
                        </div>
                    </x-ui.card>
                @endforeach
            </div>
        @endif
    </div>
</x-layout.admin>
