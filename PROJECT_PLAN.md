# PROJECT_PLAN.md — Masud Alimi Islamic Learning & Examination Platform

**Status:** Steps A–E complete, plus the first application-layer phase. Domain hardening,
authentication, the design system, and the student & admin management interfaces (students,
points, courses, lessons, import, audit) are built and tested (**133 passing tests**). The quiz
builder, exam engine UI, results, leaderboards and public content modules have not started.

**Last updated:** 2026-08-13

---

## 1. Scope of this document

This is the living plan and audit record. Section 11 tracks what is actually built versus what is
still outstanding. Nothing is marked done here unless it exists in the repository and has been run.

Companion documents:

| File | Purpose |
|---|---|
| `DATABASE_SCHEMA.md` | Normalised MySQL schema, indexes, invariants |
| `MIGRATION.md` | How legacy data moves into the new schema, and what cannot be reconstructed |
| `SECURITY.md` | Threat model, the vulnerabilities found, mandatory rules |
| `DEPLOYMENT.md` | cPanel deployment runbook |
| `CLAUDE.md` | Conventions for future engineering sessions |

---

## 2. Legacy system as found

Two files constitute the entire production application:

```
masudalimi.html   162,644 bytes   1,964 lines   single-file SPA (vanilla JS, no build step)
quiz-api.php        4,051 bytes      95 lines   single-file PHP/PDO endpoint
```

Both have been preserved byte-identically in `legacy/` (SHA-256 verified). The originals at the
repository root are untouched — the live site continues to run on them until cutover.

### 2.1 Runtime architecture

```
Browser (masudalimi.html)
  ├── Google Sheets gviz JSON ──► quiz questions + answer key + schedule + password
  ├── Google Sheets gviz JSON ──► student roll / name / guardian / PLAINTEXT PASSWORD
  └── quiz-api.php ─────────────► cPanel MySQL  (table: quiz_submissions)
```

There is no server-side application logic beyond an insert and a select. Every rule that matters —
authentication, scoring, eligibility, timing — is enforced in client-side JavaScript only.

### 2.2 Data sources

**Quiz workbook** (`15xgI5Q8...GnWY`) — one tab per quiz, plus a `Live` tab holding the currently
running exam. 23 columns, A–W:

| Col | Idx | Meaning | Col | Idx | Meaning |
|---|---|---|---|---|---|
| A | 0 | Question | N | 13 | Correct Answer |
| B–M | 1–12 | Option 1 … Option 12 | O | 14 | Timer (seconds) |
| | | | P | 15 | Password |
| | | | Q–T | 16–19 | Start Date / Start Time / End Date / End Time |
| | | | U | 20 | Exam Name |
| | | | V | 21 | Leaderboard Password |
| | | | W | 22 | Mega Question (custom marks) |

Config is read from the **second** row (index 1) when any of O/P/Q/R/U are populated there,
otherwise from the first row. Questions start from that same row onward, filtered to rows with a
non-empty column A that is not the literal header `Question` / `প্রশ্ন`.

**Student workbook** (`18Ua7znB...Daxz`) — `Roll No.`, `Name`, `Father's/Husband's Name`,
`Password`. Fetched **into the browser in full, in plaintext**, on every login attempt.

### 2.3 Behaviour worth preserving

These are the parts of the legacy UX that work well and must survive the rebuild:

- **Multi-answer questions.** Correct Answer accepts `1`, `1, 2`, `1, 2, 3`. Scoring is exact-set
  match — partial credit is never awarded.
- **Mega questions.** Column W overrides the per-question mark (1 → 5, 10, …). Deliberately hidden
  during the live exam (`megaWrapperClass = ''`) and only revealed in review, so students are not
  steered toward high-value questions. **This behaviour is intentional and must be kept.**
- **Bengali numeral tolerance.** Roll numbers, passwords, correct-answer values and mega marks are
  all run through `parseBanglaNumber()` so `২` and `2` are equivalent.
