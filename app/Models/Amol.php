<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * One row of the fixed daily checklist ("দৈনন্দিন আমলনামা"). Seeded by AmolSeeder;
 * `label` is Bengali content and is never localised (same rule as a lesson title).
 *
 * `group` clusters a daily prayer's sub-items (sunnah/prayer/takbir-e-oola) so the UI
 * can render them as one dropdown instead of flat rows; null for everything else.
 */
#[Fillable(['key', 'label', 'group', 'sort_order', 'is_active'])]
class Amol extends Model
{
    /** Display order for the five prayer-group dropdowns; the label is chrome (see lang amol.php files). */
    public const GROUP_ORDER = ['fajr', 'zuhr', 'asr', 'maghrib', 'isha'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @param Builder<Amol> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<Amol> $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order');
    }

    /**
     * Split a checklist (as AmolService::checklistFor returns it) into the five prayer
     * groups — each in GROUP_ORDER, possibly empty — plus everything ungrouped, each
     * side keeping its original (sort_order) sequence. Shared by the student and admin
     * checklist views so the grouping rule lives in exactly one place.
     *
     * @param  Collection<int, array{amol:Amol, is_done:bool}>  $checklist
     * @return array{grouped: Collection<string, Collection<int, array{amol:Amol, is_done:bool}>>, ungrouped: Collection<int, array{amol:Amol, is_done:bool}>}
     */
    public static function partition(Collection $checklist): array
    {
        $grouped = collect(self::GROUP_ORDER)->mapWithKeys(
            fn (string $group) => [$group => $checklist->filter(fn (array $item) => $item['amol']->group === $group)->values()]
        );

        $ungrouped = $checklist->filter(fn (array $item) => $item['amol']->group === null)->values();

        return ['grouped' => $grouped, 'ungrouped' => $ungrouped];
    }
}
