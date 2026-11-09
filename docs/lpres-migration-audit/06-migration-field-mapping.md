# 06 — WordPress → Laravel Field Mapping

Audit date: 2026-11-04
Source: `docs/lpres-design-references/livestockproductivityandresiliencesupportprojectlpres.WordPress.2026-11-04.xml`
Target: the existing Laravel 12 + Inertia/React application in this repository.

This document maps the WordPress source fields onto the **actual** Laravel schema. Every
column below was read from `database/database.sqlite` (`PRAGMA table_info`) and cross-checked
against `database/migrations/`. Every render contract was read from
`resources/js/pages/**` and `app/Http/Controllers/**`.

Nothing here has been implemented. No code, schema or data was changed.

---

## 1. Verified target schema

Final state after all migrations, including the columns added by
`2025_10_29_000001_add_content_category_tables.php`, `2025_10_30_000001_simplify_categories_and_team.php`,
`2025_10_30_000003_add_project_event_galleries.php` and `2026_01_01_000001_add_media_associations_and_galleries.php`.

| Table | Columns |
|---|---|
| `news` | `id`, `title`, `slug` (unique), `featured_image_path`, `excerpt`, `body` (NOT NULL), `published_at` (date), `status` (default `draft`), `created_at`, `updated_at` |
| `projects` | `id`, `title`, `slug` (unique), `featured_image_path`, `description`, `status` (default `draft`), `project_category_id` (nullable FK), `gallery_image_paths` (JSON), timestamps |
| `project_categories` | `id`, `name`, `slug` (unique), `description`, timestamps |
| `team_members` | `id`, `name`, `slug` (unique), `photograph_path`, `designation`, `status` (boolean, default `0`), `team_category_id` (nullable FK), timestamps |
| `team_categories` | `id`, `name`, `slug` (unique), `description`, timestamps |
| `events` | `id`, `title`, `slug` (unique), `image_path`, `description`, `starts_at` (date), `ends_at` (date), `location`, `status` (default `draft`), `gallery_image_paths` (JSON), timestamps |
| `value_chain_clusters` | `id`, `name`, `slug` (unique), `image_path`, `description`, `status` (default `draft`), timestamps |
| `cluster_beneficiaries` | `id`, `value_chain_cluster_id` (FK), `lga`, `ward`, `beneficiaries` (int), `sort_index`, timestamps |
| `visual_reports` | `id`, `title`, `description`, `image_path`, `file_path`, `status` (default `draft`), timestamps |
| `media_items` | `id`, `title`, `media_type`, `image_path`, `video_url`, `thumbnail_path`, `caption`, `album`, `status`, `associated_type`, `associated_id`, timestamps |
| `galleries` | `id`, `title`, `slug` (unique), `description`, `status`, `associated_type`, `associated_id`, timestamps |
| `gallery_images` | `id`, `gallery_id` (FK), `path`, `sort_index`, timestamps |
| `project_components` | `id`, `title`, `description`, `image_path`, `display_order`, `status` (boolean), timestamps |
| `site_settings` | `id`, `key` (unique), `value`, timestamps |

**Retired columns** (dropped by `2025_10_30_000001`): every `display_order` on
`projects`, `value_chain_clusters`, `visual_reports`, `media_items`; `display_order` and
`status` on `project_categories` / `team_categories`; and `is_spc`, `display_order`,
`biography`, `email`, `phone` on `team_members`. They must **not** be mapped.

**There is no `pages` table and no `testimonials` table.** There is no `wp_id` /
`external_id` / `source_id` column on any table.

---

## 2. The single most important constraint: bodies are plain text

The application renders every rich-text field as **escaped plain text split on blank lines**:

- `resources/js/pages/news/show.tsx:43` — `article.body.split(/\r?\n\r?\n|\n{2,}/).map(...<p>{paragraph}</p>)`, with the interface comment “Plain-text body (escaped at render; paragraphs split on blank lines)”.
- `resources/js/pages/projects/show.tsx:40` — same `description` pattern.
- `resources/js/pages/events/show.tsx:52` — same `description` pattern.
- `resources/js/pages/value-chains/show.tsx:44` — same `description` pattern.

