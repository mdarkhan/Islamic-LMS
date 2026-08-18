<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['body', 'is_active', 'starts_at', 'ends_at', 'priority', 'audience', 'created_by'])]
class Notice extends Model
{
    public const AUDIENCE_PUBLIC = 'public';
    public const AUDIENCE_STUDENTS = 'students';
    public const AUDIENCE_ALL = 'all';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'priority' => 'integer',
        ];
    }

    /**
     * Currently live by SERVER time: active, started (or no start), not ended (or no
     * end). No cron flips a flag — the window is evaluated at read time (brief §14).
     *
     * @param  Builder<Notice>  $query
     */
    public function scopeActiveAt(Builder $query, ?CarbonInterface $now = null): void
    {
        $now ??= now();

        $query->where('is_active', true)
            ->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }

    /**
     * Notices for a given audience — the audience itself plus "all".
     *
     * @param  Builder<Notice>  $query
     */
    public function scopeForAudience(Builder $query, string $audience): void
    {
        $query->whereIn('audience', array_unique([$audience, self::AUDIENCE_ALL]));
    }
}
