<x-layout.student :title="__('amol.heading')" :heading="__('amol.heading')">
    <x-ui.card class="mb-5">
        <p class="text-ink leading-relaxed italic">{{ __('amol.verse') }}</p>
        <p class="text-xs text-muted mt-1">— {{ __('amol.verse_ref') }}</p>
    </x-ui.card>

    <x-ui.card class="mb-5">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
            <h3 class="font-bold text-ink flex items-center gap-2"><x-ui.icon name="clock" class="w-4 h-4 text-brand" /> {{ __('amol.prayer_times_heading') }}</h3>
            <span class="text-sm text-muted">{{ __('amol.next_prayer') }}:
                <strong class="text-brand">{{ __('amol.group_'.$prayer['next']) }} · {{ bn($prayer['next_at']->format('h:i')) }}</strong></span>
        </div>
        <div class="grid grid-cols-3 sm:grid-cols-6 gap-2 text-center">
            @foreach (\App\Services\Calendar\PrayerTimeService::PRAYERS as $key)
                <div @class(['rounded-lg border px-2 py-2', 'border-brand bg-brand-tint/40' => $prayer['current'] === $key, 'border-line bg-surface' => $prayer['current'] !== $key])>
                    <p class="text-xs text-muted">{{ $key === 'sunrise' ? __('amol.sunrise') : __('amol.group_'.$key) }}</p>
                    <p class="font-bold text-ink tabular-nums">{{ bn($prayer['times'][$key]->format('h:i')) }}</p>
                </div>
            @endforeach
        </div>
        <p class="text-[11px] text-muted mt-2">{{ __('amol.prayer_times_note') }}</p>
    </x-ui.card>

    <x-amol.date-nav :date="$date" :today="$today" route="student.amol.index" />

    @if (! $isToday)
        <x-ui.alert class="mb-5">{{ __('amol.view_only') }}</x-ui.alert>
    @endif

    @php $parts = \App\Models\Amol::partition($checklist); @endphp

    <x-ui.card>
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-ink">{{ bn($date->format('d/m/Y')) }}</h3>
            <span id="amol-progress" class="text-sm font-semibold text-muted tabular-nums">
                {{ __('amol.progress', ['done' => bn($checklist->where('is_done', true)->count()), 'total' => bn($checklist->count())]) }}
            </span>
        </div>

        {{-- The five daily prayers, each a dropdown of its sunnah/prayer/takbir-e-oola. --}}
        <div class="space-y-2 mb-2">
            @foreach ($parts['grouped'] as $group => $items)
                @continue($items->isEmpty())
                <x-amol.group-row :group="$group" :items="$items" :editable="$isToday" :time="$prayer['times'][$group] ?? null" />
            @endforeach
        </div>

        {{-- Everything else, flat as before. --}}
        <div class="grid gap-2 sm:grid-cols-2">
            @foreach ($parts['ungrouped'] as $item)
                <x-amol.item-row
                    :label="$item['amol']->label"
                    :is-done="$item['is_done']"
                    :editable="$isToday"
                    :toggle-url="route('student.amol.toggle', $item['amol'])" />
            @endforeach
        </div>

        <p id="amol-toggle-error" class="hidden mt-3 text-sm font-medium text-rose-600" role="alert">
            {{ __('amol.toggle_failed') }}
        </p>
    </x-ui.card>

    <div class="mt-5"><x-amol.stats :stats="$stats" /></div>

    <x-ui.card class="mt-5">
        <h3 class="font-bold text-ink mb-2 flex items-center gap-2">
            <x-ui.icon name="message" class="w-4 h-4 text-brand" /> {{ __('amol.note_heading') }}
        </h3>
        @if ($note && $note->note !== '')
            <p class="text-sm text-ink leading-relaxed whitespace-pre-wrap">{{ $note->note }}</p>
            <p class="text-xs text-muted mt-2">
                {{ __('amol.note_by', ['name' => $note->commentedBy?->name ?? '—', 'at' => $note->commented_at?->format('d/m/Y H:i') ?? '']) }}
            </p>
        @else
            <p class="text-sm text-muted italic">{{ __('amol.no_note') }}</p>
        @endif
    </x-ui.card>

    @once
        <script>
            // Each row's own click handler: PUT to its toggle URL, optimistic flip with
            // revert-on-failure. Disabled rows (a past day) never reach this — see
            // components/amol/item-row.blade.php.
            document.addEventListener('DOMContentLoaded', function () {
                var csrf = document.querySelector('meta[name="csrf-token"]')?.content;
                var errorEl = document.getElementById('amol-toggle-error');
                var progressEl = document.getElementById('amol-progress');
                var progressTemplate = @js(__('amol.progress', ['done' => '__DONE__', 'total' => '__TOTAL__']));
                var groupProgressTemplate = @js(__('amol.group_progress', ['done' => '__DONE__', 'total' => '__TOTAL__']));
                var isBengali = @js(app()->getLocale() !== 'en');
                var bnDigits = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];

                function localeNumber(n) {
                    var s = String(n);
                    return isBengali ? s.replace(/[0-9]/g, function (d) { return bnDigits[+d]; }) : s;
                }

                function setRow(row, isDone) {
                    row.dataset.done = isDone ? '1' : '0';
                    row.classList.toggle('border-brand/40', isDone);
                    row.classList.toggle('bg-brand-tint/40', isDone);
                    row.classList.toggle('border-line', !isDone);
                    row.classList.toggle('bg-surface', !isDone);
                    var check = row.querySelector('.amol-check');
                    check.classList.toggle('bg-brand', isDone);
                    check.classList.toggle('border-brand', isDone);
                    check.classList.toggle('text-brand-ink', isDone);
                    check.classList.toggle('border-line', !isDone);
                    check.classList.toggle('text-transparent', !isDone);
                }

                function refreshProgress() {
                    if (progressEl) {
                        var rows = document.querySelectorAll('.amol-row[data-done]');
                        var done = 0;
                        rows.forEach(function (r) { if (r.dataset.done === '1') done++; });
                        progressEl.textContent = progressTemplate
                            .replace('__DONE__', localeNumber(done))
                            .replace('__TOTAL__', localeNumber(rows.length));
                    }

                    document.querySelectorAll('.amol-group').forEach(function (group) {
                        var summary = group.querySelector('.amol-group-progress');
                        if (!summary) return;
                        var groupRows = group.querySelectorAll('.amol-row[data-done]');
                        var groupDone = 0;
                        groupRows.forEach(function (r) { if (r.dataset.done === '1') groupDone++; });
                        summary.textContent = groupProgressTemplate
                            .replace('__DONE__', localeNumber(groupDone))
                            .replace('__TOTAL__', localeNumber(groupRows.length));
                    });
                }

                document.querySelectorAll('.amol-row[data-url]').forEach(function (row) {
                    row.addEventListener('click', function () {
                        if (row.disabled || row.dataset.busy) return;
                        row.dataset.busy = '1';
                        errorEl.classList.add('hidden');

                        var wasDone = row.dataset.done === '1';
                        setRow(row, !wasDone);
                        refreshProgress();

                        fetch(row.dataset.url, {
                            method: 'PUT',
                            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                        })
                            .then(function (res) {
                                if (!res.ok) throw new Error('toggle failed');
                                return res.json();
                            })
                            .then(function (data) {
                                setRow(row, !!data.is_done);
                                refreshProgress();
                            })
                            .catch(function () {
                                setRow(row, wasDone);
                                refreshProgress();
                                errorEl.classList.remove('hidden');
                            })
                            .finally(function () {
                                delete row.dataset.busy;
                            });
                    });
                });
            });
        </script>
    @endonce
</x-layout.student>
