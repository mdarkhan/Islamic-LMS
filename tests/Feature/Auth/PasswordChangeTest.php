<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_forced_student_is_redirected_to_change_password(): void
    {
        $student = $this->makeStudent(['force_password_change' => true]);

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertRedirect(route('password.change'));
    }

    public function test_the_change_password_page_itself_is_reachable_while_forced(): void
    {
        $student = $this->makeStudent(['force_password_change' => true]);

        $this->actingAs($student)->get(route('password.change'))->assertOk();
    }

    public function test_changing_the_password_clears_the_flag_and_unblocks_the_app(): void
    {
        $student = $this->makeStudent([
            'force_password_change' => true,
            'password' => Hash::make('temp1234'),
        ]);

        $this->actingAs($student)->put(route('password.change.update'), [
            'current_password' => 'temp1234',
            'password' => 'newsecret123',
            'password_confirmation' => 'newsecret123',
        ])->assertRedirect(route('student.dashboard'));

        $fresh = $student->fresh();
        $this->assertFalse($fresh->force_password_change);
        $this->assertTrue(Hash::check('newsecret123', $fresh->password));

        // App is now reachable.
        $this->actingAs($fresh)->get(route('student.dashboard'))->assertOk();
    }

    public function test_a_wrong_current_password_is_rejected(): void
    {
        $student = $this->makeStudent(['force_password_change' => true, 'password' => Hash::make('temp1234')]);

        $this->actingAs($student)->put(route('password.change.update'), [
            'current_password' => 'wrong',
            'password' => 'newsecret123',
            'password_confirmation' => 'newsecret123',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue($student->fresh()->force_password_change);
    }
}
