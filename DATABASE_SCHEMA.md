# DATABASE_SCHEMA.md — Masud Alimi Platform

**Engine:** MySQL 8.0 / MariaDB 10.4+ · **Charset:** `utf8mb4` · **Collation:** `utf8mb4_unicode_ci`
**Application timezone:** `Asia/Dhaka` · **Storage timezone:** UTC (`DATETIME` columns written by
Laravel in UTC; presentation converts to Asia/Dhaka)

> Every table is `utf8mb4` so Bengali, Arabic and emoji all store correctly. The legacy connection
> used 3-byte `utf8`, which silently corrupts 4-byte characters — see `SECURITY.md` L11.

---

## 0. Conventions

- `id` — `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`.
- Timestamps — `created_at` / `updated_at` nullable `TIMESTAMP`.
- Foreign keys are declared with explicit `ON DELETE` behaviour. Student-facing history uses
  `RESTRICT` so exam records can never be orphaned by a careless delete.
- Money-like and score-like values are integers. No floats anywhere in scoring.
- Booleans are `TINYINT(1)`.
- Anything a human authored in Bengali is stored NFC-normalised (enforced in the application layer).

---

## 1. Identity & access

### `users`

| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED PK | |
| roll | VARCHAR(50) NULL | **UNIQUE**. Student roll number. NULL for staff accounts. |
| name | VARCHAR(150) | |
| guardian_name | VARCHAR(150) NULL | Father's / husband's name |
| email | VARCHAR(190) NULL | **UNIQUE**. Optional for students, required for staff |
| phone | VARCHAR(30) NULL | |
| password | VARCHAR(255) | bcrypt/argon2 hash. **Never plaintext** |
| status | ENUM('active','suspended','archived') | default `active` |
| force_password_change | TINYINT(1) | default 0 |
| points_balance | INT | default 0. Cached mirror of the ledger — see §2 invariant |
| is_legacy_import | TINYINT(1) | default 0 |
| legacy_import_batch_id | BIGINT UNSIGNED NULL FK→legacy_import_batches RESTRICT | Import provenance |
| legacy_source_key | CHAR(64) NULL UNIQUE | Canonical student fingerprint |
| last_login_at | TIMESTAMP NULL | |
| remember_token | VARCHAR(100) NULL | |
| created_at / updated_at | TIMESTAMP NULL | |

```
UNIQUE KEY users_roll_unique (roll)
UNIQUE KEY users_email_unique (email)
KEY users_status_index (status)
```

Suspension is preferred over deletion because attempts reference users with `RESTRICT`. `archived`
exists for students who have left but whose exam history must remain intact.

### `roles`, `permissions`, `role_user`, `permission_role`

Hand-rolled rather than a package — four small tables, no external dependency, and new roles are
pure data.

```
roles            id · name VARCHAR(50) UNIQUE · label VARCHAR(100) · timestamps
permissions      id · name VARCHAR(100) UNIQUE · label VARCHAR(150) · group VARCHAR(50)
role_user        role_id FK→roles ON DELETE CASCADE · user_id FK→users ON DELETE CASCADE
                 PRIMARY KEY (role_id, user_id)
permission_role  permission_id FK CASCADE · role_id FK CASCADE
                 PRIMARY KEY (permission_id, role_id)
```

Seeded roles: `super_admin`, `admin`, `student`. Adding `ustaz` or `editor` later is an insert into
`roles` plus rows in `permission_role` — **no schema change**, satisfying brief §6.

### Laravel standard tables

`password_reset_tokens`, `sessions`, `jobs`, `failed_jobs`, `cache` — stock Laravel definitions.
Sessions use the database driver so a suspended user can be logged out server-side.

---

## 2. Points

### `point_transactions` — the ledger, append-only

| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED PK | |
| user_id | BIGINT UNSIGNED FK→users RESTRICT | |
| type | ENUM('grant','deduction','adjustment','refund','import') | |
| amount | INT | **Signed.** `+` credits, `−` debits. Never zero |
| balance_after | INT | Balance immediately after this row. Audit trail |
| reference_type | VARCHAR(100) NULL | Morph type, e.g. `App\Models\QuizAttempt` |
| reference_id | BIGINT UNSIGNED NULL | |
| performed_by | BIGINT UNSIGNED NULL FK→users RESTRICT | Admin who acted; NULL if system |
| reason | VARCHAR(500) NULL | Mandatory for manual admin actions (app-enforced) |
| created_at | TIMESTAMP | |

