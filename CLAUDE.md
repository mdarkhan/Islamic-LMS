# CLAUDE.md — conventions for this project

Read this before changing anything. It encodes decisions that are easy to undo by
accident. Full reasoning lives in `PROJECT_PLAN.md`, `DATABASE_SCHEMA.md`,
`MIGRATION.md` and `SECURITY.md`.

---

## Hard rules — do not "improve" these

1. **Never store Ask Ustaz submissions.** No `ustaz_questions` / `inquiries` /
   `contact_messages` table, no retained queue payload, no logging of the question
   body. Validate → rate-limit → email → discard. Recipient from `USTAZ_EMAIL`.
2. **Never send the answer key to the browser during a live official exam.**
   `quiz_options.is_correct` is in `#[Hidden]` for exactly this reason. Do not
   remove it, and do not add `is_correct`, `marks` or `explanation` to a payload
   that a student can fetch while their attempt is open.
3. **Never trust a client-supplied score, user id, roll, expiry or elapsed time.**
   Everything is derived server-side. `QuizScoringService` is the only thing that
   may write `calculated_score` / `final_score`.
4. **Never write `users.points_balance` outside `PointService`.**
5. **Never commit credentials.** `quiz-api.php` and `legacy/quiz-api.php` hold the
   compromised legacy pair and are gitignored. `legacy/quiz-api.reference.php` is
   the redacted, committable version.
6. **Never delete or fabricate legacy data.** Legacy attempts have no stored
   answers; show "detailed answers unavailable", never invent them.
7. **Per-question marks stay hidden during a live official exam.** Legacy behaviour,
   deliberate — students must not be steered toward mega questions.
8. **Terminal attempt states are one-way.** `submitted` / `expired` / `voided` never
   transition. A late `submit()` on a still-in-progress attempt past its deadline is
   recorded as `expired` (with the authoritative deadline timestamps), never as an
   on-time submission. Only `finalise()` may make an attempt terminal.
9. **`saveAnswer` fails closed.** A foreign option id, or more than one option for a
   single-choice question, throws — it is never silently dropped or truncated. An
   empty selection clears the answer.
10. **`startOfficial` creates the attempt before debiting**, then debits with the
    attempt as the ledger reference. Never revert to updating a `point_transactions`
    row after the fact — the ledger is append-only.

---

## Quiz lifecycle & builder

- **Lifecycle** (`Quiz::isOpenAt`, `officialState`): `draft` is admin-only and never
  visible. `scheduled` and `published` are **both** window-governed — a quiz opens at
  `starts_at` and closes at `ends_at` with **no status mutation and no cron**. `archived`
  never allows a new official attempt but may offer practice when `practice_enabled`.
  Server clock is authoritative; times are Asia/Dhaka.
- **Scoring lock** (`Quiz::scoringLocked()` = has official attempts): once any official
  attempt exists, the builder freezes the answer key, marks, type and question set —
  add/edit/delete/duplicate of questions is refused with a Bengali message and
  corrections go through the (future) Regrade flow. Reordering stays allowed (it does
  not affect scores). Non-scoring metadata (title, description, schedule) stays editable.
- **Question types are explicit.** `single` requires exactly one correct option;
  `multiple` allows one or more. Never infer the type from the correct count — a
  multiple-choice question may intentionally have a single correct option.
- **Marks are canonical.** The legacy "Mega Question" is just `marks` (≥1); there is no
  separate mega engine. `Quiz::recalculateTotalMarks()` runs after every question change.
- Imported quizzes always land as `draft` for review before publishing.

## Quiz import (CSV / XLSX)

- Legacy workbook layout, 0-indexed: A(0) Question · B–M(1–12) Options · N(13) Correct
  Answer · O(14) Timer seconds · P(15) Password *(ignored)* · Q/R(16/17) Start date/time
  · S/T(18/19) End date/time · U(20) Exam Name → title · V(21) Leaderboard Password
  *(ignored)* · W(22) Mega Question → marks.
- **The config row is also the first question** — never skip it. Correct answers accept
  Latin and Bengali numerals (`1`, `১, ২`). `>1` correct ⇒ `multiple`.
- **Legacy password columns (P, V) are never imported or stored** — a non-blocking note
  is shown. **Google Sheets is never a runtime dependency**: admins download XLSX and
  import. Uploaded files are answer-key-sensitive → `ImportFileStore` (private, random
  name, TTL sweep, deleted on commit). The importer is fully transactional.
- Confirm **re-parses from the file** — question/answer data is never trusted from the
  posted preview. Course/lesson are suggested by `QuizCourseMatcher` (case-insensitive
  category prefix); a missing lesson links the course only and never fabricates a lesson.

## Slugs

