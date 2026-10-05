@php
    // The screen is shared by the official exam and Practice Mode. Each controller
    // supplies its own endpoints, submit action and (for practice) a mode badge;
    // the official routes remain the default so the Phase 7 flow is unchanged.
    $endpoints = $endpoints ?? [
        'answer' => route('student.attempts.answer', ['attempt' => $attempt->id, 'question' => '__Q__']),
        'status' => route('student.attempts.status', $attempt),
        'result' => route('student.attempts.result', $attempt),
    ];
    $submitAction = $submitAction ?? route('student.attempts.submit', $attempt);
    $practiceLabel = $practiceLabel ?? null;

    // Boot payload for the browser. Deliberately carries ONLY the allow-listed
    // question data from ExamAttemptPresenter — no answer key, marks or explanation.
    $boot = [
        'questions' => $questions,
        'selections' => (object) $selections,  // force a JS object even for int keys
        'remaining' => $remaining,
        'endpoints' => $endpoints,
        'csrf' => csrf_token(),
        'bnDigits' => app()->getLocale() === 'bn',
    ];
@endphp

<x-layout.base :title="$quiz->title">
    <div x-data="examRunner()" x-init="init(@js($boot))" class="min-h-screen flex flex-col bg-surface text-ink">

        {{-- Header: title, live timer, answered count --}}
        <header class="sticky top-0 z-30 bg-surface/90 backdrop-blur border-b border-line">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        @if ($practiceLabel)
                            <x-ui.badge color="brand">{{ $practiceLabel }}</x-ui.badge>
                        @endif
                        <h1 class="font-bold text-ink truncate">{{ $quiz->title }}</h1>
                    </div>
                    <p class="text-xs text-muted" x-text="answeredLabel()"></p>
                </div>
                <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                    <template x-if="remaining !== null">
                        <div class="flex items-center gap-1.5 rounded-xl px-3 py-1.5 font-bold tabular-nums"
                             :class="remaining <= 60 ? 'bg-rose-500/15 text-rose-600' : 'bg-brand-tint text-brand-strong'">
                            <x-ui.icon name="clock" class="w-4 h-4" />
                            <span x-text="clock()"></span>
                        </div>
                    </template>
                    <template x-if="remaining === null">
                        <span class="text-xs text-muted">{{ __('exams.live_no_time_limit') }}</span>
                    </template>

                    {{-- Always visible, never buried at the bottom of a long question
                         list — that's the whole point of putting it in the sticky header. --}}
                    <x-ui.button type="button" size="sm" @click="openConfirm()" x-bind:disabled="submitting"
                                 aria-label="{{ __('exams.live_submit') }}">
                        <x-ui.icon name="check" class="w-4 h-4" />
                        <span class="hidden sm:inline">{{ __('exams.live_submit') }}</span>
                    </x-ui.button>
                </div>
            </div>
        </header>

        <main class="flex-1 w-full max-w-4xl mx-auto px-4 sm:px-6 py-6">
            {{-- Any answer that has not reached the server: say so plainly, on every question. --}}
            <div x-show="hasFailed()" x-cloak style="display:none" role="alert"
                 class="mb-4 rounded-xl border border-rose-300 bg-rose-500/10 px-4 py-3 text-sm text-rose-700 dark:text-rose-300">
                {{ __('exams.live_unsaved_banner') }}
            </div>

            {{-- No active questions: nothing to answer, but the attempt can still be submitted. --}}
            <template x-if="questions.length === 0">
                <x-ui.card>
                    <p class="text-muted text-center py-6">{{ __('exams.live_empty') }}</p>
                </x-ui.card>
            </template>

            <template x-if="questions.length > 0">
                <div class="grid gap-6 lg:grid-cols-[1fr_13rem]">
                    {{-- Current question --}}
                    <div>
                        <x-ui.card class="flex flex-col">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs font-semibold uppercase tracking-wider text-muted"
                                      x-text="questionLabel()"></span>
                                <span class="text-xs" x-html="saveBadge()"></span>
                            </div>

                            <p class="text-lg font-semibold text-ink leading-relaxed" x-text="q().body"></p>

                            <div class="mt-4 space-y-2.5">
                                <template x-for="(opt, i) in q().options" :key="opt.id">
                                    <button type="button" @click="toggle(q(), opt.id)"
                                            class="w-full flex items-center gap-3 text-start rounded-xl border px-4 py-3 transition-colors"
                                            :class="isSelected(q().id, opt.id)
                                                ? 'border-brand bg-brand-tint text-brand-strong'
                                                : 'border-line hover:border-brand/50'">
                                        <span class="shrink-0 w-7 h-7 grid place-items-center rounded-full border text-sm font-bold tabular-nums"
                                              :class="isSelected(q().id, opt.id) ? 'border-brand bg-brand text-brand-ink' : 'border-line text-muted'"
                                              x-text="d(i + 1)"></span>
                                        <span class="flex-1" x-text="opt.body"></span>
                                        <x-ui.icon name="check" class="w-5 h-5 text-brand shrink-0"
                                                   x-show="isSelected(q().id, opt.id)" x-cloak />
                                    </button>
                                </template>
                            </div>

                            <div class="mt-6 pt-4 border-t border-line flex items-center justify-between gap-3">
                                <x-ui.button type="button" variant="secondary" size="sm"
                                             x-bind:disabled="current === 0" @click="prev()">
                                    {{ __('exams.live_prev') }}
                                </x-ui.button>
                                <x-ui.button type="button" variant="secondary" size="sm"
                                             x-bind:disabled="current === questions.length - 1" @click="next()">
                                    {{ __('exams.live_next') }}
                                </x-ui.button>
                            </div>
                        </x-ui.card>
                    </div>

                    {{-- Question palette: jump around, see answered state at a glance --}}
                    <aside class="lg:sticky lg:top-20 lg:self-start">
                        <x-ui.card>
                            <div class="grid grid-cols-6 lg:grid-cols-4 gap-1.5">
                                <template x-for="(qq, idx) in questions" :key="qq.id">
                                    <button type="button" @click="go(idx)"
                                            class="aspect-square grid place-items-center rounded-lg text-sm font-bold tabular-nums border transition-colors"
                                            :class="paletteClass(idx)"
                                            x-text="d(idx + 1)"></button>
                                </template>
                            </div>
                        </x-ui.card>
                    </aside>
                </div>
            </template>

            <div class="mt-6 flex justify-end">
                <x-ui.button type="button" @click="openConfirm()" x-bind:disabled="submitting">
                    <x-ui.icon name="check" class="w-4 h-4" /> {{ __('exams.live_submit') }}
                </x-ui.button>
            </div>
        </main>

        {{-- Submit confirmation --}}
        <div x-show="showConfirm" x-cloak class="fixed inset-0 z-50 grid place-items-center p-4" style="display:none">
            <div class="absolute inset-0 bg-black/50" @click="showConfirm = false"></div>
            <div class="relative w-full max-w-md bg-card rounded-2xl border border-line shadow-xl p-6">
                <h2 class="text-lg font-bold text-ink">{{ __('exams.live_submit_confirm_title') }}</h2>
                <p class="text-sm text-muted mt-2">{{ __('exams.live_submit_confirm_body') }}</p>
                <p class="text-sm text-amber-600 mt-2" x-show="unansweredCount() > 0" x-text="unansweredLabel()"></p>
                <div class="mt-6 flex items-center justify-end gap-3">
                    <x-ui.button type="button" variant="secondary" @click="showConfirm = false">{{ __('exams.live_cancel') }}</x-ui.button>
                    <x-ui.button type="button" @click="confirmAndSubmit()" x-bind:disabled="submitting">{{ __('exams.live_confirm') }}</x-ui.button>
                </div>
            </div>
        </div>

        {{-- Submit blocked: some answers never reached the server --}}
        <div x-show="saveFailedNotice" x-cloak class="fixed inset-0 z-50 grid place-items-center p-4" style="display:none">
            <div class="absolute inset-0 bg-black/50" @click="saveFailedNotice = false"></div>
            <div class="relative w-full max-w-md bg-card rounded-2xl border border-line shadow-xl p-6">
                <h2 class="text-lg font-bold text-rose-600">{{ __('exams.live_unsaved_title') }}</h2>
                <p class="text-sm text-muted mt-2">{{ __('exams.live_unsaved_body') }}</p>
                <div class="mt-6 flex items-center justify-end gap-3">
                    <x-ui.button type="button" variant="secondary" @click="saveFailedNotice = false">{{ __('exams.live_cancel') }}</x-ui.button>
                    <x-ui.button type="button" @click="confirmAndSubmit()" x-bind:disabled="submitting">{{ __('exams.live_retry_submit') }}</x-ui.button>
                </div>
            </div>
        </div>

        {{-- Time-up overlay --}}
        <div x-show="expiredNotice" x-cloak class="fixed inset-0 z-50 grid place-items-center bg-black/60 p-4" style="display:none">
            <div class="bg-card rounded-2xl border border-line shadow-xl p-8 text-center max-w-sm">
                <x-ui.icon name="clock" class="w-10 h-10 text-rose-600 mx-auto" />
                <p class="mt-3 font-semibold text-ink">{{ __('exams.live_expired_notice') }}</p>
            </div>
        </div>
    </div>

    {{-- Plain POST submission: works with or without JS, and the server records the
         authoritative terminal state. JS flushes pending saves, then submits this. --}}
    <form id="exam-submit-form" method="POST" action="{{ $submitAction }}" class="hidden">
        @csrf
    </form>

    <script>
        window.examRunner = function () {
            return {
                questions: [],
                answers: {},            // qid -> [optionIds]
                current: 0,
                remaining: null,
                endpoints: {},
                csrf: '',
                bnDigits: false,
                saveState: {},          // qid -> 'idle' | 'saving' | 'saved' | 'error'
                inflight: {},           // qid -> bool (one save at a time per question)
                dirty: {},              // qid -> bool (a newer value is waiting)
                noRetry: {},            // qid -> bool (server REJECTED the selection: retrying cannot help)
                submitting: false,
                showConfirm: false,
                saveFailedNotice: false,
                expiredNotice: false,
                _timer: null,
                _poll: null,
                _retry: null,

                init(data) {
                    this.questions = data.questions || [];
                    this.answers = data.selections || {};
                    this.remaining = data.remaining;
                    this.endpoints = data.endpoints;
                    this.csrf = data.csrf;
                    this.bnDigits = !!data.bnDigits;

                    this.questions.forEach((qq) => {
                        this.saveState[qq.id] = (this.answers[qq.id] || []).length ? 'saved' : 'idle';
                    });

                    // A failed save used to stay failed until the student happened to change that
                    // answer again — while the palette still showed it as answered. Retry on a
                    // timer and the moment the connection returns; the PUT sends the whole
                    // selection, so resending is idempotent.
                    this._retry = setInterval(() => this.retryFailed(), 4000);
                    window.addEventListener('online', () => this.retryFailed());
                    window.addEventListener('beforeunload', (e) => {
                        if (!this.submitting && this.hasUnsaved()) { e.preventDefault(); e.returnValue = ''; }
                    });

                    if (this.remaining !== null) {
                        this._timer = setInterval(() => this.tick(), 1000);
                        this._poll = setInterval(() => this.syncStatus(), 20000);
                        if (this.remaining <= 0) this.autoSubmit();
                    }
                },

                q() { return this.questions[this.current] || { id: 0, options: [], body: '' }; },

                // ── selection ────────────────────────────────────────────────
                isSelected(qid, oid) { return (this.answers[qid] || []).indexOf(oid) !== -1; },

                toggle(question, oid) {
                    const qid = question.id;
                    let sel = (this.answers[qid] || []).slice();
                    sel = sel.indexOf(oid) !== -1 ? sel.filter((x) => x !== oid) : sel.concat([oid]);
                    this.answers[qid] = sel;
                    this.queueSave(qid);
                },

                // ── autosave (strict per-question ordering) ──────────────────
                // At most one save in flight per question. A change arriving mid-save
                // only marks the question dirty; when the save returns we re-send the
                // LATEST value. So a stale response can never overwrite a newer answer,
                // and the last value the student chose is always what lands last.
                queueSave(qid) {
                    if (this.inflight[qid]) { this.dirty[qid] = true; return; }
                    this.sendSave(qid);
                },

                async sendSave(qid) {
                    this.inflight[qid] = true;
                    this.dirty[qid] = false;
                    this.saveState[qid] = 'saving';

                    try {
                        const res = await fetch(this.endpoints.answer.replace('__Q__', qid), {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrf,
                            },
                            body: JSON.stringify({ option_ids: this.answers[qid] || [] }),
                        });

                        if (res.status === 409) {
                            const d = await res.json().catch(() => ({}));
                            return this.onExpired(d.redirect);
                        }
                        if (!res.ok) {
                            this.saveState[qid] = 'error';
                            // 422 = the server refused this selection outright; resending the same
                            // thing forever would only spam it. Everything else (network, 5xx,
                            // expired CSRF, throttling) is worth retrying.
                            this.noRetry[qid] = res.status === 422;
                        } else {
                            const d = await res.json();
                            if (typeof d.remaining_seconds === 'number') this.remaining = d.remaining_seconds;
                            this.saveState[qid] = 'saved';
                            this.noRetry[qid] = false;
                        }
                    } catch (e) {
                        this.saveState[qid] = 'error';
                    } finally {
                        this.inflight[qid] = false;
                        // A newer value queued up while this was in flight — flush it.
                        if (this.dirty[qid]) this.sendSave(qid);
                    }
                },

                pending() {
                    return this.questions.some((qq) => this.inflight[qq.id] || this.dirty[qq.id]);
                },

                failed() { return this.questions.filter((qq) => this.saveState[qq.id] === 'error'); },
                hasFailed() { return this.failed().length > 0; },
                hasUnsaved() { return this.pending() || this.hasFailed(); },

                retryFailed() {
                    this.failed().forEach((qq) => {
                        if (!this.inflight[qq.id] && !this.noRetry[qq.id]) this.sendSave(qq.id);
                    });
                },

                // Wait until every answer is really on the server — resending failed ones as we
                // go — and say whether that happened. (It used to wait only for in-flight saves
                // and return regardless, so an answer that had already failed was submitted
                // without.) Gives up after maxMs, or at once if the server rejected an answer.
                async flushSaves(maxMs = 15000) {
                    const deadline = Date.now() + maxMs;
                    while (Date.now() < deadline) {
                        this.retryFailed();
                        if (!this.pending() && !this.hasFailed()) return true;
                        if (!this.pending() && this.failed().every((qq) => this.noRetry[qq.id])) return false;
                        await new Promise((r) => setTimeout(r, 300));
                    }
                    return !this.pending() && !this.hasFailed();
                },

                // ── timer ────────────────────────────────────────────────────
                tick() {
                    if (this.remaining === null) return;
                    this.remaining = Math.max(0, this.remaining - 1);
                    if (this.remaining === 0) this.autoSubmit();
                },

                async syncStatus() {
                    try {
                        const res = await fetch(this.endpoints.status, { headers: { 'Accept': 'application/json' } });
                        if (!res.ok) return;
                        const d = await res.json();
                        if (d.terminal) return this.onExpired(d.redirect);
                        if (typeof d.remaining_seconds === 'number') this.remaining = d.remaining_seconds;
                    } catch (e) { /* transient; the next poll retries */ }
                },

                stopTimers() { clearInterval(this._timer); clearInterval(this._poll); },

                onExpired(redirect) {
                    this.stopTimers();
                    this.expiredNotice = true;
                    setTimeout(() => { window.location = redirect || this.endpoints.result; }, 1400);
                },

                // ── submission ───────────────────────────────────────────────
                async autoSubmit() {
                    if (this.submitting) return;
                    this.submitting = true;
                    this.stopTimers();
                    this.expiredNotice = true;
                    await this.flushSaves(8000);   // time is up: submit either way, the server enforces the deadline
                    this.doSubmit();
                },

                openConfirm() { this.showConfirm = true; },

                async confirmAndSubmit() {
                    if (this.submitting) return;
                    this.submitting = true;
                    this.showConfirm = false;
                    this.saveFailedNotice = false;

                    // Timers keep running while we confirm the answers are saved, so a student
                    // stuck offline is still auto-submitted at the deadline rather than stranded.
                    const allSaved = await this.flushSaves(15000);
                    if (!allSaved && this.remaining !== 0) {
                        this.submitting = false;
                        this.saveFailedNotice = true;   // do NOT submit an exam missing answers they think they gave
                        return;
                    }

                    this.stopTimers();
                    this.doSubmit();
                },

                doSubmit() { document.getElementById('exam-submit-form').submit(); },

                // ── navigation ───────────────────────────────────────────────
                go(idx) { this.current = idx; },
                prev() { if (this.current > 0) this.current--; },
                next() { if (this.current < this.questions.length - 1) this.current++; },

                paletteClass(idx) {
                    if (idx === this.current) return 'border-brand bg-brand text-brand-ink';
                    const qid = this.questions[idx].id;
                    if (this.saveState[qid] === 'error') return 'border-rose-400 bg-rose-500/10 text-rose-600';
                    if ((this.answers[qid] || []).length) return 'border-brand/40 bg-brand-tint text-brand-strong';
                    return 'border-line text-muted hover:border-brand/40';
                },

                // ── display helpers ──────────────────────────────────────────
                answeredCount() { return this.questions.filter((qq) => (this.answers[qq.id] || []).length).length; },
                unansweredCount() { return this.questions.length - this.answeredCount(); },

                questionLabel() {
                    return @js(__('exams.live_question_of'))
                        .replace(':current', this.d(this.current + 1))
                        .replace(':total', this.d(this.questions.length));
                },
                answeredLabel() {
                    return @js(__('exams.live_answered'))
                        .replace(':count', this.d(this.answeredCount()))
                        .replace(':total', this.d(this.questions.length));
                },
                unansweredLabel() {
                    return @js(__('exams.live_unanswered_warning')).replace(':count', this.d(this.unansweredCount()));
                },
                saveBadge() {
                    const s = this.saveState[this.q().id];
                    if (s === 'saving') return '<span class="text-muted">' + @js(__('exams.live_saving')) + '</span>';
                    if (s === 'saved') return '<span class="text-emerald-600">' + @js(__('exams.live_saved')) + '</span>';
                    if (s === 'error') return '<span class="text-rose-600">' + @js(__('exams.live_save_failed')) + '</span>';
                    return '';
                },

                clock() {
                    if (this.remaining === null) return '';
                    let s = this.remaining;
                    const h = Math.floor(s / 3600); s %= 3600;
                    const m = Math.floor(s / 60); const ss = s % 60;
                    const pad = (n) => String(n).padStart(2, '0');
                    const str = (h > 0 ? pad(h) + ':' : '') + pad(m) + ':' + pad(ss);
                    return this.d(str);
                },

                // Latin -> Bengali digits when the interface is Bengali.
                d(value) {
                    const str = String(value);
                    if (!this.bnDigits) return str;
                    const map = { '0': '০', '1': '১', '2': '২', '3': '৩', '4': '৪', '5': '৫', '6': '৬', '7': '৭', '8': '৮', '9': '৯' };
                    return str.replace(/[0-9]/g, (ch) => map[ch]);
                },
            };
        };
    </script>
</x-layout.base>
