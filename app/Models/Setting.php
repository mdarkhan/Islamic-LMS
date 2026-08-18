<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A single key/value setting row. Access goes through SettingService (typed, cached,
 * defaulted) — controllers and Blade never query this model directly.
 *
 * The primary key is the string `key`; there is no auto-increment id and no created_at
 * (only updated_at, which drives the "last updated" stamp on reference rates).
 */
#[Fillable(['key', 'value', 'type', 'group', 'updated_by', 'updated_at'])]
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['updated_at' => 'datetime'];
    }
}
