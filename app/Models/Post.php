<?php

namespace App\Models;

use App\Support\Markdown;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        return ['published_at' => 'datetime'];
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

    /** Safe rendered HTML of the Markdown body (raw HTML stripped). */
    public function renderedBody(): string
    {
        return Markdown::render($this->body);
    }

    public function excerptText(int $limit = 200): string
    {
        return $this->excerpt !== null && trim($this->excerpt) !== ''
            ? $this->excerpt
            : Markdown::toText($this->body, $limit);
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
