# 08 — WordPress Import Infrastructure (Phase 1)

Date: **2026-11-04**
Status: **infrastructure only — no content imported, no media copied, no existing record touched.**

This document describes what Phase 1 added to the Laravel application so that the WordPress
migration can be built and reviewed safely in a later phase. It is the implementation companion to
`00-summary.md`, `06-migration-field-mapping.md` and `07-final-migration-decisions.md`.

---

## 1. What was built

| Capability | Where |
|---|---|
| Source configuration (XML, manifest, media dir, work dir, disk) | `config/lpres_migration.php` |
| Import tracking table | `wp_import_map` (migration + `App\Models\WpImportMap`) |
| WXR parser (namespaces, 1.2 and 1.1) | `App\Migration\WordPress\WordPressXmlParser` |
| Immutable source record | `App\Migration\WordPress\WordPressItem` |
| Parsed export + aggregates | `App\Migration\WordPress\WordPressExport` |
| HTML → plain text | `App\Migration\HtmlToText` |
| Source fingerprint | `App\Migration\SourceHash` |
| Media manifest reader | `App\Migration\MediaManifest` |
| Path resolution + validation | `App\Migration\MigrationPaths` |
| Pre-flight command | `php artisan lpres:import-wordpress --dry-run` |
| Tests | `tests/Unit/Migration`, `tests/Feature/Migration` |

Nothing in this list writes content, copies media or mutates the import map.

---

## 2. Files created

**Configuration and schema**

- `config/lpres_migration.php`
- `database/migrations/2026_11_04_000001_create_wp_import_map_table.php`
- `app/Models/WpImportMap.php`

**Migration utilities** (`App\Migration`)

- `app/Migration/MigrationPaths.php`
- `app/Migration/MigrationSourceException.php`
- `app/Migration/MediaManifest.php`
- `app/Migration/HtmlToText.php`
- `app/Migration/SourceHash.php`
- `app/Migration/WordPress/WordPressXmlParser.php`
- `app/Migration/WordPress/WordPressExport.php`
- `app/Migration/WordPress/WordPressItem.php`
- `app/Migration/WordPress/WordPressExportException.php`

**Command**

- `app/Console/Commands/ImportWordPressCommand.php`

**Tests and fixtures**

- `tests/Unit/Migration/WordPressXmlParserTest.php`
- `tests/Unit/Migration/HtmlToTextTest.php`
- `tests/Unit/Migration/SourceHashTest.php`
- `tests/Unit/Migration/MediaManifestTest.php`
- `tests/Feature/Migration/WpImportMapTest.php`
- `tests/Feature/Migration/ImportWordPressCommandTest.php`
- `tests/Feature/Migration/MigrationPathsTest.php`
- `tests/Fixtures/wordpress/sample-export.xml`
- `tests/Fixtures/wordpress/media-manifest.csv`
- `tests/Fixtures/wordpress/media-manifest-missing.csv`
- `tests/Fixtures/wordpress/media/2025/04/photo.jpg`
- `tests/Fixtures/wordpress/media/2025/04/photo-two.jpg`

**Documentation**

- `docs/lpres-migration-audit/08-import-infrastructure.md` (this file)

## 3. Files modified

- `.env.example` — a commented, additive block documenting the optional `LPRES_MIGRATION_*`
  variables. **No existing key was changed or removed.**

No model, controller, admin page, route, migration, seeder or React component was modified.

---

## 4. Database change prepared (not executed)

One new table. The dev database has **not** been migrated — `php artisan migrate` is a deliberate
operator step.

```sql
create table "wp_import_map" (
    "id"            integer primary key autoincrement not null,
    "source_type"   varchar not null,
    "source_id"     integer not null,
    "target_type"   varchar,
    "target_id"     integer,
    "source_hash"   varchar,
    "imported_at"   datetime,
    "created_at"    datetime,
    "updated_at"    datetime
);
create unique index "wp_import_map_source_type_source_id_unique"
    on "wp_import_map" ("source_type", "source_id");
create index "wp_import_map_target_type_target_id_index"
    on "wp_import_map" ("target_type", "target_id");
```

Captured with `php artisan migrate --pretend` (which prints DDL without executing it). The migration
also guards with `Schema::hasTable()` and `down()` drops the table.

**Column notes**

