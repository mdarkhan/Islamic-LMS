@php $post = $post ?? null; @endphp

<div class="grid gap-5 lg:grid-cols-[1.6fr_1fr] items-start">
    <div class="space-y-4">
        <x-ui.card class="space-y-4">
            <x-ui.field :label="__('posts.title')" name="title" :required="true">
                <x-ui.input name="title" value="{{ old('title', $post?->title) }}" required />
            </x-ui.field>
            <x-ui.field :label="__('posts.slug')" name="slug" :hint="'/articles/…'">
                <x-ui.input name="slug" value="{{ old('slug', $post?->slug) }}" placeholder="{{ __('posts.title') }}" />
            </x-ui.field>
            <x-ui.field :label="__('posts.excerpt')" name="excerpt">
                <x-ui.textarea name="excerpt" rows="2">{{ old('excerpt', $post?->excerpt) }}</x-ui.textarea>
            </x-ui.field>
            <x-ui.field :label="__('posts.body')" name="body" :hint="__('posts.body_hint')" :required="true">
                <x-ui.textarea name="body" rows="16" class="font-mono text-sm" required>{{ old('body', $post?->body) }}</x-ui.textarea>
            </x-ui.field>
        </x-ui.card>

        <x-ui.card class="space-y-4">
            <h3 class="text-sm font-bold uppercase tracking-wider text-muted">SEO</h3>
            <x-ui.field :label="__('posts.seo_title')" name="seo_title">
                <x-ui.input name="seo_title" value="{{ old('seo_title', $post?->seo_title) }}" />
            </x-ui.field>
            <x-ui.field :label="__('posts.seo_description')" name="seo_description">
                <x-ui.textarea name="seo_description" rows="2">{{ old('seo_description', $post?->seo_description) }}</x-ui.textarea>
            </x-ui.field>
            <x-ui.field :label="__('posts.featured_image')" name="featured_image">
                <x-ui.input name="featured_image" value="{{ old('featured_image', $post?->featured_image) }}" placeholder="https://…" />
            </x-ui.field>
        </x-ui.card>
    </div>

    <div class="space-y-4">
        <x-ui.card class="space-y-4">
            <x-ui.field :label="__('posts.category')" name="post_category_id" :required="true">
                <x-ui.select name="post_category_id" required>
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}" @selected(old('post_category_id', $post?->post_category_id) == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field :label="__('posts.status')" name="status" :required="true">
                <x-ui.select name="status">
                    @foreach (['draft','published','archived'] as $s)
                        <option value="{{ $s }}" @selected(old('status', $post?->status ?? 'draft') === $s)>{{ __('posts.status_'.$s) }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
            <x-ui.field :label="__('posts.published_at')" name="published_at" :hint="__('posts.scheduled_note')">
                <x-ui.input type="datetime-local" name="published_at" value="{{ old('published_at', $post?->published_at?->format('Y-m-d\TH:i')) }}" />
            </x-ui.field>
            <div class="flex items-center gap-2 pt-2">
                <x-ui.button type="submit" class="flex-1">{{ __('posts.save') }}</x-ui.button>
            </div>
        </x-ui.card>
    </div>
</div>
