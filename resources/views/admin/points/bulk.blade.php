<x-layout.admin :title="__('points.bulk_points')" :heading="__('points.bulk_points')">
    <x-ui.breadcrumbs :items="[__('nav.points') => route('admin.points.index'), __('points.bulk_points') => null]" class="mb-5" />

    <form method="POST" action="{{ route('admin.points.bulk.store') }}" x-data="{ all: false }">
        @csrf
        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Settings --}}
            <x-ui.card class="lg:col-span-1 h-fit lg:sticky lg:top-20">
                <h3 class="font-bold text-ink mb-4">{{ __('quizzes.settings_heading') }}</h3>
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-2">
                        <label class="cursor-pointer">
                            <input type="radio" name="direction" value="credit" class="peer sr-only" checked>
                            <span class="block text-center px-3 py-2 rounded-xl border border-line text-sm font-semibold peer-checked:bg-brand-tint peer-checked:border-brand peer-checked:text-brand-strong">{{ __('points.credit') }}</span>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="direction" value="deduct" class="peer sr-only">
                            <span class="block text-center px-3 py-2 rounded-xl border border-line text-sm font-semibold peer-checked:bg-rose-50 dark:peer-checked:bg-rose-500/10 peer-checked:border-rose-400 peer-checked:text-rose-600">{{ __('points.debit') }}</span>
                        </label>
                    </div>
                    <x-ui.field :label="__('points.amount_per_student')" name="amount" required>
                        <x-ui.input name="amount" type="number" min="1" :value="old('amount')" />
                    </x-ui.field>
                    <x-ui.field :label="__('ui.reason')" name="reason" required>
                        <x-ui.textarea name="reason" rows="2">{{ old('reason') }}</x-ui.textarea>
                    </x-ui.field>
                    @error('student_ids')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                    <x-ui.button type="submit" class="w-full">{{ __('points.apply') }}</x-ui.button>
                    <p class="text-xs text-muted">{{ __('points.bulk_note') }}</p>
                </div>
            </x-ui.card>

            {{-- Selection --}}
            <x-ui.card :padded="false" class="lg:col-span-2">
                <div class="p-4 flex items-center justify-between border-b border-line">
                    <label class="flex shrink-0 items-center gap-2 whitespace-nowrap text-sm font-semibold text-ink cursor-pointer">
                        <input type="checkbox" x-model="all" @change="$root.querySelectorAll('input[name=\'student_ids[]\']').forEach(c => c.checked = all)" class="rounded border-line text-brand focus:ring-brand">
                        {{ __('points.select_all') }}
                    </label>
                    <form method="GET" class="relative w-48">
                        <input name="q" value="{{ $search }}" placeholder="{{ __('ui.search_placeholder') }}" class="w-full rounded-lg bg-surface-raised border border-line px-3 py-2 text-sm outline-none focus:border-brand">
                    </form>
                </div>
                <div class="max-h-[28rem] overflow-y-auto divide-y divide-line">
                    @forelse ($students as $student)
                        <label class="flex items-center gap-3 px-4 py-3 hover:bg-ink/[0.02] cursor-pointer">
                            <input type="checkbox" name="student_ids[]" value="{{ $student->id }}" class="rounded border-line text-brand focus:ring-brand">
                            <span class="flex-1 min-w-0">
                                <span class="block text-sm font-medium text-ink truncate">{{ $student->name }}</span>
                                <span class="block text-xs text-muted">{{ __('points.roll_prefix') }} {{ bn($student->roll) }}</span>
                            </span>
                            <span class="text-sm font-semibold text-brand tabular-nums">{{ bn($student->points_balance) }}</span>
                        </label>
                    @empty
                        <div class="px-4 py-10 text-center text-sm text-muted">{{ __('points.no_active_students') }}</div>
                    @endforelse
                </div>
            </x-ui.card>
        </div>
    </form>
</x-layout.admin>
