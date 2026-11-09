# 00 — L-PRES WordPress → Laravel Migration Audit: Summary

Audit date: **2026-11-04**
Scope: **read-only**. No application code, schema, data, XML file or media file was created,
modified, moved or deleted. No migration, seeder or import was run. No live site was contacted.

Source artefacts audited:

| Artefact | Size | Result |
|---|---|---|
| `docs/lpres-design-references/livestockproductivityandresiliencesupportprojectlpres.WordPress.2026-11-04.xml` | 19,522,937 bytes | Parsed with PHP SimpleXML + WXR namespaces, **0 parser warnings** |
| `docs/lpres-design-references/lpres-migration-media/media-manifest.csv` | 1,305 data rows | 1,305 / 1,305 files present, 0 empty |
| `docs/lpres-design-references/lpres-migration-media/` | 523,139,836 bytes (498.91 MiB) | 1,305 files across 12 year/month folders |

---

## 1. Verdict

**Migration-ready for news, projects, team members and value-chain clusters. Not ready for
events, pages or testimonials.**

- The source content the site actually needs (132 news articles, 58 projects, 22 team members,
  9 value-chain clusters) is present, complete, and its images are 100% available locally.
- The export is heavily polluted with **WordPress theme demo content** from a "City Government"
  starter theme (a fake mayor, city services, WooCommerce products, GiveWP donations, five
  duplicate demo events). This accounts for **96 of the 351 analysed records** — 52 demo
  records, 15 demo pages, 10 commerce pages, 7 donation-plumbing pages, 6 demo events, 4 legal
  drafts, 1 duplicate stub and 1 junk post — and **all 6 "events"**. Exclude it, do not migrate it.
- **No genuine L-PRES event exists anywhere in the export.** The `events` table cannot be
  populated from this source.
- The Laravel schema fits news, projects, team and clusters well, but has **no home for
  news categories, tags, static pages, testimonials, team biographies, or any external
  WordPress ID**.
- One structural constraint shapes the whole importer: the React front end renders every body
  field as **escaped plain text split on blank lines**. WordPress HTML must be converted, and
  in-body images must be extracted into media records.

---

## 2. Actual record counts (recalculated from the XML)

Total WXR items: **2,140**. Twenty-seven distinct `wp:post_type` values.

### The four migration types

| Content | Published | Draft | Other | Notes |
|---|---|---|---|---|
| Ordinary posts (`post`) | **144** | 3 | — | Of these, **132 are genuine L-PRES news articles** |
| Projects (`project`) | **58** | 2 | — | 6 are duplicates of news stories |
| Team members (`team`) | **22** | 0 | — | 14 PIUs + 8 Assistant |
| Events (`tribe_events`) | **6** | 0 | — | **All 6 are the same demo event, repeated** |
| Attachments | 1,687 `inherit` | — | 1 `private` | **1,688 total**, matching the preliminary figure |
| Pages (`page`) | 54 | 5 | — | 9 are value-chain cluster pages; only 5 are real static pages |

### Other custom post types found

| Post type | Count | What it actually contains | Verdict |
|---|---|---|---|
| `testimonials` | 5 | All titled "Prof. U.B. Abubakar", five short quotes, same photo | L-PRES-relevant, but no Laravel model |
| `service` | 22 | "City Visitor Guide", "Mayor's Priorities", "Order Birth Certificate" | **Theme demo** — all 22 share one 827-byte body |
| `documentations` | 18 | "Birth & Death Registration", "Child Vacination Document" | **Theme demo** — all 18 share one body |
| `department` | 6 | "Policing and Crime", "Housing and Land" | **Theme demo** — body contains *lorem ipsum* |
| `campaign` | 6 | "Your little help can heal their pains" | **Charitable plugin demo** |
| `product` / `product_variation` | 18 / 7 | WooCommerce | Out of scope |
| `give_forms` / `give_payment` | 8 / 8 | GiveWP donations and payments | Out of scope |
| `donation` | 6 | Completed donation records | Out of scope |
| `nav_menu_item` | 41 | 5 menus | Not modelled (routes are code) |
| `elementor_library` | 3 | Page-builder templates | Not content |
| `tribe_venue` / `tribe_organizer` | 1 / 1 | "New Hyde Park, NY 11040" / "Kevin Martin" | **Demo** |
| `wpcf7_contact_form`, `custom_css`, `mc4wp-form`, `wp_global_styles`, `wp_navigation` | 8 | Plugin/config records | Not content |

