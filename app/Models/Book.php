<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'slug', 'title', 'author', 'publisher', 'page_count', 'details', 'cover_path',
    'sort_order', 'is_published',
])]
class Book extends Model
{
    /** @use HasFactory<\Database\Factories\BookFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function casts(): array
    {
        return [
            'page_count' => 'integer',
            'sort_order' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    /** @return HasMany<BookPurchaseLink, $this> */
    public function purchaseLinks(): HasMany
    {
        return $this->hasMany(BookPurchaseLink::class)->orderBy('sort_order');
    }

    /** @param  Builder<Book>  $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }
}
