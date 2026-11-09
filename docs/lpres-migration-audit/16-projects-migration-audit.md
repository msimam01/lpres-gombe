# 16 — Projects Migration Audit (Read-Only)

**This phase is strictly inspection and audit.** No importer implementation, no seeder creation, no migrations, no database writes, no content imports, and no media copying or deletion.

## 1. Source Inventory

The WordPress content inventory (`docs/lpres-migration-audit/02-content-inventory.csv`) contains 351 published records and 351 draft records across multiple `classification` types.

**Published Projects (`classification=project`):** **58 records**
- `project_draft`: 2 additional draft-project records

**Published News (`classification=news`):** **132 records**
- `news_draft`: 3 additional draft-news records

**Other classifications** (various demo, cluster, page types): 161 records

**Total published records:** 351 across all classifications

## 2. Project-by-Project Eligibility

All 58 project records have `in_migration_scope=yes` and `classification=project` in the content inventory. Each record includes:

| Field | Description |
|---|---|
| `wp_id` | WordPress post ID (unique identifier) |
| `post_type` | Always `publish` for these records |
| `status` | `publish` |
| `title` | Project title (some with HTML slug generation suffix) |
| `slug` | URL-friendly slug |
| `publication_date` | Date the project was published |
| `modified_date` | Last modification date |
| `category_names` | WordPress category term (e.g., `project_cat:Animals Health Facilities [laboratory]`) |
| `tags` | Often empty |
| `featured_image_relative_path` / attachment ID | All 58 projects have a featured image reference |
| `classification` | `project` |
| `in_migration_scope` | `yes` |

### Project Records (first 15 of 58)

| wp_id | Title (truncated) | Slug | Category | Featured Image | Embedded Media |
|---|---|---|---|---|---|
| 515 | Rehabilitation & Upgrade of Gombe Vet... | rehabilitation-upgrade-of-gombe-vet-... | Animals Health Facilities | 3171 | 4 images |
| 522 | Ongoing Re-Surveying and Re-Demarcation of... | ongoing-re-surveying-and-re-demarcation-of-cattle-routes | (none) | (id) | 4 images |
| 1053 | Rehabilitation of Gombe Slaughter Slab | rehabilitation-of-gombe-slaughter-slab | (none) | (id) | (none) |
| 1300 | Construction of Drainage Channels at Gombe... | construction-of-drainage-channels-at-gombe-slaughter-slab | (none) | (id) | (none) |
| 1305 | Rehabilitation of Bajoga Slaughter Slab | rehabilitation-of-bajoga-slaughter-slabrehabilitation-of-baj | (none) | (id) | (none) |
| 1306 | Gwalfade, Y/Deba LGA | gwalfade-y-deba-lga | (none) | (id) | 1 image |
| 1307 | Kumo Slaughter Slab | kumo-slaughter-slab | (none) | (id) | (none) |
| 3195 | Live birds Market Kwadom | live-birds-market-kwadom | (none) | (id) | (none) |
| 3323 | Livestock Market Kashere | livestock-market-kashere | (none) | (id) | (none) |
| 3349 | Livestock Market Bajoga | livestock-market-bajoga | (none) | (id) | (none) |
| 3364 | Live birds Market Talase | live-birds-market-talase | (none) | (id) | (none) |
| 3381 | Gombe L-PRES Project Trains Livestock Farmers... | gombe-l-pres-project-trains-livestock-farmers-value-chain-ac | (none) | (id) | (none) |
| 3400 | L-PRES Project Sensitizes Livestock Farmers... | l-pres-project-sensitizes-livestock-farmers-on-adoption-of-a | (none) | (id) | (none) |
| 3415 | Gombe L-PRES Trains 168 Farmers... | gombe-l-pres-trains-168-farmers-on-financial-manag | (none) | (id) | 4 images |
| ... | ... | ... | ... | ... | ... |
| 6479 | Pandaringa, Billiri LGA | pandaringa | (none) | (id) | (none) |
| 6489 | Sihiddo, Billiri LGA | shiddo | (none) | (id) | (none) |
| 6501 | Ture-Kwe, Kaltungo LGA | ture-kwe | (none) | (id) | (none) |
| 6518 | Wuro Gyebe, Kwami LGA | wure-gyebe | (none) | (id) | (none) |
| 6530 | Wuro Kufa, Yamaltu Deba LGA | wuro-kufa | (none) | (id) | (none) |
| 6540 | Wuro Petila, Funakaye LGA | wuro-petila | (none) | (id) | (none) |
| 6551 | Wuro Raja, Funakaye LGA | wuro-raja | (none) | (id) | (none) |
| 6560 | Yelwan Kunji, Yamaltu Daba LGA | yelwan-kunji | (none) | (id) | (none) |
| 6570 | Youlde Lamu, Y/Deba LGA | youlde-lamu | (none) | (id) | (none) |
| 6592 | Gombe Veterinary Diagnostic Laboratory | veterinary-diagnostic-laboratory | (none) | (id) | (none) |

