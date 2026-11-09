# WordPress → Laravel migration plan (Gombe State L-PRES)

Status: **planning only — no importer exists yet.**
The WordPress export has not been inspected, so every mapping below is a
proposal to be confirmed against the real WXR file before any code is written.

Scope of this document: inspect the current application, design a portable
migration package, define field/relationship/image mapping, migration tracking,
duplicate prevention, the seeder/command contract, and the production runbook.

Explicitly **not** in scope right now: connecting to WordPress, scraping the
public site, importing records, changing existing public-page designs or admin
forms, or deleting/overwriting existing Laravel content.

---

## 1. Existing Laravel inventory

Laravel 12.70.2, PHP 8.2, Inertia + React. Local development database is SQLite;
production is expected to be MySQL. Everything below is driver-neutral (JSON
columns and string statuses are used exactly as the app already uses them).

### 1.1 Tables that receive migrated content

| Table | Actual columns | Notes relevant to migration |
| --- | --- | --- |
| `news` | `id`, `title`, `slug` (unique), `featured_image_path`, `excerpt` (text), `body` (longText), `published_at` (date, indexed), `status` (default `draft`), `timestamps` | Public scope requires `status = published` **and** non-null `published_at <= now()`. Ordered by `published_at` desc. |
| `projects` | `id`, `title`, `slug` (unique), `featured_image_path`, `description` (text), `project_category_id` (FK, nullable, `nullOnDelete`), `gallery_image_paths` (JSON), `status`, `timestamps` | **No date column.** WordPress publish date cannot be stored without a schema change. Ordered by `id` desc. |
| `project_categories` | `id`, `name`, `slug` (unique), `description` (text), `timestamps` | No status, no display order. Alphabetical. |
| `events` | `id`, `title`, `slug` (unique), `image_path`, `gallery_image_paths` (JSON), `description` (text), `starts_at` (date), `ends_at` (date), `location`, `status`, `timestamps` | Event dates are the only date fields; `description` is plain text. |
| `team_members` | `id`, `name`, `slug` (unique), `photograph_path`, `designation` (nullable), `team_category_id` (FK, nullable), `status` (boolean, default **false**), `timestamps` | No biography/email/phone columns (removed deliberately). |
| `team_categories` | `id`, `name`, `slug` (unique), `description`, `timestamps` | No status/order. |
| `value_chain_clusters` | `id`, `name`, `slug` (unique), `image_path`, `description` (text), `status`, `timestamps` | `image_path` is the uploaded beneficiary chart image. |
| `cluster_beneficiaries` | `id`, `value_chain_cluster_id` (FK, cascade), `lga`, `ward`, `beneficiaries` (unsigned int), `sort_index` (default 0), `timestamps` | Public “S/No” is derived from `sort_index`, never stored separately. No unique key. |
| `visual_reports` | `id`, `title`, `description`, `image_path`, `file_path`, `status`, `timestamps` | Admin requires an image on create, so imported reports must have one. |
| `media_items` | `id`, `title`, `media_type` (`image`/`video`), `image_path`, `video_url`, `thumbnail_path`, `caption`, `album` (legacy, unedited), `associated_type`, `associated_id`, `status`, `timestamps` | `associated_type` ∈ `event`/`news`/`project`, resolved in PHP — **no foreign key**. `album` is vestigial. |
| `galleries` | `id`, `title`, `slug` (unique), `description`, `status`, `associated_type`, `associated_id`, `timestamps` | Same polymorphic string association; no FK. |
| `gallery_images` | `id`, `gallery_id` (FK, cascade), `path`, `sort_index` (nullable), `timestamps` | Display order by `sort_index`, then `id`. |

Supporting tables **not** part of the WordPress content import:
`project_components` (three homepage cards seeded as organisation facts),
`site_settings` (contact details), `users`, `contact_enquiries`,
`newsletter_subscribers`, `cache`/`jobs`/`sessions` tables.

There is **no** `partners` table and no standalone beneficiary table.

### 1.2 Models and relationships

- `Project belongsTo ProjectCategory` (`project_category_id`).
- `TeamMember belongsTo TeamCategory` (`team_category_id`).
- `ValueChainCluster hasMany ClusterBeneficiary` ordered by `sort_index, id`.
- `Gallery hasMany GalleryImage` ordered by `sort_index, id`.
- `MediaItem` / `Gallery` association is a manual string+id pair, resolved with
  `resolveAssociated()`; the association values are exactly `event`, `news`,
  `project`.
- Publication: string status (`draft`/`published`) everywhere except
  `team_members.status` (boolean).

