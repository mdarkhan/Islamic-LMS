<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * points_balance is a cached mirror of the ledger and must only ever be written
 * by PointService, inside the same transaction as the point_transactions row.
 */
#[Fillable([
    'roll', 'name', 'guardian_name', 'email', 'phone', 'password',
    'status', 'force_password_change', 'is_legacy_import',
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

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
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
}
