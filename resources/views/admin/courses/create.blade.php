<x-layout.admin :title="__('courses.new_course')" :heading="__('courses.new_course')">
    <x-ui.breadcrumbs :items="[__('nav.courses') => route('admin.courses.index'), __('ui.new') => null]" class="mb-5" />
    <x-ui.card class="max-w-2xl">
        <form method="POST" action="{{ route('admin.courses.store') }}" class="space-y-5">
            @csrf
            <x-ui.field :label="__('courses.title')" name="title" required>
                <x-ui.input name="title" :value="old('title')" autofocus />
            </x-ui.field>
            <x-ui.field :label="__('admin.description')" name="description">
                <x-ui.textarea name="description">{{ old('description') }}</x-ui.textarea>
            </x-ui.field>
            <label class="flex items-center gap-2 text-sm text-ink">
                <input type="checkbox" name="is_published" value="1" @checked(old('is_published')) class="rounded border-line text-brand focus:ring-brand">
                {{ __('admin.publish_now') }}
            </label>
            <div class="flex justify-end gap-3 border-t border-line pt-5">
                <x-ui.button :href="route('admin.courses.index')" variant="ghost">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button type="submit">{{ __('ui.create') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layout.admin>