### 1.3 Storage and upload conventions

- Disk: `public`, root `storage/app/public`, public URL `/storage/<path>`
  (requires `php artisan storage:link`; already linked locally).
- The admin controller stores uploads with
  `store('uploads/'.$moduleRouteBase, 'public')`, so existing paths look like:
  `uploads/news/…`, `uploads/projects/…`, `uploads/events/…`, `uploads/team/…`,
  `uploads/clusters/…`, `uploads/visual-reports/…`,
  `uploads/media/images/…`, `uploads/media/videos/…`,
  `uploads/media/galleries/…`.
- The database stores the **relative** path (`uploads/news/foo.jpg`); views build
  URLs with `asset('storage/'.$path)`.
- Galleries and `gallery_image_paths` share file paths between records; files are
  only deleted by the admin when no table still references them.

### 1.4 Admin modules (must not change)

`news`, `projects`, `project-categories`, `team`, `team-categories`, `events`,
`clusters` (with the beneficiary-table pseudo-field), `visual-reports`,
`media/images`, `media/videos`, `media/galleries`, plus the All Media index.
The generic `AdminModuleController` derives slugs from a source column and
**never changes a slug on update**; the importer must follow the same rule.

### 1.5 Public rendering constraints (these drive the mapping)

- `news.body`, `project.description`, `event.description`,
  `cluster.description` and `visual_report.description` are rendered as
  **escaped plain text**, split into paragraphs on blank lines. They are not
  HTML-rendering fields. WordPress HTML must therefore be flattened.
- `news.excerpt` is not an admin form field: `NewsAdminController` recomputes it
  from the **title** on every save. The column exists, so an import may set the
  real WordPress excerpt, but the next UI save will replace it with the title.
- News, projects, events and clusters render galleries differently:
  projects/events have `gallery_image_paths`; news has no gallery column and
  instead renders associated `galleries` / `media_items` on its detail page.
- `media_items` and standalone `galleries` are listed publicly, so every image in
  the WordPress media library must **not** become a media item (see §6).

### 1.6 Current content (reconciliation baseline)

| Table | Rows | Notes |
| --- | --- | --- |
| `news` | 5 | Built through the admin/a UI, not from WordPress. |
| `projects` | 1 | Gallery was previously lost to a form bug and may need re-upload. |
| `project_categories` | 1 (`Neil Lane`) | Placeholder-looking category. |
| `team_members` | 1 | Category `Professionals`. |
| `team_categories` | 1 | |
| `value_chain_clusters` | 2 (`beef`, `dairy`) | Beef seeded from the original website; Dairy exists. |
| `cluster_beneficiaries` | 13 | Beef table only. |
| `media_items` | 2 | |
| `galleries` | 1 | |
| `gallery_images` | 4 | |
| `visual_reports` | 3 | |
| `events` | 4 | |

None of these are owned by an importer. The importer must never adopt or modify
them implicitly (§9).

### 1.7 Gaps that affect the migration (decisions needed)

1. **Partners & supporters are static** (`resources/js/config/site.ts`, logos in
   `public/images/lpres/partners`). No table, no admin module. Importing them
   would require new schema + admin UI, i.e. a design change.
2. **Projects have no publication date column** — WordPress project dates would
   be lost unless a column is added (not proposed here).
3. **Team members have no biography** — WordPress team bios cannot be stored.
4. **News has no category/tag relation** — WordPress news categories/tags have no
   destination.
5. **Beneficiaries exist only as cluster rows** — no standalone beneficiary
   entity to import into.
6. **Media associations are string+id with no FK** — orphans are possible and
   must be avoided by importing targets before galleries/media.
7. `resources/js/config/news.ts` still contains a hard-coded list of WordPress
   articles but is **imported nowhere** (dead content). It is useful only as a
   cross-check list.

---

## 2. Portable migration package

The package lives inside `storage/app`, outside the public web root, so no file
in it is reachable through `/storage/...`:

```
storage/app/migration/wordpress/
├── README.md
├── export/
│   ├── manifest.json
│   ├── terms.json
│   ├── news.json
│   ├── projects.json
│   ├── events.json
│   ├── team.json
│   ├── clusters.json
│   ├── galleries.json
│   ├── media.json
│   ├── reports.json
│   ├── attachments.json
│   └── partners.json          (reference only — see §1.7)
└── uploads/
    ├── news/
    ├── projects/
    ├── team/
    ├── galleries/
    ├── reports/
    └── other/
```