- **Bengali option labels** ক, খ, গ, ঘ … up to 12 options.
- **Flexible sheet date parsing** — `Date(y,m,d)` objects, `YYYY-MM-DD`, and `DD/MM/YYYY`, plus
  12/24-hour times with am/pm.
- **Three-calendar widget** — Hijri, Bangla (Pohela Boishakh anchored, Bangla Academy month
  lengths, leap-year aware), and Gregorian, all rendered in Bengali numerals for Asia/Dhaka.
- **Resume after reload** via `localStorage`, keyed on an expiry timestamp.
- **Submit-locks-answers** — once জমা দিন is pressed, `isLocked` prevents further edits.
- **Countdown states** — waiting / running / ended, with the button restyled per state and a
  Telegram link surfaced once the quiz closes.
- **Leaderboard PDF export** via html2canvas + jsPDF, with a dynamically sized long page.
- **Submission serial** — chronological order of submission per quiz, shown alongside rank.
- **Results embargo** — leaderboard and answer key are withheld until `GLOBAL_END_TIME`.
- **Practice mode** on archived quizzes, which skips the payment prompt and does not submit.
- **Dark mode**, respecting `prefers-color-scheme`.

### 2.4 Scoring and ranking rules (to be reproduced server-side)

```
per question:  selected set == correct set  →  award q.points (default 1)
               otherwise                    →  award 0        (no partial credit)
ranking:       score DESC, then timeTaken ASC
serial:        chronological submission order, numbered per quiz
```

---

## 3. Security audit of the legacy system

Full detail and remediation rules are in `SECURITY.md`. Summary of what was found:

| # | Severity | Finding |
|---|---|---|
| L1 | **Critical** | Live MySQL username, database name and password are hard-coded in `quiz-api.php` (lines 16–19). The file is in the web root. **These credentials must be rotated.** |
| L2 | **Critical** | The entire student table — roll, name, guardian and **plaintext password** — is served from a publicly-readable Google Sheet and downloaded into every visitor's browser. Anyone can harvest every student credential. |
| L3 | **Critical** | The answer key (`correctIndices`) is shipped to the browser with the questions **while the exam is live**. Any student can read the answers from DevTools. |
| L4 | **Critical** | The score is computed in JavaScript and POSTed. `quiz-api.php` stores whatever number it receives. Any student can submit any score for any name, on any quiz. |
| L5 | High | `quiz-api.php` has no authentication, no CSRF protection, and `Access-Control-Allow-Origin: *` — it is a fully open write endpoint. |
| L6 | High | Raw `PDOException` messages are returned to the client, leaking schema and connection detail. |
| L7 | High | Nothing prevents duplicate submissions. No unique constraint, no attempt record, no idempotency key. |
| L8 | Medium | The quiz password (col P) and leaderboard password (col V) are parsed from a public sheet — they are not secrets. |
| L9 | Medium | Identity is self-asserted: the client posts `roll`/`name`/`guardian` as free text. Nothing binds a submission to a verified account. |
| L10 | Medium | `quiz_submissions.quiz_id` is populated inconsistently (see §4.1), so historical results cannot be reliably grouped. |
| L11 | Low | Connection uses `charset=utf8` (3-byte) rather than `utf8mb4`, so 4-byte characters are corrupted. |

L1–L4 mean the current leaderboard **cannot be treated as trustworthy competition data.** This is
recorded here because it affects how legacy results should be presented after migration (§5.3).

---

## 4. Data-integrity findings

### 4.1 `quiz_id` is ambiguous in the legacy table

The same column carries two different kinds of value depending on how the quiz was taken:

- A **live** submission stores `state.quiz.quizId = QUIZ_NAME_FROM_SHEET` — the free-text *Exam
  Name* from cell U2, e.g. `সীরাত`.
