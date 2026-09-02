<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'label'])]
class Role extends Model
{
    public const SUPER_ADMIN = 'super_admin';
    public const ADMIN = 'admin';
    public const STUDENT = 'student';

    /**
     * super_admin bypasses permission checks entirely (User::hasPermission), so editing
     * its grants here would be meaningless; student's grants are never consulted (only
     * `perm:` middleware inside /admin reads them, and student never reaches there). Both
     * are therefore locked from permission editing in the admin UI to avoid a confusing,
     * functionally-inert control. The name (slug) of every role is immutable after
     * creation regardless — `Role::ADMIN` etc. are referenced by name throughout the code.
     */
    public function isProtected(): bool
    {
        return in_array($this->name, [self::SUPER_ADMIN, self::STUDENT], true);
    }

    /**
     * Deletion is blocked for all three built-in roles, including `admin` (whose
     * permissions ARE editable via isProtected() above) — its name is referenced
     * directly throughout routes/web.php (`role:super_admin,admin`) and code
     * (`Role::ADMIN`), so removing the row would break those, not just this UI.
     */
    public function isBuiltIn(): bool
    {
        return in_array($this->name, [self::SUPER_ADMIN, self::ADMIN, self::STUDENT], true);
    }

    /** @return BelongsToMany<Permission, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
