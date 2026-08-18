<x-layout.admin :title="__('posts.create')" :heading="__('posts.create')">
    <div class="mb-4">
        <x-ui.button :href="route('admin.posts.index')" variant="ghost" size="sm">
            <x-ui.icon name="chevron" class="w-4 h-4 rotate-180" /> {{ __('posts.heading') }}
        </x-ui.button>
    </div>
    <form method="POST" action="{{ route('admin.posts.store') }}">
        @csrf
        @include('admin.posts._form', ['post' => null, 'categories' => $categories])
    </form>
</x-layout.admin>
