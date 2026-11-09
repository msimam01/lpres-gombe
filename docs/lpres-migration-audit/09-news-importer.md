# 09 — News importer (Phase 2)

Implementation report for the WordPress → Laravel news import. It covers the schema changes, the
category design, the field mapping, provenance, media handling, administrator-edit protection, the
command, the dry-run output and the test results.

**Nothing has been imported.** This phase delivers a tested importer; the first real run
(`--type=news --confirm`) is a separate, explicit decision.

Related reports: `00-summary.md`, `02-content-inventory.csv`, `03-content-media-mapping.csv`,
`04-attachment-inventory.csv`, `05-unmatched-and-issues.csv`, `06-migration-field-mapping.md`,
`07-final-migration-decisions.md`, `08-import-infrastructure.md`.

---

## 1. What was reused from Phase 1

No parser, manifest reader, path resolver, XML model or hashing utility was duplicated.

| Phase 1 artefact | Reused for |
|---|---|
| `App\Migration\WordPress\WordPressXmlParser` / `WordPressExport` / `WordPressItem` | Parsing the WXR export, attachment records, `_thumbnail_id`, categories, author |
| `App\Migration\MigrationPaths` | Resolving export, manifest, media directory (and the new inventory path) |
| `App\Migration\MediaManifest` | `url → relative_path` lookup; never a guessed path |
| `App\Migration\HtmlToText` | HTML → plain text conversion (unchanged) |
| `App\Migration\SourceHash` | `source_hash` *and* the new `target_hash` fingerprint |
| `App\Migration\MigrationSourceException` | Source errors, now labelled per CSV |
| `wp_import_map` | Every imported article and attachment, with provenance |
| `lpres:import-wordpress` | The command itself, extended (not replaced) |

Phase 1's read-only overview still exists: running the command with `--dry-run` and **no** `--type`
prints sources, export counts, media inventory and the import-map reconciliation exactly as before.

---

## 2. Schema changes

Three migrations, all additive and migration-safe (`hasTable` / `hasColumn` guards):

### `2026_11_05_000001_create_news_categories_tables.php`

```
news_categories: id, name, slug (unique), description (nullable), wp_term_id (nullable, indexed), timestamps
news_category (pivot): id, news_id FK cascade, news_category_id FK cascade, timestamps,
                       unique(news_id, news_category_id), index(news_category_id)
```

### `2026_11_05_000002_add_gallery_image_paths_to_news.php`

```
news.gallery_image_paths  JSON, nullable
```

### `2026_11_05_000003_add_target_hash_and_source_metadata_to_wp_import_map.php`

```
wp_import_map.target_hash      string(64), nullable
wp_import_map.source_metadata  JSON, nullable
```

### Schema limitations found, and what was done about them

| Finding | Decision |
|---|---|
| `news` had **no category column at all** (audit report 06 already flagged this) | Added `news_categories` + many-to-many pivot. Existing news rows and the admin CRUD keep working; no column on `news` changed. |
| `news` had **no place for images embedded in the body** — only `featured_image_path`. 123 of the 132 articles embed 779 `<img>` tags. | Added `news.gallery_image_paths` (nullable JSON), the **same ordered-JSON convention `projects` and `events` already use**, rather than a new media framework. |
| `news` has **no author column** | No author is imported or invented. The WordPress `dc:creator` is recorded in `wp_import_map.source_metadata.wp_author` so the information is not lost. The audit's demo author `admin` is therefore never presented as a byline. |
| `news.excerpt` is **derived from the title** by product rule (`News::excerptFromTitle`) and the admin controller recomputes it on every save | The importer uses the same rule. The WordPress `excerpt:encoded` field is never imported. |
| WordPress category **term ids are not on the item element** — only nicename and name | Term ids are recovered from the channel-level `<wp:category>` declarations and written to `source_metadata`. |

---

## 3. Category design

* Exactly one Laravel category: **`News`** / slug `news` / `wp_term_id = 67`.
* Created with `firstOrCreate` semantics on the unique slug, so re-runs and an existing row can never
  produce a duplicate (`news_categories.slug` is unique; the importer looks up by slug, never by id).
