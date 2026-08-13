<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Records sensitive admin actions. Sensitive fields are stripped from before/after
 * snapshots by an allow-list-style redactor, so a password hash or token can never
 * reach the audit table (SECURITY.md §2.9).
 */
class AuditLogger
{
    /**
     * Keys removed from any before/after payload before it is stored.
     */
    private const REDACT = [
        'password', 'password_confirmation', 'remember_token',
        'temporary_password', 'new_password', 'current_password',
    ];

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function log(
        string $action,
        ?Model $subject = null,
        ?array $before = null,
        ?array $after = null,
        ?User $actor = null,
    ): AuditLog {
        $request = request();

        return AuditLog::query()->create([
            'user_id' => ($actor ?? Auth::user())?->getKey(),
            'action' => $action,
            'auditable_type' => $subject ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'before' => $before !== null ? $this->redact($before) : null,
            'after' => $after !== null ? $this->redact($after) : null,
            'ip_address' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 500) ?: null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function redact(array $data): array
    {
        foreach (array_keys($data) as $key) {
            if (in_array($key, self::REDACT, true)) {
                unset($data[$key]);
            }
        }

        return $data;
    }
}
