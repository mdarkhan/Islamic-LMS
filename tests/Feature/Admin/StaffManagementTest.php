<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    // ── Access control ──────────────────────────────────────────────────────────

    public function test_a_regular_admin_cannot_reach_the_staff_area(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('admin.staff.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.staff.create'))->assertForbidden();
    }

    public function test_a_regular_admin_cannot_reach_the_roles_area(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('admin.roles.index'))->assertForbidden();
    }

    public function test_a_student_cannot_reach_the_staff_area(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student)->get(route('admin.staff.index'))->assertForbidden();
    }

    // ── Creating staff ───────────────────────────────────────────────────────────

    public function test_super_admin_creates_a_staff_account_with_a_temp_password(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $adminRole = Role::query()->where('name', Role::ADMIN)->firstOrFail();

        $this->actingAs($superAdmin)->post(route('admin.staff.store'), [
            'name' => 'সহকারী শিক্ষক', 'email' => 'assistant@masudalimi.test', 'role_id' => $adminRole->id,
        ])->assertRedirect(route('admin.staff.index'))->assertSessionHas('temp_password');

        $staff = User::query()->where('email', 'assistant@masudalimi.test')->firstOrFail();
        $this->assertTrue($staff->hasRole(Role::ADMIN));
        $this->assertTrue($staff->force_password_change);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.created']);
    }

    public function test_the_student_role_cannot_be_assigned_from_the_staff_form(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $studentRole = Role::query()->where('name', Role::STUDENT)->firstOrFail();

        $this->actingAs($superAdmin)->post(route('admin.staff.store'), [
            'name' => 'ভুয়া স্টাফ', 'email' => 'fake@masudalimi.test', 'role_id' => $studentRole->id,
        ])->assertSessionHasErrors('role_id');
    }

    // ── Self-protection ──────────────────────────────────────────────────────────

    public function test_a_super_admin_cannot_change_their_own_status(): void
    {
        $superAdmin = $this->makeSuperAdmin();

        $this->actingAs($superAdmin)->put(route('admin.staff.status', $superAdmin), [
            'status' => 'suspended',
        ])->assertSessionHasErrors('status');

        $this->assertSame('active', $superAdmin->fresh()->status);
    }

    public function test_one_of_two_super_admins_can_be_suspended_by_the_other(): void
    {
        // A super_admin can only ever reach this route as itself (role:super_admin
        // gates it), and self-changes are always blocked — so the "last super_admin"
        // guard can only ever matter when at least one other remains, which this
        // exercises: suspending B still leaves A, so it must succeed.
        $a = $this->makeSuperAdmin();
        $b = $this->makeSuperAdmin();

        $this->actingAs($a)->put(route('admin.staff.status', $b), [
            'status' => 'suspended',
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->assertSame('suspended', $b->fresh()->status);
    }

    public function test_suspending_a_staff_member_ends_their_sessions(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $staff = $this->makeAdmin();
        DB::table('sessions')->insert([
            'id' => 'sess-1', 'user_id' => $staff->id, 'payload' => 'x', 'last_activity' => time(),
        ]);

        $this->actingAs($superAdmin)->put(route('admin.staff.status', $staff), [
            'status' => 'suspended',
        ])->assertRedirect();

        $this->assertDatabaseMissing('sessions', ['user_id' => $staff->id]);
    }

    public function test_super_admin_resets_a_staff_members_password(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $staff = $this->makeAdmin();

        $this->actingAs($superAdmin)->post(route('admin.staff.reset-password', $staff))
            ->assertRedirect()->assertSessionHas('temp_password');

        $this->assertTrue($staff->fresh()->force_password_change);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.password_reset']);
    }

    public function test_a_staff_member_cannot_change_their_own_role(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        $adminRole = Role::query()->where('name', Role::ADMIN)->firstOrFail();

        $this->actingAs($superAdmin)->put(route('admin.staff.update', $superAdmin), [
            'name' => $superAdmin->name, 'email' => $superAdmin->email, 'role_id' => $adminRole->id,
        ])->assertSessionHasErrors('role_id');

        $this->assertTrue($superAdmin->fresh()->hasRole(Role::SUPER_ADMIN));
    }
}