The same folder is uploaded verbatim to production. Nothing references WordPress
at import time: the WXR → JSON conversion happens offline, before packaging.

### 2.1 `manifest.json`

```json
{
  "schema_version": 1,
  "generated_at": "2026-11-04T10:00:00+01:00",
  "source": {
    "platform": "wordpress",
    "site_url": "https://lpres.gm.gov.ng",
    "wxr_file": "lpres-gombe.WordPress.2026-11-03.xml",
    "wxr_sha256": "…"
  },
  "counts": { "news": 0, "projects": 0, "events": 0, "team": 0,
              "clusters": 0, "galleries": 0, "media": 0, "reports": 0,
              "terms": 0, "attachments": 0 },
  "files": {
    "news": "news.json", "projects": "projects.json", "events": "events.json",
    "team": "team.json", "clusters": "clusters.json",
    "galleries": "galleries.json", "media": "media.json",
    "reports": "reports.json", "terms": "terms.json",
    "attachments": "attachments.json"
  },
  "excluded": [
    { "type": "page", "id": 12, "title": "Home", "reason": "fixed design copy" }
  ]
}
```

`counts` and `excluded` let the importer fail fast on a truncated package and
explain deliberate omissions in the report.

### 2.2 Record envelope (same shape in every content file)

```json
{
  "source": {
    "type": "post",
    "id": 1234,
    "post_type": "post",
    "status": "publish",
    "date": "2024-05-11T09:12:00+01:00",
    "modified": "2024-05-12T08:00:00+01:00",
    "permalink": "https://lpres.gm.gov.ng/home/2024/05/11/…/"
  },
  "title": "Flag-off of the 2024 livestock vaccination campaign",
  "slug": "flag-off-2024-livestock-vaccination",
  "content_html": "<p>…</p>",
  "excerpt_html": "<p>…</p>",
  "featured_image": {
    "attachment_id": 1300,
    "file": "uploads/news/1300-flag-off.jpg",
    "caption": "…",
    "alt": "…"
  },
  "gallery": [
    { "attachment_id": 1301, "file": "uploads/news/1301-farmers.jpg", "caption": "…" }
  ],
  "videos": [
    { "url": "https://www.youtube.com/watch?v=…", "title": "…" }
  ],
  "terms": { "categories": [{ "id": 5, "name": "News", "slug": "news" }] },
  "meta": { "designation": null, "event_start": null, "event_end": null,
            "location": null },
  "beneficiaries": []
}
```

`source.id` + `source.type` is the identity used for tracking; it is never used
as a Laravel primary key. `file` paths are always relative to `uploads/` and are
verified to exist before any write.

`attachments.json` is the attachment index used to resolve every
`attachment_id`:

```json
{
  "1300": {
    "file": "uploads/news/1300-flag-off.jpg",
    "mime": "image/jpeg",
    "width": 1600,
    "height": 900,
    "parent_id": 1234,
    "caption": "…",
    "alt": "…",
    "missing": false
  }
}
```

---

## 3. Field mapping

### 3.1 News (`news.json` → `news`)

| Export field | Column | Rule |
| --- | --- | --- |
| `source.id` | — | Tracking identity only (`news` + post id). |
| `title` | `title` | Decode entities, trim; empty title = record failure. |
| `slug` | `slug` | Preserved exactly; collision policy in §9. |
| `content_html` | `body` | Flattened to plain text with blank-line paragraphs (§4). Required. |
| `excerpt_html` | `excerpt` | Flattened; fallback `Str::limit(title, 250, '')` to match the admin rule. |
| `source.date` | `published_at` | Date part only, site timezone as exported (no UTC shift). |
| `source.status` | `status` | `publish → published`; `draft`, `pending`, `private`, `future → draft`; `trash`/`auto-draft` excluded. |
| `featured_image.file` | `featured_image_path` | Copied to `uploads/news/…`; null if missing (logged). |
| `gallery[]` | `galleries` row + `gallery_images` | One gallery per article, associated `news`, images in exported order (§6). |
| `videos[]` | `media_items` (`video`) | YouTube URL canonicalised, associated `news`. |
| `terms.categories/tags` | — | No destination; recorded in the report. |

### 3.2 Projects (`projects.json` → `projects` + `project_categories`)

