<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Writes the one-time temporary-credential CSV produced by a student import.
 *
 * The file contains plaintext temporary passwords, so it is treated as sensitive:
 * private storage only (never public/), a random unguessable name, a short TTL, and
 * deletion the moment it is downloaded. It is never logged, never audited, and never
 * committed (the path is gitignored).
 */
class CredentialExport
{
    public const DISK = 'local';
    public const DIR = 'credential-exports';

    /** Files older than this are removed by cleanup() and the scheduled command. */
    public const TTL_MINUTES = 30;

    /**
     * @param  array<int, array{roll:?string, name:?string, password:string}>  $credentials
     * @return string  the download token (opaque, unguessable)
     */
    public function store(array $credentials): string
    {
        $token = Str::uuid()->toString();

        // UTF-8 BOM so Excel renders Bengali names correctly; CSV escaping via fputcsv.
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['Roll', 'Student Name', 'Temporary Password']);
        foreach ($credentials as $row) {
            fputcsv($handle, [$row['roll'] ?? '', csv_safe($row['name'] ?? ''), $row['password']]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        Storage::disk(self::DISK)->put(self::relative($token), $csv);

        return $token;
    }

    /** Absolute path for a token if the file exists, else null. Basename-guarded. */
    public function path(string $token): ?string
    {
        $relative = self::relative($token);

        return Storage::disk(self::DISK)->exists($relative)
            ? Storage::disk(self::DISK)->path($relative)
            : null;
    }

    public function delete(string $token): void
    {
        Storage::disk(self::DISK)->delete(self::relative($token));
    }

    /**
     * Remove exports older than the TTL. Returns how many were deleted.
     */
    public function cleanup(?int $ttlMinutes = null): int
    {
        $ttl = $ttlMinutes ?? self::TTL_MINUTES;
        $disk = Storage::disk(self::DISK);
        $cutoff = now()->subMinutes($ttl)->getTimestamp();
        $deleted = 0;

        foreach ($disk->files(self::DIR) as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    private static function relative(string $token): string
    {
        // Guard against traversal: only the uuid basename is ever used.
        return self::DIR.'/'.basename($token).'.csv';
    }
}