| Column | Purpose |
|---|---|
| `source_type` | WordPress post type: `post`, `page`, `project`, `team`, `attachment`, `testimonials`, custom types, and later `term` / `user` if needed |
| `source_id` | Original WordPress numeric ID — **never** a Laravel primary key |
| `target_type` | Target model class (`App\Models\News`, …). The application registers no morph map, so the class name is stored directly and `target()` resolves with `morphTo` |
| `target_id` | Target primary key; nullable so a mapping can be reserved before its record exists |
| `source_hash` | SHA-256 of the importable source fields (see §7) |
| `imported_at` | Set only once a record has actually been written; `NULL` means "reserved" |

The unique index on `(source_type, source_id)` is what makes a re-run idempotent. The
`(target_type, target_id)` index answers the reverse question: *which WordPress record produced this
Laravel row?*

**Why a mapping table and not `wp_id` columns:** the public schema keeps zero WordPress-specific
columns, the same mechanism covers posts, pages, attachments and terms, and the reverse-index
question stays answerable. This is decision **D1** in `07-final-migration-decisions.md`.

---

## 5. Configuration

`config/lpres_migration.php` reads five environment variables. Every default resolves **inside this
repository**, so a fresh checkout runs the dry run with no setup at all.

| Config key | Environment variable | Default |
|---|---|---|
| `xml` | `LPRES_MIGRATION_XML` | `docs/lpres-design-references/livestockproductivityandresiliencesupportprojectlpres.WordPress.2026-11-04.xml` |
| `manifest` | `LPRES_MIGRATION_MANIFEST` | `docs/lpres-design-references/lpres-migration-media/media-manifest.csv` |
| `media_directory` | `LPRES_MIGRATION_MEDIA_DIR` | `docs/lpres-design-references/lpres-migration-media` |
| `working_directory` | `LPRES_MIGRATION_WORK_DIR` | `storage/app/private/lpres-migration` |
| `media_disk` | `LPRES_MIGRATION_MEDIA_DISK` | `public` |

Behaviour:

- **No machine-specific path is committed.** The defaults are repository-relative and are passed
  through `base_path()`, so they work from any checkout directory.
- **Relative values** (from an env var) are resolved against `base_path()`, never the process
  working directory. Absolute POSIX, UNC and Windows drive paths are used as given.
- **A missing or empty path is never substituted.** The command reports the exact path and the
  environment variable that fixes it, and exits non-zero.
- `working_directory` and `media_disk` are declared for the importer phase; Phase 1 never creates or
  writes them.

### Configuring the paths

Nothing is required when the export and media stay in this repository. To point elsewhere, add to
`.env` (not `.env.example`):

```dotenv
LPRES_MIGRATION_XML=/absolute/path/to/lpres.WordPress.xml
LPRES_MIGRATION_MANIFEST=/absolute/path/to/media-manifest.csv
LPRES_MIGRATION_MEDIA_DIR=/absolute/path/to/lpres-migration-media
```

On Windows, forward slashes are fine:

```dotenv
LPRES_MIGRATION_XML=D:/migration/lpres.WordPress.xml
```

The `.env.example` block is commented out on purpose: an empty `LPRES_MIGRATION_XML=` would override
the in-repo default with an empty string.

---

## 6. The command

```bash
php artisan lpres:import-wordpress --dry-run
```

**Exit codes**

| Code | Meaning |
|---|---|
| `0` | Source is valid and the report completed (dry run only) |
| `1` | Invoked **without** `--dry-run` — import execution does not exist yet, nothing was written |
| `2` | A source is missing, unreadable, empty, unparseable, or the manifest references absent files |

**What it checks, in order**

1. **Sources** — the XML export, manifest and media directory resolve, exist, are readable and are
   non-empty; every problem is listed with the variable that fixes it.
2. **Export** — parses the WXR with `SimpleXML` and correct WordPress namespaces; reports site title
   and URL, WXR version, total records, taxonomy counts, and a per-post-type table with publish /
   draft / other columns.
3. **Media** — manifest header (`url, relative_path, referenced_by, status, http_status, bytes`,
   BOM-tolerant), row count, unique URL count, files on disk (the manifest CSV itself is excluded
   from that count), and every manifest row whose file is absent.
4. **Import map** — if `wp_import_map` exists, reconciles each source record against its mapping and
   reports, per post type: total, imported, reserved, new, and changed-since-import.
5. **No writes at any point.** No content record, media file, mapping row or schema change.

**Current output on the audited export** (abridged):

