# Phase 5 — Value Chain Clusters Migration Audit

**Date**: November 7, 2026
**Environment**: `APP_ENV=local`, SQLite (`C:\Users\HomePC\lpres-gombe\database\database.sqlite`)
**Mode**: Read-only audit. No database writes, no migrations, no media changes, no code changes, no import executed.
**Status**: Audit complete — **one blocking discrepancy requires a decision before implementation** (see §5.1).

---

## 1. Source inventory

The nine cluster pages are `page` records in the WordPress export, all `publish`, created 2025‑01‑27,
and all flagged `value_chain_cluster` / `in_migration_scope = yes` in the content inventory.

| WP ID | WP title | WP slug | Published | Embedded media |
|-------|----------|---------|-----------|----------------|
| 3846 | DAIRY - MAIN CLUSTER | `dairy-main-cluster` | 2025-01-27 16:44:35 | 1 (attachment 3847) |
| 3849 | BEEF - MAIN CLUSTERS | `beef-main-clusters` | 2025-01-27 16:45:17 | 1 (3850) |
| 3852 | SHEEP AND GOAT - MAIN CLUSTERS | `sheep-and-goat-main-clusters` | 2025-01-27 16:45:53 | 1 (3853) |
| 3855 | BROILER - MAIN CLUSTERS | `broiler-main-clusters` | 2025-01-27 16:46:28 | 1 (3856) |
| 3858 | EGGS - MAIN CLUSTERS | `eggs-main-clusters` | 2025-01-27 16:47:02 | 1 (3859) |
| 3861 | LEATHER - MAIN CLUSTERS | `leather-main-clusters` | 2025-01-27 16:47:39 | 1 (3862) |
| 3864 | FEED - MAIN CLUSTERS | `feed-main-clusters` | 2025-01-27 16:48:15 | 1 (3865) |
| 3867 | FODDER - MAIN CLUSTERS | `fodder-main-clusters` | 2025-01-27 16:48:56 | 1 (3868) |
| 3870 | PIG - MAIN CLUSTERS | `pig-main-clusters` | 2025-01-27 16:49:59 | 1 (3872) |

**Each page has the same structure and nothing else:**

1. An HTML table with header `S/NO | LGA | WARD | NUMBER OF BENEFICIARIES`.
2. A chart image (`wp-block-image`, the attachment in parentheses).
3. Some pages carry an extra `TOTAL` row at the bottom (Broiler, Eggs) — not a beneficiary record.
4. Dairy additionally has an ordered-list bullet containing only its own page title
   (`DAIRY - MAIN CLUSTER`).

**There is no prose description on any of the nine pages.** The `wp:excerpt` field is empty on all
nine, and no paragraph text exists outside the table, the chart and (for Dairy) the title bullet.
The earlier characterisation of Dairy's description as "a placeholder (`LOL`)" is not a WordPress
fact: `LOL` exists only in the Laravel row created before this phase, not in the export (§5.2).

## 2. The nine cluster records

| # | WP ID | WP title | Proposed destination name | Slug |
|---|-------|----------|---------------------------|------|
| 1 | 3846 | DAIRY - MAIN CLUSTER | Dairy | `dairy` |
| 2 | 3849 | BEEF - MAIN CLUSTERS | Beef | `beef` |
| 3 | 3852 | SHEEP AND GOAT - MAIN CLUSTERS | Sheep and Goat | `sheep-and-goat` |
| 4 | 3855 | BROILER - MAIN CLUSTERS | Broiler | `broiler` |
| 5 | 3858 | EGGS - MAIN CLUSTERS | Eggs | `eggs` |
| 6 | 3861 | LEATHER - MAIN CLUSTERS | Leather | `leather` |
| 7 | 3864 | FEED - MAIN CLUSTERS | Feed | `feed` |
| 8 | 3867 | FODDER - MAIN CLUSTERS | Fodder | `fodder` |
| 9 | 3870 | PIG - MAIN CLUSTERS | Pig | `pig` |

Names follow the existing app convention (title case, no "MAIN CLUSTER(S)" suffix); slugs match the
existing `beef` / `dairy` rows and the nine chart filenames.

## 3. Row-level reconciliation

