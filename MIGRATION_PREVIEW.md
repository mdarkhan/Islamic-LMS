# Migration Preview

This is the production preview template. Do not add exported student/result rows or temporary
credentials to Git. Generate real reports into a private operator directory.

## Current readiness

| Source | Tooling | Actual preview |
|---|---|---|
| Student CSV/XLSX | Ready | Awaiting current private export |
| Quiz CSV/XLSX | Ready in Admin → Quiz Import | Partial real source inspected: `Live` skipped; Seerat-27 staged as draft in scratch |
| 42 course lessons | Ready and verified | 42/42 expected lessons present in scratch |
| `quiz_submissions` | Ready for derived CSV/XLSX | 856-row SQL export found; 623 candidates await students and owner-approved mapping |

## Commands

```bash
php artisan legacy:students:preview /private/imports/students.xlsx \
  --report=/private/reports/students-preview.md

php artisan legacy:results:preview /private/imports/quiz_submissions.csv \
  --write-mapping=/private/reports/LEGACY_QUIZ_MAPPING.csv \
  --report=/private/reports/results-preview.md

# After reviewing every row and setting APPROVED/SKIP (leave unresolved rows as REVIEW):
php artisan legacy:results:preview /private/imports/quiz_submissions.csv \
  --mapping=/private/reports/LEGACY_QUIZ_MAPPING.csv \
  --report=/private/reports/results-approved-preview.md
```

Preview is read-only. Quiz candidates use exact normalized title or slug equality only, but even an
exact candidate remains `REVIEW` until the operator changes it to `APPROVED` or `SKIP`. Fuzzy or
ambiguous matches are never proposed automatically; names alone never match a student.

## Reconciliation fields

Students: source rows, syntactically valid rows, duplicate source rolls, existing DB conflicts,
ready rows, failures, and final active-user count.

Results: source rows, valid rows, exact import candidates, review rows, explicit skips, missing users,
duplicate fingerprints, previously imported rows, and validation errors. Each exception reports its
source row and canonical roll. Reports never include a source password.

## Approval gate

Do not run an import until the source is backed up, every error is corrected or explicitly skipped,
mapping is reviewed, preview counts are signed off, and the rollback database dump has been restored
successfully into a scratch database.
