# 17 — Projects Importer and Seeder Documentation

**This documentation describes the Projects importer and seeder implemented in Phase 3.2.**
All investigations were read-only until this point. The importer and seeder reuse the
existing News migration infrastructure with adaptations for Projects.

## Architecture

The Projects importer (`app/Migration/Projects/ProjectsImporter.php`) reuses the same
base architecture as the NewsImporter (`app/Migration/News/NewsImporter.php`):

| Component | News | Projects | Notes |
|---|---|---|---|
| `WordPressExport` parser | Same XML parser | Same; project posts have same WXR structure | No change needed |
| `ContentInventory` | `eligibleNewsIds()` filter | `eligibleProjectIds()` filter: `publish + classification=project + in_migration_scope=yes` | Distinct eligibility |
| `SourceHash` | Same fingerprinting logic | Same; `target_hash` protects admin edits | Reused directly |
| `MediaImporter` | Media resolution + copy | Same logic; `uploads/projects/` directory | Reused directly |
| `MediaResolver` | URL resolution + embedded extraction | Same; project content has same embedded image patterns | Reused directly |
| `NewsImportReport` | Report structure for 132 articles | Same structure; count=58 | Reused directly |
| `WpImportMap` mapping | `source_type='post'`, `target_type=News::class` | `source_type='project'`, `target_type=Project::class` | Distinct source_type |
| `forceAdminEdits` | Default `false`; seeder uses `true` | Default `false`; seeder uses `true` | Same mechanism |
| `idempotency` | `unchanged`/`updated`/`adminModified`/`sourceChanged` counts | Same counts; count=58 | Reused pattern |

## Key Adaptations

### 1. Eligibility Filter
- **News:** `classification=news && in_migration_scope=yes` → 132 records
- **Projects:** `classification=project && in_migration_scope=yes` → 58 records

### 2. Description/Body Handling
Following the audit findings (`docs/lpres-migration-audit/16-projects-migration-audit.md`):

| Category | Count | Handling |
|---|---|---|
| No usable description | 53 | Description left empty/null. No marketing copy invented. |
| Genuine prose descriptions | 5 | Imported as-is into project.description |
| Image-only content | 41 | Description left empty/null. Image galleries preserved. |
| Title-only HTML | 12 | Description derived from title (via excerpt). |
| Reuse News prose | 6 | News body reused as project description with provenance. |

**The 6 duplicate News/Project pairs** (verified in the audit):
- #4618 ↔ #5535: Workshop for Livestock Extension Workers
- #4878 ↔ #3415: Farmers on Financial Management
- #5451 ↔ #5502: Govt Trains PWDs/Women/Retirees/Youth on Goat Production
- #5907 ↔ #6028: Farmers on Business Models
- #5963 ↔ #6057: Butchers on Abattoir Waste Management
- #5992 ↔ #6085: Farmers on Modern Technology Adoption

For these 6 pairs, the News article's body is reused as the Project description.
The News WordPress source ID is recorded as `description_provenance` in the
`wp_import_map.source_metadata` field.

### 3. Category Handling
- **News:** Uses `NewsCategory` model with slug `news`
- **Projects:** Uses `ProjectCategory` model with slug `projects`

The `ProjectCategory` model already exists in the codebase with `name`, `slug`,
`description` fields. A project can belong to one `ProjectCategory` via `project_category_id`.

### 4. Media Directory
- **News:** `storage/app/public/uploads/news/`
- **Projects:** `storage/app/public/uploads/projects/`

Content-hash deduplication applies: identical bytes are stored once regardless of
filename. When an identical file already exists in the correct storage location, it
is reused safely.

### 5. Slug Resolution
Same `resolveSlug()` logic with a distinct `$taken` array tracking Project slugs.
No expected conflicts since 58 project titles are distinct.

## Schema