All nine tables were parsed directly from the export. Ward cells begin with a literal `&nbsp;`
entity (`26 6e 62 73 70 3b` before the ward text), which decodes to a non-breaking space and must be
trimmed. Several cells additionally contain a doubled internal space (`Bajoga  West`). Destination
values use the normalised form (leading whitespace stripped, internal runs collapsed), which is what
the existing Beef rows already contain.

Every destination cluster currently holds **0 rows** (§5.1), so "Action" is `insert` throughout
except the excluded duplicate.

### 3.1 Dairy — WP 3846 (11 rows, 818 beneficiaries)

| # | LGA | Ward (source) | Beneficiaries | Action |
|---|-----|---------------|---------------|--------|
| 1 | Akko | Akko | 35 | insert |
| 2 | Balanga | Bambam | 402 | insert |
| 3 | Billiri | Billiri South | 45 | insert |
| 4 | Dukku | Gombe Abba | 74 | insert |
| 5 | Funakaye | Bodor/Tilde | 38 | insert |
| 6 | Gombe | Shamaki | 24 | insert |
| 7 | Kaltungo | Awak | 15 | insert |
| 8 | Kwami | Malam Sidi | 78 | insert |
| 9 | Nafada | Barwo/Nasarawo | 12 | insert |
| 10 | Shomgom | Kushi | 91 | insert (source spells **Shomgom**) |
| 11 | Yamaltu Deba | Kuri/Lano/Lambam | 4 | insert |

**Total 818.** No TOTAL row in source.

### 3.2 Beef — WP 3849 (11 rows, 2,263 beneficiaries)

| # | LGA | Ward | Beneficiaries | Action |
|---|-----|------|---------------|--------|
| 1 | Akko | Akko | 69 | insert |
| 2 | Balanga | Talasse/Dong/Reme | 134 | insert |
| 3 | Billiri | Billiri South | 372 | insert |
| 4 | Dukku | Gombe Abba | 227 | insert |
| 5 | Funakaye | Bajoga West | 62 | insert |
| 6 | Gombe | Shamaki | 426 | insert |
| 7 | Kaltungo | Kaltungo West | 287 | insert |
| 8 | Kwami | Malam Sidi | 148 | insert |
| 9 | Nafada | Barwo/Nasarawo | 281 | insert |
| 10 | Shomgom | Lapan | 128 | insert |
| 11 | Yamaltu Deba | Deba | 129 | insert |

**Total 2,263.**

### 3.3 Sheep and Goat — WP 3852 (11 rows, 6,654 beneficiaries)

| # | LGA | Ward | Beneficiaries | Action |
|---|-----|------|---------------|--------|
| 1 | Akko | Garko | 361 | insert |
| 2 | Balanga | Talasse/Dong/Reme | 622 | insert |
| 3 | Billiri | Billiri South | 489 | insert |
| 4 | Dukku | Gombe Abba | 530 | insert |
| 5 | Funakaye | Bajoga West | 1,177 | insert |
| 6 | Gombe | Bajoga | 187 | insert |
| 7 | Kaltungo | Kaltungo West | 664 | insert |
| 8 | Kwami | Bojude | 377 | insert |
| 9 | Nafada | Barwo/Nasarawo | 1,174 | insert |
| 10 | Shomgom | Filiya | 218 | insert |
| 11 | Yamaltu Deba | Deba | 855 | insert |

**Total 6,654.** `Gombe / Bajoga` is preserved as published (the ward also appears under Funakaye
elsewhere); it is a suspected source mis-key but must not be "corrected" without the client.

### 3.4 Broiler — WP 3855 (11 rows, 5,221 beneficiaries)

| # | LGA | Ward | Beneficiaries | Action |
|---|-----|------|---------------|--------|
| 1 | Akko | Garko | 1,897 | insert |
| 2 | Balanga | Talasse/Dong/Reme | 304 | insert |
| 3 | Billiri | Billiri South | 384 | insert |
| 4 | Dukku | Waziri South/Central | 376 | insert |
| 5 | Funakaye | Bajoga West | 134 | insert |
| 6 | Gombe | Shamaki | 607 | insert |
| 7 | Kaltungo | Kaltungo West | 394 | insert |
| 8 | Kwami | Malam Sidi | 185 | insert |
| 9 | Nafada | Barwo/Nasarawo | 606 | insert |
| 10 | Shomgom | Filiya | 151 | insert |
| 11 | Yamaltu Deba | Deba | 183 | insert |

