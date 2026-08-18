@php
    $boot = [
        'questions' => $quiz->questions->map(fn ($q) => [
            'id' => (int) $q->id,
            'body' => $q->body,
            'marks' => (int) $q->marks,
            'type' => $q->type,
            'explanation' => $q->explanation,
            'options' => $q->options->map(fn ($o) => ['id' => (int) $o->id, 'body' => $o->body, 'correct' => (bool) $o->is_correct])->values(),
        ])->values(),
        'previewUrl' => route('admin.quizzes.regrade.preview', $quiz),
        'csrf' => csrf_token(),
    ];
@endphp

<x-layout.admin :title="__('results_admin.regrade_heading')" :heading="__('results_admin.regrade_heading')">
    <div class="mb-4">
        <x-ui.button :href="route('admin.quizzes.edit', $quiz)" variant="ghost" size="sm">
            <x-ui.icon name="chevron" class="w-4 h-4 rotate-180" /> {{ $quiz->title }}
        </x-ui.button>
    </div>

    <x-ui.alert type="warning" class="mb-5">{{ __('results_admin.regrade_intro') }}</x-ui.alert>

    <div x-data="regradeForm()" x-init="init(@js($boot))" class="grid gap-5 lg:grid-cols-2">
        {{-- Correction form --}}
        <form method="POST" action="{{ route('admin.quizzes.regrade.apply', $quiz) }}" class="space-y-4">
            @csrf
            <input type="hidden" name="question_id" :value="questionId">

            <x-ui.field :label="__('results_admin.regrade_pick_question')">
                <x-ui.select x-model.number="questionId" @change="pick()">
                    <template x-for="(q, i) in questions" :key="q.id">
                        <option :value="q.id" x-text="'#' + (i + 1) + ' — ' + q.body.slice(0, 60)"></option>
                    </template>
                </x-ui.select>
            </x-ui.field>

            <template x-if="question">
                <div class="space-y-4">
                    <x-ui.field :label="__('results_admin.regrade_new_correct')">
                        <div class="space-y-2">
                            <template x-for="opt in question.options" :key="opt.id">
                                <label class="flex items-center gap-3 rounded-xl border border-line px-4 py-2.5 cursor-pointer"
                                       :class="correct.includes(opt.id) ? 'border-brand bg-brand-tint' : ''">
                                    <input type="checkbox" name="correct_option_ids[]" :value="opt.id"
                                           :checked="correct.includes(opt.id)" @change="toggleCorrect(opt.id)"
                                           class="rounded border-line text-brand focus:ring-brand">
                                    <span x-text="opt.body" class="flex-1 text-ink"></span>
                                </label>
                            </template>
                        </div>
                        @error('correct_option_ids')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                    </x-ui.field>

                    <div class="grid grid-cols-2 gap-3">
                        <x-ui.field :label="__('results_admin.regrade_marks')" name="marks">
                            <x-ui.input type="number" name="marks" x-model.number="marks" min="1" />
                        </x-ui.field>
                        <x-ui.field :label="__('results_admin.regrade_type')" name="type">
                            <x-ui.select name="type" x-model="type" @change="onType()">
                                <option value="single">{{ __('results_admin.regrade_type_single') }}</option>
                                <option value="multiple">{{ __('results_admin.regrade_type_multiple') }}</option>
                            </x-ui.select>
                        </x-ui.field>
                    </div>

                    <x-ui.field :label="__('results_admin.regrade_explanation')" name="explanation">
                        <x-ui.textarea name="explanation" x-model="explanation" rows="2" />
                    </x-ui.field>

                    <x-ui.field :label="__('results_admin.regrade_reason')" name="reason">
                        <x-ui.input name="reason" x-model="reason" required />
                    </x-ui.field>

                    <div class="flex items-center gap-3">
                        <x-ui.button type="button" variant="secondary" @click="previewImpact()" x-bind:disabled="loading">
                            {{ __('results_admin.regrade_preview_btn') }}
                        </x-ui.button>
                        <x-ui.button type="submit" x-bind:disabled="!preview || !reason">
                            {{ __('results_admin.regrade_confirm') }}
                        </x-ui.button>
                    </div>
                </div>
            </template>
        </form>

        {{-- Impact preview --}}
        <div>
            <template x-if="preview">
                <x-ui.card>
                    <p class="text-sm text-muted">
                        <span x-text="impactLine()"></span>
                    </p>
                    <template x-if="preview.samples && preview.samples.length">
                        <div class="mt-4">
                            <h4 class="text-xs font-semibold uppercase tracking-wider text-muted mb-2">{{ __('results_admin.regrade_samples') }}</h4>
                            <table class="w-full text-sm">
                                <tbody class="divide-y divide-line">
                                    <template x-for="s in preview.samples" :key="s.roll + s.name">
                                        <tr>
                                            <td class="py-1.5" x-text="s.name"></td>
                                            <td class="py-1.5 text-end tabular-nums" x-text="s.old_final + ' → ' + s.new_final"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </x-ui.card>
            </template>
        </div>
    </div>

    <script>
        window.regradeForm = function () {
            return {
                questions: [], questionId: null, question: null,
                correct: [], marks: 1, type: 'single', explanation: '', reason: '',
                preview: null, loading: false, previewUrl: '', csrf: '',

                init(data) {
                    this.questions = data.questions || [];
                    this.previewUrl = data.previewUrl;
                    this.csrf = data.csrf;
                    if (this.questions.length) { this.questionId = this.questions[0].id; this.pick(); }
                },

                pick() {
                    this.question = this.questions.find((q) => q.id === this.questionId) || null;
                    this.preview = null;
                    if (!this.question) return;
                    this.correct = this.question.options.filter((o) => o.correct).map((o) => o.id);
                    this.marks = this.question.marks;
                    this.type = this.question.type;
                    this.explanation = this.question.explanation || '';
                },

                toggleCorrect(id) {
                    this.preview = null;
                    if (this.type === 'single') { this.correct = [id]; return; }
                    this.correct = this.correct.includes(id)
                        ? this.correct.filter((x) => x !== id)
                        : this.correct.concat([id]);
                },

                onType() {
                    if (this.type === 'single' && this.correct.length > 1) this.correct = [this.correct[0]];
                    this.preview = null;
                },

                async previewImpact() {
                    if (!this.question) return;
                    this.loading = true;
                    try {
                        const res = await fetch(this.previewUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                            body: JSON.stringify({ question_id: this.questionId, correct_option_ids: this.correct, marks: this.marks }),
                        });
                        if (res.ok) this.preview = await res.json();
                    } finally {
                        this.loading = false;
                    }
                },

                impactLine() {
                    if (!this.preview) return '';
                    return @js(__('results_admin.regrade_impact'))
                        .replace(':total', this.preview.affected_total)
                        .replace(':changed', this.preview.changed_count)
                        .replace(':unchanged', this.preview.unchanged_count)
                        .replace(':skipped', this.preview.skipped_count);
                },
            };
        };
    </script>
</x-layout.admin>
