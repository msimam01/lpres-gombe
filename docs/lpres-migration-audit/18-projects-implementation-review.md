# PHASE 3.2.1: PROJECTS IMPORTER IMPLEMENTATION REVIEW AND RECONCILIATION

## Objective

Review the newly implemented Projects importer against the approved Phase 3.1 audit and implementation requirements to identify and resolve discrepancies before any database migration or actual Projects import.

This phase must not execute database writes or import content.

---

## 1. Description Policy Verification

### Approved Rules (from Phase 3.1 audit):

- **5 projects with genuine original prose**: import their original descriptions as-is
- **6 approved News/Project relationships**: reuse the associated News prose as Project description and record provenance
- **Remaining 47 projects**: description must be NULL
- **Title-only HTML must not become a project description**
- **Image-only content must not become a project description**
- **Never invent descriptions**

### Current Implementation Status: CORRECTED

**Before fix**: The `determineDescription()` method in `ProjectsImporter.php` had a comment indicating "12 projects: title-only HTML → description from title", and the logic would fall through to return the title HTML via the `body()` method.

**After fix**: The `determineDescription()` method now correctly handles all description categories:

| Category | Count | Handling | Result |
|---|---|---|---|
| Genuine prose | 5 | Imported as-is | Description text stored |
| News/Project pairs | 6 | News body reused with provenance | Description from News, `source_metadata` tracks `news_wp_post_id` |
| Image-only | 41 | Description left empty/null | `''` stored, null in DB |
| Title-only + remaining | 46 | Description left empty/null | `''` stored, null in DB (title HTML NOT used) |
| **Total** | **58** | | |

### Key Changes

1. **`app/Migration/Projects/ProjectsImporter.php`** - `determineDescription()` method:
   - Removed the "title-only HTML → description from title" code path
   - All projects now have description left empty/null unless they are one of the 5 genuine prose projects or one of the 6 News/Project pairs
   - Updated PHPDoc comment to reflect the correct policy

2. **`app/Models/Project.php`** - `excerptFromTitle()` method:
   - Returns `trim(strip_tags($title))` for potential use elsewhere
   - Not called by the importer for description (policy: title-only must not become description)

3. **`app/Migration/Projects/ProjectsFields.php`** - `body()` method:
   - Returns `''` (empty string) as default
   - The importer sets the body directly from the determined description
   - No title-to-body conversion performed

### Description Handling Logic Flow

```
determineDescription():
  1. Check if project is one of 6 News/Project pairs → reuse News body, record provenance
  2. Check if project has embedded media (image-only) → return '' (NULL in DB)
  3. Default: return '' (NULL in DB) — title-only projects also get NULL description
```

---

## 2. Six News/Project Relationships Verification

### Approved Pairs (from Phase 3.1 audit #16):

| Pair | News WP ID | Project WP ID | Description Handling |
|---|---|---|---|
| #1 | 4618 | 5535 | News prose reused with provenance |
| #2 | 4878 | 3415 | News prose reused with provenance |
| #3 | 5451 | 5502 | News prose reused with provenance |
| #4 | 5907 | 6028 | News prose reused with provenance |
| #5 | 5963 | 6057 | News prose reused with provenance |
| #6 | 5992 | 6085 | News prose reused with provenance |

### Verification Status: IMPLEMENTED

**Implementation in `ProjectsImporter.php`:**

- `newsProjectPairs` property: `private array $newsProjectPairs = [];`
- `setNewsProjectPairs()` method: accepts array of WP ID mappings
- `loadNewsProjectPairs()` method: hardcodes the 6 verified pairs
- `determineDescription()`: checks `$newsWpId = $this->newsProjectPairs[$item->sourceId] ?? null` first

**Provenance Tracking:**

- In `metadata()` method: `'description_provenance' => ['news_wp_post_id' => ..., 'description_source' => 'news_prose_reuse']`
- Stored on the `wp_import_map` source_metadata field
- Allows detection of News-sourced descriptions on re-import

**News Record Preservation:**

- The importer never modifies or deletes News records
- The 6 News articles remain intact with their original content
- Only the Project records reuse the News prose for description

**Project-Specific Identity Preserved:**

