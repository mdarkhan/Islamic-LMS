<?php

namespace App\Services\Import;

use App\Models\Quiz;

class LegacyQuizMapping
{
    /**
     * Exact title/slug equality only. No fuzzy matching is allowed for historical results.
     *
     * @param  array<int, string>  $legacyIds
     * @return array<int, array<string, mixed>>
     */
    public function build(array $legacyIds): array
    {
        $quizzes = Quiz::query()->get(['id', 'title', 'slug']);
        $rows = [];

        foreach (array_values(array_unique(array_filter(array_map('trim', $legacyIds)))) as $legacyId) {
            $needle = $this->canonical($legacyId);
            $matches = $quizzes->filter(fn (Quiz $quiz) => in_array($needle, [
                $this->canonical($quiz->slug),
                $this->canonical($quiz->title),
            ], true))->values();

            if ($matches->count() === 1) {
                $quiz = $matches->first();
                $rows[] = [
                    'legacy_quiz_id' => $legacyId,
                    'matched_quiz_id' => (int) $quiz->id,
                    'matched_quiz_title' => $quiz->title,
                    'confidence' => '1.00',
                    'action' => 'REVIEW',
                    'notes' => 'Exact title or slug candidate; operator APPROVED or SKIP decision required.',
                ];
            } else {
                $rows[] = [
                    'legacy_quiz_id' => $legacyId,
                    'matched_quiz_id' => null,
                    'matched_quiz_title' => null,
                    'confidence' => '0.00',
                    'action' => 'REVIEW',
                    'notes' => $matches->count() > 1
                        ? 'Multiple exact candidates; choose one manually or SKIP.'
                        : 'No exact title or slug match; manual review required.',
                ];
            }
        }

        return $rows;
    }

    /** @param array<int, array<string, mixed>> $rows */
    public function writeCsv(array $rows, string $path): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Unable to create quiz mapping CSV.');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['legacy_quiz_id', 'matched_quiz_id', 'matched_quiz_title', 'confidence', 'action', 'notes']);
        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['legacy_quiz_id'], $row['matched_quiz_id'], $row['matched_quiz_title'],
                $row['confidence'], $row['action'], $row['notes'],
            ]);
        }
        fclose($handle);
    }

    /** @return array<string, array<string, mixed>> keyed by canonical legacy quiz id */
    public function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Unable to read quiz mapping CSV.');
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            throw new \RuntimeException('Quiz mapping CSV is empty.');
        }
        $header[0] = trim((string) $header[0], "\xEF\xBB\xBF");
        $indices = array_flip($header);
        foreach (['legacy_quiz_id', 'matched_quiz_id', 'action'] as $required) {
            if (! isset($indices[$required])) {
                fclose($handle);
                throw new \RuntimeException("Quiz mapping column is missing: {$required}.");
            }
        }

        $rows = [];
        while (($cells = fgetcsv($handle)) !== false) {
            $legacyId = trim((string) ($cells[$indices['legacy_quiz_id']] ?? ''));
            if ($legacyId === '') {
                continue;
            }
            $rows[$this->canonical($legacyId)] = [
                'legacy_quiz_id' => $legacyId,
                'matched_quiz_id' => ($cells[$indices['matched_quiz_id']] ?? '') !== ''
                    ? (int) $cells[$indices['matched_quiz_id']] : null,
                'action' => strtoupper(trim((string) ($cells[$indices['action']] ?? 'REVIEW'))),
                'confidence' => trim((string) ($cells[$indices['confidence']] ?? '')),
                'notes' => trim((string) ($cells[$indices['notes']] ?? '')),
            ];
        }
        fclose($handle);

        return $rows;
    }

    /** @param array<int, array<string, mixed>> $rows @return array<string, array<string, mixed>> */
    public function keyByLegacyId(array $rows): array
    {
        $keyed = [];
        foreach ($rows as $row) {
            $keyed[$this->canonical((string) $row['legacy_quiz_id'])] = $row;
        }

        return $keyed;
    }

    public function key(string $legacyId): string
    {
        return $this->canonical($legacyId);
    }

    private function canonical(string $value): string
    {
        return mb_strtolower(BengaliText::toLatinDigits(BengaliText::normalise(trim($value))));
    }
}
