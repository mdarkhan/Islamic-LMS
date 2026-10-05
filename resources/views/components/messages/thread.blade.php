@props([
    'messages' => [],     // MessagePresenter payload — the history, rendered server-side
    'pollUrl',            // GET ?after={id} → { messages: [...] }
    'sendUrl',            // POST { body } → { message: {...} }
])

{{-- Student ↔ ustaz chat.

     There is no WebSocket on this hosting (no queue worker, BROADCAST_CONNECTION=log),
     so the thread polls — the same approach the live exam screen uses. Ten seconds is
     short enough to feel live for a Q&A exchange and cheap enough for shared hosting.

     History is rendered server-side (so the page is complete and readable without JS);
     Alpine only appends what arrives while the page is open. Bodies are written by
     students and admins alike, so they are ALWAYS escaped — {{ }} here, x-text below —
     and never rendered as HTML or Markdown. --}}

<div x-data="messageThread({
        poll: @js($pollUrl),
        send: @js($sendUrl),
        lastId: {{ collect($messages)->max('id') ?? 0 }},
     })"
     class="flex flex-col rounded-2xl border border-line bg-surface overflow-hidden"
     style="height: min(70vh, 42rem)">

    <div x-ref="scroll" class="flex-1 overflow-y-auto p-4 space-y-3">
        @forelse ($messages as $message)
            <div @class(['flex', 'justify-end' => $message['mine'], 'justify-start' => ! $message['mine']])>
                <div @class([
                    'max-w-[80%] rounded-2xl px-4 py-2.5 text-sm leading-relaxed whitespace-pre-wrap break-words',
                    'bg-brand text-brand-ink' => $message['mine'],
                    'bg-surface-raised text-ink border border-line' => ! $message['mine'],
                ])>
                    {{ $message['body'] }}
                    <span @class([
                        'block mt-1 text-[11px] tabular-nums',
                        'text-brand-ink/70' => $message['mine'],
                        'text-muted' => ! $message['mine'],
                    ])>{{ $message['mine'] ? __('messages.you') : $message['author'] }} · {{ $message['at'] }}</span>
                </div>
            </div>
        @empty
            <p x-show="incoming.length === 0" class="text-sm text-muted italic text-center py-8">{{ __('messages.empty_thread') }}</p>
        @endforelse

        {{-- Everything that arrives after the page was rendered. --}}
        <template x-for="m in incoming" :key="m.id">
            <div class="flex" :class="m.mine ? 'justify-end' : 'justify-start'">
                <div class="max-w-[80%] rounded-2xl px-4 py-2.5 text-sm leading-relaxed whitespace-pre-wrap break-words"
                     :class="m.mine ? 'bg-brand text-brand-ink' : 'bg-surface-raised text-ink border border-line'">
                    <span x-text="m.body"></span>
                    <span class="block mt-1 text-[11px] tabular-nums"
                          :class="m.mine ? 'text-brand-ink/70' : 'text-muted'"
                          x-text="(m.mine ? @js(__('messages.you')) : m.author) + ' · ' + m.at"></span>
                </div>
            </div>
        </template>
    </div>

    <form @submit.prevent="send()" class="shrink-0 border-t border-line bg-surface-raised p-3">
        <p x-show="error" x-cloak class="mb-2 text-xs text-rose-600" x-text="error"></p>
        <div class="flex items-end gap-2">
            <textarea x-model="body" rows="1" required
                      aria-label="{{ __('messages.placeholder') }}"
                      @keydown.enter.exact.prevent="send()"
                      @input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 160) + 'px'"
                      placeholder="{{ __('messages.placeholder') }}"
                      class="flex-1 resize-none rounded-xl bg-surface border border-line px-3 py-2.5 text-sm text-ink outline-none focus:border-brand"></textarea>
            <button type="submit" :disabled="sending || body.trim() === ''"
                    class="shrink-0 inline-flex items-center gap-1.5 rounded-xl bg-brand text-brand-ink px-4 py-2.5 text-sm font-bold disabled:opacity-50 disabled:cursor-not-allowed">
                <x-ui.icon name="send" class="w-4 h-4" />
                <span x-text="sending ? @js(__('messages.sending')) : @js(__('messages.send'))"></span>
            </button>
        </div>
    </form>
</div>

{{-- Vite's module scripts are deferred, so this registers before Alpine.start(). --}}
@once
    <script>
        window.messageThread = function (endpoints) {
            return {
                    incoming: [],
                    lastId: endpoints.lastId,
                    body: '',
                    sending: false,
                    error: null,
                    csrf: document.querySelector('meta[name="csrf-token"]')?.content,

                    init() {
                        this.$nextTick(() => this.toBottom());
                        // Chat should feel live, so 10s while it's active; but nothing is fetched
                        // in a hidden tab, and it backs off (up to 60s) while the thread is quiet.
                        this._poller = window.pollWhenVisible(() => this.poll(), { base: 10000, max: 60000 });
                    },

                    destroy() { this._poller?.stop(); },

                    toBottom() {
                        const el = this.$refs.scroll;
                        if (el) el.scrollTop = el.scrollHeight;
                    },

                    append(message) {
                        this.incoming.push(message);
                        this.lastId = Math.max(this.lastId, message.id);
                        this.$nextTick(() => this.toBottom());
                    },

                    async poll() {
                        try {
                            const res = await fetch(endpoints.poll + '?after=' + this.lastId, {
                                headers: { 'Accept': 'application/json' },
                            });
                            if (!res.ok) return false;
                            const data = await res.json();
                            const fresh = data.messages || [];
                            fresh.forEach((m) => this.append(m));
                            return fresh.length > 0;   // true = activity, keep polling fast
                        } catch (e) {
                            // A dropped poll is harmless — the next tick catches up.
                            return false;
                        }
                    },

                    async send() {
                        const body = this.body.trim();
                        if (body === '' || this.sending) return;

                        this.sending = true;
                        this.error = null;

                        try {
                            const res = await fetch(endpoints.send, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': this.csrf,
                                },
                                body: JSON.stringify({ body }),
                            });

                            if (!res.ok) throw new Error('send failed');

                            const data = await res.json();
                            this.append(data.message);
                            this._poller?.reset();   // a reply may follow soon: back to the fast rate
                            this.body = '';
                            this.$el.querySelector('textarea').style.height = 'auto';
                        } catch (e) {
                            this.error = @js(__('messages.send_failed'));
                        } finally {
                            this.sending = false;
                        }
                    },
            };
        };
    </script>
@endonce