**Total 5,221**, confirmed by the source's own `TOTAL` row (row 12), which is not a record.

### 3.5 Eggs — WP 3858 (11 rows, 596 beneficiaries; 10 distinct rows, 566)

| # | LGA | Ward | Beneficiaries | Action |
|---|-----|------|---------------|--------|
| 1 | Akko | Garko | 40 | insert |
| 2 | Balanga | Talasse/Dong/Reme | 41 | insert |
| 3 | Billiri | Billiri South | 12 | insert |
| 4 | Dukku | Gombe Abba | 181 | insert |
| 5 | Funakaye | Bajoga West | 17 | insert |
| 6 | Gombe | Bajoga | 79 | insert |
| 7 | Kwami | Malam Sidi | 30 | insert |
| 8 | Nafada | Barwo/Nasarawo | 117 | insert |
| 9 | Shomgom | Kushi | 24 | insert |
| 10 | Kaltungo | Kaltungo East | 25 | insert |
| 11 | Kwami | Malam Sidi | 30 | **EXCLUDED — duplicate of row 7** |

**Duplicate**: source rows 7 and 11 are byte-identical (`Kwami / Malam Sidi / 30`). The table has 11
data rows but only 10 distinct `LGA + Ward` pairs, and the source `TOTAL` row (596) reproduces the
sum *including* both, so the published total double-counts 30 beneficiaries. Row 11 is the copy to
exclude: it is the trailing row, duplicates row 7, and sits immediately before the `TOTAL` row.
**Excluded row: WP 3858, row position 11 (S/NO 11), Kwami / Malam Sidi / 30.** The import must
consume 10 rows totalling **566** for this cluster. This is the only exclusion in Phase 5.

### 3.6 Leather — WP 3861 (10 rows, 49 beneficiaries)

| # | LGA | Ward | Beneficiaries | Action |
|---|-----|------|---------------|--------|
| 1 | Balanga | Talasse/Dong/Reme | 2 | insert |
| 2 | Billiri | Bare | 3 | insert |
| 3 | Dukku | Waziri North | 7 | insert |
| 4 | Funakaye | Bajoga West | 1 | insert |
| 5 | Gombe | Shamaki | 15 | insert |
| 6 | Kaltungo | Kaltungo West | 3 | insert |
| 7 | Kwami | Malam Sidi | 1 | insert |
| 8 | Nafada | Barwo/Nasarawo | 5 | insert |
| 9 | Shomgom | Kushi | 5 | insert |
| 10 | Yamaltu Deba | Deba | 7 | insert |

**Total 49.**

### 3.7 Feed — WP 3864 (9 rows, 184 beneficiaries)

| # | LGA | Ward | Beneficiaries | Action |
|---|-----|------|---------------|--------|
| 1 | Akko | Akko | 14 | insert |
| 2 | Balanga | Talasse/Dong/Reme | 11 | insert |
| 3 | Billiri | Billiri South | 21 | insert |
| 4 | Dukku | Gombe Abba | 15 | insert |
| 5 | Funakaye | Bajoga West | 13 | insert |
| 6 | Kaltungo | Kaltungo West | 5 | insert |
| 7 | Kwami | Gadam | 15 | insert |
| 8 | Nafada | Barwo/Nasarawo | 85 | insert |
| 9 | Yamaltu Deba | Kuri/Lano/Lambam | 5 | insert |

**Total 184.** No Gombe row.

### 3.8 Fodder — WP 3867 (9 rows, 40 beneficiaries)

| # | LGA | Ward | Beneficiaries | Action |
|---|-----|------|---------------|--------|
| 1 | Akko | Akko | 2 | insert |
| 2 | Balanga | Talasse/Dong/Reme | 4 | insert |
| 3 | Billiri | Billiri South | 2 | insert |
| 4 | Dukku | Gombe Abba | 1 | insert |
| 5 | Funakaye | Bajoga East | 5 | insert |
| 6 | Gombe | Shamaki | 1 | insert |
| 7 | Kwami | Malam Sidi | 3 | insert |
| 8 | Nafada | Barwo/Nasarawo | 20 | insert |
| 9 | Shomgom | Filiya | 2 | insert |

