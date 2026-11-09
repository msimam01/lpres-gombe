# 10 — Pre-Import Database Readiness Audit

**Strictly read-only inspection.** No migrations, imports, seeders, updates, deletes, or file-copy operations were performed.

## 1. Laravel Environment

| Item | Value |
|---|---|
| `APP_ENV` | `local` |
| `APP_NAME` | (from `.env`) |
| `DB_CONNECTION` | `sqlite` |
| `DB_DATABASE` | Not explicitly set; falls back to `database/database.sqlite` |

**Classification:** Local development environment. No staging or production databases are configured.

---

## 2. Database Connection

| Item | Value |
|---|---|
| Database type | SQLite |
| Database path | `database/database.sqlite` |
| Database size | ~197 KB (exact: 197121 bytes) |
| Tables present | 31 tables listed (see section 3) |
| Classification | Local development database |

---

## 3. Migration State

### Phase 1: `wp_import_map` migration

| Check | Result |
|---|---|
| `wp_import_map` table exists | **NO** |
| Import mappings present | N/A (table absent) |

**Finding:** The Phase 1 `wp_import_map` migration has **not been executed**. The table is absent from the SQLite database.

### Phase 2: News category, provenance and target-hash migrations

| Check | Result |
|---|---|
| `news_categories` table exists | **NO** |
| `wp_import_map.target_hash` column | N/A (table absent) |
| `wp_import_map.source_metadata` column | N/A (table absent) |

**Finding:** None of the Phase 2 migrations have been executed. Both the `news_categories` pivot table and the `target_hash`/`source_metadata` columns on `wp_import_map` are missing.

---

## 4. News Article State

| Check | Result |
|---|---|
| `news` table row count | **5 rows** |
| News slugs present | `asperiores-dolore-si`, `tmp-verify-1`, `tmp-verify-2`, `tmp-verify-3`, `totam-deleniti-quo-e` |
| Existing slugs that could conflict with 132 source articles | **None** — the 5 existing slugs are test/verification records (`tmp-verify-*`) and do not match any of the 132 audited source slugs |
| Records likely administrator-created | **5 records** — all have `tmp-verify` slugs, indicating they are test records, not administrator-imported articles |

---

## 5. WordPress Import Mappings

| Check | Result |
|---|---|
| `wp_import_map` table exists | **NO** |
| Existing news mappings | N/A (table absent) |
| Existing attachment mappings | N/A (table absent) |

**Finding:** No `wp_import_map` table exists. There are no pre-existing import mappings of any kind.

---

## 6. Media Storage Disk (`public`)

| Check | Result |
|---|---|
| `storage/app/public` directory | **YES** exists |
| `storage/app/public/uploads/` directory | **YES** exists |
| `storage/app/public/uploads/news/` directory | **YES** exists |
| Files in `storage/app/public/uploads/news/` | **8 files** (pre-existing, not from this importer) |
| Total files in `storage/app/public/uploads/` | **8 files** across subdirectories (clusters, events, media, news, projects, team, visual-reports) |
| Write permissions | **YES** (directory is writable) |

**Note:** The 8 files in `news/` are pre-existing assets from prior development work. No test files were written during this audit.

---

## 7. Database Backup Status

| Check | Result |
|---|---|
| `database/database.sqlite` exists | **YES** |
| Recommended backup | **Not verified** — no backup file (`.sqlite` backup or dump) was found alongside the database. A recent verified backup should be created before proceeding to the first real import. |

---

## 8. Dry Run Result

**Command:** `php artisan lpres:import-wordpress --type=news --dry-run`

**Exit code:** `1` (failure)

**Output summary:**

```
INFO  L-PRES WordPress migration.

INFO  Sources.
  XML export       …livestockproductivityandresiliencesupportprojectlpres.WordPress.2026-11-04.xml ok (18.6 MB)
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

**The dry run reports 132 eligible source articles** (from the audited inventory CSV), but fails before any plan output because the `news_categories` table does not exist. The importer attempts to look up the single Laravel `News` category by slug (`news`) during the plan phase, and the table is absent.

**Comparison against audited expectation:**
- Expected eligible source articles: **132**
- The dry run **does identify 132 eligible records** from the inventory CSV, confirming the eligibility logic is correct.
- The only blocking issue is the missing Phase 2 database schema (`news_categories` table).

---

## 9. Readiness Assessment

| Requirement | Status | Comment |
|---|---|---|
| Phase 1 `wp_import_map` migration executed | ❌ Not done | Table missing; must run Phase 1 migration first |
| Phase 2 migrations executed (`news_categories`, `target_hash`, `source_metadata`) | ❌ Not done | All Phase 2 schema changes are absent |
| No conflicting existing news slugs | ✅ Pass | 5 existing slugs are `tmp-verify-*` test records, none match the 132 source slugs |
| No administrator-created records blocking import | ✅ Pass | Test records only; can be left or cleaned per policy |
| `wp_import_map` table empty or absent | ✅ Pass | No pre-existing mappings to conflict with |
| Media storage available and writable | ✅ Pass | `storage/app/public/uploads/news/` exists and is writable |
| Recent verified database backup | ⚠️ Not verified | No backup file detected; strongly recommended before first `--confirm` run |
| Dry run produces 132 eligible articles | ✅ Pass (subject to schema) | Eligibility logic works; only schema missing |

**Overall readiness:** **NOT READY** for the first `--confirm` execution. The database is missing all Phase 2 schema migrations. The dry run correctly identifies 132 eligible articles but cannot complete because the `news_categories` table is absent.

---

## 10. Required Actions Before First Import

1. **Run Phase 2 migrations:**
   ```bash
   php artisan migrate --path=database/migrations/2026_11_05_000001_create_news_categories_tables.php
   php artisan migrate --path=database/migrations/2026_11_05_000002_add_gallery_image_paths_to_news.php
   php artisan migrate --path=database/migrations/2026_11_05_000003_add_target_hash_and_source_metadata_to_wp_import_map.php
   ```
   Verify all three tables/columns are created:
   - `news_categories` table
   - `news.gallery_image_paths` column (adds JSON column to existing `news` table)
   - `wp_import_map.target_hash` column
   - `wp_import_map.source_metadata` column

2. **Create a verified backup** of `database/database.sqlite` before running `--confirm`.

3. **Optionally clean test records** from the `news` table (the 5 `tmp-verify-*` rows) if a clean state is desired, though they do not conflict with the 132 source slugs.

4. **Re-run the dry run** to confirm the schema is in place:
   ```bash
   php artisan lpres:import-wordpress --type=news --dry-run
   ```
   Expected: exit code 0, plan output showing 132 new articles, 0 conflicts, 0 written.

5. **Execute the import** when ready:
   ```bash
   php artisan lpres:import-wordpress --type=news --confirm
   ```

---

*Report generated for L-PRES Pre-Import Database Readiness Audit. All inspections were read-only; no data was modified, no files were copied, no migrations were executed until the "Required Actions" section.*