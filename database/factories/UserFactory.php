<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'roll' => (string) fake()->unique()->numberBetween(1000, 999999),
            'name' => fake()->name(),
            'guardian_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'status' => User::STATUS_ACTIVE,
            'force_password_change' => false,
            'points_balance' => 0,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => ['email_verified_at' => null]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => ['status' => User::STATUS_SUSPENDED]);
    }

    /** Staff accounts have no roll number. */
    public function staff(): static
    {
        return $this->state(fn (array $attributes) => ['roll' => null]);
    }

    /**
     * Seed a starting balance. Writes the ledger row too, so the
     * users.points_balance === SUM(ledger) invariant holds in tests.
     */
    public function withPoints(int $points): static
    {
        return $this->afterCreating(function (User $user) use ($points) {
            if ($points !== 0) {
                app(\App\Services\Points\PointService::class)->credit(
                    user: $user,
                    amount: $points,
                    reason: 'Test seed',
                );
            }
        });
    }
}
