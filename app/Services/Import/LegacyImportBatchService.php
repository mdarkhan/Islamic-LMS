<?php

namespace App\Services\Import;

use App\Models\LegacyImportBatch;
use App\Models\Quiz;

class LegacyImportBatchService
{
    public function start(string $type, string $sourcePath): LegacyImportBatch
    {
        if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
            throw new \InvalidArgumentException('The legacy source file is not readable.');
        }

        $hash = hash_file('sha256', $sourcePath);

        return LegacyImportBatch::query()->updateOrCreate(
            ['type' => $type, 'source_sha256' => $hash],
            [
                'source_name' => basename($sourcePath),
                'status' => LegacyImportBatch::STATUS_RUNNING,
                'completed_at' => null,
            ],
        );
    }

    /** @param array<string, int|array<string, int>> $summary */
    public function complete(LegacyImportBatch $batch, array $summary): LegacyImportBatch
    {
        $batchImported = match ($batch->type) {
            LegacyImportBatch::TYPE_STUDENTS => $batch->users()->count(),
            LegacyImportBatch::TYPE_RESULTS => $batch->attempts()->count(),
            LegacyImportBatch::TYPE_QUIZZES => Quiz::query()->where('legacy_import_batch_id', $batch->id)->count(),
            default => (int) ($summary['imported'] ?? 0),
        };
        $summary['batch_imported_total'] = $batchImported;

        $batch->update([
            'status' => LegacyImportBatch::STATUS_COMPLETED,
            'source_rows' => (int) ($summary['source_rows'] ?? 0),
            'valid_rows' => (int) ($summary['valid_rows'] ?? 0),
            'imported_rows' => $batchImported,
            'skipped_rows' => (int) ($summary['skipped'] ?? 0),
            'failed_rows' => (int) ($summary['failed'] ?? 0),
            'summary' => $summary,
            'completed_at' => now(),
        ]);

        return $batch->refresh();
    }

    public function fail(LegacyImportBatch $batch, string $reason): void
    {
        $batch->update([
            'status' => LegacyImportBatch::STATUS_FAILED,
            // A short class/category only. Callers must never pass row data or secrets.
            'summary' => ['failure' => mb_substr($reason, 0, 200)],
            'completed_at' => now(),
        ]);
    }
}