**Total 40.**

### 3.9 Pig — WP 3870 (11 rows, 190 beneficiaries)

| # | LGA | Ward | Beneficiaries | Action |
|---|-----|------|---------------|--------|
| 1 | Akko | Kumo West | 37 | insert |
| 2 | Balanga | Bambam | 5 | insert |
| 3 | Billiri | Billiri South | 16 | insert |
| 4 | Dukku | Waziri North | 5 | insert |
| 5 | Funakaye | Bajoga East | 1 | insert |
| 6 | Gombe | Bolari East | 1 | insert |
| 7 | Kaltungo | Kaltungo West | 83 | insert |
| 8 | Kwami | Malam Sidi | 2 | insert |
| 9 | Nafada | Barwo/Nasarawo | 1 | insert |
| 10 | Shomgom | Lapan | 38 | insert |
| 11 | Yamaltu Deba | Deba | 1 | insert |

**Total 190.**

### 3.10 Row census

| Cluster | Source data rows | Excluded | Rows to import | Beneficiaries |
|---------|-----------------|----------|----------------|---------------|
| Beef | 11 | 0 | 11 | 2,263 |
| Dairy | 11 | 0 | 11 | 818 |
| Sheep and Goat | 11 | 0 | 11 | 6,654 |
| Broiler | 11 | 0 | 11 | 5,221 |
| Eggs | 11 | 1 | 10 | 566 |
| Leather | 10 | 0 | 10 | 49 |
| Feed | 9 | 0 | 9 | 184 |
| Fodder | 9 | 0 | 9 | 40 |
| Pig | 11 | 0 | 11 | 190 |
| **Total** | **94** | **1** | **93** | **15,985** |

## 4. Beneficiary total reconciliation

| Step | Value |
|------|-------|
| Sum of all 94 source data rows | **16,015** |
| Matches the earlier "reported before duplicate correction" figure | yes |
| Less duplicate Eggs row (Kwami / Malam Sidi / 30) | −30 |
| **Distinct total to import** | **15,985** |
| Destination total currently stored | 0 |

The earlier arithmetic is confirmed against the actual records:

- 94 source rows is correct (the Broiler and Eggs `TOTAL` rows are not records and were excluded).
- The 11-row counts for Dairy, Beef, Sheep and Goat, Broiler, Eggs and Pig, the 10-row Leather and
  the 9-row Feed and Fodder all reproduce.
- 16,015 − 30 = **15,985**, and 94 − 1 = **93** distinct rows.
- The earlier "81 inserts / 80 after excluding the duplicate" plan assumed the 13 pre-existing Beef
  and Dairy rows still existed. **They do not** (§5.1), so the insert count becomes 93, not 80. The
  difference is exactly those 13 rows; no source data is lost by importing them again, because the
  13 rows were themselves copies of source rows.

Cluster-level totals for the public page will be computed from the rows (the app derives totals at
render time and stores none), so 15,985 is the expected site-wide figure, and Eggs must display 566.

## 5. Existing Laravel implementation assessment

### 5.1 BLOCKING: the two existing clusters (and all 13 rows) are gone

The earlier audit recorded `beef` (id 1, 11 rows, 2,263) and `dairy` (id 2, 2 rows, 493). The live
database now contains **0 clusters and 0 beneficiary rows**:

| Evidence | Value |
|----------|-------|
| `SELECT COUNT(*) FROM value_chain_clusters` | **0** |
| `SELECT COUNT(*) FROM cluster_beneficiaries` | **0** |
| `sqlite_sequence` for `value_chain_clusters` | **2** (ids 1–2 were used) |
| `sqlite_sequence` for `cluster_beneficiaries` | **13** (13 rows were inserted) |
| Backup taken 2026-11-07 10:51 (`…\Temp\lpres-backup-20261107_teams_databackup.sqlite`) | 2 clusters, 13 rows, 2,756 beneficiaries |
| `value_chain_clusters` rows in live DB | none |

