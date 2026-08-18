<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Import\MigrationReportWriter;
use App\Services\Import\StudentImporter;
use App\Services\Import\StudentSpreadsheetParser;
use Illuminate\Console\Command;

class LegacyStudentsPreview extends Command
{
    protected $signature = 'legacy:students:preview {file : CSV/XLSX student export} {--report= : Markdown report path}';

    protected $description = 'Validate and reconcile a legacy student export without writing data';

    public function handle(StudentSpreadsheetParser $parser, StudentImporter $importer, MigrationReportWriter $reports): int
    {
        $path = $this->path((string) $this->argument('file'));
        if ($path === null) {
            $this->error('Source file is not readable.');

            return self::INVALID;
        }

        $preview = $importer->preview($parser->parse($path));
        $summary = $this->summary($preview['summary'], count($preview['rows']));
        $this->table(['Metric', 'Count'], collect($summary)->map(fn ($v, $k) => [$k, $v])->values()->all());
        $this->exceptions($preview['rows']);

        if ($report = $this->option('report')) {
            $reports->write((string) $report, 'Legacy Student Migration Preview', $summary, $preview['rows']);
            $this->info('Report written: '.$report);
        }

        return $preview['summary']['error'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @param array<string, int> $raw @return array<string, int> */
    private function summary(array $raw, int $sourceRows): array
    {
        return [
            'source_rows' => $sourceRows,
            'valid_rows' => $sourceRows - $raw['error'],
            'duplicate_source_rolls' => $raw['duplicate'],
            'existing_db_conflicts' => $raw['exists'],
            'ready_to_import' => $raw['import'],
            'failed_rows' => $raw['error'],
            'final_active_user_count' => User::query()->where('status', User::STATUS_ACTIVE)->count(),
        ];
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function exceptions(array $rows): void
    {
        $exceptions = collect($rows)->reject(fn ($row) => $row['status'] === 'import')
            ->map(fn ($row) => [$row['row'], $row['roll'], $row['status'], implode(' ', $row['errors'])])->all();
        if ($exceptions !== []) {
            $this->table(['Row', 'Roll', 'Status', 'Error'], $exceptions);
        }
    }

    private function path(string $input): ?string
    {
        $path = realpath($input) ?: realpath(base_path($input));

        return $path !== false && is_file($path) && is_readable($path) ? $path : null;
    }
}