```
INFO  Sources
  XML export       docs/.../...WordPress.2026-11-04.xml ok (18.6 MB)
  Media manifest   docs/.../media-manifest.csv ok (325.0 KB)
  Media directory  docs/.../lpres-migration-media ok

INFO  WordPress export
  Site            Livestock Productivity and Resilience Support Project (LPRES) https://lpres.gm.gov.ng/home
  WXR version     1.2
  Records         2,140
  Taxonomies      9 categories, 5 tags, 59 custom terms

| Post type          | Records | Publish | Draft | Other |
| attachment         | 1,688   | 0       | 0     | 1,688 |
| post               | 147     | 144     | 3     | 0     |
| project            | 60      | 58      | 2     | 0     |
| page               | 59      | 54      | 5     | 0     |
| team               | 22      | 22      | 0     | 0     |
| tribe_events       | 6       | 6       | 0     | 0     |
| …                  |         |         |       |       |

INFO  Media
  Manifest rows   1,305
  Unique URLs     1,305
  Files on disk   1,305
  Missing files   0

INFO  Import map
  WARN  The wp_import_map table does not exist yet — run `php artisan migrate`.
```

These figures reproduce the audit exactly (`00-summary.md` §2), which is a useful cross-check: the
parser and the audit scripts read the same export to the same totals.

If `wp_import_map` has not been migrated, the command warns and treats every record as new — the
dry run stays useful on a fresh clone without touching the schema.

---

## 7. Utilities the importer will build on

### WXR parsing (`WordPressXmlParser`)

- Resolves the WordPress namespace explicitly (WXR **1.2** and **1.1**); prefixed tag names are never
  used with SimpleXML, which would silently return empty values.
- Reads items, channel taxonomies (categories, tags, custom terms) and authors.
- Reads `wp:postmeta` into `key => list<string>` so multi-value meta survives.
- Reads `<category domain="…" nicename="…">` into `domain / nicename / name`, which covers
  `category`, `post_tag` and custom taxonomies such as `project_cat`.
- **Rejects a non-numeric `wp:post_id`** rather than silently dropping the record.
- Disables network access (`LIBXML_NONET`) and never enables entity loading; `LIBXML_NOENT` is not
  used, so external entities cannot be expanded.
- Distinguishes missing / unreadable / empty / not-WXR / malformed, each with its own exception
  message.

### HTML → plain text (`HtmlToText`)

Required because the public pages render `news.body`, `projects.description`,
`events.description` and `value_chains.description` as **escaped text split on blank lines**
(`06-migration-field-mapping.md` §2). Raw HTML would print literal tags.

Rules implemented and tested:

| Input | Output |
|---|---|
| `<script>`, `<style>`, `<noscript>`, `<iframe>`, forms | removed with their contents |
| `<img>`, `<picture>`, `<video>`, `<audio>` | removed entirely — image URLs never become prose |
| `<p>`, `<div>`, headings, sections, table rows | paragraph break (one blank line) |
| `<br>` | single line break |
| `<h1>`–`<h6>` | text on its own paragraph (no `##` markers — the target is plain text) |
| `<ul><li>` | `• item` lines |
| `<ol><li>` | `1. item` lines |
| `<td>`/`<th>` | cells separated by ` | ` within a row |
| `<a href>` | link text only, no URL, no tag |
| Entities (`&amp;`, `&#8211;`, `&nbsp;`) | decoded; NBSP becomes a normal space |
| 3+ consecutive newlines | collapsed to one blank line |
| Malformed HTML | parsed by DOM; a regex fallback keeps the conversion total if DOM cannot load it |

Non-ASCII is encoded to numeric entities before `DOMDocument::loadHTML` so en-dashes and curly
quotes do not turn into mojibake, and the deprecated `mb_convert_encoding(..., 'HTML-ENTITIES')`
path is avoided.

### Source fingerprint (`SourceHash`)

Hashes only the fields the migration imports: type, id, status, title, slug, dates, content,
excerpt, sorted taxonomy terms, `_thumbnail_id` and `attachment_url`. Elementor payloads, SEO plugin
keys and theme settings are excluded, so "the source changed" means "the content we imported
changed". Key order and term order never alter the hash.

### Media manifest (`MediaManifest`)

Reads the CSV with `fgetcsv`, strips a UTF-8 BOM from the header (the audit hit exactly this), skips
blank lines, validates the six required columns, and resolves `relative_path` against the configured
media directory using the platform separator. **Paths are taken from the manifest, never reconstructed
from URLs** — the audit showed that URL-based matching produces false mismatches because WordPress
serves `-WIDTHxHEIGHT` variants that resolve to differently-named originals.

---

## 8. Overwrite protection prepared for Phase 2

Not implemented, but the tracking infrastructure is shaped for it:

