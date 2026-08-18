<x-layout.admin :title="__('posts.categories')" :heading="__('posts.categories')">
    <div class="mb-4">
        <x-ui.button :href="route('admin.posts.index')" variant="ghost" size="sm">
            <x-ui.icon name="chevron" class="w-4 h-4 rotate-180" /> {{ __('posts.heading') }}
        </x-ui.button>
    </div>

    {{-- Add --}}
    <x-ui.card class="mb-5">
        <form method="POST" action="{{ route('admin.posts.categories.store') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <x-ui.field :label="__('posts.category_name')" name="name" class="flex-1 min-w-48">
                <x-ui.input name="name" value="{{ old('name') }}" required />
            </x-ui.field>
            <label class="flex items-center gap-2 text-sm pb-2.5">
                <input type="checkbox" name="is_active" value="1" checked class="rounded border-line text-brand"> {{ __('posts.category_active') }}
            </label>
            <x-ui.button type="submit" class="pb-0">{{ __('posts.category_add') }}</x-ui.button>
        </form>
    </x-ui.card>

    <div class="space-y-2">
        @foreach ($categories as $category)
            <x-ui.card class="!p-4">
                <div class="flex flex-wrap items-center gap-3">
                    <form method="POST" action="{{ route('admin.posts.categories.update', $category) }}" class="flex flex-wrap items-center gap-3 flex-1">
                        @csrf @method('PUT')
                        <x-ui.input name="name" value="{{ $category->name }}" class="flex-1 min-w-48" />
                        <label class="flex items-center gap-2 text-sm whitespace-nowrap">
                            <input type="checkbox" name="is_active" value="1" @checked($category->is_active) class="rounded border-line text-brand"> {{ __('posts.category_active') }}
                        </label>
                        <span class="text-xs text-muted whitespace-nowrap">{{ __('posts.category_posts') }}: {{ bn($category->posts_count) }}</span>
                        <x-ui.button type="submit" variant="secondary" size="sm">{{ __('posts.save') }}</x-ui.button>
                    </form>
                    @if ($category->posts_count === 0)
                        <form method="POST" action="{{ route('admin.posts.categories.destroy', $category) }}" onsubmit="return confirm('{{ __('posts.delete_confirm') }}')">
                            @csrf @method('DELETE')
                            <x-ui.button type="submit" variant="danger" size="sm">{{ __('posts.delete') }}</x-ui.button>
                        </form>
                    @endif
                </div>
            </x-ui.card>
        @endforeach
    </div>
</x-layout.admin>
