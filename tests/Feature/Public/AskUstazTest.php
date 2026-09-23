<?php

namespace Tests\Feature\Public;

use App\Mail\AskUstazQuestion;
use App\Services\Settings\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AskUstazTest extends TestCase
{
    use RefreshDatabase;

    private const RECIPIENT = 'ustaz@example.test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.ustaz_email' => self::RECIPIENT]);
    }

    private function payload(array $o = []): array
    {
        return array_merge([
            'name' => 'রহিম উদ্দিন',
            'email' => 'rahim@example.test',
            'mobile' => '01700000000',
            'subject' => 'যাকাত',
            'question' => 'ব্যবসার পণ্যের উপর যাকাত কীভাবে হিসাব করব?',
            'website' => '',
        ], $o);
    }

    public function test_a_valid_question_is_emailed_to_the_configured_recipient(): void
    {
        Mail::fake();

        $this->post(route('ask-ustaz.store'), $this->payload())
            ->assertRedirect(route('ask-ustaz.show'))
            ->assertSessionHas('success');

        Mail::assertSent(AskUstazQuestion::class, function (AskUstazQuestion $mail) {
            return $mail->hasTo(self::RECIPIENT)
                && $mail->question === 'ব্যবসার পণ্যের উপর যাকাত কীভাবে হিসাব করব?'
                && $mail->name === 'রহিম উদ্দিন';
        });
    }

    public function test_the_admin_configured_recipient_overrides_the_environment(): void
    {
        Mail::fake();
        // setUp() configured the env fallback to self::RECIPIENT; the admin sets a different one.
        app(SettingService::class)->set(['ustaz_email' => 'panel@example.test']);

        $this->post(route('ask-ustaz.store'), $this->payload())->assertSessionHas('success');

        Mail::assertSent(AskUstazQuestion::class, fn (AskUstazQuestion $mail) => $mail->hasTo('panel@example.test'));
    }

    public function test_an_admin_can_set_the_recipient_in_settings(): void
    {
        $this->actingAs($this->makeAdmin())->put(route('admin.settings.general'), [
            'site_title' => 'মাসউদ আলিমী',
            'ustaz_email' => 'inbox@example.test',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('inbox@example.test', app(SettingService::class)->get('ustaz_email'));

        // A bad address is rejected.
        $this->actingAs($this->makeAdmin())->put(route('admin.settings.general'), [
            'site_title' => 'মাসউদ আলিমী',
            'ustaz_email' => 'not-an-email',
        ])->assertSessionHasErrors('ustaz_email');
    }

    public function test_mobile_and_subject_are_optional(): void
    {
        Mail::fake();

        $this->post(route('ask-ustaz.store'), $this->payload(['mobile' => null, 'subject' => null]))
            ->assertSessionHas('success');

        Mail::assertSent(AskUstazQuestion::class);
    }

    public function test_validation_rejects_bad_email_and_missing_question(): void
    {
        Mail::fake();

        $this->post(route('ask-ustaz.store'), $this->payload(['email' => 'not-an-email']))->assertSessionHasErrors('email');
        $this->post(route('ask-ustaz.store'), $this->payload(['question' => '']))->assertSessionHasErrors('question');
        $this->post(route('ask-ustaz.store'), $this->payload(['question' => 'short']))->assertSessionHasErrors('question');

        Mail::assertNothingSent();
    }

    public function test_the_honeypot_and_fast_fill_silently_drop_spam(): void
    {
        Mail::fake();

        // Honeypot filled.
        $this->post(route('ask-ustaz.store'), $this->payload(['website' => 'http://spam']))
            ->assertSessionHas('success');   // looks fine to the bot…

        // Submitted impossibly fast: the elapsed time is timed server-side from when
        // show() was reached (session), never trusted from a client-supplied field.
        $this->get(route('ask-ustaz.show'));
        $this->post(route('ask-ustaz.store'), $this->payload())
            ->assertSessionHas('success');

        Mail::assertNothingSent();   // …but nothing was actually sent.
    }

    public function test_it_never_claims_success_when_it_cannot_send(): void
    {
        Mail::fake();
        config(['mail.ustaz_email' => null]);   // no recipient configured

        $this->post(route('ask-ustaz.store'), $this->payload())
            ->assertRedirect()
            ->assertSessionHas('error')
            ->assertSessionMissing('success');

        Mail::assertNothingSent();
    }

    public function test_it_is_rate_limited(): void
    {
        Mail::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('ask-ustaz.store'), $this->payload())->assertRedirect();
        }
        $this->post(route('ask-ustaz.store'), $this->payload())->assertStatus(429);
    }

    // ── The absolute rule: no persistence, no logging of the question ─────────────

    public function test_no_submission_table_exists(): void
    {
        foreach (['ustaz_questions', 'inquiries', 'contact_messages', 'ask_ustaz_submissions'] as $table) {
            $this->assertFalse(Schema::hasTable($table), "Table {$table} must not exist.");
        }
    }

    public function test_a_sent_question_is_never_written_to_the_database_or_audit_log(): void
    {
        Mail::fake();

        $this->post(route('ask-ustaz.store'), $this->payload(['question' => 'একটি গোপনীয় প্রশ্ন যা সংরক্ষিত হওয়া উচিত নয়']));

        // No audit rows at all, and certainly none carrying the question text.
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame(
            0,
            \DB::table('audit_logs')->where('before', 'like', '%গোপনীয় প্রশ্ন%')->orWhere('after', 'like', '%গোপনীয় প্রশ্ন%')->count(),
        );
    }
}
