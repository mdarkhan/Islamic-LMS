<?php

namespace App\Console\Commands;

use App\Services\Import\LegacyQuizMapping;
use App\Services\Import\LegacyResultImporter;
use App\Services\Import\LegacyResultParser;
use App\Services\Import\MigrationReportWriter;
use Illuminate\Console\Command;

class LegacyResultsPreview extends Command
{
    protected $signature = 'legacy:results:preview {file : CSV/XLSX quiz_submissions export}
        {--mapping= : Reviewed mapping CSV}
        {--write-mapping=LEGACY_QUIZ_MAPPING.csv : New mapping CSV path}
        {--force : Replace an existing generated mapping file}
        {--report= : Markdown preview report path}';

    protected $description = 'Preview legacy results and create exact-only quiz reconciliation';

    public function handle(
        LegacyResultParser $parser,
        LegacyQuizMapping $quizMapping,
        LegacyResultImporter $importer,
        MigrationReportWriter $reports,
    ): int {
        $path = $this->path((string) $this->argument('file'));
        if ($path === null) {
            $this->error('Source file is not readable.');

            return self::INVALID;
        }
        $rows = $parser->parse($path);

        if ($mappingPath = $this->option('mapping')) {
            $mapping = $quizMapping->readCsv((string) $mappingPath);
        } else {
            $mappingRows = $quizMapping->build(collect($rows)->pluck('legacy_quiz_id')->filter()->all());
            $output = (string) $this->option('write-mapping');
            if (is_file($output) && ! $this->option('force')) {
                $this->error("Mapping file already exists: {$output}. Use --mapping to consume it or --force to replace it.");

                return self::INVALID;
            }
            $quizMapping->writeCsv($mappingRows, $output);
            $this->info('Quiz mapping written: '.$output);
            $mapping = $quizMapping->keyByLegacyId($mappingRows);
        }

        $preview = $importer->preview($rows, $mapping);
        $this->table(['Metric', 'Count'], collect($preview['summary'])->map(fn ($v, $k) => [$k, $v])->values()->all());
        $exceptions = collect($preview['rows'])->reject(fn ($row) => in_array($row['status'], ['import', 'exists', 'skip'], true))
            ->map(fn ($row) => [$row['row'], $row['legacy_quiz_id'], $row['roll'], $row['status'], implode(' ', $row['errors'])])->all();
        if ($exceptions !== []) {
            $this->table(['Row', 'Legacy Quiz ID', 'Roll', 'Status', 'Error'], $exceptions);
        }
        if ($report = $this->option('report')) {
            $reports->write((string) $report, 'Legacy Result Migration Preview', $preview['summary'], $preview['rows']);
        }

        return $this->blocking($preview['summary']) > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @param array<string, int> $summary */
    private function blocking(array $summary): int
    {
        return $summary['review'] + $summary['missing_user'] + $summary['duplicate'] + $summary['error'];
    }

    private function path(string $input): ?string
    {
        $path = realpath($input) ?: realpath(base_path($input));

        return $path !== false && is_file($path) && is_readable($path) ? $path : null;
    }
}
