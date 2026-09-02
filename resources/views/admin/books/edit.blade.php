<x-layout.admin :title="__('books.edit_book')" :heading="__('books.edit_book')">
    <x-ui.breadcrumbs :items="[__('nav.books') => route('admin.books.index'), $book->title => null]" class="mb-5" />
    <form method="POST" action="{{ route('admin.books.update', $book) }}" enctype="multipart/form-data">
        @csrf @method('PUT')
        @include('admin.books._form')
        <div class="flex justify-between gap-3 mt-6">
            <x-ui.button type="submit" form="book-delete" variant="ghost" class="text-rose-600">{{ __('ui.delete') }}</x-ui.button>
            <div class="flex gap-3">
                <x-ui.button :href="route('admin.books.index')" variant="ghost">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button type="submit">{{ __('admin.save_changes') }}</x-ui.button>
            </div>
        </div>
    </form>
    <form id="book-delete" method="POST" action="{{ route('admin.books.destroy', $book) }}" class="hidden"
          data-confirm="{{ __('admin.confirm_delete_book') }}">
        @csrf @method('DELETE')
    </form>
</x-layout.admin>