* Every imported article is attached with `syncWithoutDetaching`, so a re-run cannot duplicate a
  pivot row (`unique(news_id, news_category_id)` also enforces it).
* Following the Project/Team category convention there is no visibility switch and no manual
  ordering.
* `wp_term_id` is indexed but **not unique**: several WordPress terms intentionally map to the one
  Laravel category (see below).

The public news index does not filter by category, so this adds classification without changing any
route, listing or detail behaviour. The admin form gains an "Article images" gallery field; the news
CRUD, slug rules, publish/unpublish and delete behaviour are unchanged.

---

## 4. WordPress → Laravel field mapping

| WordPress | Laravel | Rule |
|---|---|---|
| `wp:post_id` | `wp_import_map.source_id` (`source_type = 'post'`) | One mapping per source record (unique `(source_type, source_id)`). |
| `title` | `news.title` | Entity-decoded (`&amp;` → `&`). |
| `wp:post_name` | `news.slug` | Preserved. Only normalised through `Str::slug`; a collision gets `-2`, `-3`, … and is reported. Never truncated or regenerated from the title unless the source slug is empty. |
| `content:encoded` | `news.body` | `HtmlToText` → plain text paragraphs, the shape `news/show` renders. |
| `wp:post_date` (date part) | `news.published_at` | Original date preserved. **No date ⇒ the record fails**; today's date is never substituted. |
| `wp:status` | `news.status` | Only `publish` records are eligible, so imported rows are `published`. |
| `title` | `news.excerpt` | `News::excerptFromTitle()` — the application's existing rule. |
| `_thumbnail_id` | `news.featured_image_path` | Resolved through the attachment record + manifest (§6). |
| embedded `<img>` | `news.gallery_image_paths` | Document order, deduplicated (§6). |
| `category` terms | `news_category` pivot + `source_metadata` | One Laravel category; original terms preserved (§5). |
| `dc:creator` | `source_metadata.wp_author` only | Never a byline. |
| `wp:post_modified`, `terms`, `attachment_url` | `source_hash` | Fingerprint of the imported source fields only. |

On **updates** the stored slug is kept (public URLs stay bookmarkable, matching the admin rule that
slugs never change on update); every other mapped column is refreshed from the source.

### HTML conversion

`HtmlToText` is used unchanged (paragraphs, headings, lists with `•`/`1.`, `<br>`, decoded entities,
link text preserved; scripts, styles, theme wrappers, images and empty paragraphs removed).

Two body-shape fixes live in `NewsFields` (deliberately *not* in `HtmlToText`, which other phases
reuse):

1. **Leading title removed.** 95 articles repeat the title as an exact first paragraph, and 6 more
   have the body glued to it with no separator (`…ProductionIn a collaborative effort`). Matching is
   case-, quote- and whitespace-insensitive and only runs for titles ≥ 12 characters. Result: the
   body starts with the first real sentence, with its own capitalisation.
2. **Paragraph splitting for unstructured bodies.** 90 of the 132 articles contain no `<p>` at all
   and would otherwise render as one ~3 000-character paragraph (audit D16). A body that arrives as a
   single paragraph, longer than 700 characters, is split on sentence boundaries into paragraphs of
   about 350 characters. Splits after abbreviations and initials (`Prof.`, `Dr.`, `U.B.`, months, …)
   are folded back so a sentence is never broken mid-thought. Structured bodies are untouched: real
   data ends at a median of 3 paragraphs (max 24).

Nothing is invented: an empty body after conversion fails the record instead of creating a blank
article.

### Eligibility — how exactly 132 records are selected

Eligibility is taken **only** from the audit, never from titles or slugs:

```php
ContentInventory::fromFile('docs/lpres-migration-audit/02-content-inventory.csv')
```

A row is eligible when `post_type = post` **and** `status = publish` **and**
`classification = news` **and** `in_migration_scope = yes` → **132 rows**.

The other 15 inventory posts are reported, not imported:

| Reason | Count |
|---|---|
| not published (draft) | 3 |
| published but not genuine news: `value_chain_description` | 4 |
| published but out of scope: `needs_review` | 4 |
| published but out of scope: `static_page_content` | 2 |
| published but out of scope: `junk` | 1 |
| published but not genuine news: `spc_profile` | 1 |

The 204 non-post inventory rows (pages, projects, team, events, attachments) are listed as outside
this phase. `--type` is mandatory for any write, and only `news` is accepted.

---

## 5. Category provenance

Nothing is stored only in console output. Every article's mapping row carries:

```json
{
  "wp_post_id": 27,
  "wp_post_type": "post",
  "wp_status": "publish",
  "wp_slug": "gombe-l-pres-upgrades-farmer-field-schools",
  "wp_link": "https://lpres.gm.gov.ng/home/2023/…",
  "wp_author": "admin",
  "wp_categories": [
    { "term_id": 21, "name": "Latest News", "slug": "latest-news" },
    { "term_id": 67, "name": "News",        "slug": "news" }
  ],
  "laravel_news_category_ids": [1],
  "featured_attachment_id": 3069,
  "embedded_attachment_ids": [3069, 3070]
}
```

* All three original terms (`21 Latest News`, `67 News`, `1 Uncategorized`) collapse onto the single
  Laravel `News` category — the mapping supports any number of source terms per target category.
* `laravel_news_category_ids` records the target side explicitly, so the many-to-one relation is
  readable from the mapping alone.
* `featured_attachment_id` and `embedded_attachment_ids` tie the record to the attachment mappings.
* A `wp_categories` entry carries `term_id: null` only if a term is attached to an article but not
  declared at channel level (does not occur in this export).

---

## 6. Media import

### Resolution chain (attachment records, never URL guessing)

```
_thumbnail_id / <img src>  →  attachment record (wp:attachment_url)
                           →  media manifest row (URL, normalised)
                           →  manifest relative_path inside the media directory
```

WordPress serves `-1024x682` variants in bodies while the attachment record holds `-scaled` and the
download drops both suffixes, so URLs are normalised (resize/scale suffix removed) **before** the
manifest lookup. The manifest's `relative_path` is then used verbatim — a basename is never guessed,
because the manifest contains duplicate basenames in different month folders.

Measured on the real export:

| Metric | Value |
|---|---|
| Featured images resolved | 131 of 132 (1 article genuinely has none) |
| Embedded `<img>` tags in the 132 articles | 779, in 123 articles |
| Unresolvable embedded images | 0 |
| Theme/plugin assets ignored (`/wp-content/plugins/`, `/wp-content/themes/`, `placeholder.png`) | 1 |
| Distinct attachments recorded | 874 |
| Files to copy / files reused (byte-identical) | 799 / 78 |

### Rules enforced by `MediaImporter`

* Target paths are `uploads/news/<manifest relative path>` with every segment sanitised; empty, `.`
  or `..` segments reject the file, so a manifest value can never traverse outside the upload
  directory.
* A file is identified by MD5 before it is copied. Byte-identical media is stored **once**: the
  in-run byte index plus an existence check on the target path. The gallery does not repeat the same
  stored path, and the featured image is excluded from the inline gallery even when it also appears
  in the body.
* An existing target file is reused **only** when its bytes match; different bytes at the same path
  are reported as a media conflict and never overwritten.
* Only media referenced by eligible news articles is touched. No source file is deleted, no existing
  Laravel media is deleted, no manifest/XML file is written.
* A missing or unreadable file is recorded as an import issue and the article is still imported.
  Missing declared media never clears an existing column: an unresolved featured image keeps the
  article's current value, and an incomplete gallery keeps the gallery the article already has.
* Every stored attachment gets its own `wp_import_map` row
  (`source_type = 'attachment'`, `source_metadata.stored_path`, `source_url`, `manifest_path`, md5),
  which is the WordPress-attachment-id → Laravel-path map.

### Public rendering