```
KEY pt_user_id_index (user_id, id)
KEY pt_reference_index (reference_type, reference_id)
KEY pt_created_at_index (created_at)
```

**Invariant:** `users.points_balance == SUM(point_transactions.amount) WHERE user_id = users.id`.

Maintained by writing both inside one transaction, having first taken `SELECT … FOR UPDATE` on the
`users` row. The cached column exists so dashboards and eligibility checks avoid aggregating the
whole ledger. A `points:verify-balances` artisan command re-aggregates and reports drift.

Rows are **never updated or deleted**. A mistaken grant is corrected by an opposing `adjustment`
row, preserving history.

---

## 3. Catalogue

### `courses`

```
id · slug VARCHAR(120) UNIQUE · title VARCHAR(200) · description TEXT NULL
sort_order INT default 0 · is_published TINYINT(1) default 0 · timestamps
KEY courses_published_sort_index (is_published, sort_order)
```

Seeded from the legacy categories: `seerat`, `tafsir`, `jummah`, `halakah`. (`tafsir` is created
with zero lessons — see `PROJECT_PLAN.md` §4.2.)

### `lessons`

| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED PK | |
| course_id | BIGINT UNSIGNED FK→courses RESTRICT | |
| slug | VARCHAR(160) | **UNIQUE**. e.g. `seerat-24` |
| title | VARCHAR(200) | e.g. `সীরাত-২৪` |
| description | TEXT NULL | |
| summary | JSON NULL | Array of bullet strings |
| syllabus | TEXT NULL | |
| held_on | DATE NULL | Parsed date. NULL where the legacy label was `সংগৃহীত` |
| date_label | VARCHAR(100) NULL | Display label. Legacy import: original Bengali text preserved verbatim. Admin-authored: derived from `held_on` via `BengaliText::formatDateLabel()` — never typed by hand |
| duration_minutes | SMALLINT UNSIGNED NULL | Parsed |
| duration_label | VARCHAR(100) NULL | Original Bengali label preserved verbatim |
| media_provider | ENUM('google_drive','external','none') | default `google_drive` |
| media_url | VARCHAR(500) NULL | |
| media_file_id | VARCHAR(120) NULL | Drive file id, so the embed URL is derived not stored |
| sort_order | INT | |
| is_published | TINYINT(1) | default 1 |
| legacy_id | INT NULL | Original `COURSE_DATA.id`, for traceability |
| created_at / updated_at | | |

```
UNIQUE KEY lessons_slug_unique (slug)
KEY lessons_course_sort_index (course_id, sort_order)
KEY lessons_published_index (is_published)
KEY lessons_held_on_index (held_on)
```

Keeping both `held_on` and `date_label` is deliberate: the label is the only faithful record for
the 17 lessons whose date is the word "সংগৃহীত", and re-rendering a parsed date would change what
students have always seen. For lessons created or edited through the admin panel, the calendar
(`held_on`) is the only thing an admin sets — `date_label` is always recomputed from it on save, so
the two columns can never drift apart for non-legacy content. `media_provider` exists so a future
storage backend does not require a migration (brief §26).

### `lesson_resources`

```
id · lesson_id FK→lessons CASCADE · label VARCHAR(500) · url VARCHAR(500) NULL
kind ENUM('book','link','file','note') default 'link' · sort_order INT · timestamps
KEY lr_lesson_sort_index (lesson_id, sort_order)
```

`url` is **nullable** on purpose: all 25 legacy resources have the placeholder `"#"`, which is
imported as NULL. A resource with no URL renders as plain text, not a dead link.

---

## 4. Quizzes

### `quizzes`

| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED PK | |
| course_id | BIGINT UNSIGNED NULL FK→courses RESTRICT | |
| lesson_id | BIGINT UNSIGNED NULL FK→lessons SET NULL | Quizzes may outlive/precede a lesson |
| slug | VARCHAR(160) | **UNIQUE**. e.g. `seerat-26` |
| title | VARCHAR(200) | |
| description | TEXT NULL | |
| status | ENUM('draft','scheduled','published','archived') | default `draft` |
| practice_enabled | TINYINT(1) | default 0 |
| point_cost | SMALLINT UNSIGNED | default 1 |
| duration_seconds | INT UNSIGNED NULL | NULL = no per-attempt limit |
| starts_at | DATETIME NULL | |
| ends_at | DATETIME NULL | |
| result_release_at | DATETIME NULL | Independent of `ends_at` (brief §39) |
| results_released_at | DATETIME NULL | Set when an admin releases early |
| leaderboard_visible | TINYINT(1) | default 1 | Admin gate for the per-quiz leaderboard; combined with result release by `leaderboardVisibleAt()` |
| counts_toward_overall | TINYINT(1) | default 1 | Admin exclusion of trial/diagnostic quizzes from the cumulative leaderboard (brief §21). Orthogonal to the per-attempt `counts_toward_cumulative` |
| max_official_attempts | TINYINT UNSIGNED | default 1 |
| total_marks | INT UNSIGNED | default 0. Cached sum of active question marks |
| created_by | BIGINT UNSIGNED NULL FK→users RESTRICT | |
| legacy_import_batch_id | BIGINT UNSIGNED NULL FK→legacy_import_batches RESTRICT | Source workbook batch |
| legacy_source_key | CHAR(64) NULL UNIQUE | Workbook hash + sheet fingerprint |
| published_at | TIMESTAMP NULL | |
| created_at / updated_at | | |

```
UNIQUE KEY quizzes_slug_unique (slug)
KEY quizzes_status_index (status)
KEY quizzes_schedule_index (starts_at, ends_at)
KEY quizzes_course_index (course_id)
```

`total_marks` is recalculated whenever questions change and after every regrade; it is a cache, and
`SUM(quiz_questions.marks WHERE is_active)` remains the source of truth.

### `quiz_questions`

```
id · quiz_id FK→quizzes CASCADE · sort_order INT
type ENUM('single','multiple') default 'single'
body TEXT · explanation TEXT NULL
marks SMALLINT UNSIGNED default 1        -- the "Mega Question" value
is_active TINYINT(1) default 1
timestamps
KEY qq_quiz_sort_index (quiz_id, sort_order)
```

`type` is stored rather than inferred from the number of correct options, so an admin can author a
multiple-choice question that currently has one correct answer without it silently behaving as
single-choice. The `ENUM` leaves room for future types (brief §10).

`is_active` allows a bad question to be withdrawn without deleting stored student answers — regrade
then treats it as excluded and `total_marks` shrinks accordingly.

### `quiz_options`

```
id · question_id FK→quiz_questions CASCADE · sort_order INT
body TEXT · is_correct TINYINT(1) default 0
timestamps
KEY qo_question_sort_index (question_id, sort_order)
```

Every option has its own id (brief §10). The answer key lives here in `is_correct`, and **is never
serialised into a response while an official attempt is open.**

### `quiz_attempts`

| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED PK | |
| quiz_id | BIGINT UNSIGNED FK→quizzes RESTRICT | |
| user_id | BIGINT UNSIGNED FK→users RESTRICT | |
| kind | ENUM('official','practice') | |
| attempt_no | SMALLINT UNSIGNED | default 1 |
| status | ENUM('in_progress','submitted','expired','voided') | |
| started_at | DATETIME | |
| expires_at | DATETIME NULL | Server-computed deadline |
| submitted_at | DATETIME NULL | |
| time_taken_seconds | INT UNSIGNED NULL | |
| calculated_score | INT | default 0. Machine-computed |
| manual_adjustment | INT | default 0. Signed admin delta |
| final_score | INT | default 0. `calculated_score + manual_adjustment` |
| total_marks_snapshot | INT UNSIGNED | Marks available at submission |
| submission_seq | INT UNSIGNED NULL | Chronological serial within the quiz |
| counts_toward_cumulative | TINYINT(1) | default 1. Practice → 0 |
| is_legacy_import | TINYINT(1) | default 0 |
| answer_details_available | TINYINT(1) | default 1. Legacy rows → 0 |
| legacy_import_batch_id | BIGINT UNSIGNED NULL FK→legacy_import_batches RESTRICT | Source batch |
| legacy_source_key | CHAR(64) NULL UNIQUE | Stable normalized row fingerprint |
| legacy_quiz_id | VARCHAR(200) NULL | Original ambiguous source value, preserved |
| point_transaction_id | BIGINT UNSIGNED NULL FK→point_transactions RESTRICT | The debit |
| created_at / updated_at | | |

```
UNIQUE KEY qa_unique_attempt (quiz_id, user_id, kind, attempt_no)
KEY qa_quiz_status_index (quiz_id, status)
KEY qa_user_index (user_id)
KEY qa_leaderboard_index (quiz_id, kind, status, final_score, time_taken_seconds)
KEY qa_submitted_index (quiz_id, submitted_at)
```

