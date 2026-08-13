# MIGRATION.md — Legacy → New Platform

Four independent migrations, each with a dry-run preview, each reversible, none destructive.

| # | Source | Target | Reconstructable? |
|---|---|---|---|
| 1 | Student Google Sheet | `users` | Fully |
| 2 | Quiz workbook tabs | `quizzes` / `quiz_questions` / `quiz_options` | Fully |
| 3 | `COURSE_DATA` in `masudalimi.html` | `courses` / `lessons` / `lesson_resources` | Fully (already extracted) |
| 4 | `quiz_submissions` table | `quiz_attempts` | **Partially — see §4.3** |

**Golden rule:** no legacy source is modified or dropped. `quiz_submissions` survives migration and
survives a rollback of the new schema.

---

## 0. Order of operations

```
1. Back up the production database (DEPLOYMENT.md §Backup). Verify the dump restores.
2. Import students        → users must exist before attempts can reference them
3. Import courses/lessons → from legacy/extracted/course-data.json
4. Import quizzes         → per workbook tab; lessons linked by slug
5. Import legacy results  → last, because it depends on 2 and 4
6. Run verification (§5). Only then consider retiring the legacy endpoint.
```

Every importer follows the same shape:

```
parse → normalise (NFC) → validate → PREVIEW (no writes) → confirm → transactional commit → report
```

---

## 1. Students → `users`

**Source:** sheet `18Ua7znB…Daxz`, columns `Roll No.`, `Name`, `Father's/Husband's Name`, `Password`.

**Mapping**

| Sheet | Column | Rule |
|---|---|---|
| Roll No. | `roll` | NFC-normalised, trimmed, Bengali numerals → Latin |
| Name | `name` | Trimmed, preserved as authored |
| Father's/Husband's Name | `guardian_name` | Trimmed; empty → NULL |
| Password | `password` | **`Hash::make()` immediately. The plaintext is never written, never logged, never kept in a variable beyond the hashing call.** |

**Roll normalisation.** The legacy login compared `parseBanglaNumber(input)` against
`parseBanglaNumber(stored)`, so `২৫` and `25` were the same student. The importer therefore stores
the Latin-digit form as canonical, and login applies the same normalisation to input. Without this,
students who have always typed Bengali digits would be locked out.

**Duplicates.** Roll is `UNIQUE`. The preview screen lists every duplicate roll with its row numbers
and blocks the import until resolved — it never silently keeps "the last one wins".

**Post-import:** every imported user gets `force_password_change = 1` and `is_legacy_import = 1`.
The legacy passwords were publicly readable (`SECURITY.md` L2), so all of them must be treated as
compromised and rotated on first login.

**Header rows** matching `রোল নম্বর` / `Roll Number` are skipped, mirroring the legacy filter.

---

## 2. Quiz workbook → `quizzes` / `quiz_questions` / `quiz_options`

**Source:** one tab per quiz in workbook `15xgI5Q8…GnWY`, plus `Live`.

> **Built (Phase 6).** This mapping is implemented by `QuizImportParser` +
> `QuizImporter`, driven by the admin importer at `/admin/quizzes/import`. Workflow:
> **download the sheet as XLSX → upload → select worksheet → preview → confirm**. The
> running application never fetches Google Sheets. Uploaded files are treated as
> answer-key-sensitive (`ImportFileStore`: private, random name, TTL sweep, deleted on
> commit). Import is transactional; confirm re-parses from the file. Imported quizzes
> land as `draft`. Course/lesson are suggested from the tab/Exam name by
> `QuizCourseMatcher`; a missing lesson (e.g. Seerat 26/27) links the course only and
> never fabricates a lesson. Legacy password columns P and V are detected and ignored.

### 2.1 Column mapping

| Col | Idx | Target |
|---|---|---|
| A | 0 | `quiz_questions.body` |
| B–M | 1–12 | `quiz_options.body` (only non-empty cells become options) |
| N | 13 | `quiz_options.is_correct` — parsed, see §2.2 |
| O | 14 | `quizzes.duration_seconds` (0/empty → NULL, meaning no limit) |
| P | 15 | *Discarded.* Quiz passwords are replaced by authenticated sessions |
| Q + R | 16, 17 | `quizzes.starts_at` (combined, Asia/Dhaka → UTC) |
| S + T | 18, 19 | `quizzes.ends_at` |
| U | 20 | `quizzes.title` |
| V | 21 | *Discarded.* Leaderboard visibility is a role check now |
| W | 22 | `quiz_questions.marks` (Mega Question; empty → 1) |

Columns P and V are intentionally dropped: they were secrets held in a public spreadsheet, so they
never provided access control (`SECURITY.md` L8).

### 2.2 Correct-answer parsing

Values seen in the wild: `1`, `2`, `1, 2`, `1, 2, 3`, and Bengali-numeral equivalents.

```
normalise NFC → Bengali digits → Latin → split on comma → trim → parse int → 1-based → 0-based
```

