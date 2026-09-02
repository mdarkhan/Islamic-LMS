<?php

namespace Tests\Feature\Public;

use App\Models\Permission;
use App\Services\Settings\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ZakatAndSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_zakat_page_loads_for_guests(): void
    {
        $this->get(route('zakat.index'))->assertOk()->assertSee(__('zakat.disclaimer'));
    }

    public function test_posting_values_returns_a_server_computed_result(): void
    {
        app(SettingService::class)->set(['silver_price_per_gram' => '100', 'nisab_basis' => 'silver']);

        // 100,000 net, silver nisab 61,236 → due 2,500.
        $this->post(route('zakat.calculate'), ['cash' => '100000', 'basis' => 'silver'])
            ->assertOk()
            ->assertSee('2500.00');
    }

    public function test_the_calculator_persists_nothing(): void
    {
        $this->post(route('zakat.calculate'), ['cash' => '50000', 'basis' => 'silver'])->assertOk();

        $this->assertFalse(Schema::hasTable('zakat_calculations'));
    }

    public function test_admin_updates_zakat_rates_and_it_is_audited(): void
    {
        $this->actingAs($this->makeAdmin())->put(route('admin.settings.zakat'), [
            'gold_price_per_gram' => '8000', 'silver_price_per_gram' => '100',
            'nisab_basis' => 'gold', 'currency_label' => '৳',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('8000', app(SettingService::class)->get('gold_price_per_gram'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.zakat_updated']);
    }

    public function test_admin_updates_the_about_bio_and_photo_and_it_shows_on_the_homepage(): void
    {
        Storage::fake('public');
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->put(route('admin.settings.about'), [
            'about_bio' => 'একজন শিক্ষক ও দাঈ।',
            'about_photo' => UploadedFile::fake()->image('ustaz.jpg'),
        ])->assertRedirect()->assertSessionHas('success');

        $settings = app(SettingService::class);
        $this->assertSame('একজন শিক্ষক ও দাঈ।', $settings->get('about_bio'));
        Storage::disk('public')->assertExists($settings->get('about_photo'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.about_updated']);

        $this->get(route('home'))->assertOk()->assertSee('একজন শিক্ষক ও দাঈ।');
    }

    public function test_replacing_the_about_photo_deletes_the_old_file(): void
    {
        Storage::fake('public');
        $admin = $this->makeAdmin();
        $old = UploadedFile::fake()->image('old.jpg')->store('about', 'public');
        app(SettingService::class)->set(['about_photo' => $old]);

        $this->actingAs($admin)->put(route('admin.settings.about'), [
            'about_bio' => 'বায়ো', 'about_photo' => UploadedFile::fake()->image('new.jpg'),
        ])->assertRedirect();

        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists(app(SettingService::class)->get('about_photo'));
    }

    public function test_the_about_section_is_hidden_when_no_bio_is_set(): void
    {
        $this->get(route('home'))->assertOk()->assertDontSee(__('public.about_heading'));
    }

    public function test_calendar_offset_is_bounded(): void
    {
        $admin = $this->makeAdmin();
        $base = ['calendar_latitude' => '23.8', 'calendar_longitude' => '90.4', 'calendar_timezone' => 'Asia/Dhaka'];

        $this->actingAs($admin)->put(route('admin.settings.calendar'), $base + ['hijri_offset_days' => '2'])
            ->assertSessionHasErrors('hijri_offset_days');
        $this->actingAs($admin)->put(route('admin.settings.calendar'), $base + ['hijri_offset_days' => '1'])
            ->assertSessionHasNoErrors();
    }

    public function test_settings_require_permission(): void
    {
        $this->actingAs($this->makeStudent())->get(route('admin.settings.edit'))->assertForbidden();

        $admin = $this->makeAdmin();
        $admin->roles()->first()->permissions()->detach(Permission::query()->where('name', 'settings.manage')->pluck('id'));
        $this->actingAs($admin->fresh())->get(route('admin.settings.edit'))->assertForbidden();
    }
}
