<?php

namespace App\Console\Commands;

use App\Services\Integrity\IntegrityService;
use Illuminate\Console\Command;

class AppVerifyIntegrity extends Command
{
    protected $signature = 'app:verify-integrity {--json : Emit machine-readable JSON}';

    protected $description = 'Read-only verification of database and migration invariants';

    public function handle(IntegrityService $integrity): int
    {
        $checks = $integrity->inspect();

        if ($this->option('json')) {
            $this->line(json_encode($checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Check', 'Status', 'Count', 'Severity'], collect($checks)->map(
                fn ($check, $name) => [$name, $check['ok'] ? 'PASS' : 'FAIL', $check['count'], strtoupper($check['severity'])]
            )->values()->all());
            foreach ($checks as $name => $check) {
                if (! $check['ok'] && $check['details'] !== []) {
                    $this->warn($name.': '.json_encode($check['details'], JSON_UNESCAPED_UNICODE));
                }
            }
        }

        $errors = collect($checks)->contains(fn ($check) => ! $check['ok'] && $check['severity'] === 'error');

        return $errors ? self::FAILURE : self::SUCCESS;
    }
}
