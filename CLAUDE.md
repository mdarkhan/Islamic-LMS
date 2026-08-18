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
`C:\Users\mdark\tools\php83`. XAMPP itself is untouched and still provides
MariaDB.

```bash
"C:/Users/mdark/tools/php83/php.exe" artisan test
```

| Task | Command |
|---|---|
| Run tests | `php artisan test` |
| One suite | `php artisan test --testsuite=Feature` |
| Rebuild db | `php artisan migrate:fresh --seed` |
| Composer | `php "C:/Users/mdark/tools/php83/composer.phar" ...` |
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
  labels, buttons, both dashboards, auth, profile, exams, points, courses, and the
  **entire admin CRUD surface** (students, points, courses, lessons, quizzes +
  question builder, quiz import, audit log, shared components) are translated via
  `__('group.key')`, with files in `lang/bn/*.php` and `lang/en/*.php` (groups: `nav`,
  `ui`, `dashboard`, `auth`, `profile`, `exams`, `points`, `courses`, `admin`,
  `lessons`, `quizzes`, `students`, `audit`). Lesson titles, quiz questions/options,
  notices and other **content stay Bengali** regardless of locale — never localise
  content strings, only chrome (a course's actual name like "সীরাত" must keep showing
  even when the interface is set to English).
- Default locale is `bn` (`APP_LOCALE`). `SetLocale` middleware (web group) resolves the
  language per request: authenticated `users.locale` → session → app default. The
  `x-ui.locale-toggle` (in every layout header + the profile page) posts to
  `locale.update`, which saves to the user and the session.
- The site name **মাসউদ আলিমী** and the Arabic bismillah are never translated.
- New user-facing chrome must use `__()` keys, not hardcoded strings. `admin.php` holds
  generic cross-entity CRUD vocabulary (order, slug, publish/unpublish, delete confirms,
  status badge, temp-password reveal); entity-specific copy lives in its own file.

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

Phases 1–9 and the Phase 10 migration/production-readiness tooling are built. Actual production
imports and cPanel cutover are not complete because private exports, hosting inputs and explicit
production access have not been supplied. Keep `CODEX_TAKEOVER_AUDIT.md` and
`MIGRATION_RECONCILIATION.md` honest; never turn tooling readiness into a deployment claim.

The **secure live OFFICIAL exam** IS built (Phase 7): start/resume, per-question
autosave, the authoritative server timer, expiry finalisation and a released-score
summary. `ExamAttemptController` only orchestrates — the point debit, one-way terminal
states, expiry and scoring stay in `QuizAttemptService` / `QuizScoringService`. The
browser never receives the answer key (`ExamAttemptPresenter` allow-lists the payload),
never holds score authority, and a save past the deadline is refused (409) and the
attempt finalised as EXPIRED. Expired-but-abandoned attempts are swept by
`attempts:finalize-expired` (every-minute schedule). The result page shows the score
only once `resultsReleasedAt()`. The live screen is built so Practice Mode can reuse it.

The **post-exam & practice ecosystem** IS built (Phase 8): student `/results` history +
detailed answer sheets, Practice Mode, per-quiz and overall leaderboards, and the admin
results/adjustment/regrade surface. Key rules, all centralised:

- **Ranking lives only in `LeaderboardService`** — never re-derive a rank in a controller
  or Blade. Per-quiz order: `final_score` desc, `time_taken` asc, then a deterministic
  unique tiebreak (`submitted_at`, `id`) for display only. Overall order: total obtained
  desc, percentage desc, then user id. **Competition ranking** (1,2,2,4): two students
  share a rank only when equal on every dimension *before* the deterministic tiebreak.
  Ranking is computed live from stored `final_score`, so an adjustment or regrade reorders
  boards immediately — there is nothing cached to invalidate.
- **Eligibility everywhere:** official + terminal (`submitted`/`expired`) only; practice and
  `voided` never rank. **Best attempt per student** (`max_official_attempts > 1`).
- **Release/visibility gates are server-side and central.** A score, percentage, rank or
  answer sheet is exposed only when `Quiz::resultsReleasedAt($now)`; a per-quiz leaderboard
  needs `Quiz::leaderboardVisibleAt($now)` (released AND `leaderboard_visible`). Enforce in
  the controller with `abort_unless`/`abort(404)` — never rely on a hidden button.
- **Practice safety:** `Quiz::practiceAvailableAt($now)` = enabled, not draft, official
  window closed AND results released. Practice must never open while the key is still
  secret. Practice is **untimed** (`expires_at = null`; never reuse `ends_at`), free, unranked,
  repeatable, and reviewed immediately. Enforced on start in `PracticeController`.
