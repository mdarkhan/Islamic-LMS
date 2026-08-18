@php $post = $post ?? null; @endphp

<style>
    /* Placeholder for the empty rich-text editor (contenteditable has no :placeholder). */
    .rt-editor:empty::before { content: attr(data-placeholder); color: var(--muted); pointer-events: none; }
    .rt-editor:focus { outline: none; }
</style>

<div
    x-data="postEditor({
        title: @js(old('title', $post?->title ?? '')),
        slug: @js(old('slug', $post?->slug ?? '')),
        slugLocked: {{ ($post && $post->slug) || old('slug') ? 'true' : 'false' }},
        status: @js(old('status', $post?->status ?? 'draft')),
        body: @js(old('body', $post?->body ?? '')),
        linkPrompt: @js(__('posts.link_prompt')),
    })"
    class="grid gap-5 lg:grid-cols-[1.6fr_1fr] items-start"
>
    <div class="space-y-4">
        <x-ui.card class="space-y-4">
            <x-ui.field :label="__('posts.title')" name="title" :required="true">
                <x-ui.input name="title" x-model="title" @input="onTitle()" required />
            </x-ui.field>

            {{-- WordPress-style permalink: auto-filled from the title, editable, live preview. --}}
            <x-ui.field :label="__('posts.permalink')" name="slug" :hint="__('posts.slug_hint')">
                <div class="flex items-center gap-1 rounded-xl border border-line bg-surface-raised px-3 py-2.5 text-sm focus-within:border-brand transition-colors">
                    <span class="text-muted whitespace-nowrap select-none" dir="ltr">/articles/</span>
                    <input type="text" name="slug" id="slug" x-model="slug" @input="onSlugInput()" @blur="normaliseSlug()"
                           dir="ltr" autocomplete="off" spellcheck="false"
                           class="flex-1 min-w-0 bg-transparent text-ink placeholder-muted/70 outline-none" />
                </div>
            </x-ui.field>

            <x-ui.field :label="__('posts.excerpt')" name="excerpt">
                <x-ui.textarea name="excerpt" rows="2">{{ old('excerpt', $post?->excerpt) }}</x-ui.textarea>
            </x-ui.field>

            {{-- Rich-text (WYSIWYG) editor. Emits HTML into a hidden input; the server
                 reduces it to a safe allow-list on save and again on display. --}}
            <x-ui.field :label="__('posts.body')" name="body" :hint="__('posts.body_hint')" :required="true">
                <div class="rounded-xl border border-line bg-surface-raised overflow-hidden focus-within:border-brand transition-colors">
                    <div class="flex flex-wrap items-center gap-0.5 border-b border-line p-1.5" role="toolbar" aria-label="{{ __('posts.body') }}">
                        @php
                            $btn = 'inline-flex h-8 min-w-8 items-center justify-center rounded-md px-2 text-sm text-ink hover:bg-surface transition-colors';
                            $div = '<span class="mx-1 h-5 w-px bg-line"></span>';
                        @endphp
                        <button type="button" class="{{ $btn }}" @mousedown.prevent="exec('bold')" title="{{ __('posts.fmt_bold') }}" aria-label="{{ __('posts.fmt_bold') }}"><span class="font-black">B</span></button>
                        <button type="button" class="{{ $btn }}" @mousedown.prevent="exec('italic')" title="{{ __('posts.fmt_italic') }}" aria-label="{{ __('posts.fmt_italic') }}"><span class="italic font-serif text-[15px]">I</span></button>
                        <button type="button" class="{{ $btn }}" @mousedown.prevent="exec('underline')" title="{{ __('posts.fmt_underline') }}" aria-label="{{ __('posts.fmt_underline') }}"><span class="underline">U</span></button>
                        {!! $div !!}
                        <button type="button" class="{{ $btn }}" @mousedown.prevent="block('h2')" title="{{ __('posts.fmt_h2') }}" aria-label="{{ __('posts.fmt_h2') }}"><span class="font-bold text-xs">H2</span></button>
                        <button type="button" class="{{ $btn }}" @mousedown.prevent="block('h3')" title="{{ __('posts.fmt_h3') }}" aria-label="{{ __('posts.fmt_h3') }}"><span class="font-bold text-xs">H3</span></button>
                        {!! $div !!}
                        <button type="button" class="{{ $btn }}" @mousedown.prevent="exec('insertUnorderedList')" title="{{ __('posts.fmt_bullet') }}" aria-label="{{ __('posts.fmt_bullet') }}">
                            <svg viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><circle cx="3" cy="5" r="1.3"/><circle cx="3" cy="10" r="1.3"/><circle cx="3" cy="15" r="1.3"/><rect x="7" y="4" width="11" height="2" rx="1"/><rect x="7" y="9" width="11" height="2" rx="1"/><rect x="7" y="14" width="11" height="2" rx="1"/></svg>
                        </button>
                        <button type="button" class="{{ $btn }}" @mousedown.prevent="exec('insertOrderedList')" title="{{ __('posts.fmt_number') }}" aria-label="{{ __('posts.fmt_number') }}"><span class="font-bold text-xs tabular-nums">1.</span></button>
                        <button type="button" class="{{ $btn }}" @mousedown.prevent="block('blockquote')" title="{{ __('posts.fmt_quote') }}" aria-label="{{ __('posts.fmt_quote') }}"><span class="text-lg leading-none">&ldquo;</span></button>
                        {!! $div !!}
                        <button type="button" class="{{ $btn }}" @mousedown.prevent="link()" title="{{ __('posts.fmt_link') }}" aria-label="{{ __('posts.fmt_link') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M10 13a5 5 0 0 0 7.07 0l1.42-1.42a5 5 0 0 0-7.07-7.07L10.29 5.6"/><path d="M14 11a5 5 0 0 0-7.07 0L5.5 12.42a5 5 0 0 0 7.07 7.07l1.13-1.12"/></svg>
                        </button>
                        <button type="button" class="{{ $btn }}" @mousedown.prevent="clearFormat()" title="{{ __('posts.fmt_clear') }}" aria-label="{{ __('posts.fmt_clear') }}"><span class="text-xs">&times;</span></button>
                    </div>

                    <div x-ref="editor" x-init="$el.innerHTML = html"
                         contenteditable="true" role="textbox" aria-multiline="true"
                         data-placeholder="{{ __('posts.body_placeholder') }}"
                         @input="sync()" @blur="sync()"
                         class="rt-editor min-h-[22rem] max-h-[38rem] overflow-y-auto px-4 py-3 text-ink leading-loose
                                [&_h2]:text-2xl [&_h2]:font-bold [&_h2]:mt-4 [&_h2]:mb-2
                                [&_h3]:text-xl [&_h3]:font-bold [&_h3]:mt-3 [&_h3]:mb-1.5
                                [&_p]:mb-2
                                [&_a]:text-brand [&_a]:underline [&_a]:underline-offset-2
                                [&_ul]:list-disc [&_ul]:ps-6 [&_ul]:mb-2 [&_ul]:space-y-1
                                [&_ol]:list-decimal [&_ol]:ps-6 [&_ol]:mb-2 [&_ol]:space-y-1
                                [&_blockquote]:border-s-4 [&_blockquote]:border-brand/40 [&_blockquote]:ps-4 [&_blockquote]:italic [&_blockquote]:text-muted
                                [&_strong]:font-bold [&_b]:font-bold"></div>
                </div>
                <input type="hidden" name="body" :value="html" />
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
        {{-- WordPress-style Publish panel. --}}
        <x-ui.card class="p-0 overflow-hidden">
            <div class="border-b border-line px-4 py-3">
                <h3 class="text-sm font-bold text-ink">{{ __('posts.publish_box') }}</h3>
            </div>
            <div class="space-y-4 p-4">
                <x-ui.field :label="__('posts.status')" name="status">
                    <x-ui.select name="status" x-model="status">
                        @foreach (['draft','published','archived'] as $s)
                            <option value="{{ $s }}">{{ __('posts.status_'.$s) }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.field :label="__('posts.published_at')" name="published_at" :hint="__('posts.scheduled_note')">
                    <x-ui.input type="datetime-local" name="published_at" value="{{ old('published_at', $post?->published_at?->format('Y-m-d\TH:i')) }}" />
                </x-ui.field>
            </div>
            <div class="flex items-center gap-2 border-t border-line bg-surface/60 px-4 py-3">
                {{-- When publishing, still offer a one-click "save as draft instead". --}}
                <x-ui.button type="submit" variant="ghost" size="sm" @click="status = 'draft'" x-show="status === 'published'" x-cloak>
                    {{ __('posts.save_draft') }}
                </x-ui.button>
                <x-ui.button type="submit" class="ms-auto"
                             x-text="status === 'published' ? '{{ $post ? __('posts.update_article') : __('posts.publish_now') }}' : (status === 'archived' ? '{{ __('posts.archive') }}' : '{{ __('posts.save_draft') }}')">
                    {{ __('posts.save') }}
                </x-ui.button>
            </div>
        </x-ui.card>

        <x-ui.card class="space-y-4">
            <x-ui.field :label="__('posts.category')" name="post_category_id" :required="true">
                <x-ui.select name="post_category_id" required>
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}" @selected(old('post_category_id', $post?->post_category_id) == $c->id)>{{ $c->name }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
        </x-ui.card>
    </div>
