<x-layout.admin :title="$course->title" :heading="$course->title">
    <x-ui.breadcrumbs :items="[__('nav.courses') => route('admin.courses.index'), $course->title => null]" class="mb-5" />
    <x-ui.card class="max-w-2xl">
        <form method="POST" action="{{ route('admin.courses.update', $course) }}" class="space-y-5">
            @csrf @method('PUT')
            <x-ui.field :label="__('courses.title')" name="title" required>
                <x-ui.input name="title" :value="old('title', $course->title)" />
            </x-ui.field>
            <x-ui.field :label="__('admin.description')" name="description">
                <x-ui.textarea name="description">{{ old('description', $course->description) }}</x-ui.textarea>
            </x-ui.field>
            <div class="grid grid-cols-2 gap-4">
                <x-ui.field :label="__('admin.order').' (sort order)'" name="sort_order">
                    <x-ui.input name="sort_order" type="number" min="0" :value="old('sort_order', $course->sort_order)" />
                </x-ui.field>
                <label class="flex items-end gap-2 text-sm text-ink pb-2.5">
                    <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $course->is_published)) class="rounded border-line text-brand focus:ring-brand">
                    {{ __('admin.published') }}
                </label>
            </div>
            {{-- Course-topper rewards: position → points --}}
            <div class="border-t border-line pt-5 space-y-2"
                 x-data="{ rows: @js(old('topper_rewards', $course->topperRewardRows() ?: [['position' => 1, 'points' => '']])) }">
                <p class="text-sm font-semibold text-ink">{{ __('courses.topper_rewards') }}</p>
                <p class="text-xs text-muted">{{ __('courses.topper_rewards_hint') }}</p>
                <template x-for="(row, i) in rows" :key="i">
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-muted w-14">{{ __('courses.position') }}</span>
                        <input type="number" min="1" :name="'topper_rewards['+i+'][position]'" x-model="row.position"
                               class="w-20 rounded-lg border border-line bg-surface-raised px-2 py-1.5 text-sm text-ink outline-none focus:border-brand">
                        <span class="text-muted">→</span>
                        <input type="number" min="1" :name="'topper_rewards['+i+'][points]'" x-model="row.points"
                               placeholder="{{ __('courses.points') }}"
                               class="w-24 rounded-lg border border-line bg-surface-raised px-2 py-1.5 text-sm text-ink outline-none focus:border-brand">
                        <span class="text-xs text-muted">{{ __('courses.points') }}</span>
                        <button type="button" @click="rows.splice(i, 1)" class="ms-1 text-muted hover:text-rose-600" aria-label="{{ __('ui.delete') }}">&times;</button>
                    </div>
                </template>
                <button type="button" @click="rows.push({ position: rows.length + 1, points: '' })"
                        class="inline-flex items-center gap-1 text-sm font-medium text-brand hover:underline">
                    <span class="text-base leading-none">+</span> {{ __('courses.add_position') }}
                </button>
            </div>

            <div class="flex justify-between gap-3 border-t border-line pt-5">
                {{-- Delete uses a separate form (declared below) to avoid nesting. --}}
                <x-ui.button type="submit" form="course-delete" variant="ghost" class="text-rose-600"
                             onclick="return confirm('{{ __('admin.confirm_delete_course') }}')">{{ __('ui.delete') }}</x-ui.button>
                <div class="flex gap-3">
                    <x-ui.button :href="route('admin.courses.index')" variant="ghost">{{ __('ui.cancel') }}</x-ui.button>
                    <x-ui.button type="submit">{{ __('admin.save_changes') }}</x-ui.button>
                </div>
            </div>
        </form>

        <form id="course-delete" method="POST" action="{{ route('admin.courses.destroy', $course) }}" class="hidden">
            @csrf @method('DELETE')
        </form>
    </x-ui.card>

    {{-- Award the configured topper rewards to the current course toppers. --}}
    <x-ui.card class="max-w-2xl mt-5">
        <h3 class="font-bold text-ink mb-1">{{ __('courses.award_toppers') }}</h3>
        <p class="text-xs text-muted mb-3">{{ __('courses.award_toppers_hint') }}</p>
        <form method="POST" action="{{ route('admin.courses.award-toppers', $course) }}"
              onsubmit="return confirm('{{ __('courses.award_confirm') }}')">
            @csrf
            <x-ui.button type="submit" variant="secondary">{{ __('courses.award_toppers') }}</x-ui.button>
        </form>
    </x-ui.card>
</x-layout.admin>
