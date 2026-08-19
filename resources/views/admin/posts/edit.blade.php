<x-layout.admin :title="__('posts.edit')" :heading="__('posts.edit')">
    <div class="flex items-center justify-between gap-3 mb-4">
        <x-ui.button :href="route('admin.posts.index')" variant="ghost" size="sm">
            <x-ui.icon name="chevron" class="w-4 h-4 rotate-180" /> {{ __('posts.heading') }}
        </x-ui.button>
        <div class="flex items-center gap-2">
            <x-ui.button :href="route('admin.posts.preview', $post)" variant="secondary" size="sm" target="_blank">{{ __('posts.preview') }}</x-ui.button>
            @if ($post->status !== 'published')
                <form method="POST" action="{{ route('admin.posts.destroy', $post) }}" data-confirm="{{ __('posts.delete_confirm') }}">
                    @csrf @method('DELETE')
                    <x-ui.button type="submit" variant="danger" size="sm">{{ __('posts.delete') }}</x-ui.button>
                </form>
            @endif
        </div>
    </div>
    <form method="POST" action="{{ route('admin.posts.update', $post) }}">
        @csrf @method('PUT')
        @include('admin.posts._form', ['post' => $post, 'categories' => $categories])
    </form>
</x-layout.admin>
