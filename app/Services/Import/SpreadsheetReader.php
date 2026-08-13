<?php

namespace App\Services\Import;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Thin wrapper over openspout for the quiz importer: lists worksheet names and
 * returns rows as 0-indexed cell arrays with their 1-based sheet row number.
 *
 * Date cells are preserved as DateTimeInterface so schedule columns can be parsed
 * robustly; everything else is trimmed to a string.
 */
class SpreadsheetReader
{
    /**
     * Worksheet names. CSV has a single implicit sheet.
     *
     * @return array<int, string>
     */
    public function sheetNames(string $path, string $extension): array
    {
        if (strtolower($extension) === 'csv') {
            return ['CSV'];
        }

        $reader = $this->reader($extension);
        $reader->open($path);

        $names = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $names[] = $sheet->getName();
        }
        $reader->close();

        return $names;
    }

    /**
     * All rows of the chosen sheet (or the first / named one).
     *
     * @return array<int, array{row:int, cells:array<int, mixed>}>
     */
    public function rows(string $path, string $extension, ?string $sheet = null): array
    {
        $reader = $this->reader($extension);
        $reader->open($path);

        $out = [];

        foreach ($reader->getSheetIterator() as $sheetObj) {
            if ($sheet !== null && strtolower($extension) !== 'csv' && $sheetObj->getName() !== $sheet) {
                continue;
            }

            foreach ($sheetObj->getRowIterator() as $index => $row) {
                $out[] = ['row' => $index, 'cells' => $this->cells($row)];
            }

            break;   // one sheet only
        }

        $reader->close();

        return $out;
    }

    private function reader(string $extension): ReaderInterface
    {
        return strtolower($extension) === 'csv' ? new CsvReader : new XlsxReader;
    }

    /** @return array<int, mixed> */
    private function cells(Row $row): array
    {
        return array_map(function ($value) {
            if ($value instanceof \DateTimeInterface) {
                return $value;
            }

            return is_scalar($value) ? trim((string) $value) : '';
        }, $row->toArray());
    }
}