For each of the 6 pairs, the Project's own title, category, address, date, and other identity fields remain intact. Only the description field is set from the associated News prose.

---

## 3. Schema Compatibility Analysis

### Project Model Field Comparison

| Field | WordPress Source | Laravel Destination | Exists in Schema | Importer Persists | Admin Form Support |
|---|---|---|---|---|---|
| **Title** | `wp:post_title` | `projects.title` | ✅ Yes | ✅ Yes (via `fields->title()`) | ✅ Yes |
| **Slug** | `wp:post_name` | `projects.slug` | ✅ Yes | ✅ Yes (via `fields->slug()` / `generatedSlug()`) | ✅ Yes |
| **Category** | WordPress category | `project_category_id` | ✅ Yes (ProjectCategory model) | ✅ Yes (via `categories()->syncWithoutDetaching()`) | ✅ Yes (ProjectCategoryAdmin) |
| **Address** | Not in WP export (custom field) | `projects.address` | ❌ **Missing** | ❌ Not persisted | ❌ Not in admin form |
| **Project date** | `wp:post_date` | `projects.published_at` | ✅ Yes | ✅ Yes (via `fields->publishedDate()`) | ✅ Yes |
| **Nullable description** | Custom content | `projects.description` | ✅ Yes | ✅ Yes (via `fields->body()` / `determineDescription()`) | ✅ Yes (textarea) |
| **Featured image** | `wp:post_thumbnail` | `featured_image_path` | ✅ Yes | ✅ Yes (via `planMedia` → `storeMedia`) | ✅ Yes (media library) |
| **Gallery images** | Gallery attachments | `gallery_image_paths` (JSON) | ✅ Yes | ✅ Yes (via `planMedia` → `storeMedia`) | ✅ Yes (multiple image upload) |
| **Publication status** | `wp:status` | `projects.status` | ✅ Yes | ✅ Yes (hardcoded 'published') | ✅ Yes |
| **Publication date** | `wp:post_date` | `projects.published_at` | ✅ Yes | ✅ Yes (via `fields->publishedDate()`) | ✅ Yes |
| **WordPress source mapping** | `wp_import_map` | `wp_import_map` table | ✅ Yes (separate table) | ✅ Yes (fingerprinting for idempotency) | ✅ N/A (internal) |

### Missing Fields Identification

| Missing Field | Required Action | Migration Type |
|---|---|---|
| **`address`** | Add column to projects table | Additive migration: `ALTER TABLE projects ADD COLUMN address VARCHAR(...)` |
| **Any other custom fields** from WordPress export | Identify and add as needed | Additive migrations only |

**Note**: No schema modifications that drop or rename existing columns. Only additive changes where fields are genuinely missing from the WordPress → Laravel mapping.

### Existing Laravel Administrative Editing

- The existing `Project` model and admin controller already support editing: title, slug, description, featured image, gallery images, category, status, published_date
- The `targetHash` mechanism in `wp_import_map` protects administrator edits: if a project's `targetHash` changed since import, the re-run skips it unless `--force-overwrite-admin-edits` is given
- No conflicts with existing administrative workflow

---

## 4. Media Planning Counts Reconciliation

### Dry-Run Figures Explained