- The **archive leaderboard** filters by the derived slug, e.g. `seerat-24`.

So a historical row for Seerat-24 may be stored under `সীরাত` but is looked up under `seerat-24`.
The legacy UI papers over this with a three-way `||` comparison in `renderLeaderboard()`. Any
migration must therefore build an explicit mapping table from the distinct `quiz_id` values
actually present in the production database — this cannot be derived from the source files alone.
**Requires a database export to resolve.** See `MIGRATION.md` §4.

### 4.2 Course catalogue gaps

All 42 hard-coded lessons were extracted programmatically to
`legacy/extracted/course-data.json` (no manual transcription). Counts:

| Category | Lessons | Titles present |
|---|---|---|
| seerat | 25 | সীরাত-০১ … সীরাত-২৫ |
| halakah | 14 | হালাকাহ-০১…০৫, ০৭…১৫ (**০৬ absent**, explicitly commented as omitted) |
| jummah | 3 | জুমার বয়ান-০১ … ০৩ |
| tafsir | **0** | — |

Inconsistencies to resolve with the site owner **before** seeding:

1. **`tafsir` has a category filter button on the homepage but zero lessons.** The quiz workbook
   contains a `tafseer-1` tab. Either the Tafsir lessons were never added to the HTML, or the
   course has not started. Content is missing and cannot be invented.
2. **Quizzes exist beyond the lesson list.** The workbook has `Seerat-27`, `seerat-26`; the HTML
   stops at সীরাত-২৫. Two quizzes have no corresponding lesson record.
3. **হালাকাহ-০৬ is missing** from lessons; if a `halakah-6` quiz tab exists it will have no lesson.
4. **22 of 42 lessons** carry the placeholder description
   *"এই ক্লাসের বিস্তারিত তথ্য ও কুইজ শীঘ্রই আপডেট করা হবে।"*
5. **Every one of the 25 resource entries has `url: "#"`** — no resource link has ever been set.
   The labels (book name and author) are real and worth keeping; the URLs are not.
6. **17 lessons have no resources at all**; only 7 have a syllabus; only 2 have a summary.
7. **Halakah and Jummah lessons have no real date** — `date` is the literal string `সংগৃহীত`
   ("collected"). Only the 25 Seerat lessons have parseable dates (০২ জানুয়ারি ২০২৬ → ১৭ জুলাই ২০২৬).
8. Dates and durations are **display strings in Bengali numerals**, not machine values
   (`"২ ঘণ্টা ১৫ মিনিট"`). Both a parsed value and the original label will be stored — see
   `MIGRATION.md` §5.

None of these are invented data. Where content is genuinely absent it stays absent and is reported.

### 4.3 Unicode normalisation

Bengali য়, ড়, ঢ় each have a precomposed form (U+09DF, U+09DC, U+09DD) and a decomposed form
(base + nukta U+09BC). They render identically but compare unequal. The legacy file happens to be
internally consistent, so this is **not** a live bug — but text authored in Google Sheets will not
reliably match text authored elsewhere. **The importer must NFC-normalise every incoming string
before comparison or slug derivation.** (Note: NFC *decomposes* these Bengali codepoints, because
they carry composition exclusions — so normalising can make a string longer. That is correct.)

---

## 5. Target architecture

### 5.1 Stack

```
Laravel 13 (PHP 8.3+)      Blade + Alpine.js (+ Livewire where it earns its place)
MySQL 8 / MariaDB 10.4+    utf8mb4_unicode_ci                  Asia/Dhaka
Tailwind CSS via Vite
```

**Hosting note:** Laravel 13 requires **PHP 8.3+**. This must be confirmed available in cPanel
before deployment (`DEPLOYMENT.md` §1). PHP 8.2 left security support in December 2025, so 8.3 is
the correct floor regardless — but if the host cannot provide it, pinning to an older Laravel is a
decision to take deliberately, not a silent workaround.

