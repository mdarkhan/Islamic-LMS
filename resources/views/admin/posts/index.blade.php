@php
    $statusTone = ['draft' => 'neutral', 'published' => 'success', 'archived' => 'warning'];
@endphp

<x-layout.admin :title="__('posts.heading')" :heading="__('posts.heading')">
    <div class="flex items-center justify-between gap-3 mb-5">
        <x-ui.button :href="route('admin.posts.categories.index')" variant="secondary" size="sm">{{ __('posts.categories') }}</x-ui.button>
        <x-ui.button :href="route('admin.posts.create')" size="sm"><x-ui.icon name="plus" class="w-4 h-4" /> {{ __('posts.create') }}</x-ui.button>
    </div>

    <form method="GET" class="grid gap-3 sm:grid-cols-4 mb-5">
        <x-ui.select name="status">
            <option value="">{{ __('posts.status') }}: {{ __('posts.all') }}</option>
            @foreach (['draft','published','archived'] as $s)
                <option value="{{ $s }}" @selected($filters['status'] === $s)>{{ __('posts.status_'.$s) }}</option>
            @endforeach
        </x-ui.select>
        <x-ui.select name="category">
            <option value="">{{ __('posts.category') }}: {{ __('posts.all') }}</option>
            @foreach ($categories as $c)
                <option value="{{ $c->id }}" @selected($filters['category'] == $c->id)>{{ $c->name }}</option>
            @endforeach
        </x-ui.select>
        <x-ui.input name="q" value="{{ $filters['q'] }}" placeholder="{{ __('posts.search') }}" />
        <x-ui.button type="submit" variant="secondary">{{ __('posts.all') }}</x-ui.button>
    </form>

    @if ($posts->isEmpty())
        <x-ui.card><x-ui.empty :title="__('posts.none')" /></x-ui.card>
    @else
        <div class="overflow-x-auto rounded-2xl border border-line">
            <table class="w-full text-sm">
                <thead class="bg-surface-raised text-muted">
                    <tr>
                        <th scope="col" class="px-4 py-2.5 text-start font-semibold">{{ __('posts.title') }}</th>
                        <th scope="col" class="px-4 py-2.5 text-start font-semibold">{{ __('posts.category') }}</th>
                        <th scope="col" class="px-4 py-2.5 text-start font-semibold">{{ __('posts.status') }}</th>
                        <th scope="col" class="px-4 py-2.5"><span class="sr-only">actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($posts as $post)
                        <tr class="hover:bg-surface-raised/50">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.posts.edit', $post) }}" class="font-medium text-ink hover:text-brand">{{ $post->title }}</a>
                                <div class="text-xs text-muted">{{ $post->author?->name }}</div>
                            </td>
                            <td class="px-4 py-3 text-muted">{{ $post->category?->name }}</td>
                            <td class="px-4 py-3"><x-ui.badge :color="$statusTone[$post->status] ?? 'neutral'">{{ __('posts.status_'.$post->status) }}</x-ui.badge></td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    <x-ui.button :href="route('admin.posts.preview', $post)" variant="ghost" size="sm" target="_blank">{{ __('posts.preview') }}</x-ui.button>
                                    <x-ui.button :href="route('admin.posts.edit', $post)" variant="secondary" size="sm">{{ __('posts.edit') }}</x-ui.button>
                                    @if ($post->status !== 'published')
                                        <form method="POST" action="{{ route('admin.posts.publish', $post) }}">@csrf<x-ui.button type="submit" size="sm">{{ __('posts.publish') }}</x-ui.button></form>
                                    @else
                                        <form method="POST" action="{{ route('admin.posts.archive', $post) }}">@csrf<x-ui.button type="submit" variant="secondary" size="sm">{{ __('posts.archive') }}</x-ui.button></form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $posts->links() }}</div>
    @endif
</x-layout.admin>
