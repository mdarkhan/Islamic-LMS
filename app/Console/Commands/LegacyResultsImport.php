<?php

namespace App\Console\Commands;

use App\Models\LegacyImportBatch;
use App\Models\User;
use App\Services\Import\LegacyImportBatchService;
use App\Services\Import\LegacyQuizMapping;
use App\Services\Import\LegacyResultImporter;
use App\Services\Import\LegacyResultParser;
use App\Services\Import\MigrationReportWriter;
use Illuminate\Console\Command;

class LegacyResultsImport extends Command
{
    protected $signature = 'legacy:results:import {file : CSV/XLSX quiz_submissions export}
        {--mapping= : Reviewed mapping CSV (required)}
        {--confirm : Perform the import}
        {--report= : Markdown reconciliation report path}';

    protected $description = 'Import approved legacy result rows without answers or point transactions';

    public function handle(
        LegacyResultParser $parser,
        LegacyQuizMapping $quizMapping,
        LegacyResultImporter $importer,
        LegacyImportBatchService $batches,
        MigrationReportWriter $reports,
    ): int {
        $path = $this->path((string) $this->argument('file'));
        $mappingPath = (string) $this->option('mapping');
        if ($path === null || $mappingPath === '' || ! is_file($mappingPath)) {
            $this->error('A readable source file and reviewed --mapping CSV are required.');

            return self::INVALID;
        }
        $rows = $parser->parse($path);
        $mapping = $quizMapping->readCsv($mappingPath);
        $preview = $importer->preview($rows, $mapping);
        $blocking = $preview['summary']['review'] + $preview['summary']['missing_user']
            + $preview['summary']['duplicate'] + $preview['summary']['error'];
        if ($blocking > 0) {
            $this->error("Import blocked: {$blocking} row(s) need correction or an explicit SKIP action.");

            return self::FAILURE;
        }
        if (! $this->option('confirm')) {
            $this->warn('Dry run only. Re-run with --confirm to write historical attempts.');
            $this->table(['Metric', 'Count'], collect($preview['summary'])->map(fn ($v, $k) => [$k, $v])->values()->all());

            return self::SUCCESS;
        }

        $batch = $batches->start(LegacyImportBatch::TYPE_RESULTS, $path);
        try {
            $result = $importer->import($rows, $mapping, $batch);
            $summary = [
                'source_rows' => count($rows),
                'valid_rows' => $preview['summary']['valid_rows'],
                'imported' => $result['imported'],
                'skipped' => $result['skipped'],
                'failed' => $result['failed'],
                'final_active_user_count' => User::query()->where('status', User::STATUS_ACTIVE)->count(),
            ];
            $batches->complete($batch, $summary);
        } catch (\Throwable $e) {
            $batches->fail($batch, $e::class);
            throw $e;
        }

        $this->table(['Metric', 'Count'], collect($summary)->map(fn ($v, $k) => [$k, $v])->values()->all());
        if ($report = $this->option('report')) {
            $reports->write((string) $report, 'Legacy Result Migration Reconciliation', $summary, $preview['rows']);
        }

        return self::SUCCESS;
    }

    private function path(string $input): ?string
    {
        $path = realpath($input) ?: realpath(base_path($input));

        return $path !== false && is_file($path) && is_readable($path) ? $path : null;
    }
}