Rules enforced by the importer, each reported against **the exact row number**:

- Index must reference an option that exists and is non-empty → otherwise `E_ANSWER_OUT_OF_RANGE`.
- At least one correct index required → otherwise `E_NO_CORRECT_ANSWER`.
- Duplicates within a row (`1, 1`) are collapsed, with a warning.
- More than one index ⇒ `quiz_questions.type = 'multiple'`, else `'single'`.
- Non-numeric content → `E_ANSWER_UNPARSEABLE`.

**Nothing is silently corrected.** A quiz with any error cannot be committed; the preview lists
every failing row (brief §12).

### 2.3 Config-row heuristic

Reproduces the legacy rule exactly: if the second data row has a value in any of O, P, Q, R or U it
is the config row and questions start there; otherwise the first row is config and questions start
at row 1. The importer *shows* which row it treated as config in the preview, because getting this
wrong silently shifts every question by one.

### 2.4 Tab naming

Tab names are matched **case-insensitively** after NFC normalisation and trimming, so `Seerat-27`,
`seerat-26` and `SEERAT-25` all resolve to slug `seerat-{n}` (brief §12). The `Live` tab is imported
as a normal quiz — there is no privileged "live" tab in the new system; whether a quiz is live is
decided by `starts_at`/`ends_at`.

### 2.5 Timezone

Sheet dates and times are naive local values. They are interpreted as **Asia/Dhaka** and converted
to UTC on write. Getting this wrong shifts every exam by 6 hours, so the preview displays the
resulting `starts_at`/`ends_at` back in Asia/Dhaka for the admin to confirm.

### 2.6 Fallback detection

Google's gviz endpoint silently returns the **first** sheet when the requested tab does not exist.
The legacy code detected this by comparing a signature of the first question against the `Live`
sheet. The importer keeps this guard and refuses the import with `E_SHEET_FALLBACK` rather than
importing the Live quiz under an archived quiz's name.

---

## 3. `COURSE_DATA` → `courses` / `lessons` / `lesson_resources`

**Already extracted** to `legacy/extracted/course-data.json` by
`legacy/extracted/extract-course-data.mjs` — programmatically, so no Bengali text was retyped.

42 lessons: 25 `seerat`, 14 `halakah`, 3 `jummah`, 0 `tafsir`.

**Mapping**

| JSON | Target | Rule |
|---|---|---|
| `category` | `courses.slug` | Course rows seeded first |
| `title` | `lessons.title` | Verbatim |
| `quiz_slug` | `lessons.slug` | e.g. `seerat-24`; links quizzes by slug |
| `description` | `lessons.description` | Placeholder text imported as-is, then flagged (below) |
| `summary` | `lessons.summary` (JSON) | Array preserved |
| `syllabus` | `lessons.syllabus` | `"#"` → NULL |
| `date_label` | `lessons.date_label` | **Verbatim Bengali** |
| `date_label` | `lessons.held_on` | Parsed where possible; `সংগৃহীত` → NULL |
| `duration_label` | `lessons.duration_label` | Verbatim |
| `duration_label` | `lessons.duration_minutes` | Parsed from Bengali numerals |
| `drive_link` | `lessons.media_url` + `media_file_id` | File id extracted from `/file/d/{id}/` |
| `resources[]` | `lesson_resources` | `url: "#"` → **NULL**, label kept |
| `legacy_id` | `lessons.legacy_id` | Traceability |

**Known content gaps carried forward, not invented** (`PROJECT_PLAN.md` §4.2): 22 lessons have
placeholder descriptions; all 25 resource URLs are `"#"`; 17 lessons have no resources; only 7 have
a syllabus; halakah-06 does not exist; tafsir has no lessons. The importer emits a
**content-gap report** listing these so the site owner can fill them in through the admin panel.
It does not generate substitute text.

`has_family_tree` on সীরাত-০২ is a legacy UI flag with no rendering behaviour in the current file;
it is recorded in the import report and otherwise dropped.

---

## 4. `quiz_submissions` → `quiz_attempts`

This is the migration with real information loss. Read §4.3 before running it.

### 4.1 Legacy structure

```sql
quiz_submissions(
  id, quiz_id VARCHAR(100), roll VARCHAR(50), name VARCHAR(100),
  guardian VARCHAR(100), score INT, total_questions INT,
  time_taken INT, created_at TIMESTAMP
)
```

### 4.2 Mapping

| Legacy | Target |
|---|---|
| `quiz_id` | resolved to `quiz_attempts.quiz_id` via the mapping table (§4.4) |
| `roll` | resolved to `user_id` via normalised `users.roll` |
| `name`, `guardian` | **not copied** — identity now comes from `users`; retained in the import report for reconciliation |
| `score` | `calculated_score` **and** `final_score`; `manual_adjustment = 0` |
| `total_questions` | `total_marks_snapshot` — **see the caveat below** |
| `time_taken` | `time_taken_seconds` |
| `created_at` | `submitted_at`, `started_at` (best effort: `submitted_at − time_taken`) |
| — | `kind='official'`, `status='submitted'`, `is_legacy_import=1`, `answer_details_available=0` |

