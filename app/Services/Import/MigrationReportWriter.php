<?php

namespace App\Services\Import;

class MigrationReportWriter
{
    /** @param array<string, int> $summary @param array<int, array<string, mixed>> $rows */
    public function write(string $path, string $title, array $summary, array $rows): void
    {
        $lines = ["# {$title}", '', 'Generated: '.now()->toIso8601String(), '', '## Reconciliation', ''];
        foreach ($summary as $label => $value) {
            $lines[] = '- '.str_replace('_', ' ', $label).': '.$value;
        }

        $lines[] = '';
        $lines[] = '## Row-level exceptions';
        $lines[] = '';
        $lines[] = '| Row | Roll | Legacy quiz ID | Status | Errors |';
        $lines[] = '|---:|---|---|---|---|';
        foreach ($rows as $row) {
            if (in_array($row['status'] ?? '', ['import', 'exists', 'skip'], true)) {
                continue;
            }
            $lines[] = sprintf(
                '| %s | %s | %s | %s | %s |',
                $this->cell($row['row'] ?? ''),
                $this->cell($row['roll'] ?? ''),
                $this->cell($row['legacy_quiz_id'] ?? ''),
                $this->cell($row['status'] ?? ''),
                $this->cell(implode(' ', $row['errors'] ?? [])),
            );
        }

        if (file_put_contents($path, implode(PHP_EOL, $lines).PHP_EOL) === false) {
            throw new \RuntimeException('Unable to write migration report.');
        }
    }

    private function cell(mixed $value): string
    {
        return str_replace(["\r", "\n", '|'], [' ', ' ', '\\|'], (string) $value);
    }
}
