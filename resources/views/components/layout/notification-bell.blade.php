@php
    $bellUser = auth()->user();
    $bellService = $bellUser ? app(\App\Services\Notifications\NotificationService::class) : null;
    $bellUnread = $bellService ? $bellService->unreadCountFor($bellUser) : 0;
    $bellRecent = $bellService ? \App\Support\NotificationPresenter::collection($bellService->recentFor($bellUser)) : [];
@endphp

{{-- The bell icon: shared by both roles (student and admin headers use the same
     x-layout.app), so its data is resolved from auth()->user() here rather than being
     passed in as a prop. Server-rendered on first paint, then kept live by polling —
     there is no queue worker / broadcast driver on this host (see CLAUDE.md), the same
     constraint the message thread and live exam screen already work within. --}}
<div class="relative" x-data="notificationBell({
        pollUrl: @js(route('notifications.poll')),
        markAllUrl: @js(route('notifications.read-all')),
        unread: {{ (int) $bellUnread }},
        notifications: @js($bellRecent),
        bengali: {{ app()->getLocale() !== 'en' ? 'true' : 'false' }},
     })"
     x-init="init()">
    <button @click="toggle()" class="relative p-2 rounded-lg text-muted hover:text-ink hover:bg-ink/5 transition-colors" aria-label="{{ __('notifications.heading') }}">
        <x-ui.icon name="bell" class="w-5 h-5" />
        <span x-show="unread > 0" x-cloak x-text="badgeText()"
              class="absolute top-0.5 right-0.5 min-w-[1.1rem] h-[1.1rem] px-1 rounded-full bg-rose-500 text-white text-[10px] font-bold grid place-items-center leading-none"></span>
    </button>

    <div x-show="open" x-cloak @click.outside="open = false" x-transition
         class="absolute right-0 mt-2 w-80 max-w-[90vw] bg-card border border-line rounded-xl shadow-lg z-30 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-line">
            <p class="font-bold text-ink text-sm">{{ __('notifications.heading') }}</p>
            <button type="button" x-show="unread > 0" x-cloak @click="markAllRead()" class="text-xs font-semibold text-brand hover:text-brand-strong">
                {{ __('notifications.mark_all_read') }}
            </button>
        </div>

        <div class="max-h-96 overflow-y-auto divide-y divide-line">
            <p x-show="notifications.length === 0" class="text-sm text-muted text-center py-8">{{ __('notifications.empty') }}</p>
            <template x-for="n in notifications" :key="n.id">
                <div class="flex items-start" :class="n.read ? '' : 'bg-brand-tint/30'">
                    <a :href="n.openUrl" class="flex-1 min-w-0 px-4 py-3" @click="n.read = true">
                        <p class="text-sm font-semibold text-ink truncate" x-text="n.title"></p>
                        <p class="text-xs text-muted truncate mt-0.5" x-show="n.body" x-text="n.body"></p>
                        <p class="text-[11px] text-muted mt-1" x-text="n.at"></p>
                    </a>
                    <button type="button" @click="remove(n)" class="p-3 text-muted hover:text-rose-600 shrink-0" aria-label="{{ __('notifications.delete') }}">
                        <x-ui.icon name="trash" class="w-3.5 h-3.5" />
                    </button>
                </div>
            </template>
        </div>

        <a href="{{ route('notifications.index') }}" class="block text-center text-sm font-semibold text-brand hover:text-brand-strong px-4 py-3 border-t border-line">
            {{ __('notifications.see_all') }}
        </a>
    </div>
</div>

@once
    <script>
        window.notificationBell = function (config) {
            return {
                open: false,
                unread: config.unread,
                notifications: config.notifications,
                csrf: null,
                _poller: null,

                init() {
                    this.csrf = document.querySelector('meta[name="csrf-token"]')?.content;
                    // Pauses in a hidden tab and backs off while nothing new arrives (app.js).
                    this._poller = window.pollWhenVisible(() => this.refresh(), { base: 30000, max: 120000 });
                },

                localeNumber(n) {
                    if (!config.bengali) return String(n);
                    var digits = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
                    return String(n).replace(/[0-9]/g, function (d) { return digits[+d]; });
                },

                badgeText() {
                    return this.unread > 99 ? this.localeNumber(99) + '+' : this.localeNumber(this.unread);
                },

                toggle() {
                    this.open = !this.open;
                    if (this.open) {
                        this.refresh();
                        this._poller?.reset();   // someone is looking: poll at the fast rate again
                    }
                },

                // Resolves true when something changed (count or list), so the poller knows
                // whether to back off.
                async refresh() {
                    try {
                        const res = await fetch(config.pollUrl, { headers: { Accept: 'application/json' } });
                        if (!res.ok) return false;
                        const data = await res.json();
                        const signature = (list) => list.map((n) => n.id + ':' + (n.read ? 1 : 0)).join(',');
                        const changed = data.count !== this.unread || signature(data.notifications) !== signature(this.notifications);
                        this.unread = data.count;
                        this.notifications = data.notifications;
                        return changed;
                    } catch (e) {
                        // A dropped poll is harmless — the next tick catches up.
                        return false;
                    }
                },

                async markAllRead() {
                    this.notifications.forEach(function (n) { n.read = true; });
                    this.unread = 0;
                    try {
                        await fetch(config.markAllUrl, {
                            method: 'PUT',
                            headers: { 'X-CSRF-TOKEN': this.csrf, Accept: 'application/json' },
                        });
                    } catch (e) {}
                },

                async remove(n) {
                    this.notifications = this.notifications.filter(function (x) { return x.id !== n.id; });
                    if (!n.read) this.unread = Math.max(0, this.unread - 1);
                    try {
                        await fetch(n.deleteUrl, {
                            method: 'DELETE',
                            headers: { 'X-CSRF-TOKEN': this.csrf, Accept: 'application/json' },
                        });
                    } catch (e) {}
                },
            };
        };
    </script>
@endonce
