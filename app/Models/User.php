<?php

namespace App\Models;

use App\Mail\PasswordResetLink;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * points_balance is a cached mirror of the ledger and must only ever be written
 * by PointService, inside the same transaction as the point_transactions row.
 */
#[Fillable([
    'roll', 'name', 'guardian_name', 'email', 'phone', 'password',
    'status', 'force_password_change', 'is_legacy_import', 'legacy_import_batch_id',
    'legacy_source_key', 'locale',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_ARCHIVED = 'archived';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'force_password_change' => 'boolean',
            'notify_by_email' => 'boolean',
            'is_legacy_import' => 'boolean',
            'points_balance' => 'integer',
        ];
    }

    /**
     * Roll numbers are compared in Latin digits: the legacy site matched
     * parseBanglaNumber(input) against parseBanglaNumber(stored), so a student who
     * has always typed "২৫" must still match the stored "25".
     */
    public static function normaliseRoll(?string $roll): ?string
    {
        if ($roll === null) {
            return null;
        }

        $roll = \Normalizer::normalize(trim($roll), \Normalizer::FORM_C) ?: trim($roll);

        return strtr($roll, [
            '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
            '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
        ]);
    }

    /** Sends the self-service reset link (called by the password broker with a fresh token). */
    public function sendPasswordResetNotification($token): void
    {
        Mail::to($this->email)->send(new PasswordResetLink(
            $this->name,
            route('password.reset', ['token' => $token, 'email' => $this->email]),
            (int) config('auth.passwords.users.expire', 60),
        ));
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * The password rule for THIS account. Staff/admin accounts guard the whole CRUD surface
     * and the answer key, so they need more than a student's 6-character minimum; students
     * keep the lower bar deliberately (young, roll-based, simple to memorise). The one
     * place this is decided — every "set my own password" screen uses it.
     */
    public function passwordRule(): Password
    {
        return $this->isAdmin()
            ? Password::min(10)->letters()->numbers()
            : Password::min(6);
    }

    /**
     * Invalidate any "remember me" cookie the browser is holding. A suspend, an
     * admin-issued password reset, or a self password change must all close this
     * door — the DB `sessions` row those call sites already delete only covers an
     * active session, not a remember cookie sitting on a signed-out browser.
     */
    public function rotateRememberToken(): void
    {
        $this->forceFill(['remember_token' => Str::random(60)])->save();
    }

    /** Users holding the student role — the population the admin student area manages. */
    public function scopeStudents(Builder $query): void
    {
        $query->whereHas('roles', fn ($r) => $r->where('name', Role::STUDENT));
    }

    /** Users holding any non-student role — the population the Staff admin area manages. */
    public function scopeStaff(Builder $query): void
    {
        $query->whereHas('roles', fn ($r) => $r->where('name', '!=', Role::STUDENT));
    }

    public function isStudent(): bool
    {
        return $this->hasRole(Role::STUDENT);
    }

    public function assignRole(string $name): void
    {
        $role = Role::query()->where('name', $name)->firstOrFail();
        $this->roles()->syncWithoutDetaching([$role->getKey()]);
        $this->unsetRelation('roles');
    }

    public function hasRole(string $name): bool
    {
        return $this->roles->contains('name', $name);
    }

    public function hasAnyRole(string ...$names): bool
    {
        return $this->roles->whereIn('name', $names)->isNotEmpty();
    }

    public function isAdmin(): bool
    {
        return $this->hasAnyRole(Role::SUPER_ADMIN, Role::ADMIN);
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->hasRole(Role::SUPER_ADMIN)) {
            return true;
        }

        return $this->roles->loadMissing('permissions')
            ->pluck('permissions')->flatten()
            ->contains('name', $permission);
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /** @return HasMany<PointTransaction, $this> */
    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class);
    }

    /** @return HasMany<QuizAttempt, $this> */
    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /** @return HasMany<RewardGrant, $this> */
    public function rewardGrants(): HasMany
    {
        return $this->hasMany(RewardGrant::class);
    }
}