`news/show.tsx` renders `article.gallery` below the body with the shared `ImageGallery` component
already used by projects and events; `NewsController@show` maps the stored paths to
`asset('storage/…')`. The admin edit form exposes the same images through the standard gallery field,
and `AdminModuleController::releaseFile()` now counts `news.gallery_image_paths` before deleting a
shared file. No other page was touched.

---

## 7. Administrator-edit protection

Two independent fingerprints, kept in separate columns:

| Column | Answers |
|---|---|
| `source_hash` | Did the WordPress record change? (Phase 1, unchanged) |
| `target_hash` | Has the Laravel article changed since we wrote it? |

`target_hash` is taken **from the stored record after the write** (reloaded with its categories), so a
later run compares like with like. It covers `title`, `slug`, `body`, `excerpt`, `published_at`,
`status`, `featured_image_path`, `gallery_image_paths` and the sorted category ids.

Decision table (`NewsImporter`):

| State | Outcome |
|---|---|
| No mapping | **create** |
| Mapping, no target / target deleted | **conflict** — "its article is missing"; nothing written |
| Source unchanged, target unchanged | **unchanged** — no write at all, no media work |
| Source changed, target unchanged | **update** from the source |
| Target changed (or `target_hash` missing) | **skipped as administrator-modified**, article left byte-for-byte intact, listed in the report |
| Target changed + `--force-overwrite-admin-edits` | **update**, with a note in the report |

A missing `target_hash` is deliberately treated as "changed" (the conservative answer): the importer
cannot prove the row is untouched, so it refuses to overwrite it without the explicit override.

---

## 8. Command

```
php artisan lpres:import-wordpress --type=news --dry-run
php artisan lpres:import-wordpress --type=news --confirm
php artisan lpres:import-wordpress --type=news --confirm --force-overwrite-admin-edits
php artisan lpres:import-wordpress --dry-run          # Phase 1 overview, unchanged
```

| Option | Meaning |
|---|---|
| `--type=news` | Required for any news work. Any other value is refused with exit 2. |
| `--dry-run` | Full plan, zero writes (default posture). |
| `--confirm` | Executes the plan. |
| `--force-overwrite-admin-edits` | The only way past the administrator-edit guard. |

Exit codes:

| Code | Meaning |
|---|---|
| `0` | Run completed and nothing needs attention |
| `1` | Run completed but records need review (conflicts, administrator edits, media conflicts, failures, orphaned files) |
| `2` | Invalid input or configuration (bad paths, unparseable XML, bad manifest, unsupported `--type`, `--confirm` without `--type`, `--dry-run` with `--confirm`) |

Per-record database writes run in a transaction. Media is copied inside that transaction and
released when it rolls back; anything that could not be removed is listed as an orphaned file.
A failure never leaves a half-created article or a mapping pointing at nothing.

### Dry run: read-only guarantees

No insert, update or delete; no mapping change; no file copy or delete; no change to the XML,
manifest or inventory. Verified by `it never writes anything during a dry run`.

---

## 9. Dry-run output (real export)

Command:

```
php artisan lpres:import-wordpress --type=news --dry-run
```

The development database has not been migrated, so the run above was executed against an **isolated
temporary SQLite database** created only for this check (migrations applied there, file deleted
afterwards). No development or production data was touched, and no import was executed.

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
INFO  News import plan (dry run).
  Eligible news records        132
  Existing mappings            0
  New articles                 132
  Unchanged articles           0
  Changed WordPress sources    0
  Updated articles             0
  Administrator-edited         0
  Conflicts                    0
  Failed                       0
