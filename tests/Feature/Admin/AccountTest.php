<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** An admin changing their OWN password after the forced first-login change. */
class AccountTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return $this->makeAdmin(['password' => Hash::make('currentpass1')]);
    }

    public function test_an_admin_can_open_their_account_page(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.account.edit'))->assertOk()->assertSee($admin->email);
    }

    public function test_an_admin_can_change_their_own_password(): void
    {
        $admin = $this->admin();
        $oldToken = $admin->remember_token;

        $this->actingAs($admin)->put(route('admin.account.password'), [
            'current_password' => 'currentpass1',
            'password' => 'brandnewpass9',
            'password_confirmation' => 'brandnewpass9',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertTrue(Hash::check('brandnewpass9', $admin->fresh()->password));
        $this->assertNotSame($oldToken, $admin->fresh()->remember_token, 'the remember-me cookie must die with the old password');
        $this->assertDatabaseHas('audit_logs', ['action' => 'password.self_changed']);
    }

    public function test_the_current_password_must_be_right(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.account.password'), [
            'current_password' => 'wrong',
            'password' => 'brandnewpass9',
            'password_confirmation' => 'brandnewpass9',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('currentpass1', $admin->fresh()->password));
    }

    public function test_the_staff_password_strength_rule_applies(): void
    {
        $admin = $this->admin();

        // Too short / no digit.
        $this->actingAs($admin)->put(route('admin.account.password'), [
            'current_password' => 'currentpass1',
            'password' => 'shortpw',
            'password_confirmation' => 'shortpw',
        ])->assertSessionHasErrors('password');

        // Unchanged password is refused too.
        $this->actingAs($admin)->put(route('admin.account.password'), [
            'current_password' => 'currentpass1',
            'password' => 'currentpass1',
            'password_confirmation' => 'currentpass1',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('currentpass1', $admin->fresh()->password));
    }

    public function test_a_student_cannot_reach_the_admin_account_page(): void
    {
        $this->actingAs($this->makeStudent())->get(route('admin.account.edit'))->assertForbidden();
    }
}
