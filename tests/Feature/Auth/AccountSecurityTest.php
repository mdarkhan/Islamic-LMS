<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression coverage for the "remember me survives a suspend/reset" gap: the DB
 * `sessions` row those actions already delete only ends an active session, not a
 * remember cookie sitting on a signed-out browser. Fixed by User::rotateRememberToken()
 * at every suspend/reset/self-password-change call site, plus EnsureAccountActive as
 * defence in depth for any other path that reaches a protected route.
 */
class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: string, 1: string} [cookie name, cookie value] */
    private function rememberCookie(User $user): array
    {
        return [Auth::guard('web')->getRecallerName(), $user->id.'|'.$user->remember_token.'|'.$user->password];
    }

    /**
     * actingAs() leaves the test client authenticated as that user for EVERY later
     * request in the test, not just the next one. Drop it so the following request
     * is authenticated (or not) purely from the cookie it carries — a browser that
     * never touched the admin session.
     */
    private function browserWithOnlyCookie(string $name, string $value): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withCookie($name, $value);
    }

    public function test_suspending_a_student_invalidates_their_remember_me_cookie(): void
    {
        $student = $this->makeStudent();
        $student->forceFill(['remember_token' => Str::random(60)])->save();
        [$name, $value] = $this->rememberCookie($student->fresh());

        $this->actingAs($this->makeAdmin())
            ->put(route('admin.students.status', $student), ['status' => User::STATUS_SUSPENDED])
            ->assertRedirect();

        $this->browserWithOnlyCookie($name, $value)->get(route('student.dashboard'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_resetting_a_students_password_invalidates_their_remember_me_cookie(): void
    {
        $student = $this->makeStudent();
        $student->forceFill(['remember_token' => Str::random(60)])->save();
        [$name, $value] = $this->rememberCookie($student->fresh());

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.students.reset-password', $student))
            ->assertRedirect();

        $this->browserWithOnlyCookie($name, $value)->get(route('student.dashboard'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_suspending_a_staff_member_invalidates_their_remember_me_cookie(): void
    {
        $staff = $this->makeAdmin();
        $staff->forceFill(['remember_token' => Str::random(60)])->save();
        [$name, $value] = $this->rememberCookie($staff->fresh());

        $this->actingAs($this->makeSuperAdmin())
            ->put(route('admin.staff.status', $staff), ['status' => User::STATUS_SUSPENDED])
            ->assertRedirect();

        $this->browserWithOnlyCookie($name, $value)->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_students_own_password_change_invalidates_a_remember_me_cookie(): void
    {
        $student = $this->makeStudent(['password' => Hash::make('secret123')]);
        $student->forceFill(['remember_token' => Str::random(60)])->save();
        [$name, $value] = $this->rememberCookie($student->fresh());

        $this->actingAs($student)->put(route('student.profile.password'), [
            'current_password' => 'secret123',
            'password' => 'newsecret456',
            'password_confirmation' => 'newsecret456',
        ])->assertRedirect();

        $this->browserWithOnlyCookie($name, $value)->get(route('student.dashboard'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /**
     * Defence in depth: even if a request reaches a protected route with a still-valid
     * session (bypassing the controller's own session-row cleanup entirely), the
     * EnsureAccountActive middleware ends it the moment the account is not active.
     */
    public function test_a_live_session_is_ended_the_moment_the_account_is_suspended(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student)->get(route('student.dashboard'))->assertOk();

        $student->forceFill(['status' => User::STATUS_SUSPENDED])->save();

        $this->get(route('student.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_is_rate_limited_per_ip_across_different_rolls(): void
    {
        // Rolls are short sequential numbers — simulate an attacker cycling through
        // many DIFFERENT accounts from one IP, not repeatedly guessing one account.
        for ($i = 0; $i < 30; $i++) {
            $this->post('/login', ['identifier' => (string) (500 + $i), 'password' => 'wrong']);
        }

        $this->makeStudent(['roll' => '999', 'password' => Hash::make('secret123')]);

        $this->post('/login', ['identifier' => '999', 'password' => 'secret123'])
            ->assertSessionHasErrors('identifier');
        $this->assertGuest();
    }
}
