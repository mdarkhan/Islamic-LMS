<?php

namespace App\Models;

use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

#[Fillable([
    'slug', 'post_category_id', 'title', 'excerpt', 'body', 'featured_image',
    'author_id', 'status', 'published_at', 'seo_title', 'seo_description',
])]
class Post extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'views_count' => 'integer'];
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /** Comma-separated tag names, for pre-filling the admin form. */
    public function tagsInput(): string
    {
        return $this->relationLoaded('tags') || $this->exists
            ? $this->tags->pluck('name')->implode(', ')
            : '';
    }

    /**
     * Publicly visible: published AND its publish time has arrived. A draft, an archived
     * post, or a future-scheduled published post is never returned here — the single
     * definition the public index, detail, sitemap and homepage all share.
     *
     * @param  Builder<Post>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $query->where('status', self::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function isPublic(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && $this->published_at !== null
            && $this->published_at->lessThanOrEqualTo(now());
    }

    /**
     * The article body as safe HTML. The rich-text editor stores HTML; this passes it
     * through the allow-list sanitiser at render time, so the output can never carry a
     * script, event handler, style or unsafe link — no matter how the body reached the
     * database (editor, seeder, import).
     */
    public function renderedBody(): string
    {
        return HtmlSanitizer::clean($this->body);
    }

    public function excerptText(int $limit = 200): string
    {
        if ($this->excerpt !== null && trim($this->excerpt) !== '') {
            return $this->excerpt;
        }

        // Derive a plain-text summary from the sanitised body: drop tags, collapse
        // whitespace, decode entities, then trim to length.
        $text = html_entity_decode(strip_tags($this->renderedBody()), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return Str::limit(trim(preg_replace('/\s+/u', ' ', $text) ?? ''), $limit);
    }

    public function metaTitle(): string
    {
        return $this->seo_title ?: $this->title;
    }

    public function metaDescription(): string
    {
        return $this->seo_description ?: $this->excerptText(160);
    }

    /** @return BelongsTo<PostCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class, 'post_category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
