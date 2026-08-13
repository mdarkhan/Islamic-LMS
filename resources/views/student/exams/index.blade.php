@php
    $sections = [
        'open' => ['এখন চলছে', 'success'],
        'upcoming' => ['আসন্ন পরীক্ষা', 'info'],
        'completed' => ['সম্পন্ন', 'neutral'],
        'previous' => ['পূর্ববর্তী / আর্কাইভ', 'warning'],
    ];
@endphp
<x-layout.student title="পরীক্ষা" heading="পরীক্ষা">
    <div class="flex items-center justify-between mb-6">
        <p class="text-sm text-muted">আপনার পয়েন্ট</p>
        <span class="text-2xl font-black text-brand tabular-nums">{{ bn($user->points_balance) }}</span>
    </div>

    @php $anything = collect($groups)->flatten(1)->isNotEmpty(); @endphp
    @unless ($anything)
        <x-ui.card><x-ui.empty title="এখনো কোনো পরীক্ষা নেই।">নতুন পরীক্ষা প্রকাশিত হলে এখানে দেখা যাবে।</x-ui.empty></x-ui.card>
    @endunless

    @foreach ($sections as $key => [$title, $tone])
        @if (! empty($groups[$key]))
            <section class="mb-8">
                <h2 class="text-sm font-bold uppercase tracking-wider text-muted mb-3">{{ $title }}</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($groups[$key] as $card)
                        @php $quiz = $card['quiz']; @endphp
                        <x-ui.card class="flex flex-col">
                            <div class="flex items-start justify-between gap-2 mb-2">
                                <x-ui.badge :color="$tone">{{ $title }}</x-ui.badge>
                                @if ($card['practice_available'])<x-ui.badge color="brand">অনুশীলন উপলব্ধ</x-ui.badge>@endif
                            </div>
                            <h3 class="font-bold text-ink">{{ $quiz->title }}</h3>
                            @if ($quiz->course)<p class="text-xs text-muted mt-0.5">{{ $quiz->course->title }}</p>@endif

                            <dl class="mt-3 space-y-1.5 text-sm text-muted">
                                @if ($quiz->starts_at)
                                    <div class="flex items-center gap-2"><x-ui.icon name="calendar" class="w-4 h-4" /> {{ $quiz->starts_at->format('d/m/Y H:i') }}</div>
                                @endif
                                <div class="flex items-center gap-4">
                                    @if ($quiz->duration_seconds)<span class="flex items-center gap-1.5"><x-ui.icon name="clock" class="w-4 h-4" /> {{ bn(intdiv($quiz->duration_seconds, 60)) }} মিনিট</span>@endif
                                    <span class="flex items-center gap-1.5"><x-ui.icon name="results" class="w-4 h-4" /> {{ bn($quiz->total_marks) }} নম্বর</span>
                                </div>
                                <div class="flex items-center gap-1.5"><x-ui.icon name="points" class="w-4 h-4" /> {{ bn($quiz->point_cost) }} পয়েন্ট</div>
                            </dl>

                            <div class="mt-4 pt-3 border-t border-line text-sm">
                                @if ($card['completed'])
                                    @if ($card['results_pending'])
                                        <span class="text-muted">ফলাফল প্রকাশ অপেক্ষমাণ</span>
                                    @else
                                        <span class="font-semibold text-brand">প্রাপ্ত নম্বর: {{ bn($card['score']) }} / {{ bn($quiz->total_marks) }}</span>
                                    @endif
                                @elseif ($card['resume'])
                                    <span class="font-semibold text-brand">চালিয়ে যাওয়ার জন্য প্রস্তুত</span>
                                @elseif ($key === 'open')
                                    @if ($card['enough_points'])
                                        <span class="text-muted">অংশগ্রহণের জন্য প্রস্তুত <span class="text-xs">(শীঘ্রই চালু হবে)</span></span>
                                    @else
                                        <span class="text-rose-600">পর্যাপ্ত পয়েন্ট নেই</span>
                                    @endif
                                @elseif ($key === 'upcoming')
                                    <span class="text-muted">শুরুর অপেক্ষায়</span>
                                @else
                                    <span class="text-muted">{{ $card['state'] === 'archived' ? 'আর্কাইভকৃত' : 'সমাপ্ত' }}</span>
                                @endif
                            </div>
                        </x-ui.card>
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach
</x-layout.student>