The sequence values prove the rows existed and were deleted (a fresh migration would reset them).
No admin-delete audit trail is written by the application, so the actor cannot be confirmed from the
database or logs; the admin cluster list was visited at 11:07 today, and the database file was last
written at 12:11. Nothing in the Phase 4 team work touches cluster tables.

**Consequences for the plan:**

- There are no "13 existing rows to preserve" in the live database. The requirement is unsatisfiable
  as written and must be restated.
- Dairy's `Shongom / Kushi / 91` row and its `LOL` description exist only in the backup, not in the
  live app.
- Importing the source creates **9 clusters and 93 rows** (all inserts). The 13 former rows are
  reproduced by the import — Beef's 11 exactly, and Dairy's 2 with the same counts — so no
  information is lost. The only value-level change is Dairy's LGA spelling, which becomes the
  project's documented **Shomgom** instead of the old `Shongom`.
- No duplicate records can result, because the tables are empty and slugs are unique.

**Decision required:** confirm the target is a clean import of 9 clusters / 93 rows / 15,985
beneficiaries (recommended), rather than restoring the 13 rows from the 10:51 backup first. Restoring
would also need the `LOL` description and `Shongom` spelling corrected, so it converges on the same
end state through extra steps.

### 5.2 Description reality

- WordPress supplies **no** cluster descriptions, so no source text exists to import.
- The live `LOL` value is a Laravel-side placeholder typed into the admin form earlier. It is not
  source content and is not present anywhere in the live database now.
- Beef's description in the backup is project-authored text (it lives in
  `database/seeders/BeefClusterSeeder.php`), not WordPress content: it describes the table and chart
  in neutral terms and makes no unsupported claims.
- **Recommended**: keep the established Beef text verbatim (name-adjusted only if desired), and
  leave the other eight descriptions `NULL`. The public card renders name + record count +
  beneficiary total without a description; the detail page simply omits the paragraph block.
  Nothing is invented, and Dairy's `LOL` never returns.
- **Alternative**: a single neutral, clearly generic sentence for all nine. This is a content
  decision, not a migration fact, and should be approved explicitly if wanted.

### 5.3 Schema and models

| Layer | Current state | Fit for Phase 5 |
|-------|---------------|-----------------|
| `value_chain_clusters` | `id, name, slug (unique), image_path, description (text, nullable), display_order (default 0), status (string, default 'draft'), timestamps` | Sufficient. No change needed. |
| `cluster_beneficiaries` | `id, value_chain_cluster_id (FK, cascade delete), lga, ward, beneficiaries (unsigned int), sort_index (indexed), timestamps` | Sufficient. No change needed. |
| `ValueChainCluster` | `$fillable = name, slug, image_path, description, status`; `beneficiaries()` ordered by `sort_index, id`; `scopePublished()` filters `status = 'published'` and orders by name | Fine. Note `display_order` is unused by the model and the query. |
| `ClusterBeneficiary` | casts `beneficiaries` and `sort_index` to int; `cluster()` belongsTo | Fine. |
| `wp_import_map` | generic source→target map with `source_type`, `source_id`, `source_hash`, `target_hash`, `source_metadata`; currently `attachment=896, post=132, project=421, team=22` and **no cluster rows** | Ready to hold `source_type = 'value_chain_cluster'`. |

No new migration is required for the data itself. Optional, not recommended now: a uniqueness
constraint on beneficiary rows would have rejected the Eggs duplicate at insert time, but the product
needs to remain able to store what the source contains after the client confirms it.

### 5.4 Public behaviour

- Routes: `GET /value-chains` → `ValueChainClusterController@index`; `GET /value-chains/{cluster:slug}`
  → `@show`. Both are public and read-only.
- Index: published clusters only, alphabetical; card shows chart image, name, 28-word excerpt of the
  description (omitted when null), record count and beneficiary total.
- Detail: 404 unless `status === 'published'`; renders description paragraphs (split on blank lines),
  the table with **derived** S/No and a derived total, and the chart from `image_path`.
