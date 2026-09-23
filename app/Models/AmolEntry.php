<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One student's checkmark for one deed on one calendar date. Written only by
 * AmolService::toggle(), which always targets the server's current Dhaka date — never a
 * client-supplied one, so a past day can never be edited after it has passed.
 */
#[Fillable(['user_id', 'amol_id', 'date', 'is_done'])]
class AmolEntry extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_done' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Amol, $this> */
    public function amol(): BelongsTo
    {
        return $this->belongsTo(Amol::class);
    }
}