| Requirement | Mechanism now available |
|---|---|
| Detect whether a source record changed | `source_hash` + `SourceHash::forItem()` |
| Identify whether a record is already mapped | `WpImportMap::lookup()`, unique `(source_type, source_id)` |
| Distinguish new / reserved / imported | `scopePending()`, `scopeImported()`, `imported_at` |
| Require an explicit override before replacing content | `hasSourceChanged()` returns `false` when either hash is missing, so an unknown or legacy mapping can never trigger an overwrite on its own |

A future importer still has to implement the administrator-edit guard (compare `imported_at` with the
target row's `updated_at` before writing). Nothing destructive exists today.

---

## 9. Tests

```bash
vendor/bin/pest tests/Unit/Migration tests/Feature/Migration   # 72 tests, 197 assertions
vendor/bin/pest                                                # full suite
vendor/bin/pint --test app/Migration tests/Unit/Migration tests/Feature/Migration \
  app/Console/Commands/ImportWordPressCommand.php app/Models/WpImportMap.php
```

**Results on this machine**

| Check | Result |
|---|---|
| Migration tests | **72 passed** (197 assertions) |
| Full suite | **164 passed** (858 assertions) |
| Pint (new files) | PASS — 20 files |

**Coverage**

- Import map: composite uniqueness, same id across different types, lookup, casts, `morphTo`
  target, `hasSourceChanged` (including the null-hash case), pending/imported scopes.
- Parser: metadata, per-type counts, per-status counts, numeric ids, slug/dates, categories, custom
  terms, featured image, attachment URL, WXR 1.1 namespace, and the five failure modes (missing,
  empty, not-WXR, malformed, non-numeric id).
- HTML conversion: tag stripping, script/style/media removal, entity and NBSP decoding, paragraph
  separation, headings, ordered and unordered lists, `<br>`, image dropping, link handling, table
  cells, blank-line collapsing, malformed HTML, plain-text passthrough.
- Manifest: rows/columns, unique URLs, BOM tolerance, blank lines, missing file, missing columns,
  `missingFiles()`, unsuccessful rows.
- Paths: valid configuration, missing file, missing directory, empty path, empty file, relative
  resolution, all problems collected.
- Command: reports inventory; **writes nothing to the database or storage**; reports new / imported /
  reserved / changed; missing XML, unparseable XML, missing media file and bad manifest header all
  exit `2`; running without `--dry-run` exits `1` and writes nothing.

Feature tests use the isolated in-memory SQLite test database (`phpunit.xml`: `DB_DATABASE=:memory:`)
and `Storage::fake('public')`, so they never touch the development database or real media.

---

## 10. Intentionally not implemented

- **No content import.** Posts, pages, projects, team, events, clusters, testimonials and terms are
  not written.
- **No media copy.** No file is read into `storage/app/public`, no upload path is generated.
- **No import-map writes.** The table is created by a migration but never populated.
- **No seeder, no startup hook.** `db:seed` and application boot do nothing migration-related.
- **No `--force`, `--type`, `--only` or rollback flags.** They belong with the importer.
- **No front-end change.** No React component, route, controller or design was touched.
- **No Mission/Core-Values content edit** — approved, but scheduled for a later phase.

---

## 11. Assumptions and open questions

**Assumptions**

1. The audited export and media folder remain the migration source; the command reads them
   read-only and never modifies them.
2. `source_type` holds the WordPress post type verbatim (`post`, `project`, `attachment`, …). Terms
   and users would use `term` / `user` when that phase arrives.
3. `target_type` stores a fully-qualified model class because the app registers no morph map. If a
   morph map is introduced later, this column must store the alias instead.
4. HTML conversion targets the current plain-text rendering contract. If the site later renders
   rich text, the conversion becomes lossy — that is a product decision, not a bug.

**Open questions carried into Phase 2** (from `07-final-migration-decisions.md`)

- **D5 — news categories.** `news` still has no category column or pivot. The importer cannot
  preserve WordPress categories until that schema decision is made.
- **D2/D6 — duplicate pairs and project descriptions.** Six post↔project pairs need the agreed merge
  rule; 53 of 58 projects have no prose.
- **D8/D9 — Mission and Core Values copy.** Approved for the WordPress wording, deliberately not
  changed in this phase.
- **D10–D14 — cluster reconciliation.** 81 rows to insert, the Dairy `Shongom` → `Shomgom` fix, the
  `LOL` placeholder description, and the duplicated EGGS row.
- **D17 — newsletters/magazine.** Still out of scope until the 3 PDFs and 9 images are downloaded.

**Local notes**

- `wp_import_map` does not exist in `database/database.sqlite` yet. Run `php artisan migrate` when
  you are ready; until then the dry run warns and reports every record as new.
- `php artisan lpres:import-wordpress` without `--dry-run` intentionally fails with exit `1`.
