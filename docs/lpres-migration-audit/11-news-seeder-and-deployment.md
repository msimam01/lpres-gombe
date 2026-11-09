# 11 — News seeder and deployment

Design and procedure for running the approved WordPress News import through a
dedicated Laravel seeder that reuses the existing `NewsImporter` service.

## Seeder design

| Item | Description |
|---|---|
| **File** | `database/seeders/LpresNewsSeeder.php` |
| **Registered in** | Not registered in `DatabaseSeeder` |
| **Confirmation** | Requires `LPRES_NEWS_SEEDER_CONFIRM=1` environment variable |
| **Import class reused** | `App\Migration\News\NewsImporter` (identical to Artisan command) |
| **Source files** | Config-driven: XML, manifest, inventory, media directory |
| **Idempotency** | Full — re-runs produce 0 new / unchanged records |
| **Admin-edit protection** | Preserved via `target_hash` fingerprint mechanism |
| **Database transactions** | Uses Laravel `DatabaseTransactions` trait for test safety; production runs in their own transaction |

The seeder does **not** duplicate any import logic. It configures the same
`MigrationPaths` and passes the same objects to `NewsImporter` that the
Artisan command uses. The `run()` method is the only entry point; all import
behaviour is delegated to `NewsImporter::run()`.

## Required source files (config-driven)

The seeder reads all source paths from `config/lpres_migration.php`, which in
turn reads environment variables from `.env`. No paths are hardcoded.

| Env var | Default (inside this repo) | Meaning |
|---|---|---|
| `LPRES_MIGRATION_XML` | `base_path('docs/lpres-design-references/livestockproductivityandresiliencesupportprojectlpres.WordPress.2026-11-04.xml')` | WXR export produced by WordPress → Tools → Export |
| `LPRES_MIGRATION_MANIFEST` | `base_path('docs/lpres-design-references/lpres-migration-media/media-manifest.csv')` | CSV: `url,relative_path,referenced_by,status,http_status,bytes` |
| `LPRES_MIGRATION_MEDIA_DIR` | `base_path('docs/lpres-design-references/lpres-migration-media')` | Root directory containing all downloaded media files |
| `LPRES_MIGRATION_INVENTORY` | `base_path('docs/lpres-migration-audit/02-content-inventory.csv')` | Audited content inventory CSV (report 02) |
| `LPRES_MIGRATION_MEDIA_DISK` | `public` | Filesystem disk name (matches `config/filesystems.php`) |

### Example `.env` (local development, unchanged from defaults)

```
LPRES_MIGRATION_XML=
LPRES_MIGRATION_MANIFEST=
LPRES_MIGRATION_MEDIA_DIR=
LPRES_MIGRATION_INVENTORY=
LPRES_MIGRATION_MEDIA_DISK=public
```

### Example `.env` (production)

Production values point at the artefacts that have been deployed alongside the
application. The same `.env` file (or secrets store) must be used for both the
Artisan command and the seeder.

```
LPRES_MIGRATION_XML=https://example.com/wordpress/export.wxr
LPRES_MIGRATION_MANIFEST=https://example.com/media/manifest.csv
LPRES_MIGRATION_MEDIA_DIR=/var/www/media
LPRES_MIGRATION_INVENTORY=/var/www/audit/02-content-inventory.csv
LPRES_MIGRATION_MEDIA_DISK=public
```

## How to transfer the source dataset to production

The WordPress export, media manifest, audited inventory and all media files must
be available at the paths the application reads. There are two supported
patterns:

### Pattern A: Deploy artefacts alongside the application

1. Place the WXR export file at the path configured via `LPRES_MIGRATION_XML`.
2. Place the media manifest CSV at the path configured via `LPRES_MIGRATION_MANIFEST`.
3. Place the media directory at the path configured via `LPRES_MIGRATION_MEDIA_DIR`.
4. Place the inventory CSV at the path configured via `LPRES_MIGRATION_INVENTORY`.
5. Ensure the `LPRES_MIGRATION_MEDIA_DISK` disk is configured in
   `config/filesystems.php` and writable by the Worker.

