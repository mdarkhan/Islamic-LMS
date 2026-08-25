# SECURITY.md — Masud Alimi Platform

This document records what was found in the legacy system, what must be done about it, and the
rules the new application enforces. It contains **no credentials** and never will.

---

## 0. Immediate action required

### A. Rotate the database password — do this first

`quiz-api.php` (lines 16–19) hard-codes the live MySQL host, database name, username and password
in plaintext, in a file inside the public web root. The file is in this project folder, has been
copied around, and must be assumed exposed.

**Rotate the MySQL user's password in cPanel now**, before any further work. Do not reuse it.
The new value goes only into `.env` on the server, which is never committed.

Until rotation is done, treat the current database as compromised: anyone who obtained that file
could read and write `quiz_submissions` directly.

### B. Make both Google Sheets private

The student sheet is publicly readable and contains **every student's plaintext password**
(finding L2). Anyone who has ever had the link — or found it in the page source — can download the
full credential list. Restrict both sheets to specific accounts.

### C. Force a password reset for every student

Because of B, every legacy password must be considered public. All imported accounts are created
with `force_password_change = 1` (`MIGRATION.md` §1).

---

## 1. Findings in the legacy system

| # | Severity | Finding | Fix |
|---|---|---|---|
| L1 | **Critical** | DB credentials hard-coded in a web-root PHP file | Rotate; `.env` only; gitignored |
| L2 | **Critical** | Student roll/name/guardian/**plaintext password** served from a public sheet to every browser | Sheet made private; credentials hashed in MySQL; forced reset |
| L3 | **Critical** | Answer key (`correctIndices`) shipped to the browser during the live exam | Key never serialised while an official attempt is open |
| L4 | **Critical** | Score computed in JS and POSTed; the API stores whatever it receives | Server-side scoring only; client scores rejected outright |
| L5 | High | No auth, no CSRF, `Access-Control-Allow-Origin: *` on a write endpoint | Session auth + CSRF + no wildcard CORS |
| L6 | High | Raw `PDOException` messages returned to the client | Generic error responses; detail to logs only |
| L7 | High | No duplicate-submission protection of any kind | Unique key + row lock + idempotent submit |
| L8 | Medium | Quiz password and leaderboard password stored in a public sheet | Both discarded; replaced by session auth and role checks |
| L9 | Medium | Identity self-asserted — client posts `roll`/`name`/`guardian` | Identity from the session; never from the request body |
| L10 | Medium | `quiz_id` written inconsistently, so results cannot be reliably grouped | Explicit reviewed mapping (`MIGRATION.md` §4.4) |
| L11 | Low | Connection used 3-byte `charset=utf8` | `utf8mb4` throughout |

### Consequence

L3 and L4 together mean any student could read the answers and submit any score for any name.
L2 means anyone could do so **as another student**. The legacy leaderboard is therefore not
trustworthy competition data, and migrated results are flagged accordingly
(`PROJECT_PLAN.md` §5.3).

---

## 2. Rules for the new application

### 2.1 Secrets

- All credentials come from `.env`. `.env` is gitignored; `.env.example` contains placeholders only.
- No credential appears in source, documentation, comments, logs, error output or audit records.
- The legacy `quiz-api.php` credentials are **never** copied into the new configuration. The
  rotated ones are entered directly on the server.
- `APP_DEBUG=false` in production. `APP_KEY` generated per environment, never shared.

### 2.2 Passwords

- Hashed with bcrypt (or argon2id) via Laravel's `Hash` facade. No plaintext column exists.
- The legacy importer hashes on read; the plaintext is never persisted or logged
  (`MIGRATION.md` §1).
- Login is rate-limited per roll **and** per IP. Failures are logged without the attempted password.
- `force_password_change` intercepts every authenticated route until the password is changed.
- Password reset tokens are single-use and expire.

### 2.3 Examination integrity — the core rule

**While an official attempt is open, the response payload contains no information about
correctness.**

Permitted in the question payload:

```
question id · question body · display order · option ids · option bodies
```

Forbidden until the attempt is graded *and* results are released:

```
is_correct · correct option ids · correct indices · marks per question · explanation
```

Per-question marks are withheld during a live official exam so students are not steered toward
mega questions — this reproduces deliberate legacy behaviour (`PROJECT_PLAN.md` §2.3).

Practice mode may reveal correctness immediately, because it is unranked and costs no points.

### 2.4 Trust boundary

The server never accepts any of these from the client:

```
user_id · roll · score · correctness · marks · point balance
quiz eligibility · attempt expiry · submission time
```

Each is derived server-side from the session, the database and the server clock. An attempt is
addressed by its id and then re-authorised against `auth()->id()` on every request — an attempt id
belonging to another student is a 403, never a silent read.

### 2.5 Time

The server clock is authoritative. `starts_at`, `expires_at`, `ends_at` and `result_release_at` are
re-checked server-side on every write. A write arriving after `expires_at` is rejected regardless of
what the browser's timer displayed. The client timer is presentation only.

### 2.6 Transactions and idempotency

- Point debit + attempt creation: one transaction, `SELECT … FOR UPDATE` on the user row.
- Resuming an attempt never debits again — guarded by `quiz_attempts.point_transaction_id`.
- Autosave is an upsert on `(attempt_id, question_id)`, so retries cannot duplicate.
- Submit is idempotent: submitting an already-submitted attempt returns the existing result rather
  than rescoring or re-charging.
- A failure anywhere in the chain rolls back both the point movement and the attempt.

### 2.7 Standard protections

CSRF on all state-changing requests · Eloquent/prepared statements everywhere (no string-built SQL)
· Blade auto-escaping, with `{!! !!}` permitted only for admin-authored content that has been
sanitised · mass-assignment guarded via explicit `$fillable` · authorisation via policies and
middleware, not inline checks · rate limiting on login, password reset and Ask Ustaz · generic
error pages, details to logs · security headers (`X-Content-Type-Options`, `X-Frame-Options`,
`Referrer-Policy`, HSTS where TLS terminates correctly) · session cookies `HttpOnly`, `Secure`,
`SameSite=Lax`, regenerated on login and invalidated on logout and on suspension.

### 2.8 Ask Ustaz

Validated, honeypot-checked, rate-limited, **emailed, and not stored**. No table, no retained queue
payload, no logging of the question body. Recipient from `USTAZ_EMAIL` via config — never
hard-coded. See `PROJECT_PLAN.md` §6 and `DATABASE_SCHEMA.md` §8.

### 2.9 Audit logging

Sensitive admin actions are recorded with actor, action, entity, before/after and timestamp. A
redaction list keeps password hashes, plaintext passwords and tokens out of `before`/`after`;
`AuditLogTest` asserts this. Implemented by `AuditLogger`; wired into student create/update/status/
password-reset, point credit/deduct/bulk, course & lesson create/update/delete, and student import
(summary counts only — never rows or passwords).

### 2.10 Application-layer specifics (phase 2)

- **Login does not leak account existence.** Credentials are verified (with a dummy hash check when
  no account matches, to avoid a timing oracle) before any "account suspended/inactive" message is
  shown. Wrong password and unknown roll return the identical generic error. Rate-limited per
  identifier+IP.
- **Temporary passwords** (admin create with "generate", and password reset) are shown once via a
  flash message for delivery, hashed immediately, and never persisted in plaintext or logged. A
  reset also deletes the student's `sessions` rows; suspending or archiving does the same.
- **Student import** hashes each password the instant the user is created, never writes an
  intermediate plaintext table, forces a password change on every imported account, and skips
  existing rolls rather than overwriting. The uploaded file is held in a private temp location
  between preview and confirm (not the session) and deleted the moment the import commits.
- **Forced password change** is enforced by middleware, not by hidden UI — every protected route
  redirects to the change screen until the flag clears.
- **Answer-key confidentiality carried into Eloquent**: `QuizOption::is_correct` is in the model's
  `$hidden` set, so it cannot leak into a serialised response by accident.

### 2.11 Phase 6 specifics (quiz builder & import)

- **Legacy passwords are never reused.** The student importer treats the legacy spreadsheet
  password as compromised (L2): it is read only to recognise the format, never used as the account
  password, never saved, never logged. Each imported account gets a fresh random temp password and
  a one-time `CredentialExport` CSV — private storage, unguessable name, deleted on download, 30-min
  TTL, gitignored, never audited. A test asserts the legacy password does not authenticate.
- **Throttle keys are canonical.** The login limiter keys on the normalised roll (Bengali↔Latin) or
  the trimmed/lower-cased email, so one account cannot obtain multiple throttle buckets by varying
  the spelling.
- **Quiz import files are answer-key-sensitive.** `ImportFileStore` keeps them in private storage
  with a random name, a 60-min TTL sweep (`imports:cleanup` + opportunistic prune), and deletes them
  on commit. The importer is transactional (all-or-nothing). Confirm **re-parses from the file** —
  the answer key is derived server-side, never trusted from the posted preview.
- **Legacy quiz password columns (P, V) are ignored** — detected, noted, never stored.
- **No runtime Google Sheets dependency.** Quizzes are imported from uploaded CSV/XLSX; the running
  app never fetches a sheet, so exams do not depend on a sheet staying reachable or public.
- **The student `/exams` page never carries the answer key.** It loads no options at all (only
  counts and schedule); a test inspects the rendered HTML for `is_correct` / option bodies /
  `correctOption` / `correctIndices` and asserts none are present.
- **Bulk point concurrency fails safe.** A debit that becomes insufficient after the pre-check
  (a concurrent drain) rolls the whole batch back and returns a Bengali validation message, never a
  500 or a partial movement.
- **Timezone integrity.** `config/app.php` now honours `APP_TIMEZONE=Asia/Dhaka` (it was silently
  UTC), so admin-entered exam schedules are interpreted in Dhaka rather than shifted six hours.

### 2.12 Phase 7–8 specifics (live exam, results, practice, leaderboards, regrade)

- **The live exam payload is allow-listed.** `ExamAttemptPresenter` emits only question id/body
  and option id/body — never question type, `is_correct`, `marks` or `explanation`. Withholding type
  lets every question use the same multi-select interaction without revealing answer cardinality.
  `AttemptReviewPresenter` (which
  *does* reveal the key) is used only for terminal attempts the caller has already release-gated.
- **Result release is enforced server-side on every path**, not by hiding buttons. A score,
  percentage, rank, correctness or answer sheet is exposed only when `Quiz::resultsReleasedAt($now)`;
  the student results controller `abort(403)`s an early answer-sheet request, and the per-quiz
  leaderboard `abort(404)`s until `Quiz::leaderboardVisibleAt($now)` (released AND admin-visible).
  Tests hit these routes directly before release and assert nothing leaks.
- **Ownership (IDOR).** Every student attempt route checks `attempt.user_id` against the session user;
  another student gets 403 on the answer sheet, practice attempt, submit and save.
- **Practice cannot leak the key.** `Quiz::practiceAvailableAt($now)` requires the official window
  closed *and* results released, enforced on start in `PracticeController` — so practice (which reveals
  answers on submit) can never be opened for an exam a student could still sit. Practice is untimed,
  free (no point ledger movement) and unranked.
- **Scores stay server-authoritative through regrade & adjustment.** `manual_adjustment`/`final_score`
  are written only by `QuizScoringService`; a manual adjustment is validated to `0..total` (refused,
  not clamped) and audited; a regrade recomputes from stored selections, preserves the manual delta and
  every terminal timestamp, and is one transaction. The ordinary question editor stays locked once
  official attempts exist — key changes go only through the regrade workflow, gated by `results.regrade`.
- **Leaderboard privacy.** Boards expose roll, name and score-derived figures only — never guardian,
  email, phone or point balance; a test asserts contact fields are absent from the rendered board.
- **CSV export** carries attempt-level scores only, never answer-level detail.

### 2.13 Phase 9 specifics (public site, CMS, Ask Ustaz, Zakat, calendar)

- **Ask Ustaz is email-only and never persisted.** The flow is validate → anti-spam → Mail →
  `USTAZ_EMAIL` → discard. There is deliberately no `ustaz_questions` / `inquiries` /
  `contact_messages` table (a test asserts none exists), the question is never audited or written
  to the app log, and it is never flashed back into the (MySQL) session — only name/email/mobile/
  subject are. A failed delivery returns a safe Bengali error and NEVER reports success. Anti-spam
  is free and server-side: honeypot field, minimum fill time, and a per-IP `throttle:5,10`.
- **The recipient is configured, never hard-coded.** `config('mail.ustaz_email')` ← `USTAZ_EMAIL`.
  SMTP credentials live only in the environment; the admin Settings page reports configured/not,
  never the values, and cannot edit the SMTP password / APP_KEY / DB credentials.
- **CMS content is sanitised.** Post bodies are Markdown rendered with raw HTML stripped and
  `javascript:`/`data:` links removed (`App\Support\Markdown`), so admin- or paste-authored content
  cannot execute a script, event handler or iframe. A test feeds `<script>`, `<iframe>` and a
  `javascript:` link and asserts none survive. Only `Post::scopePublic()` content is ever public —
  drafts, archived and future-scheduled posts 404.
- **Zakat inputs are never persisted or logged.** The calculator is stateless per request (no
  `zakat_calculations` table, no login); money maths uses BCMath decimal strings, never floats.
- **Public/admin boundaries hold.** Guests reach only home, articles, the Zakat calculator and Ask
  Ustaz; `/dashboard`, `/exams`, `/results`, `/practice`, `/admin` redirect to login (tests assert
  this). `robots.txt` disallows every private area and the authenticated layout is `noindex`.
- **Calendar reliability:** the Hijri date is a documented *tabular (civil)* calculation, not a
  moon-sighting claim — hence the admin `hijri_offset_days`. Sunset uses the offline built-in
  `date_sun_info` for the configured institutional location; no paid prayer-time API, no visitor
  geolocation.

---

## 3. Files that must never be committed

```
.env
quiz-api.php                 # contains the exposed credentials
legacy/quiz-api.php          # verbatim copy, same credentials
legacy/db/                   # database exports
storage/, vendor/, node_modules/, public/build/
```

`.gitignore` covers these. `legacy/quiz-api.reference.php` — a redacted copy safe to commit — is
provided so the legacy logic stays reviewable in version control without the secrets.

This is an active Git repository. Before every commit, verify the credential-bearing ignored files,
private exports and generated credentials remain untracked with `git status --ignored` as needed.

---

## 4. Deployment checklist

- [ ] MySQL password rotated; old one invalid
- [ ] Both Google Sheets set to private
- [ ] `.env` present on server, outside the document root, permissions `600`
- [ ] `APP_DEBUG=false`, `APP_ENV=production`, `APP_KEY` generated
- [ ] Document root points at `public/`, so `.env`, `storage/` and `vendor/` are unreachable
- [ ] HTTPS enforced; HSTS enabled
- [ ] `storage/` and `bootstrap/cache/` writable; nothing else world-writable
- [ ] Legacy `quiz-api.php` write path removed or the file taken offline
- [ ] Every imported student has `force_password_change = 1`
- [ ] Database backup taken and a restore verified