| Export field | Column | Rule |
| --- | --- | --- |
| `title` | `title` | Required. |
| `slug` | `slug` | Preserved; never changed on update. |
| `content_html` | `description` | Plain text, blank-line paragraphs. |
| `terms.project_category` | `project_category_id` | Category created in phase 1 from the term name/slug; unmatched/empty leaves null. |
| `source.status` | `status` | Same mapping as news. |
| `featured_image.file` | `featured_image_path` | Copied to `uploads/projects/…`. |
| `gallery[]` | `gallery_image_paths` | JSON array in exported order; copied to `uploads/projects/…`. |
| `source.date` | — | **No destination**; reported as an accepted loss (§1.7). |
| `videos[]` | `media_items` (`video`) | Associated `project`. |

### 3.3 Events (`events.json` → `events`)

| Export field | Column | Rule |
| --- | --- | --- |
| `title` | `title` | Required. |
| `slug` | `slug` | Preserved. |
| `content_html` | `description` | Plain text. |
| `image.file` | `image_path` | Copied to `uploads/events/…`. |
| `gallery[]` | `gallery_image_paths` | JSON array, order preserved. |
| `meta.event_start` | `starts_at` | Date; falls back to `source.date`. |
| `meta.event_end` | `ends_at` | Date or null. |
| `meta.location` | `location` | Trimmed string or null. |
| `source.status` | `status` | Same mapping. |

Event meta names depend on how events are stored in WordPress (custom post type
/ plugin); confirm before implementation (§14).

### 3.4 Team (`team.json` → `team_members`, `team_categories`)

| Export field | Column | Rule |
| --- | --- | --- |
| `title` | `name` | Person’s full name. |
| `slug` | `slug` | Preserved. |
| `featured_image.file` | `photograph_path` | Copied to `uploads/team/…`; missing photo logged. |
| `meta.designation` | `designation` | Required by the admin form; missing value logged as a warning, record still imported. |
| `terms.team_category` | `team_category_id` | Category created in phase 1. |
| `content_html` | — | Biography has no column: reported as dropped. |
| `source.status` | `status` | `publish → true` (visible); everything else `false` (hidden). |

Team categories: `name`, `slug` (preserved), `description` from the term
description; no status/order.

### 3.5 Value chain clusters (`clusters.json` → `value_chain_clusters` + `cluster_beneficiaries`)

| Export field | Column | Rule |
| --- | --- | --- |
| `title` | `name` | E.g. “Beef”, “Dairy”. |
| `slug` | `slug` | Preserved. |
| `content_html` | `description` | Plain text. |
| `featured_image.file` | `image_path` | The chart image; copied to `uploads/clusters/…`. |
| `beneficiaries[]` | `cluster_beneficiaries` | `lga`, `ward`, `beneficiaries`, `sort_index` = array index. |
| `source.status` | `status` | `publish → published`. |

If `beneficiaries` is empty but the content contains a table, the exporter (not
the importer) should parse it **only** when headers clearly identify LGA, Ward
and Number of Beneficiaries. Numbers are never inferred from prose; an
unparseable table is exported as `beneficiaries: []` plus a warning, and staff
enter it through the existing beneficiary-table admin UI.

### 3.6 Galleries and media (`galleries.json`, `media.json`)

| Export field | Destination | Rule |
| --- | --- | --- |
| Gallery `title`, `slug`, `description` | `galleries` | Slug generated only when WordPress has no slug; imported galleries need one for `/media/galleries/{slug}`. |
| Gallery `images[]` | `gallery_images` | `path` + `sort_index`; `sort_index` = index. |
| Gallery `source.status` | `galleries.status` | Draft galleries stay hidden. |
| Gallery/media `associated` | `associated_type`/`associated_id` | `event`/`news`/`project` + destination id resolved from tracking; null when standalone. |
| Image media `title`, `caption` | `media_items` | `media_type = image`, `image_path`. |
| Video media `url`, `title`, `caption`, `thumbnail` | `media_items` | `media_type = video`, canonical watch URL, `thumbnail_path` optional. |

Only **editorially selected** images belong in `media.json`. Content images are
gallery material, not public media items (§6).

### 3.7 Visual reports (`reports.json` → `visual_reports`)

| Export field | Column | Rule |
| --- | --- | --- |
| `title` | `title` | Required. |
| `content_html` | `description` | Plain text. |
| `image.file` | `image_path` | Required: a report without an image is reported as `failed`, not imported silently. |
| `file.file` | `file_path` | PDF/Office document copied to `uploads/visual-reports/…`. |
| `source.status` | `status` | Same mapping. |

### 3.8 Partners and supporters

`partners.json` is reference data only. The public marquee reads
`resources/js/config/site.ts` and `public/images/lpres/partners`. The
recommended approach is **no database import**: the exporter lists each partner
(name, logo file, link) and the operator confirms the existing static list is
complete, adding a missing logo asset manually if needed. Introducing a
`partner_logos` table plus an admin module is a separate, explicitly approved
change and is not part of this migration.