**`total_questions` is not `total_marks`.** The legacy column counted *questions*, while the score
was summed with **mega marks** (`score += q.points`). For any quiz containing a mega question,
`score` can exceed `total_questions`. The importer therefore recomputes the real total from the
imported quiz where the quiz is available, records the legacy value separately in the import
report, and **flags every row where `score > total_questions`** for review. Copying the legacy value
into `total_marks_snapshot` unexamined would produce percentages above 100% on the cumulative
leaderboard.

### 4.3 What cannot be reconstructed

The legacy system **never stored individual answer selections** — only a final score. Therefore:

- No `quiz_answers` or `quiz_answer_options` rows are created for legacy attempts.
- No answer sheet can be shown. The result screen displays:
  *"এই পরীক্ষার বিস্তারিত উত্তরপত্র সংরক্ষিত নেই।"*
- **Legacy attempts can never be regraded.** A regrade run skips
  `is_legacy_import = 1` rows and reports how many it skipped.
- Fabricating plausible answers is prohibited (brief §17).

Additionally, because scores were computed and submitted by the client with no authentication
(`SECURITY.md` L3–L5, L9), legacy scores are **unverified by construction**. They are imported
as historical record, not as attested results.

### 4.4 Resolving the ambiguous `quiz_id` — **requires a database export**

As documented in `PROJECT_PLAN.md` §4.1, `quiz_id` holds either the free-text *Exam Name* from
cell U2 (live submissions, e.g. `সীরাত`) or a derived slug (archive, e.g. `seerat-24`). The two
cannot be told apart from the source files.

Procedure:

```sql
SELECT quiz_id, COUNT(*) AS rows_, MIN(created_at) AS first_seen, MAX(created_at) AS last_seen
FROM quiz_submissions GROUP BY quiz_id ORDER BY first_seen;
```

The date window of each distinct `quiz_id` is matched against the `starts_at`/`ends_at` of the
imported quizzes to produce an explicit `legacy_quiz_map` (legacy value → quiz slug), reviewed by a
human in the preview screen before anything is written. Rows whose `quiz_id` cannot be mapped are
**not guessed** — they are listed in the report and skipped.

If several archived exams were run under the same generic Exam Name (`সীরাত`) with overlapping
dates, those rows may be genuinely unattributable. That possibility is why this step is manual.

### 4.5 Unmatched rolls

A submission whose `roll` matches no user (blank roll, typo, or a student since removed) is
**skipped and reported**, never attached to an arbitrary account. Blank rolls are expected: the
legacy practice flow submitted with `userRoll = ""`.

### 4.6 Submission serial

`submission_seq` is recomputed as `ROW_NUMBER() OVER (PARTITION BY quiz_id ORDER BY submitted_at)`,
reproducing the legacy per-quiz chronological serial.

---

## 5. Verification

Migration is not complete until all of these pass. `php artisan migrate:verify-legacy` runs them
and prints a report.

| Check | Expectation |
|---|---|
| Student count | `users` where `is_legacy_import` = sheet rows − skipped duplicates |
| No plaintext passwords | every `users.password` matches a bcrypt/argon2 prefix |
| Attempt count | `quiz_attempts` where `is_legacy_import` = `quiz_submissions` − skipped, and skipped is itemised |
| Score fidelity | every migrated `final_score` equals its source `score` |
| Orphans | zero attempts with unresolved `quiz_id` or `user_id` |
| Percentage sanity | zero attempts with `final_score > total_marks_snapshot` |
| Answer flags | every legacy attempt has `answer_details_available = 0` and zero `quiz_answers` |
| Point ledger | `users.points_balance = SUM(point_transactions.amount)` for every user |
| Encoding | round-trip a Bengali + Arabic + emoji string through every text column |
| Legacy intact | `quiz_submissions` still present with its original row count |

Row counts before and after are written to `legacy/db/migration-report-{timestamp}.json`.

---

## 6. Rollback

Each importer runs in a transaction and is idempotent on re-run (matching on `legacy_id` /
`is_legacy_import` + source id). Rollback of any single import is a scoped delete of rows carrying
that import's marker; it never touches the legacy sources.

`php artisan migrate:rollback` on the new schema **does not drop `quiz_submissions`** — no
migration in this project declares it, so it cannot be dropped by accident.

---

## 7. After cutover

1. Keep `quiz-api.php` reachable but **read-only** for a grace period — remove the `submit` branch
   first, since that is the forgeable write path.
2. Rotate the database credentials (`SECURITY.md` L1) **before** the new application goes live, so
   the new `.env` never contains the exposed pair.
3. Restrict the two Google Sheets from "anyone with the link" to private. They remain useful as an
   authoring surface for the quiz importer, which reads them with admin credentials — they are no
   longer a runtime dependency (brief §42).
4. Retire `quiz-api.php` entirely once §5 verification has held for a full exam cycle.