WordPress stores these fields as **HTML**, including Elementor wrapper `div`s, `<p>`,
`<h2>`, `<img>` and `srcset`. Measured in the export: 1,450 `<img>` tags and 6,544 unique
`wp-content/uploads` URLs across the content bodies.

Consequence: if the raw WXR HTML is written into `news.body` or `projects.description`, the
public pages will print literal `<div class="...">` markup.

The importer must therefore convert HTML → plain text (preserving paragraph boundaries) and
move every `<img>` out of the body into the media tables. See §6.

Additional content-quality facts measured on the 132 published news posts:

| Measurement | Result |
|---|---|
| Posts whose body begins with the literal title again | 101 / 144 |
| News body length (readable characters) | min 534, median 3,291, max 8,148, mean 3,396 |
| Posts with a WordPress excerpt | 0 (every `excerpt:encoded` is empty) |
| Posts with at least one embedded image | 123 / 132 (774 images) |

---

## 3. News (`post` → `news`)

132 published posts are genuine L-PRES news articles out of 144 published posts. The 12
exclusions are: 4 value-chain descriptions (#3464, #3487, #3501, #3510), 2 duplicates of static
page copy (#6786, #6791), 1 SPC profile (#3670), 1 junk stub (#3638), and 4 published records
with no usable body (#4457, #4642, #7090, #7285) — see `00-summary.md` §4.

| WordPress source | Laravel target | Mapping | Notes |
|---|---|---|---|
| `wp:post_id` | *(none)* | **GAP** | Must be preserved as an external reference, never as `news.id`. No column exists. |
| `title` | `news.title` | direct, HTML-decode entities | e.g. `&amp;` → `&` |
| `wp:post_name` | `news.slug` | direct, then de-duplicate | Unique index. 5 slugs are unrelated leftovers of theme demo content, one of them 196 characters long (see `05-unmatched-and-issues.csv`). |
| `content:encoded` | `news.body` | **transform** HTML → plain text | Required; see §2. Strip the duplicated leading title where present. |
| `excerpt:encoded` | `news.excerpt` | direct — but **do not rely on it** | All are empty, and `NewsAdminController::validatePayload()` overwrites `excerpt` with `Str::limit(title, 250)` on every create/update. Treat as derived. |
| `wp:post_date` | `news.published_at` | date part only | Cast is `date`; the 04:58:34 time is lost. 132 values preserve. |
| `wp:status` `publish` | `news.status` `published` | enum translate | Source uses `publish`, target uses `published`. |
| `wp:status` `draft` | `news.status` `draft` | enum translate | 3 draft posts; must not be imported as published. |
| `_thumbnail_id` | `news.featured_image_path` | copy file, store relative path | 131 of 132 have one; all 131 are downloaded. |
| `category` terms | *(none)* | **GAP** | `news` has no category column and no pivot. 9 WordPress categories exist. |
| `post_tag` terms | *(none)* | **GAP** | No tag table. 5 tags exist. |
| `wp:post_modified` | `news.updated_at` (candidate) | optional | Setting it makes “administrator edited after import” detection harder; prefer import timestamp. |
| `dc:creator` | *(none)* | **GAP** | Single author (`admin`) for the whole site. |
| `_elementor_data` | *(none)* | **GAP** | Page-builder payload; not needed once `content:encoded` is converted. |
| `_siteseo_*`, `_yoast_*`, `towngov_meta*` | *(none)* | **GAP** | SEO/theme settings with no equivalent. Not required for the public site. |

News media roles in the target model: exactly one featured image (`featured_image_path`) plus
the association tables (`media_items` / `galleries`). `news` has **no**
`gallery_image_paths` column, unlike `projects` and `events`.

---

## 4. Projects (`project` → `projects`)

58 published projects (60 total; 2 drafts).

| WordPress source | Laravel target | Mapping | Notes |
|---|---|---|---|
| `wp:post_id` | *(none)* | **GAP** | External reference needed. |
| `title` | `projects.title` | direct | |
| `wp:post_name` | `projects.slug` | direct + de-dupe | Unique. 5 slugs are shared verbatim with a news post (duplicate stories, §8). |
| `content:encoded` | `projects.description` | **transform** HTML → plain text | Required. 40 published projects have image-only bodies (0 readable characters); their `description` would be empty/NULL. |
| `_thumbnail_id` | `projects.featured_image_path` | copy file | 58 / 58 downloaded. |
| `<img>` in content | `projects.gallery_image_paths` (JSON array) | **good fit** | 55 projects carry 342 embedded images; this is exactly what the column is for. |
| `project_cat` term | `projects.project_category_id` | create/match `project_categories` by slug | 10 of 15 terms used; 1 project has none. |
| `wp:status` | `projects.status` | `publish`→`published` | |
| `towngov_meta_projects.project_name` | *(none)* | **GAP** | e.g. “Solar -Powered Borehole”. Distinct from the title. |
| `towngov_meta_projects.project_date` | *(none)* | **GAP** | “Year of Construction”, e.g. `2024`. |
| `towngov_meta_projects.project_address` | *(none)* | **GAP** | Location string. |
| `wp:post_date` | *(none)* | **GAP** | `projects` has no date column at all. Publication date cannot be preserved for projects. |
| `towngov_meta`, `_elementor_*` | *(none)* | **GAP** | Theme/builder settings. |

Because `projects` has no `published_at`, the “preserve publication dates” requirement can
only be met for news (and as admin-only data for projects if a column is added).

---

## 5. Team members (`team` → `team_members`)

22 published members. Categories: `PIUs` (14), `Assistant` (8). The `Spc` term exists but has
**no** members — the SPC is deliberately not a member record (see `TeamMember` docblock).

| WordPress source | Laravel target | Mapping | Notes |
|---|---|---|---|
| `wp:post_id` | *(none)* | **GAP** | External reference needed. |
| `title` | `team_members.name` | direct | |
| `wp:post_name` | `team_members.slug` | direct + de-dupe | 3 are demo leftovers: `christine-eve`, `suleiman-sambo-yakubu-clone`, `abubakar`. |
| `_thumbnail_id` | `team_members.photograph_path` | copy file | 22 / 22 downloaded. |
| `towngov_meta_team.team_designation` | `team_members.designation` | direct | e.g. “Project Environmental Officer”. |
| `team_cat` term | `team_members.team_category_id` | create/match `team_categories` | |
| `wp:status` `publish` | `team_members.status` `true` | boolean translation | Target is **boolean**, not the `draft/published` string used elsewhere. |
| `content:encoded` (biography) | *(none)* | **GAP** | `biography` column was dropped. 13 of 22 members have under 120 characters of text and 4 have none at all; only a handful carry a real biography, and those will be discarded. |
| `towngov_meta_team.team_email` | *(none)* | **GAP** | `email` column dropped. |
| `towngov_meta_team.team_phone` | *(none)* | **GAP** | `phone` column dropped. |
| `towngov_meta_team.team_image_caption` | *(none)* | **GAP** | No caption column. |
| `towngov_meta_team.team_age` | *(none)* | **GAP** | “Experiance: 7 years” — not migratable meaningfully. |

---

## 6. Media, galleries and storage

### Storage convention (verified)

- Disk: `public`, root `storage/app/public`, URL `APP_URL/storage` (`config/filesystems.php`).
- `public/storage` is already a symlink to `storage/app/public`.
- Upload folders are created by `$file->store('uploads/'.$routeBase, 'public')`:
  `uploads/news`, `uploads/projects`, `uploads/events`, `uploads/team`, `uploads/clusters`,
  `uploads/visual-reports`, `uploads/media/images`, `uploads/media/videos`,
  `uploads/media/galleries`, `uploads/project-components`.
- Public URL = `asset('storage/'.$path)`; files must be physically under
  `storage/app/public/uploads/...` and the symlink must exist.

### Content-to-media mapping

| WordPress source | Laravel target | Mapping |
|---|---|---|
| `_thumbnail_id` | `news.featured_image_path`, `projects.featured_image_path`, `team_members.photograph_path`, `events.image_path`, `value_chain_clusters.image_path`, `visual_reports.image_path` | copy file into `uploads/<module>/`, store relative path |
| `<img>` inside project content | `projects.gallery_image_paths` (JSON) | ordered list of `uploads/projects/...` |
| `<img>` inside event content | `events.gallery_image_paths` (JSON) | none in practice — no real events |
| `<img>` inside news content | `media_items` (`associated_type='news'`, `associated_id=<news.id>`) or `galleries` + `gallery_images` | no JSON column on `news`; choose one model |
| `<img>` inside cluster page content | `value_chain_clusters.image_path` | one chart per cluster |
| attachment `caption` (`excerpt:encoded`) | `media_items.caption` | available |
| attachment `description` | `media_items.caption` (alt) | mostly empty |
| `attachment_url` | *(not stored)* | `original_url` is audit-only; the target stores paths, not URLs |

`media_items.media_type` must be set explicitly (`image` or `video`); it has no default.
`media_items`/`galleries`/`gallery_images` `status` defaults to `draft` and must be set to
`published` explicitly.

Videos: the only video reference found in content is a bare Facebook reel URL in post #7285
(`https://www.facebook.com/reel/1271511848195330`) and 3 `_oembed_*` meta keys containing
`{{unknown}}` (no usable embed URL). **No YouTube/Vimeo embeds exist in any content body.**
`media_items.video_url` therefore has almost no source data.

---

## 7. Value chain clusters (`page` → `value_chain_clusters` + `cluster_beneficiaries`)

Nine WordPress pages hold the cluster tables. This is the cleanest mapping in the migration.

| WordPress source | Laravel target | Mapping | Notes |
|---|---|---|---|
| page `title` “DAIRY - MAIN CLUSTER” | `value_chain_clusters.name` “Dairy” | transform | Strip ` - MAIN CLUSTER(S)`, title-case. |
| page `wp:post_name` `dairy-main-cluster` | `value_chain_clusters.slug` `dairy` | transform | Existing Laravel rows use `beef` and `dairy`; the importer should match on these. |
| `<table>` rows | `cluster_beneficiaries` (`lga`, `ward`, `beneficiaries`) | parse | `sort_index` = row order (0-based), S/No is derived. |
| `<img>` chart | `value_chain_clusters.image_path` | copy | All 9 charts verified present in `public/images/lpres/clusters/`. |
| `wp:post_id` | *(none)* | **GAP** | External reference needed. |
| `wp:status` | `value_chain_clusters.status` | `publish`→`published` | |
| description | `value_chain_clusters.description` | from 4 news posts | Posts #3464, #3487, #3501, #3510 contain Sheep&Goat / Poultry / Beef / Dairy descriptions — the only source of cluster prose. Poultry has no cluster table. |

Parsed rows per cluster (TOTAL rows excluded):

| Cluster | Source rows | Sum of rows | Published TOTAL row |
|---|---|---|---|
| Dairy | 11 | 818 | — |
| Beef | 11 | 2,263 | — |
| Sheep and Goat | 11 | 6,654 | — |
| Broiler | 11 | 5,221 | 5,221 (agrees) |
| Eggs | 11 | 596 | 596 (agrees, but contains a duplicated row) |
| Leather | 10 | 49 | — |
| Feed | 9 | 184 | — |
| Fodder | 9 | 40 | — |
| Pig | 11 | 190 | — |

Data-quality notes: `Bajoga  West` has a double space in 5 tables; the Eggs table repeats
`Kwami / Malam Sidi / 30` as both row 7 and row 11; the source spells the LGA `Shomgom`
while the existing Laravel Dairy rows use `Shongom`.

---

## 8. Fields with no Laravel equivalent (consolidated)

These cannot be imported without a schema change or an explicit decision.

| WordPress data | Volume | Status |
|---|---|---|
| Original WordPress ID (all records) | 2,140 items / 1,688 attachments | **Blocker for idempotent re-runs** — no `wp_id`/`external_id` column, no mapping table |
| News categories (`category`) | 9 categories, 132 articles | No column, no pivot |
| News tags (`post_tag`) | 5 tags | No table |
| Post/page author | 1 author (`admin`) | No column |
| Static pages | 5 real + 4 listing + 4 media pages | **No `pages` table** |
| Testimonials | 5 records | No `testimonials` table |
| Team biography / email / phone | 22 members | Columns dropped |
| Project year/location/project-name meta | 60 projects | No columns |
| Project publication date | 58 projects | No column |
| Attachment alt text, captions, descriptions | 1,688 | Only `media_items.caption` exists |
| SEO metadata (`_siteseo_*`, `_yoast_*`) | dozens | No equivalent |
| Menu structure (`nav_menu_item`) | 41 items, 5 menus | Not modelled (routes are code) |

---

## 9. Laravel fields that will need a default or manual input

| Table.field | Constraint | Recommended source |
|---|---|---|
| `news.body` | NOT NULL | HTML→text conversion; never leave empty |
| `news.excerpt` | derived by admin controller | let the app regenerate from title; do not import WP value |
| `news.status` | default `draft` | explicit `published` only for approved records |
| `news.published_at` | nullable | `wp:post_date` date part |
| `projects.status` | default `draft` | explicit `published` |
| `projects.description` | nullable but **required by admin validation** | plain-text body; 40 projects have no prose |
| `projects.project_category_id` | nullable FK | match `project_cat`; 1 project has none |
| `team_members.status` | boolean, default `0` | `true` for published members |
| `team_members.designation` | nullable | `towngov_meta_team.team_designation` |
| `team_members.team_category_id` | nullable FK | `team_cat` |
| `events.*` | default `draft` | **no real events exist** — do not create from demo data |
| `value_chain_clusters.status` | default `draft` | `published` |
| `cluster_beneficiaries.sort_index` | default `0`, NOT NULL | table row order |
| `media_items.media_type` | NOT NULL, no default | `image` |
| `media_items.status` | default `draft` | `published` |
| `galleries.status` | default `draft` | `published` |
| `gallery_images.sort_index` | nullable | `img` order within the body |
| `visual_reports.*` | default `draft` | only if newsletters/magazine are modelled here |

---

## 10. Blockers that need a decision before implementation

1. **External reference column.** Without a `wp_id` (or a `wp_import_map` table keyed by
   `post_type` + `wp_id`), re-running the importer cannot avoid duplicates. Recommended:
   one nullable `wp_id` per imported table **plus** a `wp_import_map` table, or a dedicated
   mapping table only. This is the one schema change the integrity requirements genuinely need.
2. **HTML → plain text.** Confirm the conversion is acceptable, or decide to render HTML
   (which requires a front-end change, not currently present).
3. **Duplicate post↔project stories** (6 pairs, plus 1 post↔empty page; see
   `05-unmatched-and-issues.csv`). Decide whether the news record, the project record, or both
   survive.
4. **News categories.** Add a category relation, or accept that all articles land uncategorised.
5. **Static pages.** Decide whether the 5 real pages become seeded `site_settings` /
   hardcoded React copy, or a new `pages` table.
6. **Testimonials.** Keep the 5 (all the same person) as `media_items`, a new table, or drop.
7. **Missing documents.** 3 PDFs (newsletter ×2, magazine) and 9 newsletter images must be
   downloaded before they can be mapped to `visual_reports.file_path`.
8. **Draft content.** 3 draft posts, 2 draft projects, 4 draft legal pages must not be
   imported as published without explicit approval.

---

## 11. Verified vs assumed

**Verified by inspection:** every column and type in §1 (PRAGMA against the live SQLite DB);
every render contract in §2 (`news/show.tsx`, `projects/show.tsx`, `events/show.tsx`,
`value-chains/show.tsx`); `NewsAdminController` excerpt behaviour; storage disk and symlink;
upload folder names; attachment counts and coverage; cluster table contents; presence/absence
of PDFs; absence of YouTube embeds.

**Assumed (not verified):** that the production database matches `database/database.sqlite`
(the local DB contains Faker/test rows); that the importer will run on the same disk
configuration; that `media_items` is the intended home for news-level galleries rather than
`galleries` (both exist and either would work).
