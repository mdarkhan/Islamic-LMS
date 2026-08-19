<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Shared sort resolution for the admin student and points lists. Only whitelisted keys
 * and directions are ever used, so the raw ORDER BY is safe. Roll is sorted numerically
 * (CAST … UNSIGNED) so 101 < 102 < 1010, and is always the stable final tiebreak.
 */
class StudentSort
{
    /** @var array<string, string> sort key → SQL expression */
    private const COLUMNS = [
        'roll' => 'CAST(roll AS UNSIGNED)',
        'name' => 'name',
        'points' => 'points_balance',
    ];

    public const DEFAULT = 'roll';

    /**
     * The active sort key and direction from the request (defaults: roll, ascending).
     *
     * @return array{0: string, 1: string}
     */
    public static function resolve(Request $request): array
    {
        $sort = in_array($request->query('sort'), array_keys(self::COLUMNS), true)
            ? (string) $request->query('sort')
            : self::DEFAULT;

        $dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';

        return [$sort, $dir];
    }

    /**
     * Apply the resolved ordering to a query, with roll as the deterministic tiebreak.
     *
     * @param  Builder<\App\Models\User>  $query
     */
    public static function apply(Builder $query, string $sort, string $dir): void
    {
        $column = self::COLUMNS[$sort] ?? self::COLUMNS[self::DEFAULT];

        $query->orderByRaw("{$column} {$dir}")
            ->orderByRaw('CAST(roll AS UNSIGNED) asc');
    }
}
