<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_student_logs_in_with_roll_and_password(): void
    {
        $student = $this->makeStudent(['roll' => '101', 'password' => Hash::make('secret123')]);

        $response = $this->post('/login', ['identifier' => '101', 'password' => 'secret123']);

        $response->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($student);
        $this->assertNotNull($student->fresh()->last_login_at);
    }

    public function test_bengali_numeral_roll_is_accepted(): void
    {
        $student = $this->makeStudent(['roll' => '25', 'password' => Hash::make('secret123')]);

        $this->post('/login', ['identifier' => '২৫', 'password' => 'secret123'])
            ->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticatedAs($student);
    }

    public function test_an_admin_logs_in_with_email(): void
    {
        $admin = $this->makeAdmin(['email' => 'boss@example.test', 'password' => Hash::make('secret123')]);

        $this->post('/login', ['identifier' => 'boss@example.test', 'password' => 'secret123'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_wrong_password_is_rejected_with_a_generic_message(): void
    {
        $this->makeStudent(['roll' => '101', 'password' => Hash::make('secret123')]);

        $this->from('/login')
            ->post('/login', ['identifier' => '101', 'password' => 'wrong'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('identifier');

        $this->assertGuest();
    }

    public function test_unknown_roll_gives_the_same_generic_error_as_a_wrong_password(): void
    {
        // No account exists — the message must not reveal that.
        $this->post('/login', ['identifier' => '999', 'password' => 'whatever'])
            ->assertSessionHasErrors(['identifier' => 'রোল/ইমেইল অথবা পাসওয়ার্ড সঠিক নয়।']);

        $this->assertGuest();
    }

    public function test_a_suspended_student_cannot_log_in(): void
    {
        $this->makeStudent(['roll' => '101', 'password' => Hash::make('secret123'), 'status' => User::STATUS_SUSPENDED]);

        $this->post('/login', ['identifier' => '101', 'password' => 'secret123'])
            ->assertSessionHasErrors('identifier');

        $this->assertGuest();
    }

    public function test_an_archived_student_cannot_log_in(): void
    {
        $this->makeStudent(['roll' => '102', 'password' => Hash::make('secret123'), 'status' => User::STATUS_ARCHIVED]);

        $this->post('/login', ['identifier' => '102', 'password' => 'secret123'])
            ->assertSessionHasErrors('identifier');

        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        $this->makeStudent(['roll' => '101', 'password' => Hash::make('secret123')]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['identifier' => '101', 'password' => 'wrong']);
        }

        $this->post('/login', ['identifier' => '101', 'password' => 'secret123'])
            ->assertSessionHasErrors('identifier');

        // Even the correct password is blocked once the limiter trips.
        $this->assertGuest();
    }

    public function test_logout_ends_the_session(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student)->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
