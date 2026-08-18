# Migration Reconciliation

## Safe local/staging verification — Phase 10B, 2026-08-18

- Student migration architecture: ready; no current production student export supplied.
- Quiz content migration: one real archived Seerat-27 CSV staged in fresh MySQL as a draft; the
  workbook's `Live` sheet was explicitly skipped.
- Legacy result migration tooling: **READY**.
- Legacy result source: **LOCATED AND PREVIEWED** (856 rows from one canonical SQL export; a second
  copy had the identical SHA-256 and was not double-counted).
- Actual historical result import: **BLOCKED BY STUDENT EXPORT AND OWNER-APPROVED QUIZ MAPPING**.
- Explicit source skips: 232 blank-roll anonymous/practice rows and 1 duplicate fingerprint.
- Remaining result candidates: 623 valid rows across 20 legacy quiz IDs; all remain `REVIEW`.
- Historical attempts imported: 0. No unresolved row was imported.
- Expected legacy course lessons: 42.
- Expected lessons present in scratch: 42.
- Known source distribution: 25 Seerat, 14 Halakah, 3 Jummah, 0 Tafsir.
- Additional post-legacy content was preserved and treated as informational.
- Placeholder/dead `#` links stored as clickable URLs: 0.
- Integrity verification errors: 0 on the safe local and Phase 10B scratch databases.
- Archived quiz idempotency: second run detected the source fingerprint and kept the quiz count at 1.
- Imported quiz policy: `status=draft`, `counts_toward_overall=false`, 23 questions, 23 marks.
- Automated verification: 356 tests / 1,563 assertions passed; Vite production build passed during
  Phase 10B.

Known source gaps remain honest: Tafsir has no supplied lesson content; the absent Halakah lesson is
not fabricated; placeholder descriptions and non-date `সংগৃহীত` labels remain identifiable. The
private Phase 10B operator package is under `storage/app/private/phase10b/reports/` and is gitignored.

## Production reconciliation template

| Metric | Students | Results |
|---|---:|---:|
| Source rows | pending | pending |
| Valid rows | pending | pending |
| Duplicate source records | pending | pending |
| Existing DB/source-key conflicts | pending | pending |
| Ambiguous mapping / missing user | n/a | pending |
| Imported | pending | pending |
| Explicitly skipped | pending | pending |
| Failed | pending | pending |
| Temporary credentials generated | pending | n/a |
| Final active users | pending | pending |

For results, also record each legacy quiz ID, approved target quiz ID/title, confidence, action, and
notes in `LEGACY_QUIZ_MAPPING.csv`. Retain the reviewed mapping and Markdown reports with the private
cutover evidence; never store them under `public_html`.

## Historical-result representation

Imported records are official terminal attempts with `is_legacy_import=true`,
`answer_details_available=false`, the original legacy quiz ID, score, total-question value, time
taken, and source timestamp. No answer or answer-option row is invented. No point transaction is
created. The source score remains visibly legacy/client-authoritative rather than being represented
as a new-system attested score.

Reliable mapped records may appear on that quiz's historical leaderboard. Overall inclusion also
requires the admin-controlled quiz flag `counts_toward_overall`; keep migrated quizzes excluded until
the owner explicitly reviews the historical ranking effect.
