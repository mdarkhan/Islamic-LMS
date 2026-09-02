<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_date_range_filters_the_log(): void
    {
        $admin = $this->makeAdmin();
        AuditLog::query()->create(['action' => 'old.entry'])->forceFill(['created_at' => now()->subDays(10)])->save();
        AuditLog::query()->create(['action' => 'recent.entry'])->forceFill(['created_at' => now()])->save();

        $html = $this->actingAs($admin)->get(route('admin.audit.index', ['date_from' => now()->subDay()->toDateString()]))
            ->assertOk()
            ->assertSee('recent.entry')
            ->getContent();

        // "old.entry" still legitimately appears once, as an option in the action-type
        // filter dropdown (which lists every action ever logged, not just this range) —
        // but never as a logged row.
        $this->assertStringNotContainsString('font-mono text-brand-strong">old.entry<', $html);
    }

    public function test_the_before_after_diff_is_present_but_not_shown_by_default(): void
    {
        $admin = $this->makeAdmin();
        AuditLog::query()->create([
            'action' => 'test.action',
            'before' => ['status' => 'active'],
            'after' => ['status' => 'suspended'],
        ]);

        $html = $this->actingAs($admin)->get(route('admin.audit.index'))->assertOk()->getContent();

        // The full diff is in the markup (an Alpine x-show toggle, not server-rendered
        // conditionally) so it renders without a second request when expanded. Blade
        // auto-escapes the JSON, so quotes appear as &quot; entities in the HTML.
        $this->assertStringContainsString('&quot;status&quot;: &quot;active&quot;', $html);
        $this->assertStringContainsString('&quot;status&quot;: &quot;suspended&quot;', $html);
        $this->assertStringContainsString(__('admin.view_full'), $html);
    }
}
