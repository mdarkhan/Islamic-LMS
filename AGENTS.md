# AGENTS.md

Masud Alimi is a Laravel 13 / PHP 8.3 / MySQL Islamic learning and examination platform. Read
`CLAUDE.md`, `SECURITY.md`, `DATABASE_SCHEMA.md`, `MIGRATION.md`, and `DEPLOYMENT.md` before changing
domain or production code.

## Working commands

```bash
php artisan test
npm ci
npm run build
php artisan app:preflight
php artisan app:verify-integrity
php artisan legacy:verify
```

Tests use MySQL (`masudalimi_test`), not SQLite. On this Windows workstation use
`C:\Users\mdark\tools\php83\php.exe`; XAMPP's PHP 8.0 is too old. Do not run `composer update` for
a deployment; production uses `composer install --no-dev --optimize-autoloader` from the lock file.

## Invariants

- cPanel MySQL/utf8mb4 remains the system of record. Do not add another database service.
- `PointService` is the only writer of `users.points_balance`; it must equal the signed ledger sum.
- Official scoring is server-side exact-set scoring. Never trust client score/time/user/roll data.
- Never expose answer keys, explanations, or question marks during an open official attempt.
- Result and answer-sheet visibility is server-side release-gated. Practice is free, untimed,
  unranked, repeatable, and available only after the answer key is safe to reveal.
- Regrade through `QuizRegradeService`; manual adjustments preserve `calculated_score` and are
  bounded/audited. Do not unlock scoring fields after official attempts exist.
- Ask Ustaz is email-only: validate → rate-limit → email → discard. Never persist or log questions.
- Zakat inputs/results are never persisted or logged.
- Legacy passwords are discarded. Imports generate fresh temporary passwords, force a change, and
  keep the one-time credential export in private storage only.
- Legacy results have no answer details, create no point transaction, and require exact/reviewed quiz
  and canonical-roll mapping. Source fingerprints make retries idempotent.
- Never fabricate missing Tafsir/Halakah content or legacy answer sheets.

## Production constraints

Do not deploy or cut over without a verified database/file backup, local/staging dry run,
reconciliation, rollback readiness, no active official exam, and explicit production authority.
The Laravel app belongs outside the web root with only `public/` exposed. Node is build-time only;
one cPanel cron runs `schedule:run`. After cutover, Google Sheets are private authoring/export inputs,
not runtime storage, and legacy `quiz-api.php` must be disabled while its backup stays outside the
web root. See `CODEX_TAKEOVER_AUDIT.md`, `PRODUCTION_PREFLIGHT.md`, `CUTOVER_CHECKLIST.md`, and
`ROLLBACK.md`.