- Images are served as `asset('storage/'.$image_path)`, i.e. **from the public storage disk**, not
  from `public/images/lpres/clusters/`. The nine PNGs must therefore be copied into
  `storage/app/public/uploads/clusters/` and referenced by `image_path`; leaving `image_path` null
  would render the card's dark placeholder and hide the chart.
- With the tables empty, the index currently renders its "No value chain clusters have been
  published yet" empty state and every detail URL 404s. (Not exercised live: no server was running
  during this audit; verified from code and database state.)

### 5.5 Admin behaviour

- `ClusterAdminController` extends the generic module: name, description, chart image, beneficiary
  table, status (`draft`/`published`). Slug is generated on create and frozen on update.
- The beneficiary table is a repeatable row editor with automatic S/No, add/remove, and `id` carried
  for existing rows. On save, `syncBeneficiaries()` updates rows that belong to the cluster, inserts
  new ones, and deletes rows dropped from the submitted table — all inside one transaction.
- Editing a cluster without submitting the table leaves existing rows untouched (the controller
  skips syncing when `beneficiaries` is absent).
- Tests already cover cluster creation through the admin and published-only public listing
  (`AdminModulesTest`, `PublicPagesTest`).

## 6. Chart image verification

Each page's chart is an attachment in the export. The nine local PNGs were compared with the
attachment metadata (`_wp_attachment_metadata.filesize`, `width`, `height`):

| Cluster | WP attachment | WP file | Bytes (WP = local) | Dimensions | Local file |
|---------|---------------|---------|--------------------|------------|------------|
| Dairy | 3847 | 2025/01/image.png | 21,820 | 438×293 | `public/images/lpres/clusters/dairy.png` |
| Beef | 3850 | 2025/01/image-1.png | 20,784 | 449×221 | `beef.png` (identical MD5 to `storage/…/beef-country-profile.jpg`) |
| Sheep and Goat | 3853 | 2025/01/image-2.png | 17,206 | 450×276 | `sheep-and-goat.png` |
| Broiler | 3856 | 2025/01/image-3.png | 21,364 | 450×224 | `broiler.png` |
| Eggs | 3859 | 2025/01/image-4.png | 21,746 | 451×224 | `eggs.png` |
| Leather | 3862 | 2025/01/image-5.png | 19,777 | 453×264 | `leather.png` |
| Feed | 3865 | 2025/01/image-6.png | 18,522 | 447×235 | `feed.png` |
| Fodder | 3868 | 2025/01/image-7.png | 17,023 | 448×230 | `fodder.png` |
| Pig | 3872 | 2025/01/image-9.png | 20,472 | 450×236 | `pig.png` |

All nine match on both byte size and pixel dimensions, so the local PNGs are the WordPress originals.
`image-8.png` (attachment 3871) is not referenced by any cluster. The attachment files themselves
were never downloaded into the media folder, so **no download is needed**: the repo copies are the
source of truth, and they must be copied into the storage disk during import.

Existing storage-disk leftovers (all unreferenced now, none touched):

| File | Bytes | Note |
|------|-------|------|
| `uploads/clusters/beef-country-profile.jpg` | 20,784 | byte-identical to `beef.png`; old Beef chart |
| `uploads/clusters/beef-vista.png` | 20,784 | older Beef variant |
| `uploads/clusters/CjX7OOjUP2N6IG4U7g31cYolq3iH8XjiEzboS5Ag.jpg` | 54,793 | old Dairy placeholder image |
| `uploads/clusters/RM0aIq4XDgfsGfcxQ6BuzpvdkpeHNFMeuZikMlqI.png` | 440,989 | origin unknown; not referenced by any cluster row |

**Recommendation**: import writes `uploads/clusters/{slug}.png`; no existing file is overwritten or
deleted, so a staff-uploaded replacement survives a re-run.

## 7. Proposed field mapping

### Cluster

