<?php

namespace Tests\Feature\Public;

use App\Models\Permission;
use App\Services\Settings\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
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
