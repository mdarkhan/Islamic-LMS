<?php

namespace Tests\Feature\Public;

use App\Services\Settings\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_footer_shows_no_social_links_when_none_are_configured(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('WhatsApp')
            ->assertDontSeeText(__('public.social_heading'));
    }

    public function test_the_footer_shows_only_the_configured_channels(): void
    {
        app(SettingService::class)->set([
            'whatsapp_url' => 'https://wa.me/8801700000000',
            'facebook_page_url' => 'https://facebook.com/example',
            // facebook_group_url and telegram_url left unset.
        ]);

        $response = $this->get(route('home'))->assertOk();

        $response->assertSee('https://wa.me/8801700000000', false);
        $response->assertSee('https://facebook.com/example', false);
        $response->assertDontSeeText(__('public.social_facebook_group'));
        $response->assertDontSeeText(__('public.social_telegram_group'));
    }

    public function test_an_admin_can_set_the_social_links_in_settings(): void
    {
        $this->actingAs($this->makeAdmin())->put(route('admin.settings.general'), [
            'site_title' => 'মাসউদ আলিমী',
            'whatsapp_url' => 'https://wa.me/8801700000000',
            'facebook_page_url' => 'https://facebook.com/example',
            'facebook_group_url' => 'https://facebook.com/groups/example',
            'telegram_url' => 'https://t.me/example',
        ])->assertRedirect()->assertSessionHas('success');

        $settings = app(SettingService::class);
        $this->assertSame('https://wa.me/8801700000000', $settings->get('whatsapp_url'));
        $this->assertSame('https://facebook.com/example', $settings->get('facebook_page_url'));
        $this->assertSame('https://facebook.com/groups/example', $settings->get('facebook_group_url'));

        // A bad URL is rejected.
        $this->actingAs($this->makeAdmin())->put(route('admin.settings.general'), [
            'site_title' => 'মাসউদ আলিমী',
            'whatsapp_url' => 'not-a-url',
        ])->assertSessionHasErrors('whatsapp_url');
    }

    public function test_social_links_do_not_appear_on_the_student_dashboard(): void
    {
        app(SettingService::class)->set(['whatsapp_url' => 'https://wa.me/8801700000000']);

        $this->actingAs($this->makeStudent())->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee('https://wa.me/8801700000000', false);
    }
}
