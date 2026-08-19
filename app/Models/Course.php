<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'title', 'description', 'sort_order', 'is_published', 'topper_rewards'])]
class Course extends Model
{
    /** @use HasFactory<\Database\Factories\CourseFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'sort_order' => 'integer',
            'topper_rewards' => 'array',
        ];
    }

    /** @return HasMany<Lesson, $this> */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('sort_order');
    }

    /** @return HasMany<Quiz, $this> */
    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    /**
     * The configured position → points rewards, cleaned and sorted by position. Each row
     * is { position:int, points:int } with position ≥ 1 and points ≥ 1.
     *
     * @return array<int, array{position:int, points:int}>
     */
    public function topperRewardRows(): array
    {
        return collect($this->topper_rewards ?? [])
            ->map(fn ($r) => ['position' => (int) ($r['position'] ?? 0), 'points' => (int) ($r['points'] ?? 0)])
            ->filter(fn ($r) => $r['position'] >= 1 && $r['points'] >= 1)
            ->unique('position')
            ->sortBy('position')
            ->values()
            ->all();
    }
}