### Statuses

`publish` **435** · `inherit` 1,687 (attachments) · `draft` **10** · `private` 1 ·
`charitable-completed` 6 · `abandoned` 1. The 10 drafts are 3 posts, 5 pages and 2 projects.
**Nothing is imported as published unless it was published in WordPress.**

### Taxonomies

- Categories: 9 — City News, Community, Culture, **Devlopement** (sic), Government,
  Latest News, News, SPC Profile, Uncategorized.
- Tags: 5 — City, Culture, Development, Government, Life.
- `project_cat`: 15 terms, but 2 are duplicates of each other (`Farmer Field School [farmers]`
  and `Farmers field school [farmers-field-school]`); 10 used, 5 unused.
- `team_cat`: 3 — PIUs, Assistant, Spc. Spc has no members (the SPC is a separate profile).
- `tribe_events_cat`: 1 — Events. `testimonials_cat`: 1 — quotes.

---

## 3. Reconciliation with the preliminary figures

| Preliminary figure | Recalculated | Verdict |
|---|---|---|
| 58 published projects | **58** | ✅ exact |
| 22 published team members | **22** | ✅ exact |
| 6 published event records | **6** | ⚠️ exact, **but all six are demo duplicates of one fake event** |
| 144 published ordinary posts | **144** | ✅ exact — but only **132** are real news |
| 1,688 attachment records | **1,688** | ✅ exact |
| 229 published content records selected | **230** calculable (58+22+6+144) | ⚠️ off by one; see below |
| 1,305 unique media URLs | **1,305** manifest rows, **1,305** unique URLs | ✅ exact |
| ~499.22 MB downloaded | **523,139,836 bytes = 498.91 MiB** | ✅ consistent (unit difference) |

**On the 229 figure.** The four types sum to 230, so the stated 229 cannot be reproduced
exactly. Two adjacent counts exist and neither is 229:

- 230 published records across the four types;
- **228** of them carry at least one media reference (the two exceptions are posts #6786
  "Our Vision" and #6791 "Our Core Values", which have neither a featured image nor an
  embedded image).

The manifest itself does not encode 229 either: its `referenced_by` column names only
**183 distinct content records** (125 posts + 55 projects + 3 pages) plus **216 distinct
`featured-attachment` ids**. The manifest's `referenced_by` field also merges multiple
references into one cell (`featured-attachment:3069 | post:27:…`) and contains rows with a
bare `featured-attachment:2923` and no content id, so it cannot be used as the authoritative
content↔media map. This audit rebuilt that map from the XML instead.

**Reconciliation of the 1,305 URLs.** 1,305 = every media URL reachable from the selected
records and their featured images, de-duplicated. Note that the manifest contains **zero**
`-WIDTHxHEIGHT` resized variants even though the content bodies reference them constantly;
the downloader resolved each variant back to its full-size original. That is correct
behaviour, and it is why the audit resolves content URLs through attachment records rather
than matching URL strings directly.

---

## 4. Is the content what it claims to be?

### Genuine L-PRES content (236 records)

| Classification | Records | Migration intent |
|---|---|---|
| News articles | 132 | → `news` |
| Projects | 58 | → `projects` |
| Team members | 22 | → `team_members` |
| Value chain clusters | 9 | → `value_chain_clusters` + `cluster_beneficiaries` |
| Value chain descriptions | 4 | → cluster `description` |
| Real static pages (About, Contact, Mission, Vision, Core Values) | 5 | → no table; copy or settings |
| Testimonials | 5 | → no table; decision required |
| SPC profile | 1 | → already a React page + JSON seed |

### Content that is not what it appears to be