**Why `final_score` is stored** rather than computed on read: the cumulative leaderboard aggregates
across every quiz a student has taken, and a generated/derived column would prevent the composite
leaderboard index above from being used. It is written only by `QuizScoringService` and
`QuizRegradeService`, both of which set all three score columns together in one transaction.

**Duplicate-attempt prevention.** `qa_unique_attempt` makes a second official attempt a database
error, not a race. MySQL has no partial unique index, so "at most one *in-progress* attempt" is
enforced by taking the user row lock in the same transaction that creates the attempt, then
re-checking. The unique key is the backstop, the lock is the fast path — JavaScript is never
relied upon (brief §45).

**Submission serial integrity.** `qa_unique_submission_seq` — `unique(quiz_id, submission_seq)` —
backs the per-quiz serial. `QuizAttemptService::finalise()` assigns the serial under a
`lockForUpdate()` on the quiz row, so two students finalising the same quiz concurrently cannot read
the same `MAX(submission_seq)` and collide; the unique index turns any residual race into an error.
Only official attempts get a serial (practice → `NULL`, and MySQL permits multiple NULLs in a unique
index). The serial is informational only — ranking never uses it.

`point_transaction_id` links the attempt to its debit, which makes "did this attempt already
charge the student?" a single nullable-FK check — that is what makes resume idempotent.

### `quiz_answers`

```
id · attempt_id FK→quiz_attempts CASCADE · question_id FK→quiz_questions RESTRICT
is_correct TINYINT(1) NULL         -- NULL until graded
marks_awarded INT default 0
answered_at DATETIME NULL          -- last autosave for this question
timestamps
UNIQUE KEY qan_attempt_question_unique (attempt_id, question_id)
KEY qan_question_index (question_id)
```

The unique key is what makes autosave idempotent: each save is an upsert on
`(attempt_id, question_id)`, so a retried or duplicated request cannot create a second row.

### `quiz_answer_options`

```
id · answer_id FK→quiz_answers CASCADE · option_id FK→quiz_options RESTRICT
UNIQUE KEY qao_answer_option_unique (answer_id, option_id)
KEY qao_option_index (option_id)
```

One row per selected option — this is what makes multi-answer questions and later regrading
possible. **This table is the reason regrading works**: because the student's raw selections are
retained independently of the answer key, changing `quiz_options.is_correct` and recomputing yields
a correct new score without any guesswork.

**Historical-snapshot decision (brief §4).** There are deliberately **no** answer-snapshot columns
(no stored copy of question/option text on the attempt). Two existing invariants make them
unnecessary: (1) selections are stored as immutable `option_id` references (`RESTRICT`), and
(2) the builder scoring-lock (`Quiz::scoringLocked()`) refuses every add/edit/delete of a question
once any official attempt exists, so question and option **text are frozen** from that moment. The
only sanctioned post-lock change is `QuizRegradeService`, which alters `is_correct` / `marks` /
`type` / `explanation` but **never body text**. So a rendered answer sheet always shows exactly what
the student saw, while correctness reflects the *current* corrected key — no snapshot required.

---

## 5. Regrading & manual adjustment

### `score_adjustments`

```
id · attempt_id FK→quiz_attempts CASCADE · admin_id FK→users RESTRICT
old_manual INT · new_manual INT · old_final INT · new_final INT
reason VARCHAR(500)          -- required
created_at
KEY sa_attempt_index (attempt_id)
```

### `regrade_runs`

```
id · quiz_id FK→quizzes CASCADE · admin_id FK→users RESTRICT
reason VARCHAR(500) · attempts_affected INT default 0
started_at DATETIME · completed_at DATETIME NULL
status ENUM('running','completed','failed')
created_at
```

### `regrade_entries`

```
id · regrade_run_id FK→regrade_runs CASCADE · attempt_id FK→quiz_attempts CASCADE
old_calculated INT · new_calculated INT · old_final INT · new_final INT
KEY re_run_index (regrade_run_id)
```

**Regrade contract.** Recompute `calculated_score` from stored answers against the current key;
leave `manual_adjustment` untouched; set `final_score = calculated_score + manual_adjustment`. An
admin's +2 goodwill mark therefore survives an answer-key correction (brief §20). The whole run is
one transaction with a `regrade_entries` row per attempt, so the before/after of every score is
recoverable.

---

## 6. Content

### `posts`

