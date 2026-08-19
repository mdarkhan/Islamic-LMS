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
                        <button type="button" class="{{ $btn }}" @mousedown.prevent="toggleBlock('h2')" title="{{ __('posts.fmt_h2') }}" aria-label="{{ __('posts.fmt_h2') }}"><span class="font-bold text-xs">H2</span></button>
                        <button type="button" class="{{ $btn }}" @mousedown.prevent="toggleBlock('h3')" title="{{ __('posts.fmt_h3') }}" aria-label="{{ __('posts.fmt_h3') }}"><span class="font-bold text-xs">H3</span></button>
                        {!! $div !!}
                        <button type="button" class="{{ $btn }}" @mousedown.prevent="exec('insertUnorderedList')" title="{{ __('posts.fmt_bullet') }}" aria-label="{{ __('posts.fmt_bullet') }}">
                            <svg viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><circle cx="3" cy="5" r="1.3"/><circle cx="3" cy="10" r="1.3"/><circle cx="3" cy="15" r="1.3"/><rect x="7" y="4" width="11" height="2" rx="1"/><rect x="7" y="9" width="11" height="2" rx="1"/><rect x="7" y="14" width="11" height="2" rx="1"/></svg>
                        </button>
                        <button type="button" class="{{ $btn }}" @mousedown.prevent="exec('insertOrderedList')" title="{{ __('posts.fmt_number') }}" aria-label="{{ __('posts.fmt_number') }}"><span class="font-bold text-xs tabular-nums">1.</span></button>
                        <button type="button" class="{{ $btn }}" @mousedown.prevent="toggleBlock('blockquote')" title="{{ __('posts.fmt_quote') }}" aria-label="{{ __('posts.fmt_quote') }}"><span class="text-lg leading-none">&ldquo;</span></button>
                        {!! $div !!}

                        {{-- Text colour (picker + hex code). --}}
                        <div class="relative">
                            <button type="button" class="{{ $btn }} flex-col !gap-0" @mousedown.prevent="openColor('text')" @click.stop title="{{ __('posts.fmt_text_color') }}" aria-label="{{ __('posts.fmt_text_color') }}">
                                <span class="font-bold leading-none">A</span>
                                <span class="block h-1 w-4 rounded-sm" :style="'background:'+textColor"></span>
                            </button>
                            <div x-show="popover === 'text'" x-cloak @mousedown.stop @click.outside="popover = null"
                                 class="absolute z-30 mt-1 w-56 rounded-xl border border-line bg-card p-3 shadow-lg space-y-2">
                                <p class="text-xs font-semibold text-muted">{{ __('posts.fmt_text_color') }}</p>
                                <div class="flex items-center gap-2">
                                    <input type="color" x-model="textColor" @change="applyColor('text')" @mousedown.stop
                                           class="h-9 w-10 shrink-0 cursor-pointer rounded border border-line bg-transparent p-0.5" aria-label="{{ __('posts.color_pick') }}">
                                    <input type="text" x-model="textColor" @keydown.enter.prevent="applyColor('text')" @mousedown.stop
                                           dir="ltr" maxlength="7" placeholder="#1c1917" aria-label="{{ __('posts.color_code') }}"
                                           class="w-full rounded-lg border border-line bg-surface-raised px-2 py-1.5 text-sm text-ink outline-none focus:border-brand">
                                </div>
                                <button type="button" @mousedown.prevent="applyColor('text')" class="w-full rounded-lg bg-brand px-3 py-1.5 text-sm font-semibold text-brand-ink hover:bg-brand-strong">{{ __('posts.apply') }}</button>
                            </div>
                        </div>

                        {{-- Background / highlight colour (picker + hex code). --}}
                        <div class="relative">
                            <button type="button" class="{{ $btn }} flex-col !gap-0" @mousedown.prevent="openColor('bg')" @click.stop title="{{ __('posts.fmt_bg_color') }}" aria-label="{{ __('posts.fmt_bg_color') }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M12 19l7-7 3 3-7 7-3-3z"/><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/><path d="M2 2l7.586 7.586"/><circle cx="11" cy="11" r="2"/></svg>
                                <span class="block h-1 w-4 rounded-sm" :style="'background:'+bgColor"></span>
                            </button>
                            <div x-show="popover === 'bg'" x-cloak @mousedown.stop @click.outside="popover = null"
                                 class="absolute z-30 mt-1 w-56 rounded-xl border border-line bg-card p-3 shadow-lg space-y-2">
                                <p class="text-xs font-semibold text-muted">{{ __('posts.fmt_bg_color') }}</p>
                                <div class="flex items-center gap-2">
                                    <input type="color" x-model="bgColor" @change="applyColor('bg')" @mousedown.stop
                                           class="h-9 w-10 shrink-0 cursor-pointer rounded border border-line bg-transparent p-0.5" aria-label="{{ __('posts.color_pick') }}">
                                    <input type="text" x-model="bgColor" @keydown.enter.prevent="applyColor('bg')" @mousedown.stop
                                           dir="ltr" maxlength="7" placeholder="#fef08a" aria-label="{{ __('posts.color_code') }}"
                                           class="w-full rounded-lg border border-line bg-surface-raised px-2 py-1.5 text-sm text-ink outline-none focus:border-brand">
                                </div>
                                <button type="button" @mousedown.prevent="applyColor('bg')" class="w-full rounded-lg bg-brand px-3 py-1.5 text-sm font-semibold text-brand-ink hover:bg-brand-strong">{{ __('posts.apply') }}</button>
                            </div>
                        </div>
                        {!! $div !!}

                        {{-- Link (inline URL field — no browser prompt, which some browsers block). --}}
                        <div class="relative">
                            <button type="button" class="{{ $btn }}" @mousedown.prevent="openLink()" @click.stop title="{{ __('posts.fmt_link') }}" aria-label="{{ __('posts.fmt_link') }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M10 13a5 5 0 0 0 7.07 0l1.42-1.42a5 5 0 0 0-7.07-7.07L10.29 5.6"/><path d="M14 11a5 5 0 0 0-7.07 0L5.5 12.42a5 5 0 0 0 7.07 7.07l1.13-1.12"/></svg>
                            </button>
                            <div x-show="popover === 'link'" x-cloak @mousedown.stop @click.outside="popover = null"
                                 class="absolute z-30 mt-1 w-72 rounded-xl border border-line bg-card p-3 shadow-lg space-y-2">
                                <p class="text-xs font-semibold text-muted">{{ __('posts.fmt_link') }}</p>
                                <input type="url" x-model="linkUrl" x-ref="linkInput" @keydown.enter.prevent="applyLink()" @mousedown.stop
                                       dir="ltr" placeholder="https://…" aria-label="{{ __('posts.fmt_link') }}"
                                       class="w-full rounded-lg border border-line bg-surface-raised px-2 py-1.5 text-sm text-ink outline-none focus:border-brand">
                                <div class="flex items-center gap-2">
                                    <button type="button" @mousedown.prevent="applyLink()" class="flex-1 rounded-lg bg-brand px-3 py-1.5 text-sm font-semibold text-brand-ink hover:bg-brand-strong">{{ __('posts.apply') }}</button>
                                    <button type="button" @mousedown.prevent="removeLink()" class="rounded-lg border border-line px-3 py-1.5 text-sm text-muted hover:text-ink">{{ __('posts.link_remove') }}</button>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="{{ $btn }}" @mousedown.prevent="clearFormat()" title="{{ __('posts.fmt_clear') }}" aria-label="{{ __('posts.fmt_clear') }}"><span class="text-xs">&times;</span></button>
                    </div>

                    <div x-ref="editor" x-init="$el.innerHTML = html"
                         contenteditable="true" role="textbox" aria-multiline="true"
                         data-placeholder="{{ __('posts.body_placeholder') }}"
                         @input="sync()" @blur="sync()" @paste="onPaste($event)"
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
            <div x-data="{ adding: {{ old('new_category') ? 'true' : 'false' }} }">
                <x-ui.field :label="__('posts.category')" name="post_category_id" :required="true">
                    {{-- Pick an existing category… --}}
                    <div x-show="!adding">
                        <x-ui.select name="post_category_id">
                            @foreach ($categories as $c)
                                <option value="{{ $c->id }}" @selected(old('post_category_id', $post?->post_category_id) == $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </x-ui.select>
                        <button type="button" @click="adding = true; $nextTick(() => $refs.newCategory.focus())"
                                class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-brand hover:underline">
                            <span class="text-base leading-none">+</span> {{ __('posts.category_add') }}
                        </button>
                    </div>

                    {{-- …or create a new one inline (WordPress-style). --}}
                    <div x-show="adding" x-cloak class="space-y-2">
                        <x-ui.input name="new_category" x-ref="newCategory" :value="old('new_category')"
                                    :placeholder="__('posts.category_name')" />
                        <button type="button" @click="adding = false"
                                class="text-sm text-muted hover:text-ink hover:underline">
                            {{ __('ui.cancel') }}
                        </button>
                    </div>
                </x-ui.field>
                @error('new_category') <p class="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            </div>
        </x-ui.card>

        <x-ui.card class="space-y-4">
            <x-ui.field :label="__('posts.tags')" name="tags" :hint="__('posts.tags_hint')">
                <x-ui.input name="tags" :value="old('tags', $post?->tagsInput())" placeholder="{{ __('posts.tags_placeholder') }}" />
            </x-ui.field>
        </x-ui.card>
    </div>
</div>

<script>
    window.postEditor = function (config) {
        // ── Paste sanitiser (mirrors App\Support\HtmlSanitizer's allow-list) ──────────
        // Cleans pasted HTML client-side so the editor keeps only safe, allow-listed
        // formatting (bold, italic, underline, colours, lists, links) — the server
        // re-enforces the same list on save and on display.
        const ALLOWED = {
            p: [], br: [], strong: [], b: [], em: [], i: [], u: [], s: [],
            h2: [], h3: [], h4: [], ul: [], ol: [], li: [], blockquote: [],
            code: [], pre: [], span: [], a: ['href'],
        };
        const STYLE_PROPS = ['color', 'background-color', 'font-weight', 'font-style', 'text-decoration', 'text-decoration-line'];

        function filterStyle(style) {
            const kept = [];
            (style || '').split(';').forEach(function (decl) {
                const i = decl.indexOf(':');
                if (i < 0) return;
                const prop = decl.slice(0, i).trim().toLowerCase();
                const value = decl.slice(i + 1).trim();
                const lower = value.toLowerCase();
                if (! value || STYLE_PROPS.indexOf(prop) === -1) return;
                if (lower.includes('url(') || lower.includes('expression') || lower.includes('javascript:')
                    || value.includes('/*') || value.includes('<') || value.includes('>')) return;
                if (! /^[#0-9a-z.,%()\- ]+$/i.test(value)) return;
                kept.push(prop + ': ' + value);
            });
            return kept.join('; ');
        }
        function hardenLink(a) {
            const href = (a.getAttribute('href') || '').trim();
            const safe = href !== '' && (href.startsWith('/') || href.startsWith('#') || /^(https?:|mailto:)/i.test(href));
            if (! safe) { a.removeAttribute('href'); return; }
            a.setAttribute('rel', 'noopener nofollow ugc');
            a.setAttribute('target', '_blank');
        }
        function sanitizeNode(node) {
            Array.prototype.slice.call(node.childNodes).forEach(function (child) {
                if (child.nodeType === 3) return;               // text node
                if (child.nodeType !== 1) { child.remove(); return; }
                const tag = child.tagName.toLowerCase();
                if (! (tag in ALLOWED)) {
                    sanitizeNode(child);
                    if (tag === 'script' || tag === 'style') { child.remove(); return; }
                    while (child.firstChild) node.insertBefore(child.firstChild, child);
                    child.remove();
                    return;
                }
                Array.prototype.slice.call(child.attributes).forEach(function (attr) {
                    const name = attr.name.toLowerCase();
                    if (name === 'style') {
                        const s = filterStyle(attr.value);
                        s ? child.setAttribute('style', s) : child.removeAttribute('style');
                        return;
                    }
                    if (ALLOWED[tag].indexOf(name) === -1) child.removeAttribute(attr.name);
                });
                if (tag === 'a') hardenLink(child);
                sanitizeNode(child);
            });
        }
        function sanitizeFragment(html) {
            const doc = new DOMParser().parseFromString('<div id="__rt">' + html + '</div>', 'text/html');
            const root = doc.getElementById('__rt');
            if (! root) return '';
            sanitizeNode(root);
            return root.innerHTML;
        }

        return {
            title: config.title || '',
            slug: config.slug || '',
            slugLocked: !! config.slugLocked,   // an existing slug is never auto-overwritten by title edits
            status: config.status || 'draft',
            html: config.body || '',
            popover: null,                      // 'text' | 'bg' | 'link' | null (only one open at a time)
            textColor: '#1c1917',
            bgColor: '#fef08a',
            linkUrl: 'https://',
            savedRange: null,                   // editor selection, preserved while a pop-over has focus

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
            saveSelection() {
                const sel = window.getSelection();
                this.savedRange = (sel && sel.rangeCount && this.$refs.editor.contains(sel.anchorNode))
                    ? sel.getRangeAt(0).cloneRange() : null;
            },
            restoreSelection() {
                if (! this.savedRange) return;
                const sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(this.savedRange);
            },
            exec(command, value = null) {
                this.$refs.editor.focus();
                document.execCommand('styleWithCSS', false, false);   // prefer <b>/<i>/<u> over inline styles
                document.execCommand(command, false, value);
                this.sync();
            },
            // Headings and quote toggle: applying the same block again returns to a paragraph.
            toggleBlock(tag) {
                this.$refs.editor.focus();
                const current = (document.queryCommandValue('formatBlock') || '').toLowerCase().replace(/[<>]/g, '');
                document.execCommand('formatBlock', false, current === tag ? 'p' : tag);
                this.sync();
            },
            clearFormat() {
                this.$refs.editor.focus();
                document.execCommand('styleWithCSS', false, true);
                document.execCommand('removeFormat');   // clears bold/italic/underline/colours
                document.execCommand('unlink');
                document.execCommand('formatBlock', false, 'p');
                this.sync();
            },
            openColor(which) {
                this.saveSelection();                          // keep the selection while the pop-over has focus
                this.popover = this.popover === which ? null : which;
            },
            applyColor(which) {
                const color = which === 'text' ? this.textColor : this.bgColor;
                if (! /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(color)) return;   // ignore an incomplete hex code
                this.$refs.editor.focus();
                this.restoreSelection();
                document.execCommand('styleWithCSS', false, true);
                if (which === 'text') {
                    document.execCommand('foreColor', false, color);
                } else if (! document.execCommand('hiliteColor', false, color)) {
                    document.execCommand('backColor', false, color);   // Safari/older fallback
                }
                this.popover = null;
                this.sync();
            },
            // Inline link pop-over (no window.prompt — some browsers block it).
            openLink() {
                this.saveSelection();
                this.linkUrl = this.selectedLinkHref() || 'https://';
                const opening = this.popover !== 'link';
                this.popover = opening ? 'link' : null;
                if (opening) this.$nextTick(() => this.$refs.linkInput && this.$refs.linkInput.focus());
            },
            selectedLinkHref() {
                const sel = window.getSelection();
                let node = sel && sel.anchorNode;
                while (node && node !== this.$refs.editor) {
                    if (node.nodeType === 1 && node.tagName === 'A') return node.getAttribute('href') || '';
                    node = node.parentNode;
                }
                return '';
            },
            applyLink() {
                const url = (this.linkUrl || '').trim();
                this.$refs.editor.focus();
                this.restoreSelection();
                const sel = window.getSelection();

                if (url === '' || url === 'https://') {
                    document.execCommand('unlink');
                } else if (sel && sel.isCollapsed) {
                    // No text selected: insert the URL itself as a linked label.
                    const esc = url.replace(/&/g, '&amp;').replace(/"/g, '&quot;')
                        .replace(/</g, '&lt;').replace(/>/g, '&gt;');
                    document.execCommand('insertHTML', false, '<a href="' + esc + '">' + esc + '</a>');
                } else {
                    document.execCommand('createLink', false, url);
                }
                this.popover = null;
                this.sync();
            },
            removeLink() {
                this.$refs.editor.focus();
                this.restoreSelection();
                document.execCommand('unlink');
                this.popover = null;
                this.sync();
            },
            onPaste(event) {
                const data = event.clipboardData || window.clipboardData;
                if (! data) return;                             // let the browser handle it
                event.preventDefault();
                this.$refs.editor.focus();
                const html = data.getData('text/html');
                if (html && html.trim() !== '') {
                    document.execCommand('insertHTML', false, sanitizeFragment(html));   // keep safe formatting
                } else {
                    document.execCommand('insertText', false, data.getData('text/plain'));
                }
                this.sync();
            },
            sync() {
                this.html = this.$refs.editor.innerHTML;
            },
        };
    };
</script>
