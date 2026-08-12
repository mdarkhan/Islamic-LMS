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

---

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

---

## Architecture

```
app/Models/            Eloquent models, thin
app/Services/Points/   PointService (the only writer of points_balance)
app/Services/Quiz/     QuizAttemptService, QuizScoringService
app/Services/Import/   BengaliText, LegacyCourseImporter
```

Controllers stay thin; business logic lives in services. Use Form Requests for
validation and Policies for authorisation.

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