```
id · slug VARCHAR(200) UNIQUE · post_category_id FK→post_categories RESTRICT
title VARCHAR(250) · excerpt TEXT NULL · body LONGTEXT
featured_image VARCHAR(500) NULL · author_id FK→users RESTRICT
status ENUM('draft','published','archived') default 'draft'
published_at DATETIME NULL
seo_title VARCHAR(250) NULL · seo_description VARCHAR(500) NULL
timestamps
KEY posts_status_published_index (status, published_at)
KEY posts_category_index (post_category_id)
```

### `post_categories`

```
id · slug VARCHAR(120) UNIQUE · name VARCHAR(150) · sort_order INT · timestamps
```

Seeded: Fatwa, Questions & Answers, Seerat, Tafsir, Aqidah, Family, Ibadah, General.

### `notices`

```
id · body VARCHAR(1000) · is_active TINYINT(1) default 1
starts_at DATETIME NULL · ends_at DATETIME NULL · priority SMALLINT default 0
audience ENUM('public','students','all') default 'all'
created_by FK→users RESTRICT · timestamps
KEY notices_active_window_index (is_active, starts_at, ends_at)
```

Replaces the hard-coded `GLOBAL_NOTICE` constant (brief §34). The existing notice text is seeded.

### `settings`

```
key VARCHAR(120) PRIMARY KEY · value TEXT NULL · type VARCHAR(20) default 'string'
group VARCHAR(50) NULL · updated_by FK→users RESTRICT NULL · updated_at TIMESTAMP
```

Known keys: `hijri_offset_days` (−1/0/+1), `gold_price_per_gram`, `silver_price_per_gram`,
`nisab_basis` (`gold`|`silver`), `zakat_rates_updated_at`, `site_title`, `telegram_url`.
`updated_at` is what drives the "last updated" stamp the brief requires on reference rates (§30).

---

## 7. Audit log

### `audit_logs`

```
id · user_id FK→users RESTRICT NULL          -- actor
action VARCHAR(100)                          -- e.g. 'quiz.answer_key_changed'
auditable_type VARCHAR(100) NULL · auditable_id BIGINT UNSIGNED NULL
before JSON NULL · after JSON NULL
ip_address VARCHAR(45) NULL · user_agent VARCHAR(500) NULL
created_at TIMESTAMP
KEY al_auditable_index (auditable_type, auditable_id)
KEY al_user_created_index (user_id, created_at)
KEY al_action_index (action)
```

Logged actions include student created/suspended, password reset, points added/deducted, quiz
edited, correct answer changed, regrade triggered, score manually adjusted, quiz published.

**`before`/`after` never contain password hashes, plaintext passwords or remember tokens.** The
writer applies a redaction allow-list; this is asserted by a test.

---

## 8. Legacy import provenance

### `legacy_import_batches`

```text
id · type VARCHAR(40) · source_name VARCHAR(255) · source_sha256 CHAR(64)
status ENUM('running','completed','failed')
source_rows · valid_rows · imported_rows · skipped_rows · failed_rows
summary JSON NULL · completed_at TIMESTAMP NULL · timestamps
UNIQUE (type, source_sha256)
```

Only the basename and hash are stored, never source contents or credentials. User/quiz/attempt
fingerprints are nullable for native records and unique when present. Re-running the same source
therefore reports existing rows instead of duplicating them.

---

## 9. What is deliberately absent

- **No table for Ask Ustaz submissions.** Questions are emailed and never persisted (brief §29).
  Do not add `ustaz_questions`, `inquiries` or `contact_messages`.
- **No plaintext password column** anywhere, including the legacy import path.
- **No score column writable from a request.** No table stores a client-supplied score.

---

## 10. Legacy table

`quiz_submissions` (the legacy table) is **left in place, untouched and not dropped** by any
migration. Migration reads from it; a rollback of the new schema leaves it intact. See
`MIGRATION.md`.

---

## 11. Index rationale summary

| Query | Index used |
|---|---|
| Login by roll | `users_roll_unique` |
| Student point history (paginated) | `pt_user_id_index (user_id, id)` |
| Quiz leaderboard | `qa_leaderboard_index (quiz_id, kind, status, final_score, time_taken_seconds)` |
| Cumulative leaderboard | `qa_user_index` + aggregate over `counts_toward_cumulative` |
| "Is there a live quiz?" | `quizzes_schedule_index (starts_at, ends_at)` |
| Resume an attempt | `qa_unique_attempt` |
| Autosave upsert | `qan_attempt_question_unique` |
| Published lesson listing | `lessons_course_sort_index` |
| Blog index | `posts_status_published_index` |

Leaderboards are paginated; no endpoint returns an unbounded result set (brief §46).
