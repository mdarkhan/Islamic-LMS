<x-layout.admin :title="__('books.new_book')" :heading="__('books.new_book')">
    <x-ui.breadcrumbs :items="[__('nav.books') => route('admin.books.index'), __('ui.new') => null]" class="mb-5" />
    <form method="POST" action="{{ route('admin.books.store') }}" enctype="multipart/form-data">
        @csrf
        @include('admin.books._form', ['book' => null])
        <div class="flex justify-end gap-3 mt-6">
            <x-ui.button :href="route('admin.books.index')" variant="ghost">{{ __('ui.cancel') }}</x-ui.button>
            <x-ui.button type="submit">{{ __('ui.create') }}</x-ui.button>
        </div>
    </form>
</x-layout.admin>