| Source | Destination | Rule |
|--------|-------------|------|
| `wp:title` (`DAIRY - MAIN CLUSTER` …) | `name` | Map to the title-case names in §2 |
| — | `slug` | Fixed per cluster (§2); unique; preserved on re-run |
| `wp:content` table | `cluster_beneficiaries` | One row per data row, in source order → `sort_index` 0..n |
| `wp:content` image (`wp-image-{id}`) | `image_path` | Copy `public/images/lpres/clusters/{slug}.png` → `uploads/clusters/{slug}.png` |
| — | `description` | **Not in source.** Beef: established text; others `NULL` (pending decision §5.2) |
| `wp:status = publish` | `status` | `published` |
| `wp:modified` / attachment id / WP title / WP slug / source row count / excluded rows | `source_metadata` (JSON on the mapping) | Provenance only |

### Beneficiary row

| Source cell | Destination | Rule |
|-------------|-------------|------|
| S/NO | — | Not stored; the public/admin S/No is the row position |
| LGA | `lga` | Trim; `Shomgom` preserved exactly |
| WARD | `ward` | Trim `&nbsp;`, collapse internal whitespace (`Bajoga  West` → `Bajoga West`) |
| NUMBER OF BENEFICIARIES | `beneficiaries` | Integer, unchanged meaning/units |
| row order | `sort_index` | 0-based source order after exclusions |
| — | `value_chain_cluster_id` | Parent |

Ward normalisation is whitespace only; no wording changes. `Yamaltu Deba`, `Barwo/Nasarawo`,
`Talasse/Dong/Reme`, `Kuri/Lano/Lambam`, `Waziri South/Central` and `Bodor/Tilde` are all preserved
verbatim.

## 8. Required schema changes

**None.** `value_chain_clusters` and `cluster_beneficiaries` already carry every field the import
needs, and `wp_import_map` already supports a new `source_type`. No migration should be created for
Phase 5.

Non-blocking observation: `display_order` exists but is unused by the model (public ordering is
alphabetical). Leaving it at 0 keeps current behaviour.

## 9. Import and idempotency strategy

Follow the established command pattern (`lpres:import-wordpress --type=…`, dry run by default,
`--confirm` to write), adding a `clusters` type:

1. **Source set**: WP IDs 3846, 3849, 3852, 3855, 3858, 3861, 3864, 3867, 3870 only. Reject anything
   not in that list; do not pick up other `page` records.
2. **Parse** each page: data rows only (skip the header and any `TOTAL` row), strip `&nbsp;`,
   collapse whitespace, cast counts to int.
3. **Exclude** Eggs row position 11 (S/NO 11, Kwami / Malam Sidi / 30) with an explicit, reported
   reason; never silently. Any other duplicate must abort the run, not be dropped.
4. **Map** via `wp_import_map` with `source_type = 'value_chain_cluster'`, `target_type` =
   `App\Models\ValueChainCluster`:
   - `source_hash` over title, slug, ordered rows, and chart attachment id;
   - `target_hash` over name, slug, image_path, description, status and ordered rows.
5. **Write** in one transaction per cluster: `firstOrCreate` by slug, replace-or-sync rows by
   position (update when the row at that position differs, insert when missing, delete only rows
   beyond the source count), copy the chart if the destination file is absent, then record the
   mapping and hashes.
6. **Idempotency**: a second run must report 9 existing mappings, 0 new clusters, 0 row inserts,
   0 updates, 0 files copied, 0 conflicts. Any difference means a bug.
7. **Safety**: dry run prints the plan and the exact row/beneficiary totals (9 clusters, 93 rows,
   15,985) and writes nothing; `--confirm` is required to execute; re-running `--confirm` on an
   unchanged source is a no-op.
8. **Admin edits**: if a cluster's `target_hash` no longer matches, treat the record as
   administrator-modified and skip it, exactly like the news/projects/teams importers.

## 10. Pre-import backup and verification plan

**Backup (before any write):**

1. Copy `database/database.sqlite` to a new dated file (e.g.
   `%TEMP%\lpres-backup-20261107-clusters.sqlite`); never overwrite an existing backup.
2. Confirm the copy is non-zero, readable, and byte-identical (MD5/size) to the live database, and
   that `PRAGMA integrity_check` returns `ok`. The earlier 0-byte `/tmp` artefact is why this check is
   mandatory.
3. Record the live MD5 and the pre-import counts: clusters 0, rows 0, `wp_import_map` by type,
   News 132, Projects 58, team members 22.

