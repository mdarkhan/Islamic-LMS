@php
    // Seed the Alpine purchase-link editor from old input, else the book's links.
    $linkRows = old('purchase_links', isset($book)
        ? $book->purchaseLinks->map(fn ($l) => ['website_name' => $l->website_name, 'url' => $l->url])->values()->all()
        : []);
    if (empty($linkRows)) {
        $linkRows = [['website_name' => '', 'url' => '']];
    }
@endphp

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-5">
        <x-ui.card>
            <div class="space-y-5">
                <x-ui.field :label="__('books.title')" name="title" required>
                    <x-ui.input name="title" :value="old('title', $book->title ?? '')" autofocus />
                </x-ui.field>
                <x-ui.field :label="__('admin.slug')" name="slug" :hint="__('books.slug_hint')">
                    <x-ui.input name="slug" :value="old('slug', $book->slug ?? '')" dir="ltr" placeholder="{{ __('courses.slug_placeholder') }}" />
                </x-ui.field>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <x-ui.field :label="__('books.author')" name="author" required>
                        <x-ui.input name="author" :value="old('author', $book->author ?? '')" />
                    </x-ui.field>
                    <x-ui.field :label="__('books.publisher')" name="publisher">
                        <x-ui.input name="publisher" :value="old('publisher', $book->publisher ?? '')" />
                    </x-ui.field>
                </div>
                <x-ui.field :label="__('books.page_count')" name="page_count" class="max-w-[10rem]">
                    <x-ui.input name="page_count" type="number" min="1" :value="old('page_count', $book->page_count ?? '')" />
                </x-ui.field>
                <x-ui.field :label="__('books.details')" name="details" :hint="__('books.details_hint')">
                    <x-ui.textarea name="details">{{ old('details', $book->details ?? '') }}</x-ui.textarea>
                </x-ui.field>
            </div>
        </x-ui.card>

        {{-- Purchase links --}}
        <x-ui.card x-data="{ rows: {{ Illuminate\Support\Js::from($linkRows) }} }">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-ink">{{ __('books.purchase_links_heading') }}</h3>
                <button type="button" @click="rows.push({ website_name: '', url: '' })" class="text-sm font-semibold text-brand hover:underline">{{ __('books.add') }}</button>
            </div>
            <div class="space-y-3">
                <template x-for="(row, i) in rows" :key="i">
                    <div class="grid grid-cols-1 sm:grid-cols-[1fr_1.4fr_auto] gap-2 items-start">
                        <input x-model="row.website_name" :name="`purchase_links[${i}][website_name]`" placeholder="{{ __('books.website_name_placeholder') }}"
                               class="rounded-xl bg-surface-raised border border-line px-3 py-2 text-sm outline-none focus:border-brand">
                        <input x-model="row.url" :name="`purchase_links[${i}][url]`" placeholder="https://" dir="ltr"
                               class="rounded-xl bg-surface-raised border border-line px-3 py-2 text-sm outline-none focus:border-brand">
                        <button type="button" @click="rows.splice(i, 1)" class="p-2 text-muted hover:text-rose-600" aria-label="{{ __('books.remove') }}"><x-ui.icon name="close" class="w-4 h-4" /></button>
                    </div>
                </template>
            </div>
            <p class="text-xs text-muted mt-3">{{ __('books.purchase_links_hint') }}</p>
        </x-ui.card>
    </div>

    {{-- Sidebar meta --}}
    <div class="space-y-5">
        <x-ui.card>
            <h3 class="font-bold text-ink mb-4">{{ __('books.cover') }}</h3>
            <div class="space-y-3">
                @if (isset($book) && $book->coverUrl())
                    <div>
                        <p class="text-xs text-muted mb-1.5">{{ __('books.current_cover') }}</p>
                        <img src="{{ $book->coverUrl() }}" alt="" class="w-24 aspect-[3/4] object-cover rounded-xl border border-line">
                    </div>
                @endif
                <x-ui.field name="cover" :hint="__('books.cover_hint')">
                    <input type="file" name="cover" accept="image/png,image/jpeg,image/webp"
                           class="w-full text-sm text-ink file:me-3 file:rounded-lg file:border-0 file:bg-brand-tint file:text-brand-strong file:px-3 file:py-1.5 file:text-sm file:font-semibold">
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card>
            <label class="flex items-center gap-2 text-sm text-ink">
                <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $book->is_published ?? false)) class="rounded border-line text-brand focus:ring-brand">
                {{ __('admin.published') }}
            </label>
            @isset($book)
                <p class="text-xs text-muted mt-3">{{ __('books.order_hint') }}</p>
            @endisset
        </x-ui.card>
    </div>
</div>
