@php
    $seed = old('options');
    if ($seed === null) {
        $seed = isset($question)
            ? $question->options->map(fn ($o) => ['body' => $o->body, 'correct' => (bool) $o->is_correct])->values()->all()
            : [['body' => '', 'correct' => false], ['body' => '', 'correct' => false]];
    } else {
        $seed = collect($seed)->map(fn ($o) => ['body' => $o['body'] ?? '', 'correct' => (bool) ($o['correct'] ?? false)])->values()->all();
    }
    $type = old('type', $question->type ?? 'single');
@endphp

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-5"
         x-data="{
            type: '{{ $type }}',
            options: {{ \Illuminate\Support\Js::from($seed) }},
            indices: ['ক','খ','গ','ঘ','ঙ','চ','ছ','জ','ঝ','ঞ','ট','ঠ'],
            setCorrect(i) {
                if (this.type === 'single') { this.options.forEach((o, idx) => o.correct = idx === i); }
                else { this.options[i].correct = ! this.options[i].correct; }
            },
            onTypeChange() {
                if (this.type === 'single') {
                    let first = this.options.findIndex(o => o.correct);
                    this.options.forEach((o, idx) => o.correct = idx === first && first !== -1);
                }
            },
            add() { if (this.options.length < 12) this.options.push({ body: '', correct: false }); },
            remove(i) { if (this.options.length > 2) this.options.splice(i, 1); },
         }">
        <x-ui.card>
            <div class="space-y-5">
                <x-ui.field label="প্রশ্ন" name="body" required>
                    <x-ui.textarea name="body">{{ old('body', $question->body ?? '') }}</x-ui.textarea>
                </x-ui.field>
                <div class="grid sm:grid-cols-2 gap-4">
                    <x-ui.field label="ধরন" name="type">
                        <select name="type" x-model="type" @change="onTypeChange()"
                                class="w-full rounded-xl bg-surface-raised text-ink border border-line focus:border-brand px-4 py-2.5 text-sm outline-none">
                            <option value="single">একক উত্তর</option>
                            <option value="multiple">বহু-উত্তর</option>
                        </select>
                    </x-ui.field>
                    <x-ui.field label="নম্বর" name="marks" required>
                        <x-ui.input type="number" name="marks" min="1" :value="old('marks', $question->marks ?? 1)" />
                    </x-ui.field>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card>
            <div class="flex items-center justify-between mb-1">
                <h3 class="font-bold text-ink">অপশন <span class="text-muted font-normal text-sm">(<span x-text="type === 'single' ? 'একটি সঠিক' : 'এক বা একাধিক সঠিক'"></span>)</span></h3>
                <button type="button" @click="add()" x-show="options.length < 12" class="text-sm font-semibold text-brand hover:underline">+ অপশন</button>
            </div>
            @error('options')<p class="text-xs text-rose-600 mb-2">{{ $message }}</p>@enderror
            <div class="space-y-2">
                <template x-for="(row, i) in options" :key="i">
                    <div class="flex items-center gap-2">
                        <button type="button" @click="setCorrect(i)"
                                :class="row.correct ? 'bg-brand text-brand-ink border-brand' : 'bg-surface-raised border-line text-muted'"
                                class="shrink-0 w-9 h-9 rounded-lg border grid place-items-center font-bold transition-colors"
                                :aria-label="row.correct ? 'সঠিক উত্তর' : 'সঠিক হিসেবে চিহ্নিত করুন'">
                            <span x-text="indices[i]"></span>
                        </button>
                        <input x-model="row.body" :name="`options[${i}][body]`" placeholder="অপশন লিখুন..."
                               class="flex-1 rounded-xl bg-surface-raised border border-line px-3 py-2 text-sm outline-none focus:border-brand">
                        <template x-if="row.correct"><input type="hidden" :name="`options[${i}][correct]`" value="1"></template>
                        <button type="button" @click="remove(i)" x-show="options.length > 2" class="p-2 text-muted hover:text-rose-600" aria-label="মুছুন"><x-ui.icon name="close" class="w-4 h-4" /></button>
                    </div>
                </template>
            </div>
            <p class="text-xs text-muted mt-3">সঠিক উত্তর চিহ্নিত করতে অপশনের অক্ষরে ক্লিক করুন। ২–১২টি অপশন দেওয়া যাবে।</p>
        </x-ui.card>
    </div>

    <div class="space-y-5">
        <x-ui.card>
            <h3 class="font-bold text-ink mb-4">অতিরিক্ত</h3>
            <div class="space-y-4">
                <x-ui.field label="ব্যাখ্যা (ঐচ্ছিক)" name="explanation" hint="ফলাফলের সময় দেখানো হতে পারে">
                    <x-ui.textarea name="explanation">{{ old('explanation', $question->explanation ?? '') }}</x-ui.textarea>
                </x-ui.field>
                <label class="flex items-center gap-2 text-sm text-ink">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $question->is_active ?? true)) class="rounded border-line text-brand focus:ring-brand">
                    সক্রিয় (নিষ্ক্রিয় প্রশ্ন নম্বরে যোগ হয় না)
                </label>
            </div>
        </x-ui.card>
    </div>
</div>
