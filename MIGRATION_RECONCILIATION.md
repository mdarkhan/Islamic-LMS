# Migration Reconciliation

## Safe local/staging verification — 2026-08-18

- Student migration architecture: ready; no current production export supplied.
- Quiz content migration architecture: ready; no current production workbook supplied.
- Legacy result migration tooling: **READY**.
- Actual historical result migration: **BLOCKED BY EXPORT**.
- Expected legacy course lessons: 42.
- Expected lessons present locally: 42.
- Known source distribution: 25 Seerat, 14 Halakah, 3 Jummah, 0 Tafsir.
- Additional post-legacy content was preserved and treated as informational.
- Placeholder/dead `#` links stored as clickable URLs: 0.
- Integrity verification errors: 0 on the safe local database.
- Automated verification: 355 tests / 1,560 assertions passed; Vite production build passed.

Known source gaps remain honest: Tafsir has no supplied lesson content; the absent Halakah lesson is
not fabricated; placeholder descriptions and non-date `সংগৃহীত` labels remain identifiable.

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