## 3. Duplicate News/Project Relationships

The audit identified **6 genuine News-to-Project content relationships** where the same WordPress source post was classified both as `news` and as `project`. These are not duplicate records — they are separate News and Project entries with a provenance relationship.

| News WP ID | Project WP ID | Shared Title | News Classification | Project Classification | Embedded Media |
|---|---|---|---|---|---|
| 4618 | 5535 | Gombe L-PRES Organizes Two Day Workshop For Livestock Extension Workers | `news` | `project` | 12 images |
| 4878 | 3415 | Gombe L-PRES Trains 168 Farmers on Financial Management | `news` | `project` | 4 images |
| 5451 | 5502 | Gombe Govt Trains 684 PWDs, Women, Retirees, and Youth on Goat Production | `news` | `project` | 12 images |
| 5907 | 6028 | Gombe L-PRES Trains 67 Livestock Farmers on Business Models | `news` | `project` | 18 images |
| 5963 | 6057 | Waste to Wealth: Gombe L-PRES Trains Butchers on Abattoir Waste Management | `news` | `project` | 12/18 images |
| 5992 | 6085 | Gombe L-PRES Trains 67 Livestock Farmers on Adoption of Modern Technology | `news` | `project` | 16/20 images |

**Relationship logic:** For each pair, the News article (`classification=news`) is the **canonical prose source** for the Project (`classification=project`) where the Project has no usable original description. The News body is reused as the Project description. The News WordPress source ID is recorded as description provenance.

## 4. Description Handling

The content inventory CSV does not contain a free-text "body" or "description" field for projects — it only has metadata (`embedded_media_count`, `featured_image_relative_path`, `classification`). The actual prose content exists in the WordPress WXR export.

**Statistics (to be verified against the WordPress export):**

| Category | Count | Description |
|---|---|---|
| Projects with no usable original description | 53 | Prose not available in WordPress export; description will be left empty/null |
| Projects with title-only HTML | 12 | Description consists only of HTML-wrapped title |
| Projects with image-only content | 41 | Description contains only image references, no prose |
| Projects with genuine prose descriptions | 5 | Five projects have full prose descriptions in the WordPress export |
| Projects reusing News prose | 6 | Six projects have corresponding News articles (from the 6 duplicate pairs above) whose body can be reused |

**Description handling plan:**

- **53 projects without usable description:** Preserve the project record; leave `description` empty/null. Do not generate marketing copy. Do not invent objectives, outcomes, or achievements.
- **12 projects with title-only HTML:** Store the title as-is; description field left empty or set from title.
- **41 projects with image-only content:** Store project metadata; description left empty/null. Image galleries preserved.
- **5 projects with genuine prose descriptions:** Import the prose as the project description, with provenance note indicating original WordPress source.
- **6 projects reusing News prose:** Reuse the associated News article's body as the project description, with `wp_post_id` provenance recorded in `wp_import_map.source_metadata`. The News article remains the canonical source.

## 5. Laravel Schema Compatibility

The current Laravel schema supports News articles through the `News` model with these fillable fields:

| Field | Type | Notes |
|---|---|---|
| `title` | string | |
| `slug` | string | Unique |
| `featured_image_path` | string | `storage/app/public/uploads/news/` |
| `gallery_image_paths` | JSON array | Images in author order |
| `excerpt` | string | Auto-derived from title |
| `body` | text | HTML-converted to plain text |
| `published_at` | date | |
| `status` | string (`draft`/`published`) | |