**Pre-flight (must all pass):**

- Live DB is the file above; `APP_ENV=local`.
- 0 clusters, 0 beneficiary rows, 0 `value_chain_cluster` mappings.
- The nine PNGs exist with the byte sizes in §6.
- Dry run reports exactly: 9 new clusters, 93 new rows, 15,985 beneficiaries, 0 conflicts,
  0 missing images, 0 failures, 0 writes.

**Post-import verification:**

- 9 clusters, all `published`, slugs as §2; unique slugs.
- 93 rows and 15,985 beneficiaries in total; per cluster: 11/2,263 Beef, 11/818 Dairy, 11/6,654
  Sheep and Goat, 11/5,221 Broiler, 10/566 Eggs, 10/49 Leather, 9/184 Feed, 9/40 Fodder, 11/190 Pig.
- Eggs holds exactly one `Kwami / Malam Sidi / 30` row.
- Every `lga`/`ward` matches §3 after whitespace normalisation; `Shomgom` appears 5 times and
  `Shongom` never.
- 9 `value_chain_cluster` mappings with populated hashes/metadata; no duplicates.
- Nine chart files on the storage disk, each byte-identical to its `public/images/lpres/clusters`
  original; every `image_path` resolves and returns HTTP 200.
- Public `/value-chains` lists 9 cards with chart, count and total; each detail page returns 200 and
  shows the right table/total; a draft cluster 404s.
- Admin: edit a cluster without uploading a new chart and confirm the chart path and rows are
  preserved; edit a row and confirm only that row changes; re-run the import and confirm 0 writes.
- News 132, Projects 58, team members 22 and all media/attachment mappings unchanged.
- Targeted cluster tests plus the full suite.

## 11. Read-only confirmation

No database row was inserted, updated or deleted, no migration was run, no import or seeder was
executed, and no file in the project was created, moved or removed. All inspection used `SELECT`
queries, the read-only WordPress parser and filesystem reads.

Checksums recorded during the audit:

| File | MD5 |
|------|-----|
| `database/database.sqlite` (1,888,256 bytes, 12:11) | `1e58bdaae71f489842e8305edde3cbb5` |
| `public/images/lpres/clusters/beef.png` | `6b167ffa387100c9c452420e080aec6c` |
| `public/images/lpres/clusters/broiler.png` | `1ad5c9f80c5c5901a2bda0ed4d5170b9` |
| `public/images/lpres/clusters/dairy.png` | `d21c1a7270f722f75443d11b00e1940e` |
| `public/images/lpres/clusters/eggs.png` | `09c1c2cb26d368da860af48437d1a06f` |
| `public/images/lpres/clusters/feed.png` | `e8d6af630bde0b9f49afde4d63dc06fe` |
| `public/images/lpres/clusters/fodder.png` | `e905cb8f15035867c4f0d13d9696ac6c` |
| `public/images/lpres/clusters/leather.png` | `7f1fe9ac4cd8d0512b78c491f6633dec` |
| `public/images/lpres/clusters/pig.png` | `20e64426c36394fd24b78f2ad7e455a3` |
| `public/images/lpres/clusters/sheep-and-goat.png` | `ec2389e8bb9b991ad8ab1439f299b0d8` |

Only this document was written, as the requested deliverable.

## 12. Decisions needed before implementation

1. **The 13 existing rows no longer exist.** Confirm a clean import of 9 clusters / 93 rows /
   15,985 beneficiaries (recommended), replacing the "preserve 13 rows, insert 80" plan with
   "insert 93".
2. **Descriptions**: keep Beef's established project text and leave the other eight `NULL`
   (recommended), or approve a single neutral sentence for all nine. No WordPress prose exists.
3. **Dairy LGA spelling**: the import writes `Shomgom` (project convention, matches the other eight
   tables). The deleted `Shongom` row is not restored.
4. **Eggs duplicate**: confirm exclusion of source row 11, giving Eggs 10 rows / 566 beneficiaries
   and a site total of 15,985.
5. **`Gombe / Bajoga`** in Sheep and Goat (187) and Eggs (79): preserve as published (recommended)
   unless the client supplies corrected LGAs.