---

## 4. HTML → plain text conversion rules

Because every long-text destination is rendered as escaped text, the exporter (or
a shared importer helper) must convert WordPress HTML deterministically:

1. Remove `<!-- wp:… -->` block comments, `<script>`, `<style>`, `<noscript>`,
   `<form>`, `<iframe>` and inline `style`/`class` attributes.
2. Convert block boundaries to paragraph breaks: closing `</p>`, `</h1>`–`</h6>`,
   `</li>`, `</blockquote>`, `</figcaption>`, `<br>` → newline; blocks separated
   by a blank line.
3. Drop Gutenberg `wp-block-*` wrappers; keep their text.
4. Strip shortcodes (`[gallery …]`, `[caption …]`) after using `[gallery ids]`
   to collect images; never emit the shortcode text.
5. Decode HTML entities (`&nbsp;`, `&#8217;`, `&amp;`, …), normalise
   non-breaking spaces to normal spaces, collapse runs of spaces and more than
   one blank line.
6. Keep link and bold text but drop the markup (`<a>` text remains; the URL is
   lost). This is an accepted loss for plain-text fields; report it once per
   record batch, not per paragraph.
7. Trim leading/trailing whitespace; reject bodies that become empty.

Images inside `content_html` are extracted as gallery candidates **before** the
text conversion (they would otherwise disappear).

---

## 5. Relationship mapping

| Source relationship | Destination | Resolution |
| --- | --- | --- |
| WP category/taxonomy term for projects | `project_categories` | Phase 1; match on preserved term slug, create when absent. |
| WP taxonomy term for team | `team_categories` | Phase 1; same rule. |
| Project → category | `projects.project_category_id` | Looked up by term slug from phase 1. |
| Team member → category | `team_members.team_category_id` | Same. |
| Cluster → beneficiary rows | `cluster_beneficiaries.value_chain_cluster_id` | Rows imported immediately after their cluster, same transaction boundary. |
| Featured image | `featured_image_path` / `image_path` / `photograph_path` | Copied file path recorded in the attachment index. |
| Attachment parent post | `media_items.associated_type/id`, `galleries.associated_type/id` | Parent’s destination id read from `wordpress_imports`; if the parent failed, the media record is marked failed (never left dangling). |
| Gallery → images | `gallery_images.gallery_id` | Gallery inserted first, then images with `sort_index`. |
| Article/project/event → content images | `galleries` (news) or `gallery_image_paths` (project/event) | Policy in §6. |
| YouTube embed in content | `media_items` (`video`) associated with parent | One item per unique video id; deduplicated within the parent. |

Association strings must be exactly `event`, `news`, `project` — the same values
`MediaItem::ASSOCIATIONS` and `ResolvesAssociatedMedia` expect.

---

## 6. Image and media handling strategy

**Source → destination directories** (destination follows the existing
`uploads/{module}` convention so the admin panel can replace images normally):

| Role | Source folder | Destination |
| --- | --- | --- |
| News featured | `uploads/news` | `uploads/news` |
| News content gallery | `uploads/galleries` | `uploads/media/galleries` |
| Project featured/gallery | `uploads/projects` | `uploads/projects` |
| Event featured/gallery | `uploads/projects` (shared) | `uploads/events` |
| Team photograph | `uploads/team` | `uploads/team` |
| Cluster chart | `uploads/other` | `uploads/clusters` |
| Visual report image | `uploads/reports` | `uploads/visual-reports` |
| Visual report document | `uploads/reports` | `uploads/visual-reports` |
| Standalone media images | `uploads/other` | `uploads/media/images` |
| Video thumbnails | `uploads/other` | `uploads/media/videos` |

**Naming:** `{source_id}-{Str::slug(basename)}.{ext}`, lowercased, basename
limited to ~60 characters. Never reuse a WordPress `-300x200` derivative; the
exporter must reference the original file.

**Copy rules:**

- Copy, never move: the package remains a reusable source of truth.
- Write to `Storage::disk('public')`; store the relative path with no leading
  slash and no `storage/` prefix.
- Before copying, hash the source (SHA-256). If the destination exists with the
  same hash, reuse it — reruns stay idempotent and never duplicate files.
- Never overwrite a destination whose hash differs: choose the next free
  `…-2.ext` suffix and log it.
- Never delete files. Orphan cleanup is a separate, explicit decision.
- Verify with `finfo` that the file is really the expected mime; verify images
  with `getimagesize()` before storing.