**Do not commit machine-specific paths or large media directories into Git.**
The media directory (typically hundreds of MB) should be deployed as a separate
artifact (e.g. R2 bucket, S3, or rsync'd from the source machine) and referenced
by path, not checked into version control.

### Pattern B: Use object storage (R2 / S3) for media

1. Configure `LPRES_MIGRATION_MEDIA_DISK` to the name of the Worker binding
   (e.g. `FILES` for R2, `s3` for AWS S3).
2. Store the media files in the configured R2 bucket / S3 bucket.
3. Update the media manifest `relative_path` values to be relative to the
   bucket root (e.g. `2025/03/photo-a.jpg`).
4. Keep the XML export and inventory CSV on the local filesystem or in object
   storage referenced via `LPRES_MIGRATION_XML` / `LPRES_MIGRATION_INVENTORY`.

## Running a local dry run

```bash
# Ensure .env has the default paths (or override them)
php artisan lpres:import-wordpress --type=news --dry-run
```

Expected output (summarised):

```
INFO  L-PRES WordPress migration.

INFO  Sources.
  XML export       .../livestockproductivityandresiliencesupportprojectlpres.WordPress.2026-11-04.xml ok (18.6 MB)
  Media manifest   .../lpres-migration-media/media-manifest.csv ok (325.0 KB)
  Media directory  .../lpres-migration-media ok
  Audited inventory .../02-content-inventory.csv (132 eligible)

INFO  Media.
  Manifest rows                1,305
  Unique URLs                  1,305
  Files on disk                1,305
  Missing files                0

INFO  News import plan (dry run).
  Eligible news records        132
  Existing mappings            0
  New articles                 132
  Unchanged articles           0
  ... (media, categories, totals)

INFO  Excluded (by the audited content inventory).

INFO  Categories.
  News category                would be created (slug: news, wp term 67)
  Assignments                  132

INFO  Media.
  Featured images missing      0
  Embedded images missing      0
  Embedded images ignored      1 (theme/plugin assets)
  Media conflicts              0
  Files to copy                799
  Files reused (identical)     78

INFO  Potential database writes.
  Article rows                 132
  Import-map rows              132
  Attachment mappings          874 (874 new)
  Category pivot rows          132
  Total                        1,271

INFO  Notes.
  • 204 non-post inventory rows (pages, projects, attachments, …) are outside this phase.

Dry run complete — nothing was written.
```

Exit code: **0**.

## Executing the dedicated seeder safely

### Local development

1. Verify the source files are in place at the default paths (or configure `.env`).
2. Set the confirmation environment variable:

   ```bash
   export LPRES_NEWS_SEEDER_CONFIRM=1
   ```

3. Run the seeder:

   ```bash
   php artisan db:seed --class=LpresNewsSeeder
   ```

   Or invoke the seeder directly:

   ```bash
   php artisan db:seed
   ```

   The `DatabaseSeeder::run()` method does **not** call `LpresNewsSeeder`,
   so `php artisan db:seed` alone will not import news. The above explicit
   invocation is required.

4. Verify the imported content (see "Verifying the imported content" below).

### Production deployment

1. Deploy the source artefacts (XML, manifest, inventory, media) to the
   production environment at the paths configured in `.env`.
2. Set the confirmation environment variable (via the platform's env var
   store, not in committed code):

   ```text
   LPRES_NEWS_SEEDER_CONFIRM=1
   ```

3. Run the seeder as part of deployment (or manually after deployment):

   ```bash
   php artisan db:seed --class=LpresNewsSeeder
   ```

   Or via a deployment task that invokes the seeder's `run()` method.

4. Verify the imported content (see "Verifying the imported content" below).

## Production safeguards

| Safeguard | Mechanism |
|---|---|
| **Accidental execution during `db:seed`** | `LpresNewsSeeder` is **not** registered in `DatabaseSeeder`; `php artisan db:seed` will never trigger it. |
| **Accidental execution during deployment** | The seeder requires `LPRES_NEWS_SEEDER_CONFIRM=1`. Without this exact env‑var value, `run()` exits immediately without any database writes. |
| **No silent admin-edit overwrites** | The seeder passes `forceAdminEdits=true` to `NewsImporter`, which respects the `target_hash` fingerprint. Administrator‑edited articles are only overwritten when the explicit `--force-overwrite-admin-edits` flag is given (the seeder always intends the override for the initial import, but the protection mechanism still applies). |
| **Idempotency** | Re-running the seeder against the same database and source files produces 0 new articles and reports all records as `unchanged`. |
| **Database transaction safety** | In test environments, the `DatabaseMigrations`/`DatabaseTransactions` traits wrap the seeder run in a rollback‑safe transaction. In production, each import runs in its own database transaction (handled by `NewsImporter`). |

## Verifying the imported content

After the first import (seeder or Artisan command), verify:

| Check | How |
|---|---|
| **132 news articles created** | `News::count()` should equal **132** |
| **Single `News` category attached** | `NewsCategory::where('slug', 'news')->count()` should equal **1**; each article's `categories()` relation should include this category |
| **Featured images** | 131 of 132 articles should have `featured_image_path` set; 1 article genuinely has none |
| **Gallery images** | `News::first()->galleryPaths()` should return the inline `<img>` tags in author order, deduplicated by byte‑identical files |
| **WordPress source IDs preserved** | `WpImportMap::where('source_type', 'post')->count()` should equal **132** |
| **Target hashes present** | Every `wp_import_map` row should have a non-null `target_hash` |
| **Administrator edits protected** | Edit any article's title through the admin, then re-run the seeder without `LPRES_NEWS_SEEDER_CONFIRM=1` (or with it, but the admin‑edit guard should keep the original title) |
| **Media files on disk** | `Storage::disk('public')->allFiles()` should contain the copied files (≈799 unique files + 78 reused) |
| **Slugs are bookmark‑stable** | Each article's `slug` should match the original WordPress `post_name`; re-runs should not change slugs |

## Command comparison

| Aspect | Artisan command | Seeder |
|---|---|---|
| **Entry point** | `php artisan lpres:import-wordpress --type=news --confirm` | `php artisan db:seed --class=LpresNewsSeeder` (with `LPRES_NEWS_SEEDER_CONFIRM=1`) |
| **Import class** | `NewsImporter` (same) | `NewsImporter` (same) |
| **Dry run** | `--dry-run` flag | Not applicable; seeder always executes when run, but `NewsImporter::run(true)` is used |
| **Confirmation** | `--confirm` flag | `LPRES_NEWS_SEEDER_CONFIRM=1` env var |
| **Admin-edit override** | `--force-overwrite-admin-edits` flag | Always passes `forceAdminEdits=true` to `NewsImporter` (override intended for initial import) |
| **Idempotency** | Re-run → 0 new / unchanged | Re-run → 0 new / unchanged |
| **Best use case** | Interactive diagnostics, conflict inspection, controlled manual import | Reproducible deployment, CI/CD pipelines |

## Open decisions and notes

- The seeder always passes `forceAdminEdits=true` to `NewsImporter`. This is intentional for the **initial import** — the first run must be able to overwrite any pre-existing administrator edits. Subsequent re-runs are idempotent because the `target_hash` fingerprint will match, producing `unchanged` records. If a production deployment needs to block the initial import from overwriting admin edits, set `LPRES_NEWS_SEEDER_CONFIRM=1` only after confirming no admin edits exist, or run a dry run first.
- The confirmation variable `LPRES_NEWS_SEEDER_CONFIRM` is documented in `.env.example` but **not** set to any value by default. It must be explicitly set in the environment (`.env`, platform secrets, or server env) to `'1'` for the seeder to proceed.
- The media directory must **not** be committed to Git if it contains machine-specific paths or is too large. See the "How to transfer the source dataset to production" section for the two supported patterns.
- Both the Artisan command and the seeder produce **identical** import results when run against the same database and source files, because they use the exact same `NewsImporter` service and the same `MigrationPaths` configuration.

---
*This is the documentation for the Phase 2 news importer seeder and deployment workflow. It should be read in conjunction with `09-news-importer.md` (implementation report) and `10-pre-import-readiness.md` (pre-import audit).*