</div>

<script>
    window.postEditor = function (config) {
        return {
            title: config.title || '',
            slug: config.slug || '',
            slugLocked: !! config.slugLocked,   // an existing slug is never auto-overwritten by title edits
            status: config.status || 'draft',
            html: config.body || '',

            // ── Slug (WordPress-style permalink) ──────────────────────────────────
            slugify(value) {
                // Mirrors App\Support\Slug for a live preview: keep Latin/Bengali/Arabic
                // letters, matras and numerals; collapse everything else to a hyphen. The
                // server re-slugs authoritatively on save.
                return (value || '').toString().normalize('NFC').toLowerCase()
                    .replace(/[^\p{L}\p{M}\p{N}]+/gu, '-')
                    .replace(/^-+|-+$/g, '');
            },
            onTitle() {
                if (! this.slugLocked) {
                    this.slug = this.slugify(this.title);
                }
            },
            onSlugInput() {
                this.slugLocked = true;   // the admin took over the URL
            },
            normaliseSlug() {
                this.slug = this.slugify(this.slug);
                if (this.slug === '' && ! this.slugLocked) {
                    this.slug = this.slugify(this.title);
                }
            },

            // ── Rich-text editor ──────────────────────────────────────────────────
            exec(command, value = null) {
                this.$refs.editor.focus();
                document.execCommand(command, false, value);
                this.sync();
            },
            block(tag) {
                this.exec('formatBlock', tag);
            },
            clearFormat() {
                this.$refs.editor.focus();
                document.execCommand('removeFormat');
                document.execCommand('formatBlock', false, 'p');
                this.sync();
            },
            link() {
                const url = window.prompt(config.linkPrompt);
                if (url === null) {
                    return;   // cancelled
                }
                const clean = url.trim();
                this.$refs.editor.focus();
                const selection = window.getSelection();

                if (clean === '') {
                    document.execCommand('unlink');   // empty URL removes the link
                } else if (selection && selection.isCollapsed) {
                    const esc = clean.replace(/&/g, '&amp;').replace(/"/g, '&quot;')
                        .replace(/</g, '&lt;').replace(/>/g, '&gt;');
                    document.execCommand('insertHTML', false, '<a href="' + esc + '">' + esc + '</a>');
                } else {
                    document.execCommand('createLink', false, clean);
                }
                this.sync();
            },
            sync() {
                this.html = this.$refs.editor.innerHTML;
            },
        };
    };
</script>
