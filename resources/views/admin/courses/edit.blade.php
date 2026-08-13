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
</x-layout.admin>