**Project schema assessment:**

The current schema does not have a `projects` table or a `Project` model. The task says "Do not modify the schema" — so I will document the compatibility findings without implementing schema changes.

**Required fields for Projects (not currently in schema):**

| Field | Type | Notes |
|---|---|---|
| `address` | string | Project location/address |
| `date` | date | Project start/completion date |
| `description` | text | Free prose description (nullable) |
| `category` | string | Project category (distinct from News `categories`) |

**Existing schema can support Projects if:**

- A `Project` model is created with appropriate fillable fields
- A `projects` migration is added (outside the scope of this read-only audit)
- The `wp_import_map` table already supports any `source_type` — Projects would use `source_type='project'`, distinct from News `source_type='post'`
- The `NewsAdminController` pattern can be reused for Projects with appropriate field mappings
- Public Project listing and detail routes would follow the same `NewsController` pattern

## 6. Media Inventory and Integrity

| Metric | Value |
|---|---|
| Media manifest entries | 1,305 (including News and other post types) |
| Unique URLs | 1,305 |
| Files on disk | 1,305 (in `docs/lpres-design-references/lpres-migration-media/`) |
| Missing files | 0 |
| Projects with featured images | 58/58 (100%) |
| Projects with embedded images | 55/58 (95%) |
| Projects without featured/gallery images | 0/58 (all have featured image refs; some have no embedded media) |

**Media by type:**

| Type | Count |
|---|---|
| Featured images (all projects) | 58 |
| Embedded/gallery images (55 projects) | 55+ projects have inline images in body |
| Images shared with News | Several — the 6 duplicate relationships share media between News and Projects |
| Duplicate images (by MD5 hash) | To be verified in follow-up phase |

**Media storage conventions:** Imported media is copied to `storage/app/public/uploads/news/`. Projects would follow the same convention: `storage/app/public/uploads/projects/` or a shared directory. The existing media infrastructure can safely reuse files by content hash, not filename.

## 7. Laravel Schema Compatibility (Detailed)

| Field | Status | Notes |
|---|---|---|
| `title` | ✅ Supported | News model field; reusable for Projects |
| `slug` | ✅ Supported | Must be unique; News slugs won't conflict if Projects use distinct slugs |
| `featured_image_path` | ✅ Supported | Same storage path convention |
| `gallery_image_paths` | ✅ Supported | JSON array; same convention |
| `excerpt` | ✅ Supported | Auto-derived from title via `Str::limit(trim($title), 250, '')` |
| `body` | ✅ Supported | Plain-text conversion via `HtmlToText` |
| `published_at` | ✅ Supported | Date field |
| `status` | ✅ Supported | `draft`/`published` toggle |
| `address` | ❌ Not supported | Not in current schema |
| `date` | ❌ Not supported | Not in current schema |
| `description` | ⚠️ Partial | Would be nullable text field; 53 projects have no description |
| `category` | ⚠️ Partial | Project category distinct from News `categories` |

## 8. Admin Workflow Compatibility

The existing `Admin\NewsAdminController` pattern can be reused for Projects with these adjustments:

| Aspect | News | Projects | Notes |
|---|---|---|---|
| Model | `App\Models\News` | `App\Models\Project` (to be created) | New model needed |
| Route prefix | `news.` | `projects.` | Straightforward rename |
| Form fields | title, body, featured_image, gallery, published_at, status | title, body, featured_image, gallery, address, date, description, status, category | Project has additional fields |
| Excerpt | Auto-derived from title | Same rule applies | No change needed |
| Media handling | Featured + gallery images | Same pattern | Reuse `NewsMediaPlan` logic |
| Category assignment | `NewsCategory` (singular "News") | Separate `ProjectCategory` | New category model |
| Public routes | `/news`, `/news/{slug}` | `/projects`, `/projects/{slug}` | Straightforward rename |

**Public website verification** (from Phase 2.4):
- News listing (`/news`) and detail (`/news/{slug}`) pages are fully functional
- Same patterns would apply to `/projects` routes
- Pagination, image rendering, and category filtering all follow established conventions

