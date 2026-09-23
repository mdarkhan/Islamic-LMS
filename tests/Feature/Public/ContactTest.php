<?php

namespace Tests\Feature\Public;

use App\Mail\ContactMessage;
use App\Services\Settings\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContactTest extends TestCase
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
            'subject' => 'সাধারণ জিজ্ঞাসা',
            'message' => 'আপনার কোর্সে ভর্তি হতে চাই, প্রক্রিয়া কী?',
            'website' => '',
        ], $o);
    }

    public function test_a_valid_message_is_emailed_to_the_configured_recipient(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->payload())
            ->assertRedirect(route('contact.show'))
            ->assertSessionHas('success');

        Mail::assertSent(ContactMessage::class, function (ContactMessage $mail) {
            return $mail->hasTo(self::RECIPIENT)
                && $mail->message === 'আপনার কোর্সে ভর্তি হতে চাই, প্রক্রিয়া কী?'
                && $mail->name === 'রহিম উদ্দিন';
        });
    }

    public function test_the_contact_email_setting_overrides_the_ustaz_email_and_environment(): void
    {
        Mail::fake();
        app(SettingService::class)->set(['ustaz_email' => 'panel@example.test']);
        app(SettingService::class)->set(['contact_email' => 'front-desk@example.test']);

        $this->post(route('contact.store'), $this->payload())->assertSessionHas('success');

        Mail::assertSent(ContactMessage::class, fn (ContactMessage $mail) => $mail->hasTo('front-desk@example.test'));
    }

    public function test_it_falls_back_to_ustaz_email_when_contact_email_is_unset(): void
    {
        Mail::fake();
        app(SettingService::class)->set(['ustaz_email' => 'panel@example.test']);

        $this->post(route('contact.store'), $this->payload())->assertSessionHas('success');

        Mail::assertSent(ContactMessage::class, fn (ContactMessage $mail) => $mail->hasTo('panel@example.test'));
    }

    public function test_an_admin_can_set_the_contact_recipient_in_settings(): void
    {
        $this->actingAs($this->makeAdmin())->put(route('admin.settings.general'), [
            'site_title' => 'মাসউদ আলিমী',
            'contact_email' => 'inbox@example.test',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('inbox@example.test', app(SettingService::class)->get('contact_email'));

        // A bad address is rejected.
        $this->actingAs($this->makeAdmin())->put(route('admin.settings.general'), [
            'site_title' => 'মাসউদ আলিমী',
            'contact_email' => 'not-an-email',
        ])->assertSessionHasErrors('contact_email');
    }

    public function test_mobile_and_subject_are_optional(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->payload(['mobile' => null, 'subject' => null]))
            ->assertSessionHas('success');

        Mail::assertSent(ContactMessage::class);
    }

    public function test_validation_rejects_bad_email_and_missing_message(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->payload(['email' => 'not-an-email']))->assertSessionHasErrors('email');
        $this->post(route('contact.store'), $this->payload(['message' => '']))->assertSessionHasErrors('message');
        $this->post(route('contact.store'), $this->payload(['message' => 'short']))->assertSessionHasErrors('message');

        Mail::assertNothingSent();
    }

    public function test_the_honeypot_and_fast_fill_silently_drop_spam(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->payload(['website' => 'http://spam']))
            ->assertSessionHas('success');

        // Submitted impossibly fast: the elapsed time is timed server-side from when
        // show() was reached (session), never trusted from a client-supplied field.
        $this->get(route('contact.show'));
        $this->post(route('contact.store'), $this->payload())
            ->assertSessionHas('success');

        Mail::assertNothingSent();
    }

    public function test_it_never_claims_success_when_it_cannot_send(): void
    {
        Mail::fake();
        config(['mail.ustaz_email' => null]);   // no recipient configured anywhere

        $this->post(route('contact.store'), $this->payload())
            ->assertRedirect()
            ->assertSessionHas('error')
            ->assertSessionMissing('success');

        Mail::assertNothingSent();
    }

    public function test_it_is_rate_limited(): void
    {
        Mail::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('contact.store'), $this->payload())->assertRedirect();
        }
        $this->post(route('contact.store'), $this->payload())->assertStatus(429);
    }

    // ── The absolute rule: no persistence, no logging of the message ──────────────

    public function test_no_submission_table_exists(): void
    {
        foreach (['contact_messages', 'contact_submissions', 'inquiries'] as $table) {
            $this->assertFalse(Schema::hasTable($table), "Table {$table} must not exist.");
        }
    }

    public function test_a_sent_message_is_never_written_to_the_database_or_audit_log(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->payload(['message' => 'একটি গোপনীয় বার্তা যা সংরক্ষিত হওয়া উচিত নয়']));

        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame(
            0,
            \DB::table('audit_logs')->where('before', 'like', '%গোপনীয় বার্তা%')->orWhere('after', 'like', '%গোপনীয় বার্তা%')->count(),
        );
    }
}
