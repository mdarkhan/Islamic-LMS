<x-layout.admin :title="__('lessons.new_lesson')" :heading="__('lessons.new_lesson')">
    <x-ui.breadcrumbs :items="[__('nav.lessons') => route('admin.lessons.index'), __('ui.new') => null]" class="mb-5" />
    <form method="POST" action="{{ route('admin.lessons.store') }}">
        @csrf
        @include('admin.lessons._form', ['lesson' => null])
        <div class="flex justify-end gap-3 mt-6">
            <x-ui.button :href="route('admin.lessons.index')" variant="ghost">{{ __('ui.cancel') }}</x-ui.button>
            <x-ui.button type="submit">{{ __('ui.create') }}</x-ui.button>
        </div>
    </form>
</x-layout.admin>