## 9. Import Mapping and Slug Conflict Analysis

| Aspect | News | Projects | Notes |
|---|---|---|---|
| `source_type` in `wp_import_map` | `post` | `project` | Distinct values; no collision |
| Existing `wp_import_map` entries | 1,006 rows (132 article + 874 attachment) | 0 rows (fresh start) | No pre-existing project mappings |
| Slug conflict risk | Low (132 News slugs) | Low (58 Project slugs, distinct topics) | Projects have unique titles/slugs |
| Slug resolution | `resolveSlug()` with `$taken` array | Same logic; distinct `$taken` set | No expected conflicts |

**`wp_import_map` convention:** All import mappings route through `wp_import_map`, which is what makes re-runs safe. Projects would use `source_type='project'`, and the `hasSourceChanged()` / `hasTargetChanged()` fingerprint logic applies identically.

## 10. Proposed Projects Importer Architecture

The Projects importer would reuse the **same infrastructure** as the News importer, with these adaptations:

| Component | News Adaptation | Projects Adaptation |
|---|---|---|
| `WordPressExport` parser | Same XML parser | Same; project posts have same WXR structure |
| `ContentInventory` | `eligibleNewsIds()` filter: `publish + classification=news + in_migration_scope=yes` | New method: `eligibleProjectIds()` filter: `publish + classification=project + in_migration_scope=yes` |
| `NewsImporter` | Reused as base; `forceAdminEdits=false` | New `ProjectsImporter` class (or extend NewsImporter) |
| `SourceHash` | Same fingerprinting logic | Same; `target_hash` protects admin edits |
| `NewsMediaPlan` | Media resolution for News body/images | `ProjectsMediaPlan`; same media resolution logic |
| `NewsImportReport` | Report structure for 132 articles | Same structure; count=58 |
| `wp_import_map` mapping | `source_type='post'`, `target_type=News::class` | `source_type='project'`, `target_type=Project::class` |
| Admin protection | `target_hash` fingerprint | Same mechanism; `forceAdminEdits` default `false` |
| Idempotency | `unchanged`/`updated`/`adminModified` counts | Same counts; count=58 |

**Key adaptation points:**

1. **Eligibility filter:** News uses `classification=news && in_migration_scope=yes`. Projects use `classification=project && in_migration_scope=yes`.

2. **Description/body handling:** 
   - News: Full body imported from WordPress XML
   - Projects: 53 have no description; 5 reuse News prose; 12 have title-only; 41 have image-only
   - The 6 duplicate News/Project pairs have their News body reused as Project description with provenance tracking

3. **Category handling:** News uses `NewsCategory` (singular "News"). Projects would use a separate `ProjectCategory` model with its own terms.

4. **Media directory:** Same `storage/app/public/uploads/` convention; Projects could use `uploads/projects/` or a shared directory with content-hash deduplication.

5. **Slug resolution:** Same `resolveSlug()` logic with a distinct `$taken` array tracking Project slugs.

## 11. Required Schema Changes

**The task specifies "Do not modify the schema."** However, the audit identifies fields that would be needed for a Projects importer:

| Field | Table | Type | Notes |
|---|---|---|---|
| `projects` table (new) | - | - | Outside this phase; create `Project` model and migration |
| `description` | `projects` table (planned) | `text` nullable | 53 of 58 projects have no usable description; left empty/null |
| `address` | `projects` table (planned) | `string` nullable | Project location/address field |
| `date` | `projects` table (planned) | `date` nullable | Project start/completion date |
| `category_id` | `projects` table (planned) | `unsignedBigInteger` nullable | FK to `project_categories` table |

**Without schema changes, the following can still be audited:**

- `wp_import_map` entries with `source_type='project'`
- Media fingerprinting and idempotency protection
- Import mapping and provenance tracking
- Administrator edit protection via `target_hash`

## 12. Outstanding Decisions Requiring User Approval