1. **The 6 "events" are one fake event, six times.** Identical title ("Organizing 2022 city
   photography new contest"), identical 1,748-byte body, venue "New Hyde Park, NY 11040",
   organiser "Kevin Martin", `_EventCost = 49`, `_EventURL = www.company.com`, dates
   `2023-04-30 → 2023-12-20`. **No real L-PRES event exists in this export.**
2. **Six stories exist twice — once as a `post`, once as a `project`** with the *same slug
   and the same title* (a seventh duplicate pairs a post with an empty page):

   | News post | Project | Title |
   |---|---|---|
   | #4618 | #5535 | Gombe L-PRES Organizes Two Day Workshop For Livestock Extension Workers |
   | #4878 | #3415 | Gombe L-PRES Trains 168 Farmers on Financial Management… |
   | #5451 | #5502 | Gombe Govt Trains 684 PWDs, Women, Retirees, and Youth on Goat Production |
   | #5907 | #6028 | Gombe L-PRES Trains 67 Livestock Farmers on Business Models For Pasture… |
   | #5963 | #6057 | Waste to Wealth: Gombe L-PRES Trains Butchers on Abattoir Waste Management |
   | #5992 | #6085 | Gombe L-PRES Trains 67 Livestock Farmers on Adoption of Modern Technology… |
   | #7349 | page #7338 | Gombe L-PRES Opens Bids… (the page copy is empty) |

3. **Five news slugs are leftover theme demo slugs** that have nothing to do with their
   titles: #27 `spotlight-on-usa-reinventing-government`,
   #35 `connected-justice-and-the-rapid-change-in-technology`,
   #39 `save-soil-and-save-world-project-in-2022`,
   #43 `supporting-local-business-to-bounce-back`, and #7239 with a 196-character slug.
   Two project slugs are also affected: #6489 (title “Sihiddo, Billiri LGA”, slug `shiddo`)
   and #6459, which has an **empty slug** and a body of zero characters.
4. **Mission/Vision/Core Values exist twice** — as pages (#6778, #6821, #6828) and as posts
   (#6786, #6791) with byte-identical bodies.
5. **41 of the 58 published projects have image-only bodies.** Their HTML contains images but
   zero readable characters, so `projects.description` would be empty.
6. **The news archive page (#45), team page (#53), project page (#2750) and "Home 1" (#2560)
   are Elementor listing pages**, not content. They are replaced by React routes and should
   not be imported as records.
7. **Demo pages still published:** Home 2, Portfolio, Causes, Services, Departments, History,
   Documentation, Our Events, Dashboard, Sample Page. All contain city-government filler or
   *lorem ipsum*.

---

## 5. Media audit

### Availability

| Check | Result |
|---|---|
| Manifest rows with a local file | **1,305 / 1,305** |
| Readable and non-empty | **1,305 / 1,305** (0 zero-byte files) |
| Size matches the manifest `bytes` column | **1,305 / 1,305** |
| Extension matches the real decoded image format | **1,305 / 1,305** (via `getimagesize`; 1,167 jpg, 104 jpeg, 33 png, 1 avif) |
| Embedded upload URLs in content that resolve to an attachment | **6,544 / 6,544 (0 unmatched)** |
| Attachments downloaded | 1,263 of 1,688 |
| Attachments referenced by in-scope content | **1,250 — all available locally** (1,256 distinct paths: 1,247 in the media folder + 9 cluster charts already in the repo) |
| Media folder volume used by in-scope content | **477.87 MiB of 498.91 MiB (96%)** |

**Answer to "are all required images available locally?" — Yes, for every in-scope record.**
The 9 value-chain cluster chart PNGs were never downloaded, but byte-identical copies already
exist at `public/images/lpres/clusters/` (all nine sizes match the attachment metadata exactly;
`dairy.png` is also checksum-identical to the copy at
`docs/lpres-design-references/image.png`).

The apparent 288 "unmatched URLs" in a naive first pass were a false alarm caused by
WordPress resizing conventions: content links to `IMG_9479-1-1024x769.jpg`, the attachment
record stores `IMG_9479-1-scaled.jpg`, and the file on disk is `IMG_9479-1.jpg`. Resolving
through the manifest rather than URL strings drops unmatched URLs to **0**.

### Known gaps (all outside the core four types)

| Gap | Detail | Impact |
|---|---|---|
| 3 PDFs | `newsletter.pdf` (#5532), `newsletter-.pdf` (#6169), `Magazine.pdf` (#5738) | Newsletter and Magazine pages cannot be reproduced; relevant if `visual_reports.file_path` is used |
| 9 newsletter JPGs | `2025/02/47…_n.jpg` … referenced by page #5523 | Newsletter page images missing |
| 1 AVIF | `2-e1737033906962.avif` (featured on page #2750) | Not in the media folder, but an identical 225,869-byte copy exists at `docs/lpres-design-references/2-e1737033906962.avif` |

The manifest downloaded **no PDF, no video and no document of any kind** — only jpg, jpeg, png
and avif. So the "visual reports" requirement cannot be evaluated from downloaded media alone.

### Duplicate and irrelevant media

- **143 duplicate-content groups holding 160 redundant files.** These are genuine byte-identical
  re-uploads under different names, e.g. `Untitled-design.jpg` / `Untitled-design-1.jpg`,
  `about-3.png` / `about-3-1.png`, or a WhatsApp image uploaded twice months apart under
  different filenames. Importing all of them multiplies storage and produces duplicated
  galleries.
- **58 downloaded files are used only by demo/commerce content** and are not needed by any
  in-scope record (≈21 MiB).
- **9 SVG icons, 1 webp, 1 zip, 1 csv/txt and 1 demo PDF** are attachment records with no
  content reference at all — pure orphans.

---

## 6. Can the existing Laravel schema hold this data?

**Yes for news, projects, team and clusters. No for pages, testimonials, news categories and
external IDs.**

| Source | Target | Fit |
|---|---|---|
| News (132) | `news` | Good — but no category/tag columns, and `body` must be plain text |
| Projects (58) | `projects` + `gallery_image_paths` | Very good — the JSON gallery column matches the embedded-image model exactly |
| Team (22) | `team_members` | Good — photo, name, designation, category all present |
| Clusters (9) | `value_chain_clusters` + `cluster_beneficiaries` | Excellent — the LGA/Ward/beneficiary tables map row-for-row |
| Value-chain prose (4) | `value_chain_clusters.description` | Good |
| Events (0 real) | `events` | **Nothing to import** |
| Static pages (5) | — | **No table** |
| Testimonials (5) | — | **No table** |
| News categories / tags | — | **No columns** |
| Any external WordPress ID | — | **No column, no mapping table** |

Existing database state (read-only inspection of `database/database.sqlite`): the tables contain
**Faker/development rows** — news `tmp-verify-1`, `asperiores-dolore-si`; a project
`ullam-dolor-ea-offic` with category "Neil Lane"; a team member "Madeline Reed"; plus two
partly-populated clusters:

- **Beef** — 11 beneficiary rows that match the WordPress Beef table exactly (total 2,263). ✅
- **Dairy** — only **2 of 11** rows (Balanga/Bambam 402 and "Shongom"/Kushi 91). The WordPress
  source has all 11, totals 818, and spells the LGA **Shomgom**. ⚠️ Conflict to resolve.

The importer must therefore not assume empty tables.

---

## 7. Changes required before migration

**Blocking (needed for the stated integrity requirements):**

1. **An external reference.** A nullable `wp_id` per imported table, or a `wp_import_map`
   table keyed by `(post_type, wp_id)`. Without it, "avoid duplicate records when rerun" and
   "preserve original WordPress IDs" are both impossible. This is the only schema change the
   requirements genuinely demand.
2. **HTML → plain-text conversion.** Confirmed necessary: `resources/js/pages/news/show.tsx`
   (and the project/event/cluster detail pages) render bodies as escaped text split on blank
   lines. Raw WordPress HTML would be displayed as literal tags.
3. **Download the 3 PDFs and 9 newsletter images** if newsletters/magazine are in scope.

**Non-blocking but recommended:**

4. A news category relation (otherwise all 132 articles land uncategorised).
5. A decision on the 7 duplicate pairs (6 post↔project stories plus 1 post↔empty page).
6. A decision on where the 5 static pages and 5 testimonials live.
7. A decision on the existing Dairy cluster rows — fill the missing 9, replace all 11, or leave
   the 2 seeded rows. The Beef rows already match the source exactly and need no decision.

---

## 8. Recommended migration order

Each step should run behind `php artisan lpres:import-wordpress --dry-run` first, be idempotent
via the external reference, and never touch records an administrator has edited.

1. **Reference data first** — `project_categories` (dedupe the Farmer Field School pair),
   `team_categories` (PIUs, Assistant).
2. **Value chain clusters** — 9 clusters, then their `cluster_beneficiaries` in table order,
   reusing the existing `beef` / `dairy` slugs. Lowest risk, fully structured, verifiable totals.
3. **Team members** — 22 records with photographs and designations.
4. **Projects** — 58 records, featured image + JSON gallery. Exclude the 2 drafts.
5. **News** — 132 articles, last because it is the largest and needs the HTML conversion
   proven. Exclude the 3 drafts.
6. **Media associations** — galleries / media items / videos, after the records exist.
7. **Events** — *skip*. Revisit only if a real event source is found.
8. **Pages, testimonials, newsletters** — only after the decisions in §9.

Rationale: categories and clusters are small, internally consistent and independently
verifiable (row counts and totals can be asserted). Doing them first validates the whole
import pipeline — external IDs, idempotency, media copying, storage paths — before 132
HTML articles depend on it.

---

## 9. Decisions needed before implementation

1. **External ID strategy** — add `wp_id` columns, or a single `wp_import_map` table? (Blocking.)
2. **7 duplicate pairs** (6 post↔project, 1 post↔empty page) — which record survives?
3. **The 6 demo events** — confirm they are dropped, or does the client have real event data
   elsewhere?
4. **News categories and tags** — add a category relation, or accept uncategorised news?
5. **Static pages** (About, Contact, Mission, Vision, Core Values) — hardcoded React copy,
   `site_settings` rows, or a new `pages` table?
6. **Testimonials** — keep the 5 quotes (all the same person), and if so where?
7. **Newsletter / Magazine / Videos** — are these in scope? If yes, the 3 PDFs and 9 images
   must be downloaded first.
8. **Existing Dairy cluster** — overwrite the 2 seeded rows with the 11 WordPress rows, or
   merge and keep "Shongom"?
9. **Team biographies** — the `biography` column was deliberately dropped. Accept the loss,
   or restore it?
10. **Draft content** — 3 draft posts, 2 draft projects and 4 draft legal pages exist. Confirm
    they are excluded.

---

## 10. Report index

| File | Contents |
|---|---|
| `00-summary.md` | This document |
| `01-post-type-inventory.csv` | 56 rows — every post type × status combination with subtotals |
| `02-content-inventory.csv` | 351 rows — every non-attachment record with classification and scope |
| `03-content-media-mapping.csv` | 1,376 rows — content ↔ media with role, attachment id, local path, download status |
| `04-attachment-inventory.csv` | 1,688 rows — every attachment with MIME, size, dimensions, referencing records |
| `05-unmatched-and-issues.csv` | 376 rows — 12 issue categories, severity-ranked |
| `06-migration-field-mapping.md` | Field-by-field mapping, gaps, defaults, blockers |

Analysis scripts (read-only, re-runnable): `tools/parse-wxr.php`, `tools/analyze.php`,
`tools/report.php`, plus inspection helpers. Intermediate data lives in `work/`.

---

## 11. Verified vs assumed

**Verified by direct inspection:** all counts in §2 and §3 (parsed from the XML); all media
results in §5 (`getimagesize`, `md5_file`, `filesize`, per-file existence checks); the schema in
§6 (`PRAGMA table_info` on the live SQLite database and the migration files); the render
contracts and admin behaviour cited in `06-migration-field-mapping.md`; the existing
cluster rows; the absence of PDFs/videos in the media folder.

**Assumed, not verified:** that production data resembles the local database (the local DB
holds Faker rows); that narrative classifications (news vs value-chain description vs static
page) match the client's intent — these are argued from content and taxonomy, not from an
explicit client list; that the 229 figure came from the four content types rather than some
other selection.
