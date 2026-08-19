<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only. Never update or delete a row: correct a mistake with an opposing
 * `adjustment` entry so the history stays reconstructable.
 */
#[Fillable([
    'user_id', 'type', 'amount', 'balance_after',
    'reference_type', 'reference_id', 'performed_by', 'reason',
])]
class PointTransaction extends Model
{
    public const UPDATED_AT = null;

    public const TYPE_GRANT = 'grant';
    public const TYPE_DEDUCTION = 'deduction';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_REFUND = 'refund';
    public const TYPE_IMPORT = 'import';
    public const TYPE_BONUS = 'bonus';   // achievement / topper reward

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
