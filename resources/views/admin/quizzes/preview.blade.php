@php
    $banglaIndices = ['ক', 'খ', 'গ', 'ঘ', 'ঙ', 'চ', 'ছ', 'জ', 'ঝ', 'ঞ', 'ট', 'ঠ'];
@endphp
<x-layout.admin :title="$quiz->title.' — প্রিভিউ'" heading="কুইজ প্রিভিউ">
    <x-ui.breadcrumbs :items="['কুইজ' => route('admin.quizzes.index'), $quiz->title => route('admin.quizzes.edit', $quiz), 'প্রিভিউ' => null]" class="mb-5" />

    <x-ui.alert type="info" title="প্রিভিউ — এটি প্রকৃত পরীক্ষা নয়" class="mb-6">
        এটি শুধুমাত্র অ্যাডমিন প্রিভিউ। এখানে সঠিক উত্তর দেখানো হচ্ছে। এই পাতা কোনো অ্যাটেম্পট তৈরি করে না, পয়েন্ট কাটে না, উত্তর সংরক্ষণ করে না বা মেধাতালিকায় প্রভাব ফেলে না।
    </x-ui.alert>

    <div class="max-w-3xl mx-auto">
        <x-ui.card class="mb-6 text-center">
            <p class="font-arabic text-xl text-brand mb-2">بِسْمِ ٱللَّٰهِ ٱلرَّحْمَٰنِ ٱلرَّحِيمِ</p>
            <h1 class="text-2xl font-black text-ink">{{ $quiz->title }}</h1>
            <div class="mt-3 flex flex-wrap items-center justify-center gap-2 text-sm">
                <x-ui.badge color="brand">{{ bn($quiz->questions->count()) }} প্রশ্ন</x-ui.badge>
                <x-ui.badge color="neutral">মোট {{ bn($quiz->total_marks) }} নম্বর</x-ui.badge>
                @if ($quiz->duration_seconds)<x-ui.badge color="neutral">{{ bn(intdiv($quiz->duration_seconds, 60)) }} মিনিট</x-ui.badge>@endif
                <x-ui.badge color="warning">{{ bn($quiz->point_cost) }} পয়েন্ট</x-ui.badge>
            </div>
        </x-ui.card>

        <div class="space-y-6">
            @foreach ($quiz->questions as $i => $question)
                <x-ui.card>
                    <div class="flex items-start gap-3">
                        <span class="shrink-0 w-8 h-8 rounded-full bg-brand text-brand-ink grid place-items-center font-bold text-sm">{{ bn($i + 1) }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="font-bold text-ink">{{ $question->body }}</h3>
                                <div class="flex gap-1.5 shrink-0">
                                    <x-ui.badge :color="$question->type === 'multiple' ? 'info' : 'neutral'">{{ $question->type === 'multiple' ? 'বহু-উত্তর' : 'একক' }}</x-ui.badge>
                                    <x-ui.badge color="brand">{{ bn($question->marks) }} নম্বর</x-ui.badge>
                                </div>
                            </div>
                            <div class="mt-3 space-y-2">
                                @foreach ($question->options as $oi => $option)
                                    <div class="flex items-center gap-3 p-3 rounded-xl border {{ $option->is_correct ? 'border-brand bg-brand-tint' : 'border-line' }}">
                                        <span class="shrink-0 w-8 h-8 rounded-lg grid place-items-center text-sm font-bold {{ $option->is_correct ? 'bg-brand text-brand-ink' : 'bg-ink/5 text-muted' }}">{{ $banglaIndices[$oi] ?? ($oi + 1) }}</span>
                                        <span class="text-sm {{ $option->is_correct ? 'text-brand-strong font-medium' : 'text-ink' }}">{{ $option->body }}</span>
                                        @if ($option->is_correct)<x-ui.icon name="check" class="w-4 h-4 text-brand ml-auto" />@endif
                                    </div>
                                @endforeach
                            </div>
                            @if ($question->explanation)
                                <div class="mt-3 text-sm text-muted bg-ink/[0.03] rounded-lg p-3">
                                    <span class="font-semibold text-ink">ব্যাখ্যা:</span> {{ $question->explanation }}
                                </div>
                            @endif
                        </div>
                    </div>
                </x-ui.card>
            @endforeach
        </div>
    </div>
</x-layout.admin>
