# Rollback Plan

The rollback objective is to restore the last verified site and database without accepting writes
in two systems. Keep the legacy application and backups private/intact through the agreed rollback
window.

## Triggers

Rollback or hold traffic for any of these:

- widespread login failure or materially missing students;
- migration reconciliation/row counts do not match the approved preview;
- exam start/resume/submit failure, double debit, or point-ledger drift;
- unexpected answer-key exposure, score corruption, or result-release failure;
- repeated 500 errors, broken compiled assets, or unavailable database;
- scheduler failure that leaves attempts unfinalized;
- SMTP failure when Ask Ustaz is part of launch scope;
- HTTPS/cookie/session failure or public exposure of `.env`, dumps, backups, or legacy API.

## Immediate containment

1. Stop new writes: `php artisan down --secret="OWNER-ONLY-BYPASS"` or move traffic away from the
   new document root.
2. Do not re-enable both old and new quiz write endpoints simultaneously.
3. Capture current Laravel/PHP logs, deployment release ID, current database dump, and integrity
   output for diagnosis. Store them outside `public_html`.
4. Record the last known successful submission timestamp so any write reconciliation is explicit.

## Code-only rollback

If data/schema are confirmed compatible and only code/assets failed:

```bash
# Point the release symlink/document root back to the previous release.
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan app:preflight --production
php artisan app:verify-integrity
php artisan up
```

Do not run `composer update` and do not regenerate `APP_KEY`.

## Database/data rollback

Use this for a bad migration/import or integrity drift. A schema rollback alone does not undo all
data changes safely; restoring the verified pre-cutover dump is the authoritative recovery.

1. Keep the new app in maintenance mode.
2. Take a forensic dump of the failed state.
3. Create a new empty restoration database or drop/recreate only after the exact target is verified
   and cPanel confirms the backup is usable.
4. Restore the verified pre-cutover dump with the cPanel restore tool or:

```bash
mysql -u DB_USER -p DB_NAME < /home/USERNAME/private-backups/pre-cutover.sql
```

5. Restore the previous deployed web files/document root.
6. If the legacy site must temporarily accept writes, update it to the rotated DB credential in a
   private controlled copy, enable only that endpoint, and keep the new endpoint offline.
7. Verify login, source row counts, a sample course/result, and old submission behavior before
   restoring traffic.

## Forward recovery

After diagnosis, fix and test on a restored staging copy. Re-run preview, mapping review,
reconciliation, `app:verify-integrity`, smoke tests, and owner approval. Source fingerprints make
student/quiz/result retry safe; never delete fingerprints or fabricate a second import to bypass a
conflict.

SMTP-only failure may be handled by temporarily disabling the Ask Ustaz entry point while the rest
of the site remains healthy, but only the owner may accept that reduced launch scope because missed
questions are not persisted for retry.