INFO  Excluded (by the audited content inventory).
  published but not genuine news: value_chain_description 4
  published but out of scope: needs_review 4
  not published (draft)        3
  published but out of scope: static_page_content 2
  published but out of scope: junk 1
  published but not genuine news: spc_profile 1
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
INFO  Dry run complete — nothing was written.
```

Exit code: **0**.

---

## 10. Tests

`tests/Feature/Migration/NewsImporterTest.php` (new, 31 tests) and
`tests/Feature/Migration/ImportWordPressCommandTest.php` (extended, 20 tests) cover, on isolated
SQLite with `Storage::fake('public')` and repo fixtures (no production data, no writes to the real
XML/manifest):

eligibility of the audited set · draft exclusion · demo/out-of-scope exclusion · single `News`
category (and reuse of an existing one) · category relationship · original term-id provenance ·
author recorded as provenance only · title/slug/date/status mapping · HTML conversion (entities,
lists, links, no markup, no scripts) · leading-title removal (heading and glued forms) ·
sentence-splitting of unstructured bodies · abbreviation-safe splitting · featured-image resolution ·
embedded-image ordering · featured/inline de-duplication · byte-identical storage · WordPress resize
suffixes · missing embedded image · unresolvable featured image · post and attachment mappings ·
target-hash fingerprint · dry-run read-only · idempotent re-runs · unchanged detection · changed-source
update · administrator-edit protection · explicit override · missing `target_hash` conservatism ·
conflicting mapping · duplicate-slug resolution · transaction rollback with no orphaned copies ·
missing publication date · empty body · command exit codes and mode validation.

Results:

| Check | Result |
|---|---|
| `./vendor/bin/pest tests/Feature/Migration` | **67 passed** (239 assertions) |
| `./vendor/bin/pest` (full suite) | **204 passed** (1 026 assertions) |
| `./vendor/bin/pint --test` on the 23 changed/created PHP files | **PASS** |
| `npm run build` (news detail page changed) | **built successfully** |

Pre-existing Pint findings remain in three controllers this phase touched but did not author
(`NewsController`, `NewsAdminController`, `AdminModuleController`: fully-qualified class names and an
unused import). They were left alone to avoid unrelated formatting churn; they are not caused by this
phase.

---

## 11. Known limitations

1. **Unpublishing is not propagated.** Only `publish` records are eligible, so an article later
   unpublished in WordPress is simply not seen — its Laravel copy stays published. Removing it is a
   deliberate editorial action, not an import side effect.
2. **Deletions are not propagated.** Same reasoning: the importer never deletes articles or media.
3. **Slugs are frozen on update.** The original permalink is preserved for bookmarkability; a
   WordPress slug change does not rewrite the Laravel URL.
4. **No author byline.** `news` has no author column; the WordPress creator lives in
   `source_metadata` only. Adding a byline would be a schema decision for a later phase.
5. **Paragraph splitting is heuristic.** It only applies to single-paragraph bodies over 700
   characters, and it never splits after a known abbreviation or an initial. A typo-heavy source
   sentence could still be grouped slightly differently than the author intended.
6. **Media is re-resolved from the export each run.** If the WXR export is regenerated without an
   attachment, that article's image becomes an unresolved-featured/missing-embedded issue; existing
   values are then preserved rather than cleared (for unresolved) — the report names each case.
7. **Image dimensions/alt text are not imported.** The gallery renders the article title as alt
   text; the source `alt` attributes are empty on all 314 audited images.
8. **Category provenance is JSON, not relational.** Original term ids are queryable through
   `source_metadata` but not joinable as a table. That matches the audit decision (report 07, C.3) to
   keep one Laravel category rather than recreate the WordPress terms.

## 12. Open decisions

1. **Run the import.** Not executed in this phase. The next step is
   `--type=news --confirm` against a migrated environment, preceded by the dry run above.
2. **Front-of-site categories.** The category exists and is attached, but the public news index does
   not yet filter by it. Exposing a category filter is a design decision, not a migration one.
3. **Gallery presentation.** Inline images currently render as an ordered grid after the body
   (mirroring projects/events). If the client wants them interleaved between paragraphs, the `body`
   column would need a structured format (e.g. blocks) — a larger, separate change.
4. **Author bylines.** If WordPress authors should be shown, add a `news.author` column and re-run
   with `--force-overwrite-admin-edits` off (a plain update would then be blocked by the target hash
   for the 132 rows, so this must be decided before the first import).
5. **Publishing later WordPress changes.** Re-running after the first import is safe by design; how
   often to do it is an editorial policy question.
