@php
    $config = $parsed['config'];
    $lessonsByCourse = $lessons->groupBy('course_id')->map(fn ($g) => $g->map(fn ($l) => ['id' => $l->id, 'title' => $l->title])->values());
    $banglaIndices = ['ক', 'খ', 'গ', 'ঘ', 'ঙ', 'চ', 'ছ', 'জ', 'ঝ', 'ঞ', 'ট', 'ঠ'];
@endphp
<x-layout.admin title="ইমপোর্ট প্রিভিউ" heading="কুইজ ইমপোর্ট প্রিভিউ">
    <x-ui.breadcrumbs :items="['কুইজ' => route('admin.quizzes.index'), 'ইমপোর্ট' => route('admin.quizzes.import.form'), 'প্রিভিউ' => null]" class="mb-5" />

    {{-- Sheet-level errors / warnings --}}
    @foreach ($parsed['errors'] as $error)
        <x-ui.alert type="error" class="mb-3">{{ $error }}</x-ui.alert>
    @endforeach
    @foreach ($parsed['warnings'] as $warning)
        <x-ui.alert type="warning" class="mb-3">{{ $warning }}</x-ui.alert>
    @endforeach
    @if ($config['schedule_error'])
        <x-ui.alert type="warning" class="mb-3">সময়সূচি: {{ $config['schedule_error'] }}</x-ui.alert>
    @endif
    @foreach ($suggestion['warnings'] as $warning)
        <x-ui.alert type="info" class="mb-3">{{ $warning }}</x-ui.alert>
    @endforeach
    @if ($duplicate)
        <x-ui.alert type="warning" title="সম্ভাব্য ডুপ্লিকেট" class="mb-3">
            "{{ $config['title'] }}" শিরোনামে একটি কুইজ ইতিমধ্যে আছে। চাইলে নতুন কপি হিসেবে ইমপোর্ট করুন, নয়তো বাতিল করুন। বিদ্যমান কুইজ কখনো ওভাররাইট হবে না।
        </x-ui.alert>
    @endif

    {{-- Summary --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <x-ui.stat label="বৈধ প্রশ্ন" :value="bn($parsed['valid_count'])" tone="brand" />
        <x-ui.stat label="মোট নম্বর" :value="bn($parsed['total_marks'])" tone="ink" />
        <x-ui.stat label="টাইমার" :value="$config['duration_seconds'] ? bn(intdiv($config['duration_seconds'], 60)).' মিনিট' : '—'" tone="ink" />
        <x-ui.stat label="সময়সূচি" :value="$config['starts_at'] ? $config['starts_at']->format('d/m H:i') : '—'" tone="amber" />
    </div>

    <form method="POST" action="{{ route('admin.quizzes.import.confirm') }}"
          x-data="{ course: '{{ old('course_id', $suggestion['course_id'] ?? '') }}', lessonsByCourse: {{ \Illuminate\Support\Js::from($lessonsByCourse) }} }">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="sheet" value="{{ $sheet }}">

        <x-ui.card class="mb-6">
            <h3 class="font-bold text-ink mb-4">কুইজ তথ্য</h3>
            <div class="grid sm:grid-cols-2 gap-4">
                <x-ui.field label="শিরোনাম" name="title" required class="sm:col-span-2">
                    <x-ui.input name="title" :value="old('title', $config['title'])" />
                </x-ui.field>
                <x-ui.field label="কোর্স" name="course_id">
                    <select name="course_id" x-model="course"
                            class="w-full rounded-xl bg-surface-raised text-ink border border-line focus:border-brand px-4 py-2.5 text-sm outline-none">
                        <option value="">— কোনোটি নয় —</option>
                        @foreach ($courses as $course)
                            <option value="{{ $course->id }}">{{ $course->title }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
                <x-ui.field label="ক্লাস (ঐচ্ছিক)" name="lesson_id">
                    <select name="lesson_id"
                            class="w-full rounded-xl bg-surface-raised text-ink border border-line focus:border-brand px-4 py-2.5 text-sm outline-none">
                        <option value="">— কোনোটি নয় —</option>
                        <template x-for="lesson in (lessonsByCourse[course] || [])" :key="lesson.id">
                            <option :value="lesson.id" x-text="lesson.title" :selected="lesson.id == {{ old('lesson_id', $suggestion['lesson_id'] ?? 'null') ?: 'null' }}"></option>
                        </template>
                    </select>
                </x-ui.field>
                <x-ui.field label="পয়েন্ট খরচ" name="point_cost" required>
                    <x-ui.input type="number" name="point_cost" min="0" :value="old('point_cost', 1)" />
                </x-ui.field>
            </div>
            <p class="text-xs text-muted mt-3">ইমপোর্ট করা কুইজ <span class="font-medium text-ink">খসড়া</span> হিসেবে সংরক্ষিত হবে — প্রকাশের আগে যাচাই করে নিতে পারবেন।</p>
        </x-ui.card>

        {{-- Questions --}}
        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-3">সারি</th>
                <th class="px-4 py-3">প্রশ্ন</th>
                <th class="px-4 py-3 text-center">অপশন</th>
                <th class="px-4 py-3">সঠিক</th>
                <th class="px-4 py-3 text-center">নম্বর</th>
                <th class="px-4 py-3">অবস্থা</th>
            </x-slot:head>
            @foreach ($parsed['questions'] as $q)
                <tr class="{{ $q['errors'] ? 'bg-rose-500/[0.03]' : '' }}">
                    <td class="px-4 py-3 text-muted tabular-nums">{{ bn($q['row']) }}</td>
                    <td class="px-4 py-3 text-ink">{{ \Illuminate\Support\Str::limit($q['body'], 60) ?: '—' }}</td>
                    <td class="px-4 py-3 text-center tabular-nums">{{ bn(count($q['options'])) }}</td>
                    <td class="px-4 py-3 text-sm text-muted">
                        {{ collect($q['correct_positions'])->map(fn ($p) => $banglaIndices[$p - 1] ?? $p)->implode(', ') ?: '—' }}
                        @if (count($q['correct_positions']) > 1)<x-ui.badge color="info" class="ml-1">বহু</x-ui.badge>@endif
                    </td>
                    <td class="px-4 py-3 text-center tabular-nums">{{ bn($q['marks']) }}</td>
                    <td class="px-4 py-3">
                        @if ($q['errors'])
                            <x-ui.badge color="danger">ত্রুটি</x-ui.badge>
                            <p class="text-xs text-rose-600 mt-1">{{ implode(' ', $q['errors']) }}</p>
                        @else
                            <x-ui.badge color="success">ঠিক আছে</x-ui.badge>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-ui.table>

        <div class="mt-6 flex items-center justify-between gap-3">
            <x-ui.button :href="route('admin.quizzes.import.form')" variant="ghost">বাতিল</x-ui.button>
            <x-ui.button type="submit" :disabled="$parsed['has_fatal']">
                @if ($duplicate) নতুন কপি হিসেবে ইমপোর্ট করুন @else ইমপোর্ট নিশ্চিত করুন @endif
            </x-ui.button>
        </div>
        @if ($parsed['has_fatal'])
            <p class="text-right text-xs text-rose-600 mt-2">ত্রুটি সংশোধন না করা পর্যন্ত ইমপোর্ট করা যাবে না।</p>
        @endif
    </form>
</x-layout.admin>