- Allowed image types match admin validation: `jpg`, `jpeg`, `png`, `webp`.
  `gif`/`svg`/`avif` are **skipped and logged** (the admin cannot manage them
  later), not converted.
- Allowed documents: `pdf`, `doc`, `docx`, `xls`, `xlsx`.
- Admin limits are 4 MB per image and 10 MB per document. Larger files are still
  copied (they render fine) but reported as `oversize`, because staff could not
  re-upload a replacement of the same size through the form.
- Missing files are reported per record with the expected relative path; the
  record still imports without that image and the report marks it incomplete.
- Paths recorded in `gallery_image_paths` are plain strings (JSON), matching the
  admin form exactly.

**Media policy (important):** not every WordPress attachment becomes a public
media item, otherwise the Media page would list hundreds of unused uploads.

- Featured image → the record’s own image column.
- Other images attached to a news article → **one associated gallery**.
- Other images attached to a project/event → `gallery_image_paths` (the schema
  already supports multiple images; no parallel gallery is created, avoiding
  duplicate display).
- Standalone entries explicitly listed in `media.json` → `media_items`.
- YouTube URLs found in content → associated `media_items` videos, so videos
  survive the HTML flattening.

---

## 7. Migration tracking design

A dedicated table, created by a new migration **when implementation is
approved** (not now):

```php
Schema::create('wordpress_imports', function (Blueprint $table) {
    $table->id();
    $table->string('source', 32)->default('wordpress'); // source platform
    $table->string('source_type', 32);                  // post | page | attachment | term | row
    $table->unsignedBigInteger('source_id');            // original WordPress id
    $table->unsignedBigInteger('source_parent_id')->nullable();
    $table->string('source_key', 64)->nullable();       // stable key for table rows
    $table->string('content_type', 48);                 // news, project, project_category, …
    $table->string('destination_model', 120);           // App\Models\News
    $table->unsignedBigInteger('destination_id')->nullable();
    $table->string('status', 24)->default('pending')->index();
    $table->string('imported_hash', 64)->nullable();
    $table->text('error')->nullable();
    $table->uuid('run_id')->nullable()->index();
    $table->timestamp('imported_at')->nullable();
    $table->timestamps();

    $table->unique(['source', 'source_type', 'source_id', 'content_type'],
        'wordpress_imports_identity_unique');
    $table->index(['destination_model', 'destination_id']);
});
```

- **Identity** = `source` + `source_type` + `source_id` + `content_type`. One
  WordPress post may legitimately produce a news record, a gallery and two media
  items; those are four rows with the same `source_id` but different
  `content_type`, each preventing its own duplicate. WordPress ids are never
  written to Laravel primary keys.
- **Statuses:** `pending`, `imported`, `updated`, `unchanged`, `skipped`,
  `conflict`, `failed`, `orphaned`.
- **`imported_hash`** = SHA-256 of the canonical mapped field values actually
  written (excluding `id`, timestamps and slug). It powers both no-op detection
  and admin-edit protection.
- **`run_id`** groups a single execution for reporting and “what did this run
  touch” queries.
- Tracking rows are **not** written during `--dry-run`.

---

## 8. Seeder and Artisan command design

### 8.1 Command

```
php artisan lpres:import-wordpress --dry-run
php artisan lpres:import-wordpress
```

Options:

| Option | Behaviour |
| --- | --- |
| `--dry-run` | Read-only validation and plan. No inserts/updates/deletes, no media copies, no tracking writes. |
| `--only=news,projects` | Run a subset of phases (dependencies are automatically included or reported). |
| `--skip=media,reports` | Exclude phases. |
| `--force` | Overwrite records that import ownership says were edited by staff. Always prints each overwrite; never implied. |
| `--adopt=type:slug=id` | Explicitly take ownership of an existing Laravel record (repeatable), e.g. `--adopt=value_chain_cluster:beef=812`. |
| `--path=` | Package root, default `storage_path('app/migration/wordpress')`. |
| `--report=` | Write the JSON/text run report outside the package (default `storage/logs/wordpress-import-{timestamp}.log`). |

The package path is configurable but always defaults inside `storage/app`, never
`public/`. Exit codes: `0` success (warnings/conflicts allowed), `1` fatal
(missing/invalid manifest, unreadable package), `2` completed with at least one
`failed` record.

### 8.2 Seeder

`database/seeders/WordPressMigrationSeeder.php` calls the same importer service
with `dryRun: false`, so package uploads and Artisan seeding behave identically.
It must:

