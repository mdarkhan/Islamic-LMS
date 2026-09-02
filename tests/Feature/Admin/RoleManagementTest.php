<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_creates_a_custom_role_with_selected_permissions(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $permissionIds = Permission::query()->where('group', 'content')->pluck('id')->all();

        $this->actingAs($superAdmin)->post(route('admin.roles.store'), [
            'name' => 'editor', 'label' => 'সম্পাদক', 'permissions' => $permissionIds,
        ])->assertRedirect(route('admin.roles.index'));

        $role = Role::query()->where('name', 'editor')->firstOrFail();
        $this->assertSame('সম্পাদক', $role->label);
        $this->assertCount(count($permissionIds), $role->permissions);
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.created']);
    }

    public function test_a_regular_admin_cannot_create_a_role(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('admin.roles.store'), [
            'name' => 'sneaky', 'label' => 'Sneaky', 'permissions' => [],
        ])->assertForbidden();

        $this->assertDatabaseMissing('roles', ['name' => 'sneaky']);
    }

    public function test_the_super_admin_role_cannot_be_edited(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $role = Role::query()->where('name', Role::SUPER_ADMIN)->firstOrFail();

        $this->actingAs($superAdmin)->get(route('admin.roles.edit', $role))->assertForbidden();
        $this->actingAs($superAdmin)->put(route('admin.roles.update', $role), ['label' => 'হ্যাক', 'permissions' => []])
            ->assertForbidden();
    }

    public function test_the_student_role_cannot_be_edited(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $role = Role::query()->where('name', Role::STUDENT)->firstOrFail();

        $this->actingAs($superAdmin)->get(route('admin.roles.edit', $role))->assertForbidden();
    }

    public function test_super_admin_narrows_the_admin_roles_permissions(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $role = Role::query()->where('name', Role::ADMIN)->firstOrFail();
        $keepIds = Permission::query()->where('group', 'students')->pluck('id')->all();

        $this->actingAs($superAdmin)->put(route('admin.roles.update', $role), [
            'label' => $role->label, 'permissions' => $keepIds,
        ])->assertRedirect();

        $role->refresh();
        $this->assertSame(count($keepIds), $role->permissions()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.updated']);
    }

    public function test_a_role_still_in_use_cannot_be_deleted(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $role = Role::query()->create(['name' => 'ustaz', 'label' => 'উস্তায']);
        $this->makeAdmin()->assignRole('ustaz');

        $this->actingAs($superAdmin)->delete(route('admin.roles.destroy', $role))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('roles', ['name' => 'ustaz']);
    }

    public function test_an_unused_custom_role_can_be_deleted(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $role = Role::query()->create(['name' => 'temp-role', 'label' => 'অস্থায়ী']);

        $this->actingAs($superAdmin)->delete(route('admin.roles.destroy', $role))->assertRedirect();

        $this->assertDatabaseMissing('roles', ['name' => 'temp-role']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.deleted']);
    }

    public function test_built_in_roles_cannot_be_deleted(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $role = Role::query()->where('name', Role::ADMIN)->firstOrFail();

        $this->actingAs($superAdmin)->delete(route('admin.roles.destroy', $role))->assertForbidden();

        $this->assertDatabaseHas('roles', ['name' => Role::ADMIN]);
    }
}
