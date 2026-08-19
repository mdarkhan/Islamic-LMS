<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_a_student_with_a_manual_password(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('admin.students.store'), [
            'roll' => '২০০',
            'name' => 'নতুন শিক্ষার্থী',
            'guardian_name' => 'অভিভাবক',
            'password_mode' => 'manual',
            'password' => 'secret123',
        ])->assertRedirect();

        $student = User::query()->where('roll', '200')->first();   // normalised
        $this->assertNotNull($student);
        $this->assertTrue($student->isStudent());
        $this->assertTrue(Hash::check('secret123', $student->password));
        $this->assertFalse($student->force_password_change);
        $this->assertDatabaseHas('audit_logs', ['action' => 'student.created', 'auditable_id' => $student->id]);
    }

    public function test_generated_password_forces_a_change_and_is_shown_once(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(route('admin.students.store'), [
            'roll' => '201',
            'name' => 'শিক্ষার্থী',
            'guardian_name' => 'অভিভাবক',
            'password_mode' => 'generate',
        ]);

        $student = User::query()->where('roll', '201')->first();
        $this->assertTrue($student->force_password_change);
        $response->assertSessionHas('temp_password');
        $this->assertIsString(session('temp_password'));
    }

    public function test_guardian_name_is_required_on_create(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->from(route('admin.students.create'))->post(route('admin.students.store'), [
            'roll' => '202',
            'name' => 'শিক্ষার্থী',
            'password_mode' => 'generate',
        ])->assertSessionHasErrors('guardian_name');

        $this->assertNull(User::query()->where('roll', '202')->first());
    }

    public function test_the_student_list_defaults_to_roll_order_and_is_sortable(): void
    {
        $admin = $this->makeAdmin();
        $this->makeStudent(['roll' => '103', 'name' => 'Zeta Student'])->forceFill(['points_balance' => 5])->save();
        $this->makeStudent(['roll' => '101', 'name' => 'Alpha Student'])->forceFill(['points_balance' => 30])->save();
        $this->makeStudent(['roll' => '102', 'name' => 'Beta Student'])->forceFill(['points_balance' => 10])->save();

        // Default: roll ascending — 101, 102, 103 (numeric, not string).
        $this->actingAs($admin)->get(route('admin.students.index'))
            ->assertOk()->assertSeeInOrder(['Alpha Student', 'Beta Student', 'Zeta Student']);

        // By name, descending.
        $this->actingAs($admin)->get(route('admin.students.index', ['sort' => 'name', 'dir' => 'desc']))
            ->assertOk()->assertSeeInOrder(['Zeta Student', 'Beta Student', 'Alpha Student']);

        // By points, descending — 30, 10, 5.
        $this->actingAs($admin)->get(route('admin.students.index', ['sort' => 'points', 'dir' => 'desc']))
            ->assertOk()->assertSeeInOrder(['Alpha Student', 'Beta Student', 'Zeta Student']);
    }

    public function test_the_points_list_is_sortable_by_roll_name_and_points(): void
    {
        $admin = $this->makeAdmin();
        $this->makeStudent(['roll' => '105', 'name' => 'Low Points'])->forceFill(['points_balance' => 2])->save();
        $this->makeStudent(['roll' => '104', 'name' => 'High Points'])->forceFill(['points_balance' => 99])->save();

        // Default roll order: 104 before 105.
        $this->actingAs($admin)->get(route('admin.points.index'))
            ->assertOk()->assertSeeInOrder(['High Points', 'Low Points']);

        // Points descending: 99 before 2.
        $this->actingAs($admin)->get(route('admin.points.index', ['sort' => 'points', 'dir' => 'desc']))
            ->assertOk()->assertSeeInOrder(['High Points', 'Low Points']);
    }

    public function test_duplicate_roll_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $this->makeStudent(['roll' => '300']);

        $this->actingAs($admin)->from(route('admin.students.create'))->post(route('admin.students.store'), [
            'roll' => '৩০০',   // same roll in Bengali digits
            'name' => 'ডুপ্লিকেট',
            'password_mode' => 'generate',
        ])->assertSessionHasErrors('roll');
    }

    public function test_admin_updates_a_student_and_it_is_audited(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent(['name' => 'পুরাতন নাম']);

        $this->actingAs($admin)->put(route('admin.students.update', $student), [
            'roll' => $student->roll,
            'name' => 'নতুন নাম',
        ])->assertRedirect(route('admin.students.show', $student));

        $this->assertSame('নতুন নাম', $student->fresh()->name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'student.updated', 'auditable_id' => $student->id]);
    }

    public function test_suspending_a_student_clears_their_sessions(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        \DB::table('sessions')->insert([
            'id' => 'sess-1', 'user_id' => $student->id, 'ip_address' => '127.0.0.1',
            'user_agent' => 'x', 'payload' => 'x', 'last_activity' => time(),
        ]);

        $this->actingAs($admin)->put(route('admin.students.status', $student), ['status' => 'suspended'])
            ->assertRedirect();

        $this->assertSame(User::STATUS_SUSPENDED, $student->fresh()->status);
        $this->assertDatabaseMissing('sessions', ['id' => 'sess-1']);
    }

    public function test_admin_reactivates_a_suspended_student(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent(['status' => User::STATUS_SUSPENDED]);

        $this->actingAs($admin)->put(route('admin.students.status', $student), ['status' => 'active']);

        $this->assertSame(User::STATUS_ACTIVE, $student->fresh()->status);
    }

    public function test_admin_cannot_change_their_own_status(): void
    {
        // Give an admin the student role too so the route target check is reached.
        $admin = $this->makeAdmin();
        $admin->assignRole('student');

        $this->actingAs($admin)->put(route('admin.students.status', $admin), ['status' => 'suspended'])
            ->assertSessionHasErrors('status');

        $this->assertSame(User::STATUS_ACTIVE, $admin->fresh()->status);
    }

    public function test_password_reset_issues_a_temp_password_and_forces_change(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent(['password' => Hash::make('old12345'), 'force_password_change' => false]);

        $response = $this->actingAs($admin)->post(route('admin.students.reset-password', $student));

        $response->assertSessionHas('temp_password');
        $fresh = $student->fresh();
        $this->assertTrue($fresh->force_password_change);
        $this->assertFalse(Hash::check('old12345', $fresh->password), 'old password no longer works');
        $this->assertDatabaseHas('audit_logs', ['action' => 'student.password_reset', 'auditable_id' => $student->id]);
    }

    public function test_the_student_area_manages_only_students_not_staff(): void
    {
        $admin = $this->makeAdmin();
        $otherAdmin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('admin.students.show', $otherAdmin))->assertNotFound();
    }
}
