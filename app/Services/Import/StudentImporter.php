<?php

namespace App\Services\Import;

use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\TemporaryPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Validates and imports parsed student rows.
 *
 * The legacy spreadsheet password is treated as a COMPROMISED credential
 * (SECURITY.md L2): it was publicly exposed to every browser, so it is never used
 * as the account password, never saved, never logged. Its presence is only noted in
 * the preview. Each imported account instead receives a fresh cryptographically
 * random temporary password, is forced to change it at first login, and the plaintext
 * is handed to the admin exactly once via a downloadable CSV (never persisted long).
 *
 * Existing rolls are skipped and reported, never silently overwritten.
 */
class StudentImporter
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Dry classification of every row — no writes.
     *
     * @param  array<int, array<string, ?string>>  $rows
     * @return array{rows: array<int, array<string, mixed>>, summary: array<string, int>}
     */
    public function preview(array $rows): array
    {
        $seenRolls = [];
        $existing = $this->existingRolls($rows);
        $out = [];
        $summary = ['import' => 0, 'exists' => 0, 'duplicate' => 0, 'error' => 0];

        foreach ($rows as $raw) {
            $roll = User::normaliseRoll($raw['roll'] ?? null);
            $name = $raw['name'] !== null ? trim($raw['name']) : null;

            $errors = [];
            if ($roll === null || $roll === '') {
                $errors[] = 'রোল নম্বর নেই।';
            }
            if ($name === null || $name === '') {
                $errors[] = 'নাম নেই।';
            }

            $status = 'import';
            if ($errors !== []) {
                $status = 'error';
            } elseif (isset($seenRolls[$roll])) {
                $status = 'duplicate';
                $errors[] = 'এই ফাইলেই রোলটি একাধিকবার আছে (সারি '.$seenRolls[$roll].')।';
            } elseif (in_array($roll, $existing, true)) {
                $status = 'exists';
                $errors[] = 'এই রোলে শিক্ষার্থী ইতিমধ্যে আছে — বাদ দেওয়া হবে।';
            }

            if ($roll !== null && $roll !== '' && ! isset($seenRolls[$roll])) {
                $seenRolls[$roll] = $raw['row'] ?? '?';
            }

            $summary[$status]++;
            $out[] = [
                'row' => $raw['row'] ?? null,
                'roll' => $roll,
                'name' => $name,
                'guardian_name' => $raw['guardian_name'] ?? null,
                'has_password' => ($raw['password'] ?? '') !== '',
                'status' => $status,
                'errors' => $errors,
            ];
        }

        return ['rows' => $out, 'summary' => $summary];
    }

    /**
     * Import the rows classified as importable.
     *
     * @param  array<int, array<string, ?string>>  $rows
     * @return array{imported:int, skipped:int, credentials:array<int, array{roll:?string, name:?string, password:string}>}
     */
    public function import(array $rows, User $actor): array
    {
        $preview = $this->preview($rows);

        $imported = 0;
        $credentials = [];

        DB::transaction(function () use ($preview, &$imported, &$credentials) {
            $studentRoleId = Role::query()->where('name', Role::STUDENT)->value('id');

            foreach ($preview['rows'] as $row) {
                if ($row['status'] !== 'import') {
                    continue;
                }

                // A brand-new temporary password — the legacy spreadsheet password is
                // never read here and never becomes a usable credential.
                $temp = TemporaryPassword::generate();

                $student = User::query()->create([
                    'roll' => $row['roll'],
                    'name' => $row['name'],
                    'guardian_name' => $row['guardian_name'] ?: null,
                    'password' => Hash::make($temp),
                    'status' => User::STATUS_ACTIVE,
                    'force_password_change' => true,
                    'is_legacy_import' => true,
                ]);
                $student->roles()->syncWithoutDetaching([$studentRoleId]);

                $credentials[] = ['roll' => $student->roll, 'name' => $student->name, 'password' => $temp];
                $imported++;
            }
        });

        $skipped = count($rows) - $imported;

        // Summary counts only — never a name, roll, or password.
        $this->audit->log('students.imported', after: [
            'imported' => $imported,
            'skipped' => $skipped,
        ], actor: $actor);

        return ['imported' => $imported, 'skipped' => $skipped, 'credentials' => $credentials];
    }

    /**
     * @param  array<int, array<string, ?string>>  $rows
     * @return array<int, string>
     */
    private function existingRolls(array $rows): array
    {
        $rolls = collect($rows)
            ->map(fn ($r) => User::normaliseRoll($r['roll'] ?? null))
            ->filter()
            ->unique()
            ->all();

        return User::query()->whereIn('roll', $rolls)->pluck('roll')->all();
    }
}
