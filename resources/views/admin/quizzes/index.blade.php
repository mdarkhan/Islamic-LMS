@php
    $statusMeta = [
        'draft' => ['neutral', 'খসড়া'],
        'scheduled' => ['info', 'নির্ধারিত'],
        'published' => ['success', 'প্রকাশিত'],
        'archived' => ['warning', 'সংরক্ষণাগার'],
    ];
    $me = auth()->user();
@endphp
<x-layout.admin title="কুইজ" heading="কুইজ ব্যবস্থাপনা">
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-6">
        <form method="GET" class="flex-1 flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <x-ui.icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
                <input name="q" value="{{ $search }}" placeholder="শিরোনাম দিয়ে খুঁজুন..."
                       class="w-full rounded-xl bg-surface-raised border border-line pl-9 pr-4 py-2.5 text-sm outline-none focus:border-brand">
            </div>
            <x-ui.select name="status" class="sm:w-40" onchange="this.form.submit()">
                <option value="">সব অবস্থা</option>
                @foreach ($statusMeta as $val => [$c, $label])
                    <option value="{{ $val }}" @selected($status === $val)>{{ $label }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.select name="course" class="sm:w-44" onchange="this.form.submit()">
                <option value="">সব কোর্স</option>
                @foreach ($courses as $course)
                    <option value="{{ $course->slug }}" @selected($courseFilter === $course->slug)>{{ $course->title }}</option>
                @endforeach
            </x-ui.select>
        </form>
        <div class="flex gap-2">
            @if ($me->hasPermission('quizzes.import'))
                <x-ui.button :href="route('admin.quizzes.import.form')" variant="secondary"><x-ui.icon name="import" class="w-4 h-4" /> ইমপোর্ট</x-ui.button>
            @endif
            @if ($me->hasPermission('quizzes.create'))
                <x-ui.button :href="route('admin.quizzes.create')"><x-ui.icon name="plus" class="w-4 h-4" /> নতুন</x-ui.button>
            @endif
        </div>
    </div>

    @if ($quizzes->isEmpty())
        <x-ui.card><x-ui.empty title="কোনো কুইজ নেই।">প্রথম কুইজ তৈরি করুন অথবা ইমপোর্ট করুন।</x-ui.empty></x-ui.card>
    @else
        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-3">শিরোনাম</th>
                <th class="px-4 py-3 hidden md:table-cell">কোর্স / ক্লাস</th>
                <th class="px-4 py-3">অবস্থা</th>
                <th class="px-4 py-3 text-center hidden sm:table-cell">প্রশ্ন</th>
                <th class="px-4 py-3 text-center hidden sm:table-cell">নম্বর</th>
                <th class="px-4 py-3 text-center hidden lg:table-cell">অ্যাটেম্পট</th>
                <th class="px-4 py-3"></th>
            </x-slot:head>
            @foreach ($quizzes as $quiz)
                @php [$color, $label] = $statusMeta[$quiz->status]; @endphp
                <tr>
                    <td class="px-4 py-3">
                        <p class="font-semibold text-ink">{{ $quiz->title }}</p>
                        <p class="text-xs text-muted">
                            {{ $quiz->point_cost }} পয়েন্ট
                            @if ($quiz->duration_seconds) · {{ bn(intdiv($quiz->duration_seconds, 60)) }} মিনিট @endif
                            @if ($quiz->practice_enabled) · অনুশীলন @endif
                        </p>
                        @if ($quiz->starts_at)
                            <p class="text-xs text-muted mt-0.5">{{ $quiz->starts_at->format('d/m/Y H:i') }}@if ($quiz->ends_at) – {{ $quiz->ends_at->format('d/m H:i') }}@endif</p>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-muted text-sm hidden md:table-cell">
                        {{ $quiz->course?->title ?? '—' }}
                        @if ($quiz->lesson)<span class="block text-xs">{{ $quiz->lesson->title }}</span>@endif
                    </td>
                    <td class="px-4 py-3"><x-ui.badge :color="$color">{{ $label }}</x-ui.badge></td>
                    <td class="px-4 py-3 text-center tabular-nums hidden sm:table-cell">{{ bn($quiz->questions_count) }}</td>
                    <td class="px-4 py-3 text-center tabular-nums hidden sm:table-cell">{{ bn($quiz->total_marks) }}</td>
                    <td class="px-4 py-3 text-center tabular-nums hidden lg:table-cell">
                        {{ bn($quiz->official_attempts_count) }}
                        @if ($quiz->official_attempts_count > 0)<x-ui.icon name="key" class="w-3.5 h-3.5 inline text-amber-500" title="লক" />@endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end" x-data="{ open: false }">
                            <button @click="open = !open" class="p-2 rounded-lg text-muted hover:text-ink hover:bg-ink/5" aria-label="অ্যাকশন">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
                            </button>
                            <div x-show="open" x-cloak @click.outside="open = false" x-transition
                                 class="absolute mt-2 right-6 z-20 w-48 bg-card border border-line rounded-xl shadow-lg py-1 text-sm">
                                <a href="{{ route('admin.quizzes.edit', $quiz) }}" class="block px-4 py-2 hover:bg-ink/5 text-ink">সম্পাদনা</a>
                                <a href="{{ route('admin.quizzes.preview', $quiz) }}" class="block px-4 py-2 hover:bg-ink/5 text-ink">প্রিভিউ</a>
                                @if ($me->hasPermission('quizzes.create'))
                                    <form method="POST" action="{{ route('admin.quizzes.duplicate', $quiz) }}">@csrf<button class="block w-full text-left px-4 py-2 hover:bg-ink/5 text-ink">ডুপ্লিকেট</button></form>
                                @endif
                                @if ($me->hasPermission('quizzes.publish'))
                                    <div class="border-t border-line my-1"></div>
                                    @foreach (['published' => 'প্রকাশ', 'scheduled' => 'নির্ধারিত', 'archived' => 'আর্কাইভ'] as $st => $lbl)
                                        @if ($quiz->status !== $st)
                                            <form method="POST" action="{{ route('admin.quizzes.status', $quiz) }}">@csrf @method('PUT')<input type="hidden" name="status" value="{{ $st }}"><button class="block w-full text-left px-4 py-2 hover:bg-ink/5 text-ink">{{ $lbl }} করুন</button></form>
                                        @endif
                                    @endforeach
                                @endif
                                @if (! $quiz->official_attempts_count)
                                    <div class="border-t border-line my-1"></div>
                                    <form method="POST" action="{{ route('admin.quizzes.destroy', $quiz) }}" onsubmit="return confirm('এই কুইজটি মুছে ফেলবেন?')">@csrf @method('DELETE')<button class="block w-full text-left px-4 py-2 hover:bg-rose-500/10 text-rose-600">মুছুন</button></form>
                                @endif
                            </div>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
        <div class="mt-6">{{ $quizzes->links('components.pagination') }}</div>
    @endif
</x-layout.admin>
