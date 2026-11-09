# Phase 4 Project Teams — Import Execution Results

**Date**: November 7, 2026
**Environment**: `APP_ENV=local`, SQLite (`C:\Users\HomePC\lpres-gombe\database\database.sqlite`)
**Command**: `php artisan lpres:import-wordpress --type=teams --confirm` (run exactly once)
**Status**: Complete — 22 of 22 audited team members imported, verified, and rendered.

---

## 1. Scope and constraints

- Local database only. No production changes, no deployments, no new schema migrations.
- WordPress SPC Profile (WP ID 3670) intentionally excluded; SPC Quotes not started.
- Three photographs retained from earlier debugging were not deleted.
- Execution was attempted only after every pre-flight check passed.

## 2. Pre-flight checks

| # | Check | Result |
|---|-------|--------|
| 1 | `APP_ENV` | `local` |
| 2 | Database | `database/database.sqlite` |
| 3 | Backup present and valid | valid copy at `C:\Users\HomePC\AppData\Local\Temp\lpres-backup-20261107_teams_databackup.sqlite` (1,863,680 bytes, `PRAGMA integrity_check` = `ok`) |
| 4 | Existing `source_type='team'` mappings | 0 |
| 5 | Existing team members | 0 — the single pre-existing `Madeline Reed` row was deleted intentionally by the project owner, so zero is the expected baseline |
| 6 | Team categories intact | `3 Professionals / professionals`, `4 Project Specialists / piu`, `5 Assistant / ass` |
| 7 | News / Projects untouched | 132 / 58 |
| 8 | Dry run | 22 creates, 0 conflicts, 0 failures, 0 missing images |

Pre-check 5 was initially treated as a blocker because the audit baseline expected one
untouched member. The owner confirmed the deletion was deliberate, the baseline was
restated as zero, and the remaining checks re-ran clean.

## 3. Fixes required before execution

Both defects were found while reproducing the earlier all-fail run (see document 29).

1. **Fatal persistence bug** — `TeamsImporter::metadata()` read `$item->thumbnailId` as a
   property instead of calling `thumbnailId()`. Laravel converted the undefined-property
   warning to `ErrorException`, so every record failed after its media copy and rolled back.
   Fixed to `thumbnailId()`.
2. **Category and designation source** — the category now comes from the WordPress
   `team_cat` term (`piu` → category 4, `ass` → category 5), because the inventory
   `classification` value (`team_member`) cannot distinguish the two. The designation is
   read from the serialized `towngov_meta_team.team_designation` field.
3. **Status flag** — the importer wrote the string `'published'` into `status`, but the
   column is a boolean that the admin controller writes as `true`, so
   `TeamMember::published()` matched nothing. The importer now writes `true`, and the 22
   rows created by the first successful run were corrected to `1` in place. No other
   column was modified by that correction.

## 4. Execution

```
php artisan lpres:import-wordpress --type=teams --confirm
```

- Created: 22
- Failed: 0
- Conflicts: 0
- Media files copied: 20 · reused identical: 2

Only one `--confirm` run was performed. No retries were needed.

## 5. Results

### Records

| Metric | Value |
|--------|-------|
| Team members | 22 |
| Category 4 — Project Specialists (`piu`) | 14 |
| Category 5 — Assistant (`ass`) | 8 |
| Category 3 — Professionals | 0 |
| Published (`TeamMember::published()`) | 22 |

Names, designations, and photograph paths were compared field-by-field against the
WordPress export for all 22 records: **0 mismatches**. Every `photograph_path` resolves to
an existing file on disk: **0 missing**.

### Media

- 23 files under `storage/app/public/uploads/team-members`.
- 22 referenced by imported members (20 newly copied, 2 reused because byte-identical).
- 1 unreferenced file, `2025/01/SPC.png`, retained deliberately (debugging artefact).
- No file outside `uploads/team-members` was created, modified, or deleted.

### Provenance

- `source_type='team'` mappings: 22, one per imported member.
- `source_hash`, `target_hash`, and `source_metadata` populated on all 22 (0 gaps).
- `source_type='attachment'` mappings: 896 (874 before the run → 22 added), 0 duplicate keys.
- WP ID 3670 (SPC Profile) has no mapping and was not imported.

### Data that must not change

| Table | Before | After |
|-------|--------|-------|
| News | 132 | 132 |
| Projects | 58 | 58 |

No unrelated media was touched; verification of `uploads/` outside `team-members`
showed no changes in the execution window.

## 6. Public and administrative verification

- **Public `/project-team`** renders both groups — 14 Project Specialists and 8
  Assistants — with names and designations. Every photograph request returns HTTP 200.
- **Admin editing**: an authenticated update submitted without a new upload preserves the
  existing `photograph_path`. This is now covered by an automated test rather than a
  one-off manual check.

A screenshot of the public page was not captured: the first attempt failed with
"page has not been drawn yet", and the rendering was verified instead through the HTTP
response and the image-status checks above. No image artefact exists at
`docs/lpres-migration-audit/work/project-team-after-import.png`.

## 7. Re-run behaviour (idempotency)

A dry run against the imported database reports 22 eligible records, 22 existing
mappings, **22 unchanged, 0 changed, 0 updated, 0 conflicts, 0 writes**.

One reporting defect was found and fixed during this check: slug-collision resolution ran
before the import-mapping lookup, so a re-run reported all 22 records as having colliding
slugs even though nothing would be written. Mapping resolution now happens first, and an
existing record keeps the slug it already has. A regression assertion
(`$report->slugConflicts` is empty and `conflicts` is 0 on re-run) was added to the test
suite. The fix changes no data on the existing 22 rows.

## 8. Automated tests

`tests/Feature/TeamsImportTest.php` — 5 tests, 59 assertions:

1. Persists PIU and Assistant members with categories, photographs and designations.
2. Does not create duplicate team members when re-imported.
3. Does not overwrite a team member edited by an administrator.
4. Keeps the imported photograph when an administrator edits without uploading a new one.
5. Reports a persistence failure with its actual cause.

Full suite: **216 passed, 1098 assertions**, no failures.

## 9. Discrepancies and resolutions

| Item | Resolution |
|------|------------|
| Backup at `/tmp/lpres-backup-20261107_teams_databackup.sqlite` is 0 bytes | Unusable; the valid backup is the `C:\Users\HomePC\AppData\Local\Temp\` copy (1,863,680 bytes, integrity `ok`). Execution proceeded against the valid copy. |
| Pre-existing `Madeline Reed` member absent | Deleted intentionally by the owner; baseline restated to zero members. |
| All-fail run before execution | Root cause `thumbnailId` property access, fixed (document 29). |
| `status` written as `'published'` | Importer fixed to write `true`; 22 rows corrected to `1`. |
| Re-run reported 22 slug collisions | Mapping lookup reordered ahead of slug resolution; regression assertion added. |
| Unreferenced `2025/01/SPC.png` | Retained intentionally; not an orphan to clean up in this phase. |
| Public page screenshot | Not produced (rendering timing); page verified via HTTP responses instead. |

## 10. Deferred

- **SPC Profile (WP ID 3670)** — excluded from this phase by instruction.
- **SPC Quotes** — not started.
