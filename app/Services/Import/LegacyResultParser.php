<?php

namespace App\Services\Import;

class LegacyResultParser
{
    private const HEADERS = [
        'quiz_id' => 'legacy_quiz_id',
        'quiz id' => 'legacy_quiz_id',
        'exam' => 'legacy_quiz_id',
        'exam name' => 'legacy_quiz_id',
        'roll' => 'roll',
        'roll no' => 'roll',
        'roll no.' => 'roll',
        'name' => 'name',
        'guardian' => 'guardian_name',
        "father's/husband's name" => 'guardian_name',
        'score' => 'score',
        'total_questions' => 'total_questions',
        'total questions' => 'total_questions',
        'total' => 'total_questions',
        'time_taken' => 'time_taken',
        'time taken' => 'time_taken',
        'time_taken_seconds' => 'time_taken',
        'created_at' => 'created_at',
        'created at' => 'created_at',
        'submitted_at' => 'created_at',
    ];

    public function __construct(private readonly SpreadsheetReader $reader) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $path, ?string $extension = null): array
    {
        $extension ??= pathinfo($path, PATHINFO_EXTENSION);
        $rawRows = $this->reader->rows($path, $extension);

        if ($rawRows === []) {
            throw new \RuntimeException('The legacy result export is empty.');
        }

        $map = $this->mapHeader($rawRows[0]['cells']);
        foreach (['legacy_quiz_id', 'roll', 'score', 'total_questions', 'time_taken', 'created_at'] as $required) {
            if (! isset($map[$required])) {
                throw new \RuntimeException("Required legacy result column is missing: {$required}.");
            }
        }

        $rows = [];
        foreach (array_slice($rawRows, 1) as $entry) {
            if (! $this->hasContent($entry['cells'])) {
                continue;
            }

            $row = [
                'row' => $entry['row'],
                'legacy_quiz_id' => $this->cell($entry['cells'], $map, 'legacy_quiz_id'),
                'roll' => UserRoll::normalise($this->cell($entry['cells'], $map, 'roll')),
                'name' => $this->cell($entry['cells'], $map, 'name'),
                'guardian_name' => $this->cell($entry['cells'], $map, 'guardian_name'),
                'score' => $this->integer($this->cell($entry['cells'], $map, 'score')),
                'total_questions' => $this->integer($this->cell($entry['cells'], $map, 'total_questions')),
                'time_taken' => $this->integer($this->cell($entry['cells'], $map, 'time_taken')),
                'created_at' => $this->cell($entry['cells'], $map, 'created_at'),
            ];
            $row['source_key'] = $this->sourceKey($row);
            $rows[] = $row;
        }

        return $rows;
    }

    /** @param array<string, mixed> $row */
    public function sourceKey(array $row): string
    {
        $identity = [
            'quiz' => $this->canonical((string) ($row['legacy_quiz_id'] ?? '')),
            'roll' => UserRoll::normalise($row['roll'] ?? null),
            'score' => $row['score'] ?? null,
            'total' => $row['total_questions'] ?? null,
            'time' => $row['time_taken'] ?? null,
            'created_at' => trim((string) ($row['created_at'] ?? '')),
        ];

        return hash('sha256', json_encode($identity, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /** @param array<int, mixed> $headers @return array<string, int> */
    private function mapHeader(array $headers): array
    {
        $map = [];
        foreach ($headers as $index => $header) {
            $key = mb_strtolower(trim((string) $header, "\xEF\xBB\xBF \t\r\n"));
            if (isset(self::HEADERS[$key])) {
                $map[self::HEADERS[$key]] = $index;
            }
        }

        return $map;
    }

    /** @param array<int, mixed> $cells @param array<string, int> $map */
    private function cell(array $cells, array $map, string $field): ?string
    {
        if (! isset($map[$field])) {
            return null;
        }

        $value = $cells[$map[$field]] ?? null;
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : BengaliText::normalise($value);
    }

    private function integer(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $value = BengaliText::toLatinDigits($value);

        return preg_match('/^-?\d+$/', $value) ? (int) $value : null;
    }

    /** @param array<int, mixed> $cells */
    private function hasContent(array $cells): bool
    {
        return collect($cells)->contains(fn ($value) => $value instanceof \DateTimeInterface
            || (is_scalar($value) && trim((string) $value) !== ''));
    }

    private function canonical(string $value): string
    {
        return mb_strtolower(BengaliText::toLatinDigits($value));
    }
}
