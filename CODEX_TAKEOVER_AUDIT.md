# Codex Takeover Audit — Phase 10

Audit date: 2026-08-18. This report compares repository documentation with the implementation. No
production access, credentials, deployment, DNS change, or cutover was available or attempted.

## Baseline

- Git started clean on `master`; baseline head was `6ce5de6` (Phase 9).
- Baseline PHP: 8.3.32; Laravel: 13.25.0.
- Baseline tests: 342 passed, 990 assertions on MySQL.
- Baseline `npm run build`: passed with Vite 8.2.1. The optional `fontaine` optimization warning is
  non-fatal; fonts and assets compiled successfully.
- Local environment is intentionally `APP_ENV=local`, `APP_DEBUG=true`. It is not production proof.

## 1. Runtime requirements

`composer.json` requires PHP `^8.3`, Laravel `^13.17`, OpenSpout `^5.3`, and the committed lock file.
Application-mandatory native extensions are checked by `php artisan app:preflight`: `pdo_mysql`,
`openssl`, `fileinfo`, `tokenizer`, `dom`, `xmlreader`, `zip`, `bcmath`, and `json`. Native `ctype`,
`mbstring`, and `intl` are recommended; installed Symfony polyfills cover their used APIs, while the
preflight separately proves `Normalizer` availability. cURL, GD, and Imagick are not application
requirements today.

## 2. Database architecture and migrations

The application uses Laravel/Eloquent with cPanel MySQL/MariaDB, strict mode, `utf8mb4` /
`utf8mb4_unicode_ci`, foreign keys, and integer scoring. Existing schema covers identity/RBAC,
append-only points, courses/lessons/resources, quizzes/questions/options/attempts/answers, regrades,
CMS/notices/settings/audit, cache, sessions, and jobs.

Phase 10 adds one minimal `legacy_import_batches` table plus nullable batch/source identifiers on
legacy-capable users, quizzes, and attempts. A `(type, source_sha256)` batch key and unique record
fingerprints make retries traceable and idempotent. Existing migrations never read, alter, or drop
the old `quiz_submissions` table.

## 3. Existing and added importers

- Existing `StudentImporter`: preview/classification, canonical Bengali/Latin roll handling, fresh
  temporary credentials, forced change, private one-time credential CSV, no overwrite.
- Existing quiz importer: CSV/XLSX A–W parsing, 12 options, Bengali/Latin answer positions, custom
  marks, schedules, ignored password columns, exact course/lesson suggestions, draft-only import.
  Phase 10 adds workbook/sheet provenance and an exact-source retry guard.
- Existing `LegacyCourseImporter`: repeatable import of 42 source lessons and honest gap reporting.
- Added legacy result parser/importer: exact/reviewed quiz mapping, canonical-roll user mapping,
  source fingerprints, no fabricated answers, no historical point debit.

## 4. Security controls verified

Session authentication, role + permission middleware, CSRF, identifier/IP login throttling, forced
password change, hidden answer-key attributes, allow-listed live payload, server-authoritative time
and score, transactional point debit, private import storage, audit redaction, CMS sanitization,
release gates, and email-only Ask Ustaz behavior are implemented and tested. Runtime code contains no
GViz/direct Sheet fetch and no call to the legacy PHP endpoint.

Secret-bearing `quiz-api.php` copies exist locally as ignored legacy evidence. They are not tracked
and must not be uploaded as reachable files. Their exposed database credential must be rotated.

## 5. Scheduler and production assumptions

`imports:cleanup` runs hourly and `attempts:finalize-expired` every minute. One standard cPanel cron
is sufficient; Supervisor and a persistent Node process are not required. The target assumes HTTPS,
PHP 8.3+, MySQL/MariaDB, writable `storage` and `bootstrap/cache`, Laravel outside the public web
root, and only compiled Vite assets in production.

## 6. Environment variables

Required production groups are `APP_*`, MySQL `DB_*`, session/cache/log settings, SMTP `MAIL_*`, and
`USTAZ_EMAIL`. `.env.example` contains placeholders only. Real `.env`, SQL exports, legacy source
credentials, credential CSVs, and backup archives are ignored and must stay outside `public_html`.

## 7. Coverage and production-readiness additions

Coverage already included auth, authorization, importer security, points, quiz lifecycle/scoring,
autosave/resume, release gates, practice, leaderboards, regrade/adjustment, CMS, Ask Ustaz, Zakat,
calendar, and scheduling. Phase 10 adds result mapping/import/idempotency/no-point tests, student and
quiz repeat-import tests, provenance, cumulative-flag regression, integrity drift detection, runtime
legacy-dependency checks, and course verification.

## 8. Documentation/code discrepancies found

- Local PHP moved from `%LOCALAPPDATA%\php83` to `C:\Users\mdark\tools\php83`; old command examples
  were stale.
- README/Project Plan still said Phase 10 tooling was unbuilt.
- `MIGRATION.md` incorrectly described hashing the compromised legacy password; code correctly
  discards it and generates a new one.
- `DEPLOYMENT.md` listed cURL as mandatory and described native intl as unavoidable even though
  Composer polyfills cover the used API. Preflight now distinguishes required from recommended.
- `SECURITY.md` still claimed the folder was not a Git repository.
- Documented per-attempt `counts_toward_cumulative` semantics were not applied in the overall
  leaderboard query. The query and regression coverage were corrected.

## 9. Current data verification

The safe local DB has all 42 expected source lessons. It also has one additional post-legacy lesson
and an additional course; these were preserved and reported as informational, not treated as
corruption. No duplicate slugs, `#` resource links, invalid lesson UTF-8, point-ledger drift,
quiz-mark drift, invalid question keys, orphan answer links, duplicate source keys, or missing legacy
provenance were found after the Phase 10 migration.

## 10. Risks and blockers

- `legacy/db/` contains no production `quiz_submissions` export. Tooling is ready, but actual result
  reconciliation/import is **BLOCKED BY EXPORT**.
- No current Student Sheet export or quiz workbook was supplied, so actual row counts and conflict
  reports cannot be truthfully produced.
- cPanel paths, domain, PHP binary, DB credentials, SMTP account, and authorized production shell are
  not available. Production preflight, SMTP delivery, backup restore, smoke tests, DNS, and cutover
  remain owner-run steps.
- Never migrate an in-flight official exam. Old and new write endpoints must not accept submissions
  concurrently after cutover.

These are production-input blockers, not tooling blockers. Follow `PRODUCTION_PREFLIGHT.md`,
`CUTOVER_CHECKLIST.md`, and `ROLLBACK.md` after the missing inputs are provided.