Use `App\Support\Slug` (not `Str::slug`, which strips Bengali to nothing). It NFC-
normalises, lowercases Latin, preserves Bengali/Arabic letters + matras + numerals, and
`Slug::unique(...)` appends `-2`, `-3` on collision. Courses, Lessons and Quizzes use it.
Existing migrated slugs are never regenerated (slug is set on create only).

## Import files & credentials

- All import uploads go through `ImportFileStore` (`imports/{kind}/`, 60-min TTL).
- Student import issues a fresh temp password per account (**never the legacy sheet
  password**) and hands the admin a one-time `CredentialExport` CSV (private,
  download-once, 30-min TTL, gitignored, never logged).
- Cleanup: `imports:cleanup` (hourly via the scheduler) + opportunistic prune on preview.
  No long-running worker. cPanel needs the one `schedule:run` cron (see DEPLOYMENT.md).

## Local toolchain

XAMPP's PHP is 8.0 and cannot run this app. A side-by-side PHP 8.3 lives at
`C:\Users\mdark\AppData\Local\php83`. XAMPP itself is untouched and still provides
MariaDB.

```bash
"$LOCALAPPDATA/php83/php.exe" artisan test
```

| Task | Command |
|---|---|
| Run tests | `php artisan test` |
| One suite | `php artisan test --testsuite=Feature` |
| Rebuild db | `php artisan migrate:fresh --seed` |
| Composer | `php "$LOCALAPPDATA/php83/composer.phar" ...` |
| Start MariaDB | `/c/xampp/mysql/bin/mysqld.exe --standalone &` |

Databases: `masudalimi` (dev), `masudalimi_test` (tests, configured in
`phpunit.xml`). Tests run on **MySQL, not sqlite** — the point ledger and attempt
creation depend on `lockForUpdate`, and the schema uses `ENUM` and `utf8mb4`.

Dev accounts (from `DevAccountsSeeder`, never runs in production):
`admin@masudalimi.test` / `password` (super_admin), students roll `১০১`–`১০৬` /
`password`. Test helpers `makeStudent()` / `makeAdmin()` / `makeSuperAdmin()` live
on the base `TestCase`.

Preview the app with `php artisan serve` (or the `masudalimi` launch config).

---

## Architecture

```
app/Models/            Eloquent models, thin
app/Services/Points/   PointService (the only writer of points_balance)
app/Services/Quiz/     QuizAttemptService, QuizScoringService
app/Services/Import/   BengaliText, LegacyCourseImporter, StudentImporter, StudentSpreadsheetParser
app/Services/Audit/    AuditLogger (redacts secrets from before/after snapshots)
app/Http/Controllers/  PublicController, Auth\*, Student\*, Admin\*
app/Http/Middleware/   EnsureRole (role:), EnsurePermission (perm:), EnsurePasswordChanged
app/Http/Requests/Admin/  Form Requests for every admin mutation
```

Controllers stay thin; business logic lives in services. Use Form Requests for
validation. Authorisation is layered: `role:` gates an area, `perm:` gates an action,
and `super_admin` bypasses `perm:` via `User::hasPermission()`.

## Web layer conventions

- **Blade + Alpine, no SPA.** UI components live in `resources/views/components/ui/*`
  (button, input, field, card, badge, alert, table, modal, …). Layouts in
  `components/layout/*` (base, guest, public, student, admin, app shell).
- **Design tokens** are semantic CSS custom properties in `resources/css/app.css`
  (`bg-surface`, `text-ink`, `border-line`, `text-brand`, …), re-pointed under `.dark`.
  Use the tokens, not raw palette values, so both themes stay consistent. Emerald/teal
  on warm neutral. Respect `prefers-reduced-motion` (already handled globally).
- **Tailwind v4 via Vite.** Never the CDN. `npm run build` compiles CSS/JS and
  self-hosts the Bengali/Arabic/Latin fonts into `public/build`. Node is a build-time
  dependency only.
- **Never put `@disabled` / `@checked` / `@selected` directives inside an `<x-...>`
  component tag** — it generates a dangling `endif`. Use a bound attribute
  (`:disabled="$expr"`) instead. Plain HTML elements are fine.
- **Bengali digits for display**: use the `bn()` helper. It is **locale-aware** —
  Bengali digits in the Bengali UI, Latin digits in the English UI. Admin-authored
  resource labels may contain legacy `<br />` — render with `resource_label_html()`,
  which escapes everything and keeps only the break.
- Money/points/scores render right-aligned with `tabular-nums`.

## Interface language (i18n)

- **The UI chrome is bilingual (bn/en); content is always Bengali.** Navigation,
  labels, buttons, dashboards, auth, profile, exams, points and shared components are
  translated via `__('group.key')` with files in `lang/bn/*.php` and `lang/en/*.php`
  (groups: `nav`, `ui`, `dashboard`, `auth`, `profile`, `exams`, `points`, `courses`).
  Lesson titles, quiz questions/options, notices and other **content stay Bengali**
  regardless of locale — never localise content strings.
