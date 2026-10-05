<?php

namespace Tests\Feature\Auth;

use App\Mail\PasswordResetLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function studentWithEmail(string $email = 'kid@example.com'): User
    {
        $s = $this->makeStudent();
        $s->forceFill(['email' => $email])->save();

        return $s->fresh();
    }

    /** Request a link and return the token + url from the mail that would have been sent. */
    private function requestLink(string $identifier): ?string
    {
        Mail::fake();
        $this->post(route('password.email'), ['identifier' => $identifier])->assertSessionHas('status');

        $url = null;
        Mail::assertSent(PasswordResetLink::class, function (PasswordResetLink $m) use (&$url) {
            $url = $m->url;

            return true;
        });

        return $url;
    }

    public function test_a_student_with_an_email_gets_a_link_by_roll_and_can_reset(): void
    {
        $s = $this->studentWithEmail();
        $oldHash = $s->password;

        $url = $this->requestLink($s->roll);
        $this->assertStringContainsString('password/reset/', $url);

        $this->get($url)->assertOk();

        parse_str(parse_url($url, PHP_URL_QUERY), $q);
        $token = basename(parse_url($url, PHP_URL_PATH));

        $this->post(route('password.update'), [
            'token' => $token, 'email' => $q['email'], 'password' => 'newsecret1', 'password_confirmation' => 'newsecret1',
        ])->assertRedirect(route('login'));

        $s->refresh();
        $this->assertNotSame($oldHash, $s->password);
        $this->assertTrue(Hash::check('newsecret1', $s->password));
        $this->assertFalse($s->force_password_change);
        $this->assertDatabaseHas('audit_logs', ['action' => 'password.reset_by_email']);
    }

    public function test_the_link_is_single_use(): void
    {
        $s = $this->studentWithEmail();
        $url = $this->requestLink($s->email);
        $token = basename(parse_url($url, PHP_URL_PATH));
        $payload = ['token' => $token, 'email' => $s->email, 'password' => 'newsecret1', 'password_confirmation' => 'newsecret1'];

        $this->post(route('password.update'), $payload)->assertRedirect(route('login'));
        $this->post(route('password.update'), array_merge($payload, ['password' => 'another22', 'password_confirmation' => 'another22']))
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('newsecret1', $s->fresh()->password));
    }

    public function test_a_wrong_token_or_email_changes_nothing(): void
    {
        $s = $this->studentWithEmail();
        $this->requestLink($s->email);

        $this->post(route('password.update'), ['token' => 'nope', 'email' => $s->email, 'password' => 'newsecret1', 'password_confirmation' => 'newsecret1'])
            ->assertSessionHasErrors('email');

        $this->assertFalse(Hash::check('newsecret1', $s->fresh()->password));
    }

    public function test_the_response_is_identical_and_nothing_is_sent_for_unknown_no_email_or_suspended_accounts(): void
    {
        Mail::fake();
        $noEmail = $this->makeStudent();
        $noEmail->forceFill(['email' => null])->save();
        $suspended = $this->studentWithEmail('sus@example.com');
        $suspended->forceFill(['status' => User::STATUS_SUSPENDED])->save();

        $messages = [];
        foreach (['99999', $noEmail->roll, 'sus@example.com', 'ghost@example.com'] as $id) {
            $r = $this->post(route('password.email'), ['identifier' => $id]);
            $r->assertSessionHas('status')->assertSessionHasNoErrors();
            $messages[] = session('status');
        }

        $this->assertCount(1, array_unique($messages));
        Mail::assertNothingSent();
    }

    public function test_a_link_is_never_sent_to_an_address_supplied_in_the_form(): void
    {
        $s = $this->studentWithEmail('real@example.com');
        Mail::fake();

        $this->post(route('password.email'), ['identifier' => $s->roll, 'email' => 'attacker@example.com']);

        Mail::assertSent(PasswordResetLink::class, fn ($m) => $m->hasTo('real@example.com') && ! $m->hasTo('attacker@example.com'));
    }

    public function test_reset_drops_existing_sessions_and_remember_tokens(): void
    {
        $s = $this->studentWithEmail();
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $s->id, 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);
        $s->forceFill(['remember_token' => 'old-token'])->save();

        $token = basename(parse_url($this->requestLink($s->email), PHP_URL_PATH));
        $this->post(route('password.update'), ['token' => $token, 'email' => $s->email, 'password' => 'newsecret1', 'password_confirmation' => 'newsecret1']);

        $this->assertDatabaseMissing('sessions', ['user_id' => $s->id]);
        $this->assertNotSame('old-token', $s->fresh()->remember_token);
    }

    public function test_staff_must_meet_the_stronger_password_rule(): void
    {
        $admin = $this->makeAdmin();
        $admin->forceFill(['email' => 'boss@example.com'])->save();
        $token = basename(parse_url($this->requestLink('boss@example.com'), PHP_URL_PATH));

        $this->post(route('password.update'), ['token' => $token, 'email' => 'boss@example.com', 'password' => 'short123', 'password_confirmation' => 'short123'])
            ->assertSessionHasErrors('password');
        $this->post(route('password.update'), ['token' => $token, 'email' => 'boss@example.com', 'password' => 'longenough123', 'password_confirmation' => 'longenough123'])
            ->assertRedirect(route('login'));
    }

    public function test_the_request_form_is_throttled_per_ip(): void
    {
        Mail::fake();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);   // isolate the controller's own hourly cap
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('password.email'), ['identifier' => 'x'.$i])->assertSessionHas('status');
        }

        $this->post(route('password.email'), ['identifier' => 'x-final'])->assertSessionHasErrors('identifier');
    }

    public function test_the_login_page_links_to_the_reset_form(): void
    {
        $this->get(route('login'))->assertSee(route('password.request'), false);
        $this->get(route('password.request'))->assertOk();
    }
}
