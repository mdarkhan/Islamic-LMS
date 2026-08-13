<?php

namespace App\Services\Import;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The single hardened policy for uploaded import files (students and quizzes).
 *
 * Import files may contain sensitive data (legacy passwords, quiz answer keys), so
 * they live only in private storage under a randomised name, are never publicly
 * reachable, are deleted the moment an import commits, and are swept by a TTL if a
 * preview is abandoned. Cleanup is opportunistic on each preview plus a scheduled
 * command — no long-running worker is required.
 */
class ImportFileStore
{
    public const DISK = 'local';
    public const BASE = 'imports';
    public const TTL_MINUTES = 60;

    /** Store an upload; returns an opaque token "{uuid}.{ext}". */
    public function put(string $kind, UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: 'csv');
        $token = Str::uuid()->toString().'.'.$ext;

        $file->storeAs(self::dir($kind), $token, self::DISK);

        return $token;
    }

    /** Absolute path for a token if it exists, else null. Basename-guarded. */
    public function path(string $kind, string $token): ?string
    {
        $relative = self::dir($kind).'/'.basename($token);

        return Storage::disk(self::DISK)->exists($relative)
            ? Storage::disk(self::DISK)->path($relative)
            : null;
    }

    public function extension(string $token): string
    {
        return strtolower(pathinfo($token, PATHINFO_EXTENSION) ?: 'csv');
    }

    public function delete(string $kind, string $token): void
    {
        Storage::disk(self::DISK)->delete(self::dir($kind).'/'.basename($token));
    }

    /**
     * Delete files older than the TTL. Returns the number removed.
     *
     * @param  string|null  $kind  a specific kind, or null to sweep every kind
     */
    public function prune(?string $kind = null, ?int $ttlMinutes = null): int
    {
        $ttl = $ttlMinutes ?? self::TTL_MINUTES;
        $disk = Storage::disk(self::DISK);
        $cutoff = now()->subMinutes($ttl)->getTimestamp();
        $dir = $kind === null ? self::BASE : self::dir($kind);
        $deleted = 0;

        foreach ($disk->allFiles($dir) as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    private static function dir(string $kind): string
    {
        return self::BASE.'/'.$kind;
    }
}