No SPA framework. No Node runtime in production — assets are compiled locally and the built
`public/build` directory is uploaded. Chosen to match the stated cPanel constraint.

### 5.2 Non-negotiable server-side rules

These follow directly from the audit and are enforced in code, not convention:

1. The answer key is **never** serialised into any response while an official attempt is open.
   Question payloads expose id, body, options and display metadata only.
2. Scores are computed **only** by `QuizScoringService` on the server, from rows stored in
   `quiz_answers`. No score is ever accepted from a request.
3. Server clock is authoritative for start, expiry and result release. The browser timer is
   presentation only, and every write is re-checked against `now()` server-side.
4. Point debit and attempt creation happen in **one** database transaction with a row lock on the
   user; resuming an existing attempt never debits again.
5. Identity comes from the authenticated session. `user_id` is never read from the request body.

### 5.3 Legacy results are marked, not laundered

Because of L2–L4, historical scores were verifiable by nobody. Migrated rows are flagged
`is_legacy_import = true, answer_details_available = false` and the student result screen states
plainly that the detailed answer sheet does not exist for those exams. No fabricated answer rows
are ever created. Whether legacy results should count toward the cumulative leaderboard is a
decision for the site owner (§12, D3) — the schema supports either choice via a per-attempt
`counts_toward_cumulative` flag.

### 5.4 Module map

```
Domain/Identity      users, roles, permissions, sessions, password reset
Domain/Points        PointService  · ledger · admin grants · balance invariant
Domain/Catalog       courses, lessons, resources, search & filter
Domain/Quiz          QuizBuilder · QuizImportService · QuizAttemptService
                     QuizScoringService · QuizRegradeService · LeaderboardService
Domain/Content       posts (blog/fatwa/Q&A), notices, settings
Domain/Support       CalendarService (Hijri sunset rollover), ZakatCalculator,
                     AskUstazMailer (no persistence), AuditLogger
```

---

## 6. Ask Ustaz — persistence prohibition

Submissions are validated, rate-limited, honeypot-checked and **emailed only**. There is no
`ustaz_questions` table, no queue payload retained after send, and the question body is not written
to the application log. The recipient comes from `config('mail.ustaz_address')`, backed by
`USTAZ_EMAIL` in `.env`. This is a hard requirement and is called out in `CLAUDE.md` so it is not
"helpfully" added back by a later session.

---

## 7. Hijri date handling

The legacy widget subtracts a flat 24 hours from the current time before formatting with
`islamic-umalqura`, which is a blunt approximation of Bangladesh moon sighting.

Replacement: compute Dhaka sunset for the current date astronomically; the Hijri day advances at
sunset rather than at midnight. On top of that, an admin-configurable offset of −1 / 0 / +1 days is
applied and stored in `settings` (`hijri_offset_days`), because local moon sighting can differ from
any algorithmic calendar. Both the sunset calculation and the offset are unit-testable and the
offset is surfaced in the admin panel with the last-changed date.

---

## 8. Point system replaces the Hadia prompt

The "কুইজ ফি জমা দিয়েছেন কি?" self-declaration modal is removed entirely — it enforced nothing.

Replacement: each quiz carries `point_cost` (default 1). Starting an official attempt debits the
balance inside the attempt-creation transaction and writes a `point_transactions` row. Admins grant,
deduct and bulk-grant points with a mandatory reason. Balance is derived from the ledger, with a
cached `users.points_balance` maintained in the same transaction and a
`points:verify-balances` console command to detect drift.

---

## 9. Testing strategy

Critical paths that must be green before any module is called complete:

- **Auth** — valid / invalid login, suspended user rejected, forced password change intercepts.
- **Points** — grant, deduct, insufficient balance blocks start, resume does not re-debit,
  failure inside the transaction rolls back both sides.
- **Quiz lifecycle** — before start, active, expired; multi-answer; custom marks; unauthorised
  attempt; duplicate submit is idempotent; autosave; resume.