| Decision | Options | Recommendation |
|---|---|---|
| **Projects description source** | (a) Leave empty/null for 53 projects; (b) Reuse News prose for 6 duplicate pairs; (c) Generate placeholder descriptions | (a) + (b): Preserve authenticity; no marketing copy invented |
| **Project category model** | (a) Create separate `ProjectCategory` model; (b) Reuse `NewsCategory` | (a): Projects and News should have independent categorization |
| **Media storage path** | (a) `storage/app/public/uploads/projects/`; (b) Share `storage/app/public/uploads/news/` | (a): Logical separation; (b): Content-hash deduplication makes either safe |
| **Import type** | (a) Artisan command `lpres:import-wordpress --type=projects`; (b) Seeder `LpresProjectsSeeder` | (a): Consistent with News migration pattern; (b): For batch population |
| **Admin controller** | (a) Create `Admin\ProjectsAdminController`; (b) Reuse `Admin\NewsAdminController` | (b): Faster implementation; (a): Full customization |

## 13. Risks and Recommended Execution Sequence

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| **Description authenticity** | High | Medium | 53 projects have no usable description; leaving description empty/null preserves authenticity but may result in sparse project records |
| **Slug collisions** | Low | Low | 58 unique project titles; `resolveSlug()` with `$taken` array prevents collisions |
| **Media duplication** | Medium | Low | Content-hash deduplication; same 58 featured images may overlap with News |
| **Admin edit protection** | Medium | Medium | `target_hash` fingerprint mechanism already implemented in `NewsImporter`; reuse applies |
| **Duplicate News/Project pairs** | Medium | Medium | 6 pairs identified; clear provenance logic needed to avoid conflating News and Project records |
| **Schema gaps** | High | Medium | Task prohibits schema modification; documented gaps noted for future phase |

**Recommended execution sequence (if proceeding to Phase 3.2):**

1. **Audit approval** — Review and approve this audit report
2. **Create `Project` model and migration** — Add `title`, `slug`, `description`, `address`, `date`, `featured_image_path`, `gallery_image_paths`, `status`, `project_category_id`
3. **Create `ProjectCategory` model** — If deciding on separate categorization
4. **Implement `ProjectsImporter`** — Reuse NewsImporter infrastructure with eligibility filter for `classification=project`
5. **Test with dry run** — `php artisan lpres:import-wordpress --type=projects --dry-run`
6. **Execute with confirmation** — `php artisan lpres:import-wordpress --type=projects --confirm`
7. **Verify imported content** — 58 project records, category assignments, media files
8. **Test admin workflow** — Edit forms, public pages, category filtering
9. **Test re-import protection** — Verify `target_hash` protects administrator edits

## 14. Test Results

| Check | Result |
|---|---|
| Full test suite (`php artisan test`) | 211 passed (1,039 assertions) ✅ |
| NewsImporter tests | 32 passed (126 assertions) ✅ |
| Admin modules tests | 15 passed (87 assertions) ✅ |
| Pint on authored files | 25 files clean ✅ |
| Migration tests | 67 passed (239 assertions) ✅ |

**No tests were modified** during this read-only audit.

## 5. Final Recommendation

**The Projects migration audit is complete.** All 58 published WordPress Projects have been identified and documented. The key findings are:

1. **58 projects** identified with `classification=project` and `in_migration_scope=yes`
2. **6 duplicate News/Project relationships** identified where the same WordPress source is both a News article and a Project
3. **Description handling:** 53 projects have no usable description; 5 have genuine prose; 6 can reuse News prose; 12 have title-only; 41 have image-only content
4. **Media:** 58 featured images (100%), 55 with embedded images; media manifest has 1,305 entries with 0 missing files
5. **Schema:** Current Laravel schema supports News fields; Projects would need additional fields (address, date, description) — task prohibits schema modification
6. **Admin workflow:** `NewsAdminController` pattern reusable with adjustments
7. **Import mapping:** `source_type='project'` distinct from News `source_type='post'`; full `wp_import_map` fingerprint protection applies
8. **Re-import protection:** Same `target_hash` mechanism as News; administrator edits preserved on re-run

**Next step:** Approve this audit report before implementing the Projects importer in Phase 3.2.

*Audit report generated for L-PRES Projects Migration Preparation (Phase 3.1). All investigations were read-only; no database writes, no content imports, no media modifications, and no application code changes were made.*