- refuse to run when the package/`manifest.json` is absent, with an actionable
  message;
- never be referenced from `DatabaseSeeder` — normal seeding must not trigger the
  WordPress import;
- be safe to re-run (idempotent, conflict-aware);
- print the same summary as the command.

### 8.3 Planned code layout (implementation phase, after approval)

```
app/Console/Commands/ImportWordPressCommand.php
app/Migration/WordPress/ImportPackage.php      // orchestration + phases
app/Migration/WordPress/ExportReader.php       // manifest + JSON validation
app/Migration/WordPress/HtmlToText.php
app/Migration/WordPress/MediaCopier.php
app/Migration/WordPress/ImportTracker.php      // identity, hash, statuses
app/Migration/WordPress/ImportReport.php
app/Migration/WordPress/Importers/{News,Project,Event,Team,Cluster,Gallery,Media,VisualReport,Term}Importer.php
app/Models/WordPressImport.php
database/migrations/…_create_wordpress_imports_table.php
database/seeders/WordPressMigrationSeeder.php
tests/Feature/WordPressImportTest.php
```

No public page, admin form or existing model is modified by the importer; it
writes rows through the models/fillable attributes the admin already uses.

---

## 9. Duplicate prevention and ownership

1. **Within the import:** every write is guarded by the tracking identity. A
   second run finds the row, resolves the destination, and either skips
   (`unchanged`), updates (`updated`) or conflicts (`conflict`). Never a second
   copy.
2. **Child rows** (gallery images, beneficiary rows, media items) carry their own
   identity: attachment id for media/gallery images; `source_key` =
   `sha1(lga|ward|beneficiaries|index)` for beneficiary rows. Re-sync replaces the
   set for that parent only, and only when the parent import owns it.
3. **Slug collisions with records the importer does not own:** create the record
   with `{slug}-wp-{source_id}` and log a warning. Never mutate the foreign
   record.
4. **Explicit adoption** (`--adopt=…`) is the only way to take over an existing
   record (for example the seeded Beef cluster, or placeholder categories such as
   `Neil Lane`). Adoption writes the tracking row, prints the field diff in the
   dry run, and only then may the import update that record.
5. **Admin edits are protected:** if the destination’s current content hash no
   longer matches `imported_hash`, the record was edited after import. The
   importer marks it `conflict`, writes nothing, and lists it in the report.
   `--force` is the only override and is logged loudly.
6. **Deletions:** if staff deleted an imported record, the tracking row becomes
   `orphaned`; the importer does not resurrect it unless `--force`.

---

## 10. Import sequence (dependency-safe)

| Phase | Content | Why here |
| --- | --- | --- |
| 0 | Preflight (manifest, counts, file existence, mime checks, slug/adoption report) | Nothing is written before the package is known-good. |
| 1 | Project categories, team categories | No dependencies; referenced by projects/team. |
| 2 | Value chain clusters | No dependencies. |
| 3 | Cluster beneficiary rows | FK to clusters (cascade delete). |
| 4 | News | Needed before news galleries/media associations. |
| 5 | Projects | Needs categories; before project galleries/media. |
| 6 | Events | Needed before event galleries/media. |
| 7 | Team members | Needs team categories. |
| 8 | Galleries + gallery images | Needs news/project/event destination ids. |
| 9 | Media items (image/video) | Needs associations; standalone items allowed. |
| 10 | Visual reports | Independent; needs media copies. |
| 11 | Finalisation (cache clear, report summary) | Public pages read cached settings/scopes. |

Partners are not imported (§3.8); project components and site settings are
already seeded organisation facts and are untouched.

---

## 11. Error handling and recovery

- **Per-record transactions:** each source record (plus its child rows) is written
  inside its own transaction; one bad record never rolls back the batch.
- **Failures are recorded, not swallowed:** `status = failed`, `error` holds the
  exception message and a short stack context; the destination is left untouched
  (or the transaction rolled back) and the report lists it.
- **Missing media is a warning, not a failure:** the record imports with a null
  image or a shorter gallery, status stays `imported`, and the report lists the
  expected path.
- **Resumability:** rerunning continues from the current database state; already
  imported records are `unchanged`, failed ones retry. `--only=` narrows a rerun
  after fixing a single export file.
- **Fatal package errors stop before writes:** missing manifest, unknown
  `schema_version`, count mismatch with `manifest.counts`, invalid JSON.
