<?php

namespace App\Console\Commands;

use App\Services\Import\CredentialExport;
use App\Services\Import\ImportFileStore;
use Illuminate\Console\Command;

/**
 * Sweeps abandoned import uploads and expired credential exports.
 *
 * Runs from the scheduler (hourly) and is also invoked opportunistically on each
 * import preview, so no continuously-running worker is required. cPanel cron:
 *
 *   * * * * * cd /home/<acct>/masudalimi && php artisan schedule:run >> /dev/null 2>&1
 */
class CleanupImportFiles extends Command
{
    protected $signature = 'imports:cleanup';

    protected $description = 'Delete abandoned import uploads and expired credential exports';

    public function handle(ImportFileStore $files, CredentialExport $credentials): int
    {
        $imports = $files->prune();                 // all import kinds, 60-min TTL
        $creds = $credentials->cleanup();           // credential CSVs, 30-min TTL

        $this->info("Removed {$imports} abandoned import file(s) and {$creds} expired credential export(s).");

        return self::SUCCESS;
    }
}
