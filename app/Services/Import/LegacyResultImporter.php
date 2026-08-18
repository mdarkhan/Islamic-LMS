<?php

namespace App\Services\Import;

use App\Models\LegacyImportBatch;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class LegacyResultImporter
{
    public function __construct(private readonly LegacyQuizMapping $quizMapping) {}

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, array<string, mixed>>  $mapping
     * @return array{rows:array<int,array<string,mixed>>,summary:array<string,int>}
     */
    public function preview(array $rows, array $mapping): array
    {
        $rolls = collect($rows)->pluck('roll')->filter()->unique()->values();
        $users = User::query()->whereIn('roll', $rolls)->get(['id', 'roll'])->keyBy('roll');
        $quizIds = collect($mapping)->pluck('matched_quiz_id')->filter()->map(fn ($id) => (int) $id)->unique();
        $quizzes = Quiz::query()->whereIn('id', $quizIds)->get(['id', 'title'])->keyBy('id');
        $sourceKeys = collect($rows)->pluck('source_key')->filter()->unique();
        $existingKeys = QuizAttempt::query()->whereIn('legacy_source_key', $sourceKeys)
            ->pluck('legacy_source_key')->flip();

        $summary = [
            'source_rows' => count($rows),
            'valid_rows' => 0,
            'import' => 0,
            'review' => 0,
            'skip' => 0,
            'missing_user' => 0,
            'duplicate' => 0,
            'exists' => 0,
            'error' => 0,
        ];
        $seen = [];
        $out = [];

        foreach ($rows as $row) {
            $errors = $this->validate($row);
            $dataValid = $errors === [];
            $status = 'import';
            $mappingRow = $mapping[$this->quizMapping->key((string) ($row['legacy_quiz_id'] ?? ''))] ?? null;
            $action = strtoupper((string) ($mappingRow['action'] ?? 'REVIEW'));
            $quizId = isset($mappingRow['matched_quiz_id']) ? (int) $mappingRow['matched_quiz_id'] : null;

            if ($errors !== []) {
                $status = 'error';
            } elseif (isset($seen[$row['source_key']])) {
                $status = 'duplicate';
                $errors[] = 'Duplicate source fingerprint; first seen on row '.$seen[$row['source_key']].'.';
            } elseif ($existingKeys->has($row['source_key'])) {
                $status = 'exists';
                $errors[] = 'This source record was imported previously.';
            } elseif ($action === 'SKIP') {
                $status = 'skip';
            } elseif ($action !== 'APPROVED' || $quizId === null || ! $quizzes->has($quizId)) {
                $status = 'review';
                $errors[] = 'Quiz mapping is not explicitly approved.';
            } elseif (! $users->has($row['roll'])) {
                $status = 'missing_user';
                $errors[] = 'No user has this canonical roll number.';
            }

            if ($dataValid) {
                $summary['valid_rows']++;
            }
            $summary[$status]++;
            $seen[$row['source_key']] ??= $row['row'];

            $out[] = array_merge($row, [
                'status' => $status,
                'errors' => $errors,
                'user_id' => $users->get($row['roll'])?->id,
                'matched_quiz_id' => $quizId,
                'matched_quiz_title' => $quizId ? $quizzes->get($quizId)?->title : null,
                'mapping_action' => $action,
            ]);
        }

        return ['rows' => $out, 'summary' => $summary];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, array<string, mixed>>  $mapping
     * @return array{imported:int,skipped:int,failed:int,preview:array<string,mixed>}
     */
    public function import(array $rows, array $mapping, LegacyImportBatch $batch, ?string $timezone = null): array
    {
        $preview = $this->preview($rows, $mapping);
        $timezone ??= (string) config('app.timezone', 'Asia/Dhaka');
        $imported = 0;

        DB::transaction(function () use ($preview, $batch, $timezone, &$imported): void {
            foreach ($preview['rows'] as $row) {
                if ($row['status'] !== 'import') {
                    continue;
                }

                $submittedAt = $this->timestamp((string) $row['created_at'], $timezone);
                if ($submittedAt === null) {
                    throw new \LogicException('A validated timestamp could not be parsed.');
                }
                $timeTaken = (int) $row['time_taken'];
                $attemptNo = (int) QuizAttempt::query()
                    ->where('quiz_id', $row['matched_quiz_id'])
                    ->where('user_id', $row['user_id'])
                    ->where('kind', QuizAttempt::KIND_OFFICIAL)
                    ->max('attempt_no') + 1;

                $attempt = new QuizAttempt([
                    'quiz_id' => $row['matched_quiz_id'],
                    'user_id' => $row['user_id'],
                    'kind' => QuizAttempt::KIND_OFFICIAL,
                    'attempt_no' => $attemptNo,
                    'status' => QuizAttempt::STATUS_SUBMITTED,
                    'started_at' => $submittedAt->subSeconds($timeTaken),
                    'expires_at' => null,
                    'counts_toward_cumulative' => true,
                    'is_legacy_import' => true,
                    'answer_details_available' => false,
                    'legacy_import_batch_id' => $batch->id,
                    'legacy_source_key' => $row['source_key'],
                    'legacy_quiz_id' => $row['legacy_quiz_id'],
                    'point_transaction_id' => null,
                    'total_marks_snapshot' => $row['total_questions'],
                ]);
                $attempt->forceFill([
                    'submitted_at' => $submittedAt,
                    'time_taken_seconds' => $timeTaken,
                    'calculated_score' => $row['score'],
                    'manual_adjustment' => 0,
                    'final_score' => $row['score'],
                    'submission_seq' => null,
                    'created_at' => $submittedAt,
                    'updated_at' => $submittedAt,
                ])->save();

                $imported++;
            }
        });

        return [
            'imported' => $imported,
            'skipped' => count($rows) - $imported,
            'failed' => $preview['summary']['error'] + $preview['summary']['review']
                + $preview['summary']['missing_user'] + $preview['summary']['duplicate'],
            'preview' => $preview,
        ];
    }

    /** @param array<string, mixed> $row @return array<int, string> */
    private function validate(array $row): array
    {
        $errors = [];
        if (trim((string) ($row['legacy_quiz_id'] ?? '')) === '') {
            $errors[] = 'quiz_id is missing.';
        }
        if (trim((string) ($row['roll'] ?? '')) === '') {
            $errors[] = 'Roll is missing.';
        }
        if (! is_int($row['score'] ?? null) || $row['score'] < 0) {
            $errors[] = 'Score must be a non-negative integer.';
        }
        if (! is_int($row['total_questions'] ?? null) || $row['total_questions'] <= 0) {
            $errors[] = 'Total questions must be a positive integer.';
        }
        if (! is_int($row['time_taken'] ?? null) || $row['time_taken'] < 0) {
            $errors[] = 'Time taken must be a non-negative integer.';
        }
        if ($this->timestamp(
            (string) ($row['created_at'] ?? ''),
            (string) config('app.timezone', 'Asia/Dhaka'),
        ) === null) {
            $errors[] = 'created_at is missing or invalid.';
        }

        return $errors;
    }

    private function timestamp(string $value, string $timezone): ?CarbonImmutable
    {
        $value = trim($value);
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
            $parsed = \DateTimeImmutable::createFromFormat('!'.$format, $value, new \DateTimeZone($timezone));
            $errors = \DateTimeImmutable::getLastErrors();
            if ($parsed !== false
                && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
                && $parsed->format($format) === $value) {
                return CarbonImmutable::instance($parsed);
            }
        }

        return null;
    }
}
