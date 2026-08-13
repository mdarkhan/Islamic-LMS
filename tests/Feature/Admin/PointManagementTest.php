<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PointManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_credits_points_with_a_reason(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();

        $this->actingAs($admin)->post(route('admin.points.store', $student), [
            'direction' => 'credit', 'amount' => 5, 'reason' => 'কুইজ হাদিয়া',
        ])->assertRedirect();

        $this->assertSame(5, $student->fresh()->points_balance);
        $this->assertDatabaseHas('point_transactions', [
            'user_id' => $student->id, 'amount' => 5, 'performed_by' => $admin->id, 'reason' => 'কুইজ হাদিয়া',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'points.credit', 'auditable_id' => $student->id]);
    }

    public function test_admin_deducts_points(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $this->actingAs($admin)->post(route('admin.points.store', $student), ['direction' => 'credit', 'amount' => 5, 'reason' => 'seed']);

        $this->actingAs($admin)->post(route('admin.points.store', $student), [
            'direction' => 'deduct', 'amount' => 2, 'reason' => 'সমন্বয়',
        ]);

        $this->assertSame(3, $student->fresh()->points_balance);
    }

    public function test_deducting_more_than_the_balance_is_refused(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();

        $this->actingAs($admin)->from(route('admin.points.show', $student))->post(route('admin.points.store', $student), [
            'direction' => 'deduct', 'amount' => 10, 'reason' => 'অতিরিক্ত',
        ])->assertSessionHasErrors('amount');

        $this->assertSame(0, $student->fresh()->points_balance);
    }

    public function test_reason_is_required(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();

        $this->actingAs($admin)->from(route('admin.points.show', $student))->post(route('admin.points.store', $student), [
            'direction' => 'credit', 'amount' => 5,
        ])->assertSessionHasErrors('reason');
    }

    public function test_bulk_credit_applies_to_all_selected_students(): void
    {
        $admin = $this->makeAdmin();
        $a = $this->makeStudent();
        $b = $this->makeStudent();

        $this->actingAs($admin)->post(route('admin.points.bulk.store'), [
            'student_ids' => [$a->id, $b->id],
            'direction' => 'credit', 'amount' => 3, 'reason' => 'সবাইকে',
        ])->assertRedirect(route('admin.points.index'));

        $this->assertSame(3, $a->fresh()->points_balance);
        $this->assertSame(3, $b->fresh()->points_balance);
    }

    public function test_bulk_deduct_is_all_or_nothing_when_one_lacks_balance(): void
    {
        $admin = $this->makeAdmin();
        $rich = $this->makeStudent();
        $poor = $this->makeStudent();
        $this->actingAs($admin)->post(route('admin.points.store', $rich), ['direction' => 'credit', 'amount' => 10, 'reason' => 'seed']);

        // poor has 0; the whole batch must be rejected and rich must be untouched.
        $this->actingAs($admin)->from(route('admin.points.bulk.form'))->post(route('admin.points.bulk.store'), [
            'student_ids' => [$rich->id, $poor->id],
            'direction' => 'deduct', 'amount' => 5, 'reason' => 'ব্যাচ',
        ])->assertSessionHasErrors('amount');

        $this->assertSame(10, $rich->fresh()->points_balance, 'no partial application');
        $this->assertSame(0, $poor->fresh()->points_balance);
    }

    public function test_the_ledger_invariant_holds_after_admin_operations(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();

        $this->actingAs($admin)->post(route('admin.points.store', $student), ['direction' => 'credit', 'amount' => 7, 'reason' => 'a']);
        $this->actingAs($admin)->post(route('admin.points.store', $student), ['direction' => 'deduct', 'amount' => 3, 'reason' => 'b']);

        $ledger = (int) $student->pointTransactions()->sum('amount');
        $this->assertSame($ledger, $student->fresh()->points_balance);
    }

    public function test_a_student_without_the_permission_cannot_grant_points(): void
    {
        $this->actingAs($this->makeStudent())->post(route('admin.points.store', $this->makeStudent()), [
            'direction' => 'credit', 'amount' => 5, 'reason' => 'x',
        ])->assertForbidden();
    }
}