- **Overall leaderboard** counts each student's best attempt per quiz where
  `quizzes.counts_toward_overall` AND the quiz's results are released. `counts_toward_overall`
  (quiz-level, admin-set) is orthogonal to the per-attempt `counts_toward_cumulative`
  (practice→0). "Possible" is summed from `total_marks_snapshot`.
- **Manual adjustment** goes through `ScoreAdjustmentService`: `final = calculated + manual`,
  validated to `0..total` (refused, not clamped), audited (`score_adjustments` + `result.adjusted`).
  `QuizScoringService::setManualAdjustment` is the only writer of `manual_adjustment`/`final_score`
  besides scoring.
- **Answer-key correction goes through `QuizRegradeService` only** (never by unlocking the
  question editor). It changes `is_correct`/`marks`/`type`/`explanation` — **never body text** —
  re-scores affected official attempts (preserving `manual_adjustment` and all terminal
  timestamps), recomputes `quiz.total_marks`, and records a `regrade_run` + per-attempt
  `regrade_entries` + `quiz.regraded` audit, all in one transaction. Preview never persists.
- **Historical snapshot:** none needed. Student selections are stored as immutable
  `quiz_answer_options.option_id` references and the builder scoring-lock freezes all
  question/option TEXT once official attempts exist, so an answer sheet always shows what the
  student actually saw while the *current* corrected key drives correctness.
- **`AttemptReviewPresenter`** is the reveal counterpart to `ExamAttemptPresenter` — only for
  terminal attempts the caller has already release-gated. Legacy attempts
  (`answer_details_available = false`) show "answers not preserved", never fabricated.

The **public knowledge platform** IS built (Phase 9): homepage, Blog/Fatwa CMS, notices, Ask
Ustaz, Zakat calculator and the calendar. Conventions, all centralised:

- **Settings go through `SettingService`** (typed, cached, default-backed) — never
  `Setting::where(...)` in a controller/Blade. `set()` ignores unknown keys and invalidates the
  cache. Decimals (rates/money) are returned as STRINGS for BCMath safety. Secrets (SMTP password,
  APP_KEY, DB creds) live in the environment only and are NEVER stored or shown here.
- **Post content is Markdown**, rendered by `App\Support\Markdown::render()` with raw HTML stripped
  and `javascript:`/`data:` links neutralised — the only safe way to output a body. Never
  `{!! $post->body !!}`. `Post::scopePublic()` (published AND `published_at <= now`) is the single
  public-visibility definition; a draft, archived or future-scheduled post is never public.
- **Ask Ustaz is email-only and NEVER persisted** (CLAUDE.md #1 still holds): validate → anti-spam
  (honeypot + min-fill-time + `throttle`) → `AskUstazQuestion` Mailable → `config('mail.ustaz_email')`
  → discard. A failed send returns a safe Bengali error and NEVER claims success; the question is
  never written to a table, an audit log, the session (only name/email/mobile/subject flash back),
  or the app log. There is no submission table — do not add one.
- **Zakat: `ZakatCalculatorService` is the authority** (BCMath decimal strings, never float money).
  Constants live there (Nisab 87.48 g gold / 612.36 g silver, 1 ভরি = 11.664 g, rate 0.025). The
  browser preview must mirror the same formula/constants. Financial inputs are NEVER persisted.
- **Calendar: `CalendarService` owns all date logic.** Gregorian (Dhaka), the *revised Bangladesh*
  Bangla calendar (Pohela Boishakh = 14 April; Choitro is 31 days when Feb of the epoch-year+1 is
  leap — NOT the West Bengal system), and the *tabular Islamic (civil)* Hijri calendar. The Hijri
  day rolls over at **local sunset** (`date_sun_info`, configured Dhaka lat/lng — never a hard-coded
  18:00), then `hijri_offset_days` (−1/0/+1) is applied. Both the sunset roll and the offset are
  added to the Julian Day Number and converted ONCE, so month/year boundaries can never become
  invalid. Freeze time in tests; never hard-code a day integer.
- **Notices** are window-governed by server time (`Notice::scopeActiveAt`), no cron. `scopeForAudience`
  matches the audience plus `all`. Homepage shows public/all; student dashboard shows students/all.
- **SEO/indexing:** the public `x-layout.base` accepts `description`/`canonical`/`ogImage`; the
  authenticated `x-layout.app` sets `:noindex`. `robots.txt` + `sitemap.xml` are dynamic
  (`SitemapController`) and the sitemap lists only `Post::scopePublic()` rows.
