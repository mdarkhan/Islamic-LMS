<?php

namespace App\Models;

use App\Support\Slug;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

#[Fillable(['slug', 'name'])]
class Tag extends Model
{
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsToMany<Post, $this> */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }

    /**
     * Resolve a list of typed-in names to Tag ids, reusing a tag of the same name
     * (case-insensitive) and creating the rest with a real Bengali slug. Returns the ids
     * ready for a pivot sync.
     *
     * @param  iterable<string>  $names
     * @return array<int, int>
     */
    public static function resolveMany(iterable $names): array
    {
        $ids = [];

        foreach ($names as $raw) {
            $name = trim((string) $raw);
            if ($name === '') {
                continue;
            }

            $tag = self::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
                ?? self::query()->create([
                    'name' => $name,
                    'slug' => Slug::unique($name, fn (string $s) => self::query()->where('slug', $s)->exists(), 'tag'),
                ]);

            $ids[$tag->getKey()] = $tag->getKey();   // keyed to dedupe
        }

        return array_values($ids);
    }

    /**
     * Split an admin's comma-separated tag field into clean names.
     *
     * @return Collection<int, string>
     */
    public static function parseInput(?string $input): Collection
    {
        return collect(preg_split('/[,\n]+/', (string) $input) ?: [])
            ->map(fn ($n) => trim($n))
            ->filter()
            ->unique(fn ($n) => mb_strtolower($n))
            ->values();
    }
}
