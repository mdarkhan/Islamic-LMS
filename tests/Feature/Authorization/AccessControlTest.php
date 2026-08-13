<?php

namespace Tests\Feature\Authorization;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_student_cannot_reach_the_admin_area(): void
    {
        $this->actingAs($this->makeStudent())->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('student.dashboard'))->assertRedirect(route('login'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_an_admin_cannot_reach_the_student_area(): void
    {
        // Student routes are role:student; staff are kept out by design.
        $this->actingAs($this->makeAdmin())->get(route('student.dashboard'))->assertForbidden();
    }

    public function test_an_admin_can_reach_the_admin_dashboard(): void
    {
        $this->actingAs($this->makeAdmin())->get(route('admin.dashboard'))->assertOk();
    }

    public function test_permission_middleware_blocks_an_admin_lacking_a_permission(): void
    {
        // An admin whose role has every permission except students.create.
        $admin = $this->makeAdmin();
        $role = $admin->roles->first();
        $role->permissions()->detach(Permission::query()->where('name', 'students.create')->value('id'));

        $this->actingAs($admin)->get(route('admin.students.create'))->assertForbidden();
        // But a permission they still hold works.
        $this->actingAs($admin)->get(route('admin.students.index'))->assertOk();
    }

    public function test_super_admin_bypasses_permission_checks(): void
    {
        // super_admin has no explicit permission rows, yet reaches everything.
        $this->actingAs($this->makeSuperAdmin())->get(route('admin.students.create'))->assertOk();
        $this->assertSame(0, Role::query()->where('name', Role::SUPER_ADMIN)->first()->permissions()->count());
    }
}