- **Report contents:** run id, phase totals, table of `create/update/unchanged/
  conflict/failed/skipped`, missing media paths, slug collisions, adopted
  records, oversize files, dropped WordPress fields (news categories/tags, team
  bios, project dates).
- **Rollback:** the tracking table is the map. Deleting imported rows is a
  deliberate manual operation in a maintenance window; the recommended rollback
  is restoring the pre-import database backup, because galleries, media and
  shared image paths make partial deletes error-prone.

---

## 12. Production deployment instructions

1. **Build the package offline** (no Laravel connection to WordPress): WXR →
   `export/*.json` + `uploads/`; record the WXR checksum in `manifest.json`.
2. **Rehearse locally/staging:** restore a production database copy, place the
   package at `storage/app/migration/wordpress`, run the dry run, then the import,
   and inspect every affected page.
3. **Back up production:** database dump and `storage/app/public` (uploaded media)
   before any write. Keep the package read-only.
4. **Upload the package** to `storage/app/migration/wordpress/` on the server
   (e.g. `rsync`/`scp`), owned by the web/PHP user, directories `755`, files
   `644`. It must not be inside `public/`.
5. **Deploy the code** containing the tracking migration, command, seeder and
   importers (separate release, after this plan is approved).
6. **Prepare the app:**
   ```
   php artisan down            # optional maintenance window
   php artisan migrate --force # creates wordpress_imports only
   php artisan storage:link    # if /public/storage is not linked
   php artisan config:clear
   php artisan lpres:import-wordpress --dry-run --verbose --report=storage/logs/wp-dry-run.log
   ```
7. **Review the dry run** — counts, collisions, adoptions, missing media. Resolve
   collisions/adoptions before continuing. The dry run writes nothing.
8. **Import:**
   ```
   php artisan lpres:import-wordpress --report=storage/logs/wp-import.log
   ```
   Then rerun once to prove idempotency (expect everything `unchanged`).
9. **Finalise:** `php artisan optimize:clear` (or `cache:clear`), `php artisan up`.
   Verify `/news`, `/our-projects`, `/project-team`, `/value-chains`,
   `/visual-reports`, `/media`, and a detail page with a gallery; confirm images
   load from `/storage/uploads/...`.
10. **Record the run:** archive the report and package checksum with the release
    notes. Keep `wordpress_imports` — it is the audit trail and the guard against
    future duplicates.

---

## 13. Validation and acceptance criteria (for the implementation phase)

Automated (feature tests using a small fixture package, `Storage::fake`):

- dry run creates no records, no files, no tracking rows;
- import creates records with preserved slugs, dates and associations;
- second run reports everything `unchanged` and creates no duplicates;
- staff-edited record is skipped as `conflict`; `--force` overwrites;
- slug collision with a foreign record produces `-wp-{id}` without touching it;
- missing media is logged and the record still imports;
- gallery images keep order and are shared paths, not copies per record;
- beneficiary rows are ordered and totals derive correctly;
- `DatabaseSeeder` does not run the WordPress seeder.

Manual: visual check of each public listing/detail page and the admin edit forms
for imported records (no new fields, no layout changes).

---

## 14. Open questions to approve before implementation

1. **Real WordPress structure:** confirm custom post types/taxonomies for
   projects, events, team, clusters and reports, and the meta keys for event
   dates/location and team designation (the current plan assumes names exported
   into `meta`).
2. **Events:** does the WordPress site publish events at all, or should the four
   existing Laravel events be left as the only ones?
3. **Project dates:** accept losing WordPress project publication dates (no
   column), or approve adding a `published_at` column + admin field?
4. **Team biographies:** accept dropping them, or approve a later schema/admin
   addition?
5. **News excerpt:** set the real WordPress excerpt on first import (knowing the
   next admin save regenerates it from the title), or derive from the title
   immediately for consistency?
6. **Links in long text:** accept losing anchor URLs during HTML flattening?
7. **Partners:** keep the static list (recommended), or approve a new managed
   partner module?
8. **Adoption list:** which existing records (Beef cluster, `Neil Lane` category,
   existing news/projects/team) should be adopted instead of duplicated?
9. **Media page scope:** confirm that only editorially selected images become
   public media items, while content images become galleries.

---

## 15. Implementation phases after approval

1. Tracking migration + model + `ImportTracker` (hash/status logic).
2. Export reader/validator, HTML→text and `MediaCopier` with tests.
3. Importers in the order of §10, plus `--dry-run` planning output.
4. Artisan command and `WordPressMigrationSeeder` (never in `DatabaseSeeder`).
5. Full test suite, staging rehearsal against the real export, production runbook
   walk-through.