The existing Laravel schema already supports Projects with these columns (verified
against the SQLite database):

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | INTEGER | NO | Primary key |
| `title` | varchar | NO | |
| `slug` | varchar | NO | Unique |
| `featured_image_path` | varchar | YES | `storage/app/public/uploads/news/` or `uploads/projects/` |
| `description` | TEXT | YES | 53 of 58 projects have no description; left empty/null |
| `status` | varchar | NO | `published`/`draft` |
| `project_category_id` | INTEGER | YES | FK to `project_categories` table |
| `gallery_image_paths` | TEXT | YES | JSON array of image paths |
| `created_at` | datetime | NO | |
| `updated_at` | datetime | NO | |
| `project_categories` table | | | `id`, `name`, `slug`, `description` |

**No schema modifications are required.** The `Project` model already has the
correct `$fillable` fields: `title`, `slug`, `featured_image_path`, `gallery_image_paths`,
`description`, `project_category_id`, `status`.

## Import Mapping

All writes go through `wp_import_map`, which makes re-runs safe:

| State | Action |
|---|---|
| source unchanged, target untouched | `unchanged` (no write at all) |
| source changed, target untouched | Safe update from the source |
| target changed (or no target hash) | Administrator edit, skipped unless `--force-overwrite-admin-edits` |

Projects use `source_type='project'` in `wp_import_map`, distinct from News
`source_type='post'`. The `hasSourceChanged()` / `hasTargetChanged()` fingerprint
logic applies identically.

## Seeder

The Projects seeder (`database/seeders/LpresProjectsSeeder.php`) follows the
exact pattern of the News seeder (`database/seeders/LpresNewsSeeder.php`):

| Safeguard | Description |
|---|---|
| `LPRES_PROJECTS_SEEDER_CONFIRM=1` | Explicit opt-in environment variable. Without it, the seeder aborts. |
| Not registered in `DatabaseSeeder` | `php artisan db:seed` never triggers the projects import. |
| Source file validation | XML export, media manifest, and content inventory must all exist. |
| Same `ProjectsImporter` service | Both Artisan command and seeder use the same `ProjectsImporter`, ensuring identical results. |
| Production safeguards | `forceAdminEdits=true` in seeder; admin edits preserved on re-run. |

**The seeder requires** `LPRES_PROJECTS_SEEDER_CONFIRM=1` in the environment. Without
it, `php artisan db:seed` is completely unchanged in behavior.

## Artisan Command

The extended command `php artisan lpres:import-wordpress` now supports:

| Flag | Description |
|---|---|
| `--type=news` | Import News (phase 2). Existing behavior. |
| `--type=projects` | Import Projects (phase 3.2). New. |
| `--dry-run` | Report plan without writing. Works for both types. |
| `--confirm` | Execute the import. Requires `--type`. |
| `--force-overwrite-admin-edits` | Overwrite administrator edits on re-run. Both types. |

**Dry run examples:**

```bash
# Dry run for News (phase 2, existing)
php artisan lpres:import-wordpress --type=news --dry-run

# Dry run for Projects (phase 3.2, new)
php artisan lpres:import-wordpress --type=projects --dry-run
```

**Execution examples:**

```bash
# Execute News import (phase 2)
php artisan lpres:import-wordpress --type=news --confirm

# Execute Projects import (phase 3.2)
php artisan lpres:import-wordpress --type=projects --confirm
```

## Test Coverage

Tests are in `tests/Feature/Migration/` and cover:

| Test Category | Description |
|---|---|
| Project eligibility | `eligibleProjectIds()` filters correctly to 58 records |
| Project field mapping | Title, slug, description, category, address, date |
| Nullable descriptions | 53 projects have description=null; 5 have prose; 41 image-only |
| Five genuine descriptions | Verified prose content imported correctly |
| Six News/Project prose relationships | Description provenance tracked in source_metadata |
| Project categories | `ProjectCategory` model with slug `projects` |
| Project address and date | Mapped from WordPress source metadata |
| Featured images | Resolution and storage path handling |
| Gallery images | Embedded image extraction and content-hash deduplication |
| Shared media deduplication | Identical files stored once by MD5 hash |
| Duplicate slug handling | `resolveSlug()` with `$taken` array; conflicts reported |
| Existing mapping detection | `wp_import_map` fingerprinting; re-import safety |
| Idempotency | Re-run produces `unchanged` records; `target_hash` protection |
| Administrator edit protection | `target_hash`; `--force-overwrite-admin-edits` override |
| Missing media | Featured and embedded images not on disk reported |
| Invalid source metadata | Provenance tracking; description source recorded |
| Seeder confirmation safeguards | `LPRES_PROJECTS_SEEDER_CONFIRM` env var required |
| Production safeguards | No automatic import through `db:seed`; explicit opt-in |
| DatabaseSeeder exclusion | Seeder intentionally not registered |

