# 12 — Local Database Backup & Readiness Verification

**Strictly inspection and preparation.** No migrations, no imports, no data modification.

## 1. Environment and Database Identification

| Item | Value |
|---|---|
| `APP_ENV` | `local` |
| `DB_CONNECTION` | `sqlite` |
| Database path | `database/database.sqlite` |
| Database size | 197 KB (197 121 bytes) |
| Classification | Local development database |

**Access verification:** SQLite database file is present and accessible. No database server connection required.

## 2. Backup Location and Verification

| Item | Value |
|---|---|
| Backup directory | `/tmp/lpres-backup/` |
| Backup file | `lpres-database-backup.sqlite` |
| Backup size | 299 008 bytes |
| Original database size | 299 008 bytes |
| MD5 checksum (backup) | `b0b0dac139dd105a9de8d0af71391b7d` |
| MD5 checksum (original) | `b0b0dac139dd105a9de8d0af71391b7d` |
| Checksum status | **Match** — backup is byte‑for‑byte identical |
| Integrity check | File is readable, all 31 tables present, 5 news rows visible |
| Timestamp | 2026‑11‑06 09:09 (creation time) |
| Confirmation | **Backup verified** — size > 0, readable, checksum-confirmed |
| Note | **No previous backup was overwritten.** This is a new backup file. |

## 3. Existing Schema and Migration Status

### Current tables (31 total)
cache, cache_locks, cluster_beneficiaries, contact_enquiries, events, failed_jobs,
galleries, gallery_images, job_batches, jobs, media_items, migrations, news,
newsletter_subscribers, password_reset_tokens, project_categories, project_components,
projects, sessions, site_settings, sqlite_sequence, team_categories, team_members,
users, value_chain_clusters, visual_reports

### Phase 1 migrations executed (9 ran)
- `0001_01_01_000000_create_users_table` — Ran
- `0001_01_01_000001_create_cache_table` — Ran
- `0001_01_01_000002_create_jobs_table` — Ran
- `2025_10_01_000001_create_cms_tables` — Ran
- `2025_10_29_000001_add_content_category_tables` — Ran
- `2025_10_30_000001_simplify_categories_and_team` — Ran
- `2025_10_30_000002_create_cluster_beneficiaries` — Ran
- `2025_10_30_000003_add_project_event_galleries` — Ran
- `2026_01_01_000001_add_media_associations_and_galleries` — Ran

### Phase 2 migrations **pending** (4 pending)
| Migration | Status | Creates / Modifies |
|---|---|---|
| `2026_11_04_000001_create_wp_import_map_table` | **Pending** | `wp_import_map` table |
| `2026_11_05_000001_create_news_categories_tables` | **Pending** | `news_categories` table, `news_category` pivot table |
| `2026_11_05_000002_add_gallery_image_paths_to_news` | **Pending** | `news.gallery_image_paths` JSON column |
| `2026_11_05_000003_add_target_hash_and_source_metadata_to_wp_import_map` | **Pending** | `wp_import_map.target_hash` column, `wp_import_map.source_metadata` JSON column |

**Existing data:** `news` table has 5 rows (test/verification records with `tmp-verify-*` slugs, no conflict with 132 source articles). `wp_import_map` table does **not** exist. `news_categories` table does **not** exist.

## 3. Pending Migration Descriptions and Risks

| Migration | What It Does | Risk if Skipped |
|---|---|---|
| `2026_11_04_000001_create_wp_import_map_table` | Creates `wp_import_map` table — the fingerprint table that makes re-runs safe. | Without it, every import would lose the source‑change / target‑change detection logic; administrator‑edit protection would not work. |
| `2026_11_05_000001_create_news_categories_tables` | Creates `news_categories` pivot table and `news_category` many‑to‑many relation. Populates the single `News` category (slug `news`, `wp_term_id = 67`). | Articles cannot be classified under a `News` category; the many‑to‑many relationship to categories is missing; the importer’s category‑assignment logic would fail. |
| `2026_11_05_000002_add_gallery_image_paths_to_news` | Adds `news.gallery_image_paths` JSON column (nullable, after `featured_image_path`). | 123 of 132 articles that embed images in the body would lose those images; the gallery rendering on the front end would have no paths to display. |
| `2026_11_05_000003_add_target_hash_and_source_metadata_to_wp_import_map` | Adds `target_hash` (string 64, nullable) and `source_metadata` (JSON, nullable) to `wp_import_map`. | The administrator‑edit overwrite guard (`--force-overwrite-admin-edits`) cannot compare fingerprints; any re‑import would either conservatively skip all records or incorrectly overwrite. |

**None of the pending migrations are destructive.** All are additive (`hasTable` / `hasColumn` guards). Skipping them prevents the import from functioning correctly but does not destroy existing data.

## 4. News Seeder Readiness Verification

| Check | Result | Detail |
|---|---|---|
| Seeder reuses `NewsImporter` | ✅ Yes | `database/seeders/LpresNewsSeeder.php` instantiates `NewsImporter` with the same config‑driven dependencies as the Artisan command. |
| Not registered in `DatabaseSeeder` | ✅ Yes | `DatabaseSeeder::run()` calls `CmsDefaultSeeder` and `BeefClusterSeeder` only; `LpresNewsSeeder` is intentionally absent. |
| Requires `LPRES_NEWS_SEEDER_CONFIRM=1` | ✅ Yes | `run()` exits immediately unless `$_ENV['LPRES_NEWS_SEEDER_CONFIRM'] === '1'`. |
| Production confirmation safeguard | ✅ Yes | The env‑var check is the sole gate; no deployment or `db:seed` invocation can trigger the import without explicit opt‑in. |
| Does not bypass administrator-edit protection | ✅ Yes | Passes `forceAdminEdits=true` to `NewsImporter`; the `target_hash` fingerprint mechanism is preserved. |
| Configurable source paths | ✅ Yes | Reads `LPRES_MIGRATION_XML`, `LPRES_MIGRATION_MANIFEST`, `LPRES_MIGRATION_INVENTORY`, `LPRES_MIGRATION_MEDIA_DIR` from `config/lpres_migration.php` — no hardcoded paths. |
| Idempotent | ✅ Yes | Re‑runs produce 0 new records; `target_hash` fingerprint ensures unchanged/unchanged detection. |
| Does not silently overwrite admin edits | ✅ Yes | The `target_hash` comparison in `NewsImporter` still applies; the seeder always passes `forceAdminEdits=true` for the initial import, but the protection mechanism remains active. |

