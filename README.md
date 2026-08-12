# মাসউদ আলিমী — Islamic Learning & Examination Platform

A Laravel rebuild of the Masud Alimi Seerat/Tafsir course site: student accounts,
scheduled online examinations with server-side scoring, a points system, course
archive, leaderboards, and an admin panel that removes the need to edit source
code to manage content.

> **Status: foundation phase.** The database, domain services and legacy data
> migration are built and tested. The web interface is not. See
> [PROJECT_PLAN.md](PROJECT_PLAN.md) §11 for the honest, current status — nothing
> is listed there as done unless it has been run.

---

## Documentation

| Document | Contents |
|---|---|
| [PROJECT_PLAN.md](PROJECT_PLAN.md) | Legacy audit, target architecture, phase plan, **current status**, open decisions |
| [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md) | Tables, indexes, invariants and why they are shaped that way |
| [MIGRATION.md](MIGRATION.md) | Moving legacy data across, and what cannot be reconstructed |
| [SECURITY.md](SECURITY.md) | Vulnerabilities found in the legacy system and the rules that replace them |
| [DEPLOYMENT.md](DEPLOYMENT.md) | cPanel runbook |
| [CLAUDE.md](CLAUDE.md) | Engineering conventions — read before changing code |

---

## ⚠ Read first

The legacy `quiz-api.php` contains **live database credentials in plaintext**, and
the student spreadsheet exposed **every student's password publicly**. Both must be
dealt with before this goes near production — see [SECURITY.md](SECURITY.md) §0.

---

## Stack

Laravel 13 · PHP 8.3 · MySQL/MariaDB (`utf8mb4`) · Blade + Tailwind via Vite ·
Asia/Dhaka. No SPA framework, no Node runtime in production — built for ordinary
cPanel hosting.

---

## Local setup

XAMPP's bundled PHP 8.0 cannot run this application. A side-by-side PHP 8.3 is
installed at `%LOCALAPPDATA%\php83`, leaving XAMPP untouched (it still supplies
MariaDB).

```bash
# 1. MariaDB
/c/xampp/mysql/bin/mysqld.exe --standalone &

# 2. Databases
/c/xampp/mysql/bin/mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS masudalimi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS masudalimi_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 3. Environment
cp .env.example .env
"$LOCALAPPDATA/php83/php.exe" artisan key:generate

# 4. Schema + seed data (roles, settings, 42 migrated lessons)
"$LOCALAPPDATA/php83/php.exe" artisan migrate:fresh --seed
```

Run the tests:

```bash
"$LOCALAPPDATA/php83/php.exe" artisan test
```

---

## Legacy sources

`legacy/` holds byte-identical copies of the original `masudalimi.html` and
`quiz-api.php` (SHA-256 verified). They are reference material and are never
modified.

`legacy/extracted/course-data.json` contains all 42 hard-coded lessons, extracted
programmatically by `extract-course-data.mjs` so no Bengali text was retyped. It is
the input to the course seeder.

---

## What exists today

- Full normalised schema — 31 tables, all `utf8mb4_unicode_ci`
- `PointService` — atomic, append-only ledger with a balance invariant
- `QuizScoringService` — server-side scoring, exact-set match, custom marks
- `QuizAttemptService` — eligibility, transactional point debit, idempotent
  autosave/resume/submit
- `LegacyCourseImporter` — imports the real legacy catalogue, reports content gaps
  rather than inventing content
- 71 passing tests / 146 assertions, running on MySQL

## What does not exist yet

Web UI, admin panel, quiz builder, Google Sheet importer, leaderboards, blog,
Ask Ustaz, Zakat calculator, calendar service, student/results migration.
Tracked in [PROJECT_PLAN.md](PROJECT_PLAN.md) §10–11.