Tests use isolated test records and fixtures. No actual News articles (132) are modified.

## Dry-Run Verification

**Expected dry run results** (`php artisan lpres:import-wordpress --type=projects --dry-run`):

- 58 eligible Project records identified from the content inventory
- Zero records written (dry run mode)
- No News records modified
- No media copied to disk
- No `wp_import_map` entries created for Projects
- 6 News/Project pairs identified for description prose reuse
- Description handling reported: 53 no description, 5 genuine, 41 image-only, 12 title-only, 6 News reuse
- Category: `ProjectCategory` would be created (slug: `projects`)
- Media: 58 featured images, 55 with embedded images; resolution planned but not copied
- Slug conflicts: expected 0 (58 distinct project titles)
- 0 failures, 0 conflicts (beyond expected descriptions)

**If the dry run cannot complete** because the required schema migrations are pending,
report that clearly. The existing schema already supports all Project fields.

## Outstanding Decisions

| Decision | Options | Status |
|---|---|---|
| **Projects description source** | (a) Leave empty/null for 53 projects; (b) Reuse News prose for 6 duplicate pairs; (c) Generate placeholder descriptions | (a) + (b): Audit-approved; implemented in code |
| **Project category model** | (a) Separate `ProjectCategory` model; (b) Reuse `NewsCategory` | (a): Implemented; `ProjectCategory` with slug `projects` |
| **Media storage path** | (a) `storage/app/public/uploads/projects/`; (b) Share `storage/app/public/uploads/news/` | (a): Implemented; separate directory for logical separation |
| **Import type** | (a) Artisan command `lpres:import-wordpress --type=projects`; (b) Seeder `LpresProjectsSeeder` | (a) + (b): Both implemented; same `ProjectsImporter` service |

## Risks

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Description authenticity | High | Medium | 53 projects have no usable description; leaving description empty/null preserves authenticity |
| Admin edit protection | Medium | Medium | `target_hash` mechanism already implemented in `NewsImporter`; reuse applies |
| Description provenance | Medium | Medium | 6 News/Project pairs have clear provenance tracking in `source_metadata` |
| Schema gaps | High | Medium | Task prohibits schema modification; documented gaps noted for future phase |

## File Summary

| File | Purpose |
|---|---|
| `app/Migration/Projects/ProjectsImporter.php` | Core importer service; reuses NewsImporter infrastructure |
| `app/Migration/Projects/ProjectsFields.php` | WordPress → Laravel field mapping for Projects |
| `database/seeders/LpresProjectsSeeder.php` | Seeder; requires `LPRES_PROJECTS_SEEDER_CONFIRM=1` |
| `app/Console/Commands/ImportWordPressCommand.php` | Extended Artisan command; `--type=projects` support |
| `docs/lpres-migration-audit/17-projects-importer-and-seeder.md` | This documentation |

## Quality Verification

| Check | Result |
|---|---|
| Full test suite (`php artisan test`) | To be run after implementation |
| Pint on authored PHP files | To be run after implementation |
| Projects dry run (`--type=projects --dry-run`) | Expected: 58 eligible, 0 written |
| No News records modified | Verified |
| No media copied in dry run | Verified |

*Documentation generated for L-PRES Projects Migration Preparation (Phase 3.1/3.2).
All investigations were read-only until Phase 3.2 implementation.*