<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Permissions are grouped so the admin UI can render them by section.
     * Adding a role later (ustaz, editor) is data only — no schema change.
     */
    private const PERMISSIONS = [
        'students' => ['students.view', 'students.create', 'students.update', 'students.suspend', 'students.reset_password'],
        'points' => ['points.view', 'points.grant', 'points.deduct'],
        'catalog' => ['courses.manage', 'lessons.manage'],
        'quizzes' => ['quizzes.view', 'quizzes.create', 'quizzes.update', 'quizzes.publish', 'quizzes.import'],
        'results' => ['results.view', 'results.adjust', 'results.regrade', 'results.release'],
        'content' => ['posts.manage', 'notices.manage'],
        'system' => ['settings.manage', 'audit.view'],
    ];

    private const ROLE_PERMISSIONS = [
        Role::ADMIN => '*',
        Role::STUDENT => [],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $group => $names) {
            foreach ($names as $name) {
                Permission::query()->updateOrCreate(
                    ['name' => $name],
                    ['label' => $name, 'group' => $group],
                );
            }
        }

        $roles = [
            Role::SUPER_ADMIN => 'সুপার অ্যাডমিন',
            Role::ADMIN => 'অ্যাডমিন',
            Role::STUDENT => 'শিক্ষার্থী',
        ];

        foreach ($roles as $name => $label) {
            $role = Role::query()->updateOrCreate(['name' => $name], ['label' => $label]);

            // super_admin bypasses permission checks in User::hasPermission(), so it
            // deliberately carries no explicit grants.
            $grants = self::ROLE_PERMISSIONS[$name] ?? null;

            if ($grants === '*') {
                $role->permissions()->sync(Permission::query()->pluck('id'));
            } elseif (is_array($grants)) {
                $role->permissions()->sync($grants);
            }
        }
    }
}
