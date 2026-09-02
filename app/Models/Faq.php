<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['question', 'answer', 'sort_order', 'is_published'])]
class Faq extends Model
{
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    /** @param  Builder<Faq>  $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }
}
