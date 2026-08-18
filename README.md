# মাসউদ আলিমী — Islamic Learning & Examination Platform

A Laravel rebuild of the Masud Alimi Seerat/Tafsir course site: student accounts,
scheduled online examinations with server-side scoring, a points system, course
archive, leaderboards, and an admin panel that removes the need to edit source
code to manage content.

> **Status: Phase 10 migration and production-readiness tooling complete locally.** On top of the hardened domain layer, the quiz
> engine, the secure live exam and the post-exam/practice ecosystem, this phase adds the
> **public site**: an upgraded homepage, a **Blog/Fatwa/Q&A CMS** (Markdown, sanitised, SEO +
> sitemap + robots), admin **notices**, the **email-only Ask Ustaz** form (never persisted), a
> decimal-safe **Zakat calculator**, and a centralised **CalendarService** (Gregorian / revised
> Bangla / tabular Hijri with a sunset rollover + admin offset). The student/legacy-result data
> migrations now have dry-run, provenance, reconciliation and integrity tooling. Actual production
> Phase 10B inspected a partial real quiz export and an 856-row legacy result SQL export in an
> isolated MySQL scratch database. One archived quiz was staged as a draft; the `Live` sheet was
> not imported, and no result row was imported because the current student export and explicit
> quiz mapping approvals are still missing. Production import/cPanel cutover also require hosting
> details and explicit authority. See [MIGRATION_RECONCILIATION.md](MIGRATION_RECONCILIATION.md).

---

## Documentation

| Document | Contents |
|---|---|
| [PROJECT_PLAN.md](PROJECT_PLAN.md) | Legacy audit, target architecture, phase plan, **current status**, open decisions |
| [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md) | Tables, indexes, invariants and why they are shaped that way |
| [MIGRATION.md](MIGRATION.md) | Moving legacy data across, and what cannot be reconstructed |
| [SECURITY.md](SECURITY.md) | Vulnerabilities found in the legacy system and the rules that replace them |
| [DEPLOYMENT.md](DEPLOYMENT.md) | cPanel runbook |
| [CLAUDE.md](CLAUDE.md) / [AGENTS.md](AGENTS.md) | Engineering conventions — read before changing code |
| [PRODUCTION_PREFLIGHT.md](PRODUCTION_PREFLIGHT.md) | Runtime, environment, SMTP and cPanel preflight |
| [CUTOVER_CHECKLIST.md](CUTOVER_CHECKLIST.md) / [ROLLBACK.md](ROLLBACK.md) | Controlled launch and recovery |

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
installed at `C:\Users\mdark\tools\php83`, leaving XAMPP untouched (it still supplies
MariaDB).

```bash
# 1. MariaDB
/c/xampp/mysql/bin/mysqld.exe --standalone &

# 2. Databases
/c/xampp/mysql/bin/mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS masudalimi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS masudalimi_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 3. Environment
cp .env.example .env
"C:/Users/mdark/tools/php83/php.exe" artisan key:generate

# 4. Schema + seed data (roles, settings, 42 migrated lessons, dev accounts)
"C:/Users/mdark/tools/php83/php.exe" artisan migrate:fresh --seed
```

Dev logins (local only): `admin@masudalimi.test` / `password`, or student roll
`১০১`–`১০৬` / `password`.

Build assets and run the app / tests:

```bash
npm install && npm run build
"C:/Users/mdark/tools/php83/php.exe" artisan serve
"C:/Users/mdark/tools/php83/php.exe" artisan test
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

**Domain (hardened)**
- Full normalised schema — 31 tables, all `utf8mb4_unicode_ci`
- `PointService` — atomic, append-only ledger with a balance invariant
- `QuizScoringService` — server-side scoring, exact-set match, custom marks
- `QuizAttemptService` — one-way terminal states, concurrency-safe submission serial,
  attempt-first append-only debit, fail-closed answer validation
- `LeaderboardService` — the single owner of ranking (per-quiz + cumulative, competition
  ties, best-attempt selection, computed live from stored scores)
- `QuizRegradeService` / `ScoreAdjustmentService` — transactional answer-key correction and
  validated, audited manual adjustment; both preserve the admin's manual delta
- `LegacyCourseImporter` — imports the real legacy catalogue, reports content gaps

**Application layer**
- Design system (Tailwind v4 + Alpine, Blade component library, light/dark, self-hosted
  Bengali/Arabic fonts, mobile-first), and public / student / admin layouts
- Authentication (roll or email, rate-limited, forced temp-password change), role +
  permission middleware
- Student dashboard, profile, course archive, lesson detail, point history
- Admin dashboard, student CRUD, CSV/XLSX student import (preview → confirm), point
  management (grant / deduct / bulk), course & lesson management with inline resources,
  audit log
- **Quiz builder + CSV/XLSX importer**, and the **secure live official exam**
  (start/resume, autosave, authoritative timer, expiry sweep, release-gated result)
- **Post-exam & practice ecosystem** — student `/results` history + release-gated answer
  sheets, **Practice Mode** (free, untimed, unranked, immediate review), per-quiz +
  overall **leaderboards**, and the admin results / manual-adjustment / **regrade** surface
- **Public knowledge platform** — homepage with the calendar date widget, a **Markdown
  CMS** (Blog/Fatwa/Q&A, sanitised, SEO + `sitemap.xml` + `robots.txt`), admin **notices**,
  the **email-only Ask Ustaz** form (never persisted), a decimal-safe **Zakat calculator**,
  and `CalendarService` (Gregorian / revised Bangla / tabular Hijri with sunset rollover)
- **355 passing tests / 1,560 assertions** on MySQL, plus production preflight, legacy verification and Vite build checks
- **Phase 10:** traceable/idempotent student, quiz-source and result migration; exact-only quiz-ID
  reconciliation; read-only integrity verifier; cPanel/cutover/rollback runbooks

## Production inputs still required

Current private Student/Quiz exports, a `quiz_submissions` export under private `legacy/db/`, reviewed
quiz mapping, cPanel/domain paths, rotated DB/SMTP credentials, and explicit production access. No
production deployment or cutover has been claimed.