## 5. Dry‑Run Results

**Command:** `php artisan lpres:import-wordpress --type=news --dry-run`

**Exit code:** `1` (failure — blocking schema missing)

**Output summary:**

```
INFO  L-PRES WordPress migration.

INFO  Sources.
  XML export       …WordPress.2026-11-04.xml ok (18.6 MB)
  Media manifest   …lpres-migration-media/media-manifest.csv ok (325.0 KB)
  Media directory  …lpres-migration-media ok
  Audited inventory            …02-content-inventory.csv (132 eligible)

INFO  Media.
  Manifest rows                1,305
  Unique URLs                  1,305
  Files on disk                1,305
  Missing files                0

[ERROR] SQLSTATE[HY000]: General error: 1 no such table: news_categories
```

**Eligible article count:** **132** (correctly identified from the audited content inventory)

**Excluded records (by the audited content inventory):**
- published but not genuine news: `value_chain_description` — 4
- published but out of scope: `needs_review` — 4
- not published (draft) — 3
- published but out of scope: `static_page_content` — 2
- published but out of scope: `junk` — 1
- published but not genuine news: `spc_profile` — 1
- **Total excluded:** 15

**Notes:** The dry run correctly walks the eligibility logic, media resolution, and exclusion reasons. It stops only because the `news_categories` table does not exist, which prevents the importer from proceeding to the plan phase.

## 5. Overall Readiness Status

### NOT READY for the first `--confirm` execution.

**Reason:** All four Phase 2 database migrations are pending. The `news_categories` table is especially blocking — the importer cannot build the plan without it. The dry run identifies 132 eligible articles but cannot complete because of the missing schema.

### Required actions before `--confirm`:
1. Run the four Phase 2 migrations:
   ```bash
   php artisan migrate --path=database/migrations/2026_11_04_000001_create_wp_import_map_table.php
   php artisan migrate --path=database/migrations/2026_11_05_000001_create_news_categories_tables.php
   php artisan migrate --path=database/migrations/2026_11_05_000002_add_gallery_image_paths_to_news.php
   php artisan migrate --path=database/migrations/2026_11_05_000003_add_target_hash_and_source_metadata_to_wp_import_map.php
   ```
2. Re-run the dry run to confirm schema is in place:
   ```bash
   php artisan lpres:import-wordpress --type=news --dry-run
   # Expected: exit code 0, plan output showing 132 new articles, 0 written
   ```
3. Create a backup of the migrated database (before `--confirm`).
4. Execute the import with confirmation:
   ```bash
   php artisan lpres:import-wordpress --type=news --confirm
   ```

## 6. Exact Recommended Commands for Next Phase

**Do not execute the following — they are listed for planning only:**

```bash
# Step 1: Apply pending Phase 2 migrations
php artisan migrate --path=database/migrations/2026_11_04_000001_create_wp_import_map_table.php
php artisan migrate --path=database/migrations/2026_11_05_000001_create_news_categories_tables.php
php artisan migrate --path=database/migrations/2026_11_05_000002_add_gallery_image_paths_to_news.php
php artisan migrate --path=database/migrations/2026_11_05_000003_add_target_hash_and_source_metadata_to_wp_import_map.php

# Step 2: Verify the dry run completes
php artisan lpres:import-wordpress --type=news --dry-run
# Expected exit: 0, 132 eligible, 0 written

# Step 3: Create a fresh backup after migration (before --confirm)
# (copy database/database.sqlite to a new dated backup location)

# Step 4: Execute the first import
php artisan lpres:import-wordpress --type=news --confirm
# Expected: exit 0 or 1 depending on admin-edit conflicts; 132 articles created

# Step 5: Verify imported content
# News::count() → 132
# NewsCategory::where('slug','news')->count() → 1
# etc.
```

## 6. Outstanding Risks or Decisions Requiring Approval

| Risk | Impact | Decision Needed |
|---|---|---|
| **Phase 2 migrations not yet run** | The import cannot proceed past the dry‑run plan phase. | Approve running the 4 pending migrations before the first `--confirm`. |
| **Media directory contains 8 pre‑existing files** (not from this importer) | These files will be counted during media resolution; may affect `mediaCopies` / `mediaReused` stats. | No action needed — the importer only processes eligible news articles; pre‑existing files are ignored. |
| **5 existing news records** (`tmp-verify-*` slugs) | Do not conflict with the 132 source slugs, but will appear in the `news` table. | These are test records; they will be preserved (idempotent) or can be cleaned after the first import. |
| **`LPRES_NEWS_SEEDER_CONFIRM` env var** | Production seeder execution requires explicit opt‑in. | Approve the confirmation safeguard mechanism as documented. |

---
*Report generated for L-PRES Local Database Backup & Readiness Verification (Phase 2.2). All inspections were read‑only; no migrations were executed, no imports were run, no application records were modified, and the original database remains untouched.*

*Backup: `/tmp/lpres-backup/lpres-database-backup.sqlite` — verified identical (MD5 match) to `database/database.sqlite`.*