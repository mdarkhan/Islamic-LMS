<?php

namespace App\Console\Commands;

use App\Models\LegacyImportBatch;
use App\Models\User;
use App\Services\Import\CredentialExport;
use App\Services\Import\LegacyImportBatchService;
use App\Services\Import\MigrationReportWriter;
use App\Services\Import\StudentImporter;
use App\Services\Import\StudentSpreadsheetParser;
use Illuminate\Console\Command;

class LegacyStudentsImport extends Command
{
    protected $signature = 'legacy:students:import {file : CSV/XLSX student export}
        {--confirm : Perform the import after preview}
        {--actor= : Admin email for audit attribution}
        {--report= : Markdown reconciliation report path}';

    protected $description = 'Import legacy students with fresh one-time credentials and provenance';

    public function handle(
        StudentSpreadsheetParser $parser,
        StudentImporter $importer,
        LegacyImportBatchService $batches,
        CredentialExport $credentials,
        MigrationReportWriter $reports,
    ): int {
        $path = $this->path((string) $this->argument('file'));
        if ($path === null) {
            $this->error('Source file is not readable.');

            return self::INVALID;
        }
        $rows = $parser->parse($path);
        $preview = $importer->preview($rows);
        if (! $this->option('confirm')) {
            $this->warn('Dry run only. Re-run with --confirm to write students.');
            $this->table(['Status', 'Count'], collect($preview['summary'])->map(fn ($v, $k) => [$k, $v])->values()->all());

            return self::SUCCESS;
        }

        $actor = null;
        if ($email = $this->option('actor')) {
            $actor = User::query()->where('email', $email)->first();
            if ($actor === null || ! $actor->isAdmin()) {
                $this->error('--actor must identify an existing admin account.');

                return self::INVALID;
            }
        }

        $batch = $batches->start(LegacyImportBatch::TYPE_STUDENTS, $path);
        try {
            $result = $importer->import($rows, $actor, $batch);
            $summary = [
                'source_rows' => count($rows),
                'valid_rows' => count($rows) - $preview['summary']['error'],
                'duplicate_source_rolls' => $preview['summary']['duplicate'],
                'existing_db_conflicts' => $preview['summary']['exists'],
                'imported' => $result['imported'],
                'skipped' => $result['skipped'],
                'failed' => $preview['summary']['error'],
                'generated_temporary_credentials' => count($result['credentials']),
                'final_active_user_count' => User::query()->where('status', User::STATUS_ACTIVE)->count(),
            ];
            $batches->complete($batch, $summary);
        } catch (\Throwable $e) {
            $batches->fail($batch, $e::class);
            throw $e;
        }

        if ($result['credentials'] !== []) {
            $token = $credentials->store($result['credentials']);
            $this->warn('Temporary credentials are in private storage for 30 minutes and are deleted after delivery.');
            $this->line('Credential export token: '.$token);
            $this->line('Private path: '.$credentials->path($token));
        }
        $this->table(['Metric', 'Count'], collect($summary)->map(fn ($v, $k) => [$k, $v])->values()->all());
        if ($report = $this->option('report')) {
            $reports->write((string) $report, 'Legacy Student Migration Reconciliation', $summary, $preview['rows']);
        }

        return self::SUCCESS;
    }

    private function path(string $input): ?string
    {
        $path = realpath($input) ?: realpath(base_path($input));

        return $path !== false && is_file($path) && is_readable($path) ? $path : null;
    }
}