- Default locale is `bn` (`APP_LOCALE`). `SetLocale` middleware (web group) resolves the
  language per request: authenticated `users.locale` → session → app default. The
  `x-ui.locale-toggle` (in every layout header + the profile page) posts to
  `locale.update`, which saves to the user and the session.
- The site name **মাসউদ আলিমী** and the Arabic bismillah are never translated.
- New user-facing chrome must use `__()` keys, not hardcoded strings. Deeper admin
  CRUD forms (student/quiz/course/lesson management, imports) are **not yet localised**
  and remain Bengali — migrate them incrementally with the same `__()` pattern.

## Auth

- One login screen. Identifier with `@` → staff email; else student roll (normalised).
  Credentials are verified before any account-status message, so a suspended notice
  never leaks account existence. Rate-limited per identifier+IP.
- Students authenticate by roll (Bengali or Latin digits). No public registration.
- `force_password_change` → `EnsurePasswordChanged` redirects every protected page to
  `password.change` until the student sets a new password.
- Admin-issued temp passwords are shown once (flash `temp_password`) for delivery,
  hashed immediately, never stored in plaintext or logged. Reset also drops the
  student's DB sessions.

---

## Bengali text

**Always `BengaliText::normalise()` (NFC) before comparing or slugging.** Bengali
য় / ড় / ঢ় each have a precomposed form (U+09DF, U+09DC, U+09DD) and a decomposed
form (base + nukta U+09BC). They render identically and compare unequal.

This is not hypothetical: a test assertion in `LegacyCourseImportTest` failed
precisely because hand-typed `জানুয়ারি` used the decomposed form while the legacy
source used the precomposed one. NFC *decomposes* these codepoints (they carry
composition exclusions), so a normalised string can be longer. That is correct.

Bengali and Latin digits are interchangeable in user input — roll numbers,
passwords and answer keys all go through digit normalisation, because the legacy
site accepted both and students are used to typing `২৫`.

---

## Scoring rule

Reproduces the legacy behaviour exactly:

```
selected set == correct set  →  award question.marks
anything else                →  award 0
```

No partial credit. A partially-correct multi-answer question scores zero, as does
an unanswered one. `marks` is the "Mega Question" value from workbook column W.

**Regrade contract:** recompute `calculated_score`, leave `manual_adjustment`
alone, then `final_score = calculated_score + manual_adjustment`. An admin's
goodwill mark must survive an answer-key correction — there is a test for this.

---

## Points

- Ledger (`point_transactions`) is append-only. Correct mistakes with an opposing
  `adjustment` row; never update or delete.
- `users.points_balance` is a cache. Invariant:
  `points_balance == SUM(point_transactions.amount)`.
- Debit + attempt creation share one transaction with `lockForUpdate` on the user.
  A failure rolls back both — the student never loses a point to a half-created
  attempt.
- **Resuming never re-debits.** Guarded by `quiz_attempts.point_transaction_id`.
- Practice costs nothing and sets `counts_toward_cumulative = false`.

---

## Timezone

`APP_TIMEZONE=Asia/Dhaka`. Stored UTC, displayed Dhaka. Sheet dates and times are
naive local values and are interpreted as Dhaka on import — getting this wrong
shifts every exam by six hours.

Hijri dates roll over at **Dhaka sunset**, not midnight, plus an admin-configurable
`hijri_offset_days` (−1/0/+1) in `settings`, because local moon sighting can differ
from any algorithmic calendar.

---

## Database conventions

- `utf8mb4_unicode_ci` everywhere. Never 3-byte `utf8`.
- Integers for anything score-like. No floats in scoring.
- Student-facing history uses `RESTRICT`, so exam records cannot be orphaned.
  Prefer `status = suspended|archived` over deletion.
- Explicit `#[Fillable]`; never `$guarded = []`.
- Migrations never touch the legacy `quiz_submissions` table.

---

## Status

`PROJECT_PLAN.md` §11 is the honest status list. Keep it truthful: do not mark
anything done that has not been run. If a feature is incomplete, say so there
rather than leaving a button that pretends to work.

Unbuilt sections show a disabled "পরবর্তী ধাপ" (later phase) nav item — do not wire
a fake page behind them. Not yet built: **live exam UI** (start/resume/autosave/submit
screens), results/answer sheets, leaderboards, regrade UI, manual mark adjustment UI,
blog, Ask Ustaz, Zakat calculator, Hijri calendar, and the student/legacy-result
migrations. The Quiz Builder, quiz import (CSV/XLSX) and the student `/exams` listing
foundation ARE built (Phase 6). `/exams` is informational only — it never starts or
debits an attempt; `QuizAttemptService` remains the only authority for that.
