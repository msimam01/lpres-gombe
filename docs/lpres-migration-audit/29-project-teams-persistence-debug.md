# Phase 4 Project Teams — Persistence Debug

**Date**: November 7, 2026
**Environment**: `APP_ENV=local`, SQLite (`database/database.sqlite`)
**Backup**: `/tmp/lpres-backup-20261107_teams_databackup.sqlite` (integrity verified earlier, still present)
**Status**: Root cause identified and fixed; full test suite green; no data imported yet.

---

## 1. Original exception

The previous `--confirm` run reported 22 failures with no visible cause, because the
command's problem renderer crashed with `count(): Argument #1 ($value) must be of
type Countable|array, int given` before printing `failedDetails`.

Reproducing one eligible record through the importer's private `importOne()` inside a
rolled-back transaction surfaced the real exception:

```
Undefined property: App\Migration\WordPress\WordPressItem::$thumbnailId
```

Raised from `TeamsImporter::metadata()`, caught by the `catch (Throwable)` in
`TeamsImporter::apply()`, recorded as a per-record failure. Because it happened after
media planning and inside `apply()`, the record's transaction rolled back, the copied
file was released, and the report still counted the planned media copy — which is why
"Files copied 22" appeared while zero team members existed.

Laravel's exception handler turns the PHP warning for reading an undefined property
into an `ErrorException`, so the bug was fatal for every record, not a silent notice.

---

## 2. Root cause and secondary defects

| # | Defect | Effect |
|---|--------|--------|
| 1 | `metadata()` read `$item->thumbnailId` (a method, `thumbnailId()`, on `WordPressItem`) as a property | **Primary cause**: `ErrorException` for all 22 records inside `apply()` → 0 members, 0 mappings, media released |
| 2 | `classificationForItem()` treated the inventory's `classification` (`team_member` for all 22) as `piu`/`assistant`, which `TeamFields::teamCategoryId()` does not recognise | All 22 would have landed in **Professionals (id 3)** instead of 14 PIU (4) / 8 Assistant (5) |
| 3 | `apply()` referenced `$isCreate`, `$sourceChanged`, `$targetChanged`, which only exist in `importOne()` | `ErrorException` on any **update** path; counters were also incremented through a stale report API |
| 4 | Attachment mapping rows were written with `source_type = 'team'` | Attachment provenance collided with team-member mappings under the `(source_type, source_id)` unique index |
| 5 | `TeamsImportReport` did not match the command's contract (`needsAttention()`, `categoryExists`, `categoryWillBeCreated`, `mediaMappingRows` missing; `missingFeatured`/`missingEmbedded` declared `int` while the importer appended to them; `imported` both a property and a `void` method) | Reporting crashed with `TypeError`, hiding the real failure; dry run could never print its plan cleanly |
| 6 | `TeamFields::designation()` read `$item->customFields`, which does not exist on `WordPressItem` | Every designation imported as `''` instead of the WordPress value |
| 7 | `resolveCategory()` passed `wp_term_id` to `TeamCategory::create()` although the table has no such column | Dead attribute (silently dropped); removed to keep the create honest |
| 8 | `TeamsMediaPlan::$featuredPlan` was non-nullable while `planMedia()` can legitimately pass `null` | Fatal `TypeError` for any record without a featured image |
| 9 | The shared `renderProblems()` was typed `TeamsImportReport` while News/Projects also call it | Fatal `TypeError` for news and project imports after the teams changes |

### Where the authoritative category lives

The audit's `classification` column is `team_member` for all 22 eligible rows, so it
cannot separate PIUs from Assistants. The authoritative value is the WordPress
`team_cat` term (`nicename` `piu` / `ass`), with the inventory's
`category_names` (`team_cat:PIUs [piu]`, `team_cat:Assistant [ass]`) as fallback:

- 14 records → `piu` → **team_category_id 4**
- 8 records → `ass` → **team_category_id 5**

### Where the designation lives

The theme serialises it in the `towngov_meta_team` post meta as `team_designation`
(verified present on all 22 eligible records). It is now read with
`unserialize(..., ['allowed_classes' => false])`; nothing is invented when absent.

---

## 3. Files changed

| File | Change |
|------|--------|
| `app/Migration/Teams/TeamsImporter.php` | `metadata()` now calls `thumbnailId()`; category resolved from the `team_cat` term (inventory fallback) instead of `classification`; `apply()` takes an explicit `isCreate` flag and uses the correct counters; attachment mappings use `attachment` source type; `mappedAttachments` pre-loads attachment mappings; `wp_term_id` removed from category creation; dead `customFields` fallback removed; `$fields` declared as a typed property; unused `setCategoryMap()` removed |
| `app/Migration/Teams/TeamsImportReport.php` | Rewritten to the same contract as `NewsImportReport`: array problem lists, `imported(): int`, `skipped()`, `potentialDatabaseWrites()`, `needsAttention()`, `categoryExists`, `categoryWillBeCreated`, `conflictDetails`, `mediaMappingRows` |
| `app/Migration/Teams/TeamFields.php` | `designation()` reads serialized `towngov_meta_team.team_designation` |
| `app/Migration/Teams/TeamsMediaPlan.php` | `featuredPlan` is nullable, matching `planMedia()` |
| `app/Console/Commands/ImportWordPressCommand.php` | Teams media counts use `count($report->missingFeatured/missingEmbedded)`; `renderProblems()` cleaned up and typed `NewsImportReport\|TeamsImportReport`; teams view lists conflicts and orphaned files |
| `tests/Feature/TeamsImportTest.php` | **New** regression tests (below) |

The News and Projects importers were not modified; only the shared problem renderer's
type was widened so all three content types keep working.

No schema migration was required or created.

---

## 4. Regression tests

`tests/Feature/TeamsImportTest.php` builds a small synthetic WXR export, inventory and
media manifest (2 PIUs + 1 Assistant, each with a featured image and a serialized
designation), seeds the audited category ids 3/4/5, and drives the real importer
against `Storage::fake('public')`.

1. **Persists PIU and Assistant members with categories, photographs and designations** —
   3 created, 0 failed; categories 4/4/5; names, designations and photo paths stored;
   photo files on disk; 3 `team` + 3 `attachment` mapping rows; `source_hash` and
   `target_hash` populated; `featured_attachment_id` and `laravel_team_category_id`
   provenance correct.
2. **Does not create duplicate team members when re-imported** — second run: 0 created,
   0 updated, 3 unchanged; row counts unchanged.
3. **Does not overwrite a team member edited by an administrator** — 1 admin-edited
   record skipped (`adminModified = 1`), the other 2 unchanged, admin's name preserved.
4. **Reports a persistence failure with its actual cause** — a simulated
   `RuntimeException` in `TeamMember::creating` yields `failed = 3` and the message
   `simulated persistence failure` in `failedDetails`, with no member or mapping rows
   left behind (transaction and media cleanup verified).

Test results:

- `./vendor/bin/pest tests/Feature/Migration tests/Feature/TeamsImportTest.php` → **71 passed**, 286 assertions
- `./vendor/bin/pest` (full suite) → **215 passed**, 1086 assertions, 0 failures

---

## 5. Preserved local state (no reset, no deletion)

Checked before and after the changes:

- `APP_ENV=local`; SQLite at `database/database.sqlite`
- Team members: **1** (existing `Madeline Reed`, category 3) — no imported records exist
- Team categories: **3 Professionals (`professionals`), 4 PIUs (`piu`), 5 Assistant (`ass`)** — unchanged
- `wp_import_map`: no `team` rows; `attachment` 874, `post` 132, `project` 421 — unchanged
- News **132**, Projects **58** — unchanged
- Backup still present at `/tmp/lpres-backup-20261107_teams_databackup.sqlite`
- Media: 3 files under `storage/app/public/uploads/team-members` (left by single-record
  debugging; their paths match the import targets and will be reused, not duplicated).
  The 22 copies from the failed run were released by the importer's rollback cleanup as
  designed. Nothing was deleted or overwritten during this investigation.

---

## 6. Dry-run after the fix

`php artisan lpres:import-wordpress --type=teams --dry-run`

```
Eligible team records        22
New team members             22
Conflicts                    0
Failed                       0