- **Scoring** — exact-set match, wrong answer, partial selection scores zero, unanswered scores
  zero, mega marks applied.
- **Regrade** — answer key change recalculates, manual adjustment survives, leaderboard updates.
- **Practice** — no debit, no leaderboard effect, no cumulative effect.
- **Authorisation** — student cannot reach admin routes; role gates enforced.

---

## 10. Implementation order

| Phase | Contents | State |
|---|---|---|
| 0 | Inspection, audit, schema, migration strategy, legacy preservation | **Done** |
| 1 | Laravel scaffold, `.env.example`, local toolchain | **Done** (base layout / design tokens outstanding) |
| 2 | Migrations, models, factories, roles/permissions seed | **Done** |
| 3 | Auth, student accounts, admin student manager | **Done** (login, forced password change, roles/permissions, student CRUD + CSV/XLSX import) |
| 4 | Point ledger + admin point manager | **Done** (grant / deduct / bulk, ledger views) |
| 5 | Courses / lessons + catalogue UI + course seeder from extracted JSON | **Done** (student archive + admin course/lesson CRUD with inline resources) |
| 6 | Quiz builder, Sheet/CSV/XLSX importer with row-level validation | Student importer **done**; quiz builder & Sheet quiz import not started |
| 7 | Quiz engine: eligibility, transaction, autosave, resume, submit | Not started |
| 8 | Results, answer sheets, leaderboards, cumulative leaderboard | Not started |
| 9 | Regrading + manual score adjustment + audit log | Not started |
| 10 | Practice mode | Not started |
| 11 | Blog/Fatwa CMS, notices, settings | Not started |
| 12 | Ask Ustaz (email-only), Zakat calculator, calendar service | Not started |
| 13 | Legacy data migration + verification | Not started |
| 14 | Accessibility pass, responsive QA, performance pass | Not started |

---

## 11. Honest status

### Built, run and verified in this repository

**Legacy preservation & extraction**
- `legacy/` — byte-identical copies of both originals (SHA-256 checked).
- `legacy/quiz-api.reference.php` — redacted, committable copy with the vulnerabilities annotated.
- `legacy/extracted/course-data.json` — all 42 lessons extracted programmatically; Bengali preserved
  exactly, NFC-normalised slugs derived for all 42.
- `.gitignore` verified: the only two files containing the legacy secrets are both excluded.

**Application foundation**
- Laravel 13.25 on PHP 8.3.33 (side-by-side runtime; XAMPP untouched), Composer 2.10.2.
- Full schema — **31 tables, all `utf8mb4_unicode_ci`**. Bengali + Arabic + emoji round-trip
  verified against the live database (fixes legacy finding L11).
- Models with explicit `#[Fillable]`; `quiz_options.is_correct` marked `#[Hidden]` so the answer
  key cannot leak into a serialised response by accident.
- `PointService` — append-only ledger, row-locked, balance invariant, drift detection.
- `QuizScoringService` — server-side scoring, exact-set match, custom mega marks, regrade-safe.
- `QuizAttemptService` — eligibility, transactional debit, idempotent autosave / resume / submit,
  deadline clamped to the exam window.
- `LegacyCourseImporter` + `BengaliText` — imports the real catalogue with dry-run preview, parses
  Bengali dates and durations, reports content gaps instead of inventing content.
- Seeders: roles/permissions, settings, and the 42 migrated lessons.

### Application layer (phase 2)

- **Domain hardening**: attempt terminal states are one-way and a late submit is recorded as
  expired with authoritative timestamps; submission serial is assigned under a quiz-row lock with a
  `unique(quiz_id, submission_seq)` backstop; `startOfficial` creates the attempt then debits with
  it as an immutable reference (no ledger row is ever mutated); `saveAnswer` fails closed on foreign
  options and single-choice over-selection.
