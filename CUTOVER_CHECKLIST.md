# Controlled Cutover Checklist

Do not cut over during an official exam or immediately before one. The old live quiz should finish
on the legacy system and be imported later for history/practice, unless a deliberate maintenance
window occurs before the next exam begins. Never allow both submission endpoints to accept official
writes at the same time.

## 1. Freeze and evidence

- [ ] Confirm no active or imminent official exam.
- [ ] Export the private Student Sheet, quiz workbook, and `quiz_submissions`.
- [ ] Copy old `masudalimi.html`, `quiz-api.php`, current deployed files, and Google exports to a
  private dated backup directory outside `public_html`.
- [ ] Dump the production database without putting a password on the command line.
- [ ] Restore the dump into a scratch DB and verify key row counts/sample records.
- [ ] Record the old code release, DB dump hash, backup location, and rollback owner.

Example backup command (prompts for the password):

```bash
mysqldump -u DB_USER -p --single-transaction --routines --triggers \
  --default-character-set=utf8mb4 DB_NAME > /home/USERNAME/private-backups/pre-cutover.sql
sha256sum /home/USERNAME/private-backups/pre-cutover.sql
```

## 2. Stage code before downtime

- [ ] Upload code outside the web root and compiled `public/build/`.
- [ ] Run `composer install --no-dev --optimize-autoloader` from `composer.lock`.
- [ ] Configure `.env`; rotate the exposed legacy DB password and use the new value.
- [ ] Point a staging/subdomain document root at `public/`.
- [ ] Run `app:preflight --production`, migrations, caches, storage link, scheduler, and SMTP test.
- [ ] Dry-run students/results and review every conflict/mapping row.

## 3. Migration order

```bash
# Use maintenance mode only for the short final write window.
php artisan down --secret="OWNER-ONLY-BYPASS"

php artisan migrate --force
php artisan db:seed --force  # seeders are idempotent; DevAccountsSeeder skips production

php artisan legacy:students:preview /home/USERNAME/private-imports/students.xlsx \
  --report=/home/USERNAME/private-reports/students-preview.md
php artisan legacy:students:import /home/USERNAME/private-imports/students.xlsx --confirm \
  --actor=admin@example.com --report=/home/USERNAME/private-reports/students-final.md

# Import archived quiz tabs through Admin → Quiz Import. They remain draft for review.
# Do not import an in-flight Live tab.

php artisan legacy:results:preview /home/USERNAME/private-imports/quiz_submissions.csv \
  --write-mapping=/home/USERNAME/private-reports/LEGACY_QUIZ_MAPPING.csv
# Review mapping CSV, then:
php artisan legacy:results:preview /home/USERNAME/private-imports/quiz_submissions.csv \
  --mapping=/home/USERNAME/private-reports/LEGACY_QUIZ_MAPPING.csv
php artisan legacy:results:import /home/USERNAME/private-imports/quiz_submissions.csv \
  --mapping=/home/USERNAME/private-reports/LEGACY_QUIZ_MAPPING.csv --confirm \
  --report=/home/USERNAME/private-reports/results-final.md

php artisan app:verify-integrity
php artisan legacy:verify
```

Do not seed development accounts in production. Review every migrated quiz's
`counts_toward_overall`; default migrated/historical policy is owner review before inclusion.

## 4. Smoke tests before traffic

Use a dedicated test student and low-stakes test quiz:

- [ ] Login with Latin and Bengali roll digits; forced password change works.
- [ ] Start exam debits exactly once; refresh/resume does not debit again.
- [ ] Network/page contains no answer key, explanation, or per-question marks.
- [ ] Autosave, refresh/resume, server timer, submit, and expiry behave correctly.
- [ ] Result is hidden before release and visible after release; answer sheet is correct.
- [ ] Practice is free/unranked and only available after safe release.
- [ ] Admin pages load: Students, Points, Courses, Quiz Builder/Import, Results, Regrade, Blog,
  Notices, Settings, Audit.
- [ ] Public pages load: home/mobile nav, articles, Ask Ustaz, Zakat, calendar, sitemap, robots,
  error pages, and dark mode.
- [ ] `app:verify-integrity` reports no error-severity failures.
- [ ] SMTP message and Ask Ustaz delivery arrive; no question DB row/log entry exists.
- [ ] Scheduler runs both registered commands.

## 5. Switch traffic and retire runtime dependencies

- [ ] Change the domain document root/traffic target to the new Laravel `public/` and enforce HTTPS.
- [ ] Confirm `APP_URL` and sitemap/canonical URLs use the production HTTPS domain.
- [ ] Disable legacy `quiz-api.php` immediately as an active write endpoint (remove from web root,
  rename outside web root, or deny all access). Keep a private backup for the rollback window.
- [ ] Confirm the new app does not call Google Sheets or the legacy endpoint.
- [ ] Keep both Sheets private; use them only for manual export/authoring if desired.
- [ ] Run smoke tests again through the public domain, then `php artisan up` if maintenance mode was
  used.

Do not delete the old site/database during the rollback window. Keep it offline/read-only so it
cannot create split-brain submissions.
