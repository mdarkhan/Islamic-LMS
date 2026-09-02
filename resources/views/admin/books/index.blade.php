<x-layout.admin :title="__('nav.books')" :heading="__('books.admin_heading')">
    <div class="flex justify-end mb-6">
        <x-ui.button :href="route('admin.books.create')"><x-ui.icon name="plus" class="w-4 h-4" /> {{ __('books.new_book') }}</x-ui.button>
    </div>

    @if ($books->isEmpty())
        <x-ui.card><x-ui.empty :title="__('books.no_books')" /></x-ui.card>
    @else
        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-3">{{ __('admin.order') }}</th>
                <th class="px-4 py-3"></th>
                <th class="px-4 py-3">{{ __('books.title') }}</th>
                <th class="px-4 py-3 hidden sm:table-cell">{{ __('books.author') }}</th>
                <th class="px-4 py-3 text-right hidden md:table-cell">{{ __('books.purchase_links_heading') }}</th>
                <th class="px-4 py-3">{{ __('ui.status') }}</th>
                <th class="px-4 py-3"></th>
            </x-slot:head>
            @foreach ($books as $book)
                <tr>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <span class="text-muted tabular-nums w-4 text-center">{{ bn($book->sort_order) }}</span>
                            <div class="inline-flex items-center gap-0.5">
                                <form method="POST" action="{{ route('admin.books.move', [$book, 'up']) }}">
                                    @csrf @method('PUT')
                                    <button type="submit" @disabled($loop->first)
                                            class="p-1 rounded text-muted enabled:hover:text-brand enabled:hover:bg-brand-tint disabled:opacity-25 disabled:cursor-not-allowed transition-colors"
                                            aria-label="{{ __('courses.move_up') }}" title="{{ __('courses.move_up') }}">
                                        <x-ui.icon name="chevron" class="w-4 h-4 -rotate-90" />
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.books.move', [$book, 'down']) }}">
                                    @csrf @method('PUT')
                                    <button type="submit" @disabled($loop->last)
                                            class="p-1 rounded text-muted enabled:hover:text-brand enabled:hover:bg-brand-tint disabled:opacity-25 disabled:cursor-not-allowed transition-colors"
                                            aria-label="{{ __('courses.move_down') }}" title="{{ __('courses.move_down') }}">
                                        <x-ui.icon name="chevron" class="w-4 h-4 rotate-90" />
                                    </button>
                                </form>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        @if ($book->coverUrl())
                            <img src="{{ $book->coverUrl() }}" alt="" class="w-8 h-11 object-cover rounded border border-line">
                        @else
                            <div class="w-8 h-11 rounded border border-line bg-brand-tint text-brand grid place-items-center"><x-ui.icon name="book" class="w-4 h-4" /></div>
                        @endif
                    </td>
                    <td class="px-4 py-3 font-semibold text-ink">{{ $book->title }}</td>
                    <td class="px-4 py-3 text-muted hidden sm:table-cell">{{ $book->author }}</td>
                    <td class="px-4 py-3 text-right text-ink tabular-nums hidden md:table-cell">{{ bn($book->purchase_links_count) }}</td>
                    <td class="px-4 py-3">
                        <x-ui.badge :color="$book->is_published ? 'success' : 'neutral'">{{ $book->is_published ? __('admin.published') : __('admin.draft') }}</x-ui.badge>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-2">
                            <form method="POST" action="{{ route('admin.books.publish', $book) }}">
                                @csrf @method('PUT')
                                <button class="text-xs font-semibold text-muted hover:text-brand">{{ $book->is_published ? __('admin.unpublish') : __('admin.publish') }}</button>
                            </form>
                            <a href="{{ route('admin.books.edit', $book) }}" class="text-brand font-semibold hover:underline text-sm">{{ __('ui.edit') }}</a>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif
</x-layout.admin>
