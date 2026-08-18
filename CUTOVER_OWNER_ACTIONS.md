# Cutover Owner Actions

This is the owner-run order of operations. Do not deploy, import production data, switch DNS/document
roots, or disable the legacy endpoint until every applicable gate is checked and explicit production
authority has been given. Never cut over during or immediately before an official exam.

## 1. Before opening cPanel

- [ ] Make the Student Sheet and quiz workbook private.
- [ ] Export the current Student Sheet as CSV/XLSX. Roll and Name are required; Guardian is optional.
  A legacy Password column may be present, but it is ignored and never reused.
- [ ] Export every archived quiz tab needed by the 20 unresolved legacy quiz IDs; preserve the
  workbook unchanged and identify any `Live` tab as `SKIP`.
- [ ] Review the private `LEGACY_QUIZ_MAPPING.csv`. Set every row to exactly `APPROVED`, `SKIP`, or
  `REVIEW`; document a reason for every `SKIP`. Do not leave an intended import as `REVIEW`.
- [ ] Confirm no official exam is active or imminent and agree a maintenance window.
- [ ] Record the production domain, cPanel account, application path, public document root, PHP 8.3
  binary, database name/user, SMTP sender, `USTAZ_EMAIL`, and rollback owner. Keep passwords out of
  Git, chat, screenshots, and shell history.
- [ ] Take private copies of the old site, `quiz-api.php`, both Google exports, and the result export.
  Store them outside `public_html` and record SHA-256 hashes.

## 2. Prove backup and staging readiness

- [ ] Dump the production database with `mysqldump -p`; never put its password on the command line.
- [ ] Restore that dump into a separately named scratch database and verify table/row counts and
  sample records. A dump that has not restored successfully is not an approved backup.
- [ ] Run student preview, archived-quiz preview, result preview, mapping review, and reconciliation
  on staging. Do not import the `Live` sheet.
- [ ] Re-run each approved source and confirm fingerprints prevent duplicates.
- [ ] Run `php artisan test`, `npm ci`, `npm run build`, `app:preflight --production`,
  `app:verify-integrity`, and `legacy:verify`. Resolve every error-severity finding.

## 3. Prepare cPanel without switching traffic

- [ ] Select PHP 8.3+ and confirm PDO MySQL, OpenSSL, fileinfo, tokenizer, DOM/XMLReader, ZIP, BCMath,
  JSON, and Normalizer support.
- [ ] Put the Laravel application outside the web root. Point the domain/subdomain document root only
  at the application's `public/` directory.
- [ ] Upload the committed code and locally compiled `public/build/`. Node is not a production runtime.
- [ ] Run `composer install --no-dev --optimize-autoloader`; never run `composer update` for cutover.
- [ ] Create a new production MySQL user, rotate the exposed legacy credential, configure `.env`
  (`APP_ENV=production`, `APP_DEBUG=false`, HTTPS URL, Asia/Dhaka, MySQL utf8mb4, secure database
  sessions, SMTP), set `.env` to mode 600, and never expose it under the document root.
- [ ] Run migrations/seeders, config/route/view caches, storage link, production preflight, integrity,
  schedule listing, and the canned `app:mail-test` diagnostic.
- [ ] Configure one every-minute cPanel cron for `php artisan schedule:run` and confirm it actually runs.

## 4. Controlled migration window

- [ ] Reconfirm no active official exam; put the new app in maintenance mode.
- [ ] Import in this order: students → courses/lessons → approved archived quizzes → approved legacy
  results. Store reports and one-time credentials only in a private directory outside `public_html`.
- [ ] Verify every imported student has a fresh temporary password and `force_password_change=1`;
  never reuse a legacy password.
- [ ] Verify legacy results have no answer rows, no point transaction, `answer_details_available=0`,
  and exact user/quiz provenance. Keep imported quizzes `counts_toward_overall=false` until the owner
  approves the leaderboard review CSV.
- [ ] Reconcile source/import/skip/failure totals exactly and re-run integrity and idempotency checks.

## 5. Smoke test before and after traffic switch

- [ ] Test Bengali/Latin roll login and forced password change with a dedicated student.
- [ ] Test official start/resume/autosave/submit/expiry: exactly one debit and no answer key, marks, or
  explanation in live payloads.
- [ ] Test release gates, answer sheet, practice safety, per-quiz/overall leaderboard behavior, admin
  pages, public pages, Ask Ustaz email-only delivery, Zakat non-persistence, and scheduler execution.
- [ ] Switch the document root/traffic to Laravel `public/`, enforce HTTPS, and repeat smoke tests via
  the public domain.
- [ ] Disable `quiz-api.php` as a reachable write endpoint immediately after the switch, but keep its
  private backup outside the web root for the rollback window. Keep Google Sheets private and never
  allow old and new submission endpoints to accept writes simultaneously.

## 6. Rollback and post-cutover

- [ ] Keep the old site/database offline but intact throughout the rollback window.
- [ ] If reconciliation, login, scoring, point balance, answer-key secrecy, assets, database, SMTP,
  session/HTTPS, or scheduler checks fail, stop new writes and follow `ROLLBACK.md`.
- [ ] Monitor Laravel/PHP logs and cPanel metrics without logging passwords, questions, Zakat inputs,
  answer keys, or tokens. Record final evidence and owner sign-off.
