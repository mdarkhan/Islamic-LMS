<?php

namespace App\Services\Import;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Reads a CSV or XLSX into associative rows keyed by our four columns, mapping the
 * legacy header names. Header matching is case-insensitive and tolerant of the
 * legacy Bengali/English variants.
 *
 * This class deals only in strings — it never hashes or persists. The plaintext
 * password lives only in the returned array until the importer hashes it.
 */
class StudentSpreadsheetParser
{
    /** Legacy → canonical header map (lower-cased, trimmed keys). */
    private const HEADERS = [
        'roll' => 'roll',
        'roll no' => 'roll',
        'roll no.' => 'roll',
        'roll number' => 'roll',
        'রোল' => 'roll',
        'রোল নম্বর' => 'roll',
        'name' => 'name',
        'নাম' => 'name',
        "father's/husband's name" => 'guardian_name',
        'guardian' => 'guardian_name',
        "father's name" => 'guardian_name',
        'পিতা/স্বামীর নাম' => 'guardian_name',
        'password' => 'password',
        'পাসওয়ার্ড' => 'password',
    ];

    /**
     * @return array<int, array{row:int, roll:?string, name:?string, guardian_name:?string, password:?string}>
     */
    public function parse(string $path, ?string $extension = null): array
    {
        $reader = $this->readerFor($extension ?? pathinfo($path, PATHINFO_EXTENSION));
        $reader->open($path);

        $map = null;
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $index => $row) {
                $cells = $this->stringCells($row);

                if ($map === null) {
                    $map = $this->mapHeader($cells);

                    continue;
                }

                if (trim(implode('', $cells)) === '') {
                    continue;   // blank row
                }

                $rows[] = [
                    'row' => $index,
                    'roll' => $this->cell($cells, $map, 'roll'),
                    'name' => $this->cell($cells, $map, 'name'),
                    'guardian_name' => $this->cell($cells, $map, 'guardian_name'),
                    'password' => $this->cell($cells, $map, 'password'),
                ];
            }

            break;   // first sheet only
        }

        $reader->close();

        if ($map === null || ! isset($map['roll'], $map['name'])) {
            throw new \RuntimeException('ফাইলে প্রয়োজনীয় কলাম (Roll, Name) পাওয়া যায়নি।');
        }

        return $rows;
    }

    private function readerFor(string $extension): ReaderInterface
    {
        return strtolower($extension) === 'csv' ? new CsvReader : new XlsxReader;
    }

    /** @return array<int, string> */
    private function stringCells(Row $row): array
    {
        return array_map(function ($value): string {
            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d');
            }

            return is_scalar($value) ? trim((string) $value) : '';
        }, $row->toArray());
    }

    /**
     * @param  array<int, string>  $headerCells
     * @return array<string, int>
     */
    private function mapHeader(array $headerCells): array
    {
        $map = [];
        foreach ($headerCells as $i => $label) {
            $key = mb_strtolower(trim($label));
            if (isset(self::HEADERS[$key])) {
                $map[self::HEADERS[$key]] = $i;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, string>  $cells
     * @param  array<string, int>  $map
     */
    private function cell(array $cells, array $map, string $field): ?string
    {
        if (! isset($map[$field])) {
            return null;
        }

        $value = $cells[$map[$field]] ?? '';

        return $value === '' ? null : $value;
    }
}