- **Design system**: Tailwind v4 + Alpine, a Blade component library (button, field, input, select,
  card, badge, alert, table, modal, breadcrumbs, pagination, empty state, stat, nav), light/dark via
  semantic tokens, `prefers-reduced-motion`, self-hosted Bengali/Arabic/Latin fonts, mobile-first.
- **Auth & authorisation**: one login screen (roll or email), rate limiting, generic error that does
  not leak account existence, forced temp-password change, `role:` / `perm:` middleware, super_admin
  bypass.
- **Student area**: dashboard (real metrics), profile (contact + password, identity read-only),
  course archive with filter/search, lesson detail, point history.
- **Admin area**: dashboard, student CRUD, CSV/XLSX import (upload → preview → confirm; passwords
  hashed at the boundary, forced change, existing rolls skipped), point management (grant / deduct /
  bulk all-or-nothing), course & lesson management with inline resources and honest gap flags, audit
  log with secret redaction.

**Tests: 133 passing, 331 assertions, on MySQL** (not sqlite — the ledger and attempt creation
depend on `lockForUpdate`). Adds the phase-1 hardening regressions plus auth, forced password change,
authorisation (student↔admin isolation, permission enforcement, super_admin bypass), student
management, point management, student import, course/lesson CRUD, student course access, and audit
redaction — on top of the domain suite (points, scoring, attempts, Bengali parsing, legacy import).

**Manual browser QA** (via the in-app browser): public home, login (roll + email), admin dashboard,
students list, lesson editor (Alpine resource rows), student dashboard/profile/courses/points, and
lesson detail all render with correct Bengali/Arabic and real data; no console errors; no horizontal
overflow at 375px; mobile nav present; dark-mode tokens switch correctly.

Independent cross-check: the importer's gap report reproduced the audit's numbers exactly
(22 placeholder descriptions, 17 undated lessons, 17 without resources, 35 without syllabus,
25 resources with no URL, `tafsir` empty), which were derived separately in §4.2.

### Not built

Quiz builder, Google Sheet quiz importer, live exam UI, results & answer sheets, leaderboards
(per-quiz and cumulative), regrade UI, manual score-adjustment UI, blog/Fatwa CMS, notices admin,
Ask Ustaz, Zakat calculator, Hijri calendar service, and the student + legacy-result data
migrations. The `AuditLogger`, `settings` and `notices` tables exist and are used where relevant,
but their dedicated admin screens (beyond the audit log viewer) are not built.

---

## 12. Decisions taken and still open

**D1 — Local PHP runtime. Resolved.** Side-by-side PHP 8.3.33 installed at
`%LOCALAPPDATA%\php83` with Composer 2.10.2; both downloads checksum/signature-verified. XAMPP and
its PHP 8.0 are untouched and still provide MariaDB. Reversible by deleting the folder.

**D3 — Legacy results in the cumulative leaderboard. Resolved:** included, flagged as legacy with
no answer sheet available. Implemented via `quiz_attempts.counts_toward_cumulative` +
`is_legacy_import`, so the opposite choice remains a one-line change.

**D2 — Production database export. Still needed.** Resolving the `quiz_id` ambiguity (§4.1) and
sizing the legacy result migration needs a dump of `quiz_submissions` (structure + data). No
credentials required — a phpMyAdmin export placed in `legacy/db/` is enough (gitignored).
**This blocks migration #4 only**; everything else can proceed without it.

**D4 — Missing Tafsir content** (§4.2). The course exists as a homepage filter with zero lessons.
Needed from the site owner; it will not be fabricated.

**D6 — cPanel PHP version.** Laravel 13 requires PHP 8.3+. Confirm the host offers it
(`DEPLOYMENT.md` §1) before the first deploy.

**D5 — Environment values** at deployment: rotated MySQL credentials, SMTP settings, `USTAZ_EMAIL`,
production domain. Placeholders are in `.env.example`; development is not blocked on these.
