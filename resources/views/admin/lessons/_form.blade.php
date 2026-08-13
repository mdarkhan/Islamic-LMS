@php
    // Seed the Alpine resource editor from old input, else the lesson's resources.
    $resourceRows = old('resources', isset($lesson)
        ? $lesson->resources->map(fn ($r) => ['label' => $r->label, 'url' => $r->url, 'kind' => $r->kind])->values()->all()
        : []);
    if (empty($resourceRows)) {
        $resourceRows = [['label' => '', 'url' => '', 'kind' => 'link']];
    }
@endphp

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-5">
        <x-ui.card>
            <div class="space-y-5">
                <x-ui.field label="কোর্স" name="course_id" required>
                    <x-ui.select name="course_id">
                        @foreach ($courses as $course)
                            <option value="{{ $course->id }}" @selected(old('course_id', $lesson->course_id ?? null) == $course->id)>{{ $course->title }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field label="শিরোনাম" name="title" required>
                    <x-ui.input name="title" :value="old('title', $lesson->title ?? '')" />
                </x-ui.field>
                <x-ui.field label="বিবরণ" name="description">
                    <x-ui.textarea name="description">{{ old('description', $lesson->description ?? '') }}</x-ui.textarea>
                </x-ui.field>
                <x-ui.field label="পরবর্তী প্রস্তুতি / সিলেবাস" name="syllabus">
                    <x-ui.textarea name="syllabus">{{ old('syllabus', $lesson->syllabus ?? '') }}</x-ui.textarea>
                </x-ui.field>
            </div>
        </x-ui.card>

        {{-- Resources --}}
        <x-ui.card x-data="{ rows: {{ Illuminate\Support\Js::from($resourceRows) }} }">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-ink">রিসোর্স ও নোট</h3>
                <button type="button" @click="rows.push({ label: '', url: '', kind: 'link' })" class="text-sm font-semibold text-brand hover:underline">+ যোগ করুন</button>
            </div>
            <div class="space-y-3">
                <template x-for="(row, i) in rows" :key="i">
                    <div class="grid grid-cols-1 sm:grid-cols-[1fr_1fr_auto] gap-2 items-start">
                        <input x-model="row.label" :name="`resources[${i}][label]`" placeholder="লেবেল (যেমনঃ বই)"
                               class="rounded-xl bg-surface-raised border border-line px-3 py-2 text-sm outline-none focus:border-brand">
                        <input x-model="row.url" :name="`resources[${i}][url]`" placeholder="লিংক (ঐচ্ছিক)"
                               class="rounded-xl bg-surface-raised border border-line px-3 py-2 text-sm outline-none focus:border-brand">
                        <div class="flex items-center gap-1">
                            <select x-model="row.kind" :name="`resources[${i}][kind]`" class="rounded-xl bg-surface-raised border border-line px-2 py-2 text-sm outline-none focus:border-brand">
                                <option value="link">লিংক</option>
                                <option value="book">বই</option>
                                <option value="file">ফাইল</option>
                                <option value="note">নোট</option>
                            </select>
                            <button type="button" @click="rows.splice(i, 1)" class="p-2 text-muted hover:text-rose-600" aria-label="মুছুন"><x-ui.icon name="close" class="w-4 h-4" /></button>
                        </div>
                    </div>
                </template>
            </div>
            <p class="text-xs text-muted mt-3">লিংক ছাড়া রিসোর্স শিক্ষার্থীদের কাছে সাধারণ টেক্সট হিসেবে দেখানো হবে (ভাঙা লিংক নয়)।</p>
        </x-ui.card>
    </div>

    {{-- Sidebar meta --}}
    <div class="space-y-5">
        <x-ui.card>
            <h3 class="font-bold text-ink mb-4">তথ্য</h3>
            <div class="space-y-4">
                <x-ui.field label="তারিখ" name="held_on" hint="ক্যালেন্ডার থেকে বেছে নিন — এই তারিখই শিক্ষার্থীদের কাছে লেবেল হিসেবে দেখানো হবে">
                    <x-ui.input name="held_on" type="date" :value="old('held_on', optional($lesson->held_on ?? null)->format('Y-m-d'))" />
                </x-ui.field>
                <div class="grid grid-cols-2 gap-3">
                    <x-ui.field label="মিনিট" name="duration_minutes">
                        <x-ui.input name="duration_minutes" type="number" min="0" :value="old('duration_minutes', $lesson->duration_minutes ?? '')" />
                    </x-ui.field>
                    <x-ui.field label="সময় লেবেল" name="duration_label">
                        <x-ui.input name="duration_label" :value="old('duration_label', $lesson->duration_label ?? '')" />
                    </x-ui.field>
                </div>
                <x-ui.field label="মিডিয়া উৎস" name="media_provider">
                    <x-ui.select name="media_provider">
                        @foreach (['google_drive' => 'Google Drive', 'external' => 'অন্যান্য লিংক', 'none' => 'নেই'] as $val => $label)
                            <option value="{{ $val }}" @selected(old('media_provider', $lesson->media_provider ?? 'google_drive') === $val)>{{ $label }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field label="মিডিয়া লিংক" name="media_url">
                    <x-ui.input name="media_url" :value="old('media_url', $lesson->media_url ?? '')" placeholder="https://drive.google.com/..." />
                </x-ui.field>
                <label class="flex items-center gap-2 text-sm text-ink pt-1">
                    <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $lesson->is_published ?? true)) class="rounded border-line text-brand focus:ring-brand">
                    প্রকাশিত
                </label>
            </div>
        </x-ui.card>
    </div>
</div>
