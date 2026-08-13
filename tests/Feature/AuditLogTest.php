<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_sensitive_fields_are_redacted_from_snapshots(): void
    {
        $actor = $this->makeAdmin();
        $subject = $this->makeStudent();

        app(AuditLogger::class)->log('student.updated', $subject,
            before: ['name' => 'ক', 'password' => 'hash-value', 'remember_token' => 'tok'],
            after: ['name' => 'খ', 'password' => 'new-hash', 'temporary_password' => 'plain'],
            actor: $actor,
        );

        $log = \App\Models\AuditLog::query()->latest('id')->first();

        $this->assertArrayNotHasKey('password', $log->before);
        $this->assertArrayNotHasKey('remember_token', $log->before);
        $this->assertArrayNotHasKey('password', $log->after);
        $this->assertArrayNotHasKey('temporary_password', $log->after);

        // Non-sensitive fields survive.
        $this->assertSame('ক', $log->before['name']);
        $this->assertSame('খ', $log->after['name']);

        // Nothing sensitive anywhere in the stored row.
        $this->assertStringNotContainsString('plain', json_encode($log->getAttributes()));
        $this->assertStringNotContainsString('hash-value', json_encode($log->getAttributes()));
    }

    public function test_the_audit_index_is_reachable_by_an_admin(): void
    {
        $this->actingAs($this->makeAdmin())->get(route('admin.audit.index'))->assertOk();
    }
}