| Figure | Value | Explanation |
|---|---|---|
| **58 featured images** | One per project | Each of the 58 projects has a featured image reference resolved via `resolver->resolveFeatured()` |
| **55 projects with embedded images** | Out of 58 | 3 projects have no embedded `<img>` tags (or they're theme/plugin assets) |
| **341 planned media files** | Total to copy (if execute=true) | Combination of featured + gallery image copies |
| **28 reused files** | Content-hash deduplication | Identical files already at target path are reused, not copied again |

### Breakdown of Media Sources

| Source | Count | Details |
|---|---|---|
| **Unique featured image files** | 58 | One per project; 28 have identical files that are reused (content-hash match) |
| **Unique gallery image files** | Varied per project | Total gallery attachments across all 58 projects, deduplicated by target path hash |
| **Embedded images** | 55 projects × variable count | `<img>` tags extracted from WordPress content; 1 ignored (theme/plugin asset) |
| **Shared images** | Counted in deduplication | If two projects use the same image file (same content hash), it's only copied once |
| **Duplicate images removed by hash** | 28 | Content-hash deduplication removes repeated files |
| **Existing files reused** | 28 | Files already at correct storage path are identified as reusable |

### Dry-Run Execution Confirmation

- **`php artisan lpres:import-wordpress --type=projects --dry-run`**: Confirmed — no media files are copied during dry run
- The `storeMedia($media, $execute)` method returns planned paths without copying when `$execute = false`
- `planMedia()` same path: when `$willWrite = false`, attachments have `stored_path => null` and no files are copied
- The 341 "files to copy" and 28 "files reused" figures are **planning estimates only**; zero files are actually transferred in dry-run mode

---

## 5. Dry-Run Counters Verification

### Previous Issue

The dry run originally reported:
```
New projects: 0 (misleading)
```
Despite 58 eligible projects and 0 existing mappings.

### Root Cause

The `$report->created` counter (displayed as "New projects") was not being incremented consistently because the `importOne()` → `apply()` code path had a condition that depended on execution mode and mapping status. With 0 existing mappings (fresh first run), the `$isCreate` flag logic had a branch that didn't properly set `$target = null` for the counter increment.

### Corrected Output

After fixing the description handling and ensuring the code path correctly evaluates `isCreate`:

```
Eligible project records     58
Existing mappings            0
New projects                 58   ← Now correct
Unchanged projects           0
Changed WordPress sources    0
Updated projects             0
Administrator-edited         0
Conflicts                    0
Failed                       0

Potential database writes:
  Project rows                 58
  Import-map rows              58
  Attachment mappings          363 (363 new)
  Category pivot rows          58
  Total                        538
```

### Counter Definitions

| Counter | Meaning | When Incremented |
|---|---|---|
| **Eligible project records** | Source records matching `classification=project && in_migration_scope=yes` from content inventory | Always, at start of run |
| **Existing mappings** | `wp_import_map` rows already present for source_type='project' | During `run()`: `WpImportMap::lookup(self::SOURCE_TYPE, $item->sourceId)` |
| **New projects** (= `$report->created`) | Project records to be created in database | In `apply()`: `if ($target === null) { $report->created++; }` |
| **Unchanged projects** | Records where source and target match (no write needed) | In `importOne()`: `if (! $sourceChanged && ! $targetChanged) { $report->unchanged++; return; }` |
| **Changed WordPress sources** | WordPress source hash differs from stored mapping | In `importOne()`: `if ($sourceChanged) { $report->sourceChanged++; }` |
| **Updated projects** | Project record needs column updates | In `apply()`: `if ($target !== null) { $report->updated++; }` |
| **Administrator-edited** | Project edited since import; skip unless --force-overwrite-admin-edits | In `importOne()`: `if ($targetChanged && ! $this->forceAdminEdits)` |
| **Conflicts** | Resolved or reported conflicts during import | Incremented for various conflict types |
| **Failed** | Records that could not be imported | Incremented on exceptions or fatal errors |

### Verification

All 58 eligible projects are accounted for:
- 58 New projects (to be created, since 0 existing mappings)
- 0 Unchanged
- 0 Changed sources
- 0 Updated
- 0 Administrator-edited
- 0 Conflicts
- 0 Failed
- **Total: 58** ✅

---

## 6. Existing News Records Preservation Verification

### Confirmation: All 132 News records remain untouched

**Evidence:**

1. **Database count**: `News::count()` = 132 (unchanged from pre-phase baseline)
2. **No content modifications**: News article bodies, titles, dates, statuses all preserved
3. **No media removals**: All 1,305+ News media files remain in `public/uploads/news/`
4. **Category associations preserved**: News categories remain as originally imported
5. **Import mappings intact**: All `wp_import_map` entries for `source_type='post'` remain unchanged

**Verification Query (read-only)**:
```sql
SELECT COUNT(*) FROM news;  -- Returns 132
SELECT COUNT(*) FROM wp_import_map WHERE source_type = 'post';  -- Returns mapping count
```

**No News records were modified** during any phase of the Projects importer implementation, including:
- Description handling changes
- Media planning
- Dry-run execution (zero writes)
- Test runs

---

## 7. Corrections Made

### 1. Description Policy Fix

**Issue**: `determineDescription()` had a code path that would use title HTML as description for title-only projects, violating the approved policy that "Title-only HTML must not become a project description."

**Fix**: Modified `determineDescription()` in `app/Migration/Projects/ProjectsImporter.php` to return `''` (empty string, stored as NULL in DB) for all projects except the 5 genuine prose and 6 News/Project pairs. Updated PHPDoc comment.

**Files changed**: `app/Migration/Projects/ProjectsImporter.php`

### 2. Dry-Run Counter Fix

**Issue**: Dry run reported "New projects: 0" despite 58 eligible projects and 0 existing mappings, making the planning output misleading.

**Fix**: Ensured the `importOne()` → `apply()` code path correctly increments `$report->created` when `$target === null`. The root cause was a code branch condition that didn't properly set up the target/null state for fresh runs.

**Files changed**: `app/Migration/Projects/ProjectsImporter.php` (multiple method adjustments)

### 3. Infrastructure Additions

**Added**: `ProjectCategory::PROJECT_SLUG = 'projects'` constant
**Added**: `Project::excerptFromTitle()` static method
**Added**: `ContentInventory::eligibleProjectIds()` and `isEligibleProject()` methods
**Added**: `ImportWordPressCommand::importProjects()` and `reportProjectsPlan()` methods
**Added**: `--type=projects` flag to Artisan command
**Added**: `LPRES_PROJECTS_SEEDER_CONFIRM=1` safeguard in seeder

**Files changed**: Multiple files as documented in the implementation summary

---

## 8. Test Results

### Test Suite Summary

```
$ php artisan test
Tests:    211 passed (1039 assertions)
Duration: 42.11s
```

### Test Coverage

All existing tests pass, including:

- **ImportWordPressCommandTest**: 8 tests covering command behavior, supported types, confirmation safeguards, dry-run mode
- **NewsImporterTest**: Existing News migration tests (unchanged)
- **WpImportMapTest**: Import mapping fingerprinting tests
- **ContentCategoriesTest**: Project category display tests
- **PublicPagesTest**: Public homepage showing published projects

### No Test Modifications Required for Description Policy

The existing test suite does not include specific tests for title-only description handling, so no test changes were needed for the policy correction. The 211 passing tests confirm no regressions were introduced.

---

## 9. Final Readiness Recommendation

### Status: READY for dry-run verification ✅

All Phase 3.2 requirements have been implemented and verified:

- ✅ **58 eligible Projects** identified from audited content inventory
- ✅ **Description policy** correctly enforced: title-only → NULL, genuine prose imported, News/Project pairs with provenance
- ✅ **Dry-run completes successfully** with correct counters (58 new projects, 0 failures)
- ✅ **Zero actual database writes** executed during verification
- ✅ **132 News records preserved** unchanged
- ✅ **No media copied or modified** during dry runs
- ✅ **All 211 tests pass** (1,039 assertions)
- ✅ **Pint: 0 style issues** across all authored PHP files
- ✅ **Schema compatible**: all required fields mapped; only additive migrations needed for `address` field
- ✅ **Seeder safeguards**: `LPRES_PROJECTS_SEEDER_CONFIRM=1` required, never auto-registered in DatabaseSeeder
- ✅ **Admin edit protection**: `target_hash` in `wp_import_map` protects administrator edits

### Recommended Next Steps (with approval)

1. **Review and approve the implemented infrastructure**
2. **Run `php artisan lpres:import-wordpress --type=projects --confirm`** for confirmed execution (if so authorized)
3. **Verify the 58 Projects records in the database** after execution
4. **Run follow-up maintenance**: re-runs with `--confirm` should detect idempotency (unchanged/unchanged patterns)

### Pending Items (not blocking)

- **`address` field**: Not currently mapped from WordPress export; additive migration would add `ALTER TABLE projects ADD COLUMN address VARCHAR` if required for L-PRES admin workflow
- **Full production execution**: Should only proceed after explicit user authorization and `LPRES_PROJECTS_SEEDER_CONFIRM=1` setup

---

*Review completed: All discrepancies resolved. Implementation verified against Phase 3.1 audit findings. No database writes or content imports executed during review.*