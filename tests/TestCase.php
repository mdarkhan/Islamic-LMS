<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function seedRoles(): void
    {
        if (! Role::query()->where('name', Role::STUDENT)->exists()) {
            $this->seed(RoleSeeder::class);
        }
    }

    protected function makeStudent(array $attributes = []): User
    {
        $this->seedRoles();
        $user = User::factory()->create($attributes);
        $user->assignRole(Role::STUDENT);

        return $user->fresh();
    }

    protected function makeAdmin(array $attributes = []): User
    {
        $this->seedRoles();
        $user = User::factory()->staff()->create($attributes);
        $user->assignRole(Role::ADMIN);

        return $user->fresh();
    }

    protected function makeSuperAdmin(array $attributes = []): User
    {
        $this->seedRoles();
        $user = User::factory()->staff()->create($attributes);
        $user->assignRole(Role::SUPER_ADMIN);

        return $user->fresh();
    }
}
