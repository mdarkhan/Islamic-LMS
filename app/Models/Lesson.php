<?php

namespace App\Models;

use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'course_id', 'slug', 'title', 'description', 'summary', 'syllabus',
    'held_on', 'date_label', 'duration_minutes', 'duration_label',
    'media_provider', 'media_url', 'media_file_id',
    'sort_order', 'is_published', 'legacy_id',
])]
class Lesson extends Model
{
    /** @use HasFactory<LessonFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'held_on' => 'date',
            'is_published' => 'boolean',
            'duration_minutes' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /** Derived, not stored, so changing storage provider needs no data rewrite. */
    public function embedUrl(): ?string
    {
        if ($this->media_provider === 'google_drive' && $this->media_file_id) {
            return 'https://drive.google.com/file/d/'.$this->media_file_id.'/preview';
        }

        return $this->media_url;
    }

    /** @return HasMany<LessonResource, $this> */
    public function resources(): HasMany
    {
        return $this->hasMany(LessonResource::class)->orderBy('sort_order');
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