Team category PIUs           exists (slug: piu)
Team category Assistant      exists (slug: ass)
Assignments                  22

Featured images missing      0
Embedded images missing      0
Media conflicts              0
Files to copy                20
Files reused (identical)     2

Team member rows             22
Import-map rows              22
Attachment mappings          22 (22 new)
Category pivot rows          22
```

Category split verified directly from the export terms: **14 `piu` / 8 `ass`**.
No database write or file copy occurred during the dry run.

---

## 7. Readiness and next step

The persistence path now succeeds for both a PIU record and an Assistant record in an
isolated, rolled-back harness, and the regression suite covers creation, categories,
photos, designations, provenance, idempotency, admin-edit protection and failure
reporting.

Exact next execution command (controlled retry, local only):

```bash
php artisan lpres:import-wordpress --type=teams --confirm
```

Expected: 22 created, 0 failed, 0 conflicts, 0 missing images; 22 `team` mapping rows
plus 22 `attachment` mapping rows.

Then verify: 22 team members (14 in category 4, 8 in category 5), every
`photograph_path` resolves to a stored file, names/designations match WordPress,
`wp_import_map` has 22 rows with `source_type='team'` carrying `source_hash`,
`target_hash` and `source_metadata`, and News (132) / Projects (58) are unchanged.

---

## 8. Discrepancies and notes

- Document `28-project-teams-import-execution-results.md` recorded the failed run and
  should be read together with this one; its "technical issue in the persistence layer"
  is the `thumbnailId` defect described above.
- The dry run reports "Files reused (identical): 2" and "Files to copy: 20" because two
  team photographs were already written by the single-record debugging. This is
  expected and harmless; byte-identical files are reused rather than rewritten.
- No SPC Profile record (WP ID 3670) is imported, and no biography, contact, social,
  status, visibility or display-order fields were added.
- Work stops here; SPC Quotes is not started.

---
*Generated with Codebuff 🤖*
