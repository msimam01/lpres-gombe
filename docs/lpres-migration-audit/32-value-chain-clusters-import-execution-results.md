# Phase 5.3 — Value Chain Clusters Import Execution Report

**Date of report**: November 8, 2026
**Import executed**: November 7, 2026, 19:28 local (18:28 UTC — see §13.2)
**Environment**: `APP_ENV=local`, SQLite `database/database.sqlite`
**Task**: documentation and verification only. The importer was **not** re-run, no row was written, changed or removed by this verification, no migration was run, no media file was moved or overwritten, and no application code was changed.

## Evidence key

Every claim below is labelled with how it was established:

| Label | Meaning |
|-------|---------|
| **[DB]** | Read from the live database during this verification (`SELECT` only) |
| **[FS]** | Read from the current filesystem (sizes, MD5, mtimes) |
| **[LOG]** | Historical log, saved HTTP capture or backup file left by the executed run |
| **[TEST]** | Automated tests executed today, November 8, 2026, for this report |
| **[DEV]** | Confirmed by the developer through manual testing (reported in the request) |
| **[NOT VERIFIED]** | No independent evidence exists |

Timestamps in the database and application logs are UTC (`config/app.php` → `APP_TIMEZONE` default `UTC`); the machine clock and file mtimes are UTC+1. An `18:28` record is therefore `19:28` on disk.

---

## 1. Scope and approved decisions

The executed import covered exactly the nine audited WordPress cluster pages, per
`docs/lpres-migration-audit/31-value-chain-clusters-migration-audit.md`:

| Decision | Approved value | Encoded in code |
|----------|----------------|-----------------|
| Cluster set | WP 3846, 3849, 3852, 3855, 3858, 3861, 3864, 3867, 3870 | `ClusterFields::CLUSTERS` |
| Destination names/slugs | Dairy, Beef, Sheep and Goat, Broiler, Eggs, Leather, Feed, Fodder, Pig | same |
| Rows | 93 distinct rows | `ClusterFields::EXPECTED` (per-cluster assertion) |
| Beneficiaries | 15,985 | same |
| Eggs duplicate | Exclude WP 3858 data row 11 (`Kwami / Malam Sidi / 30`), recorded with reason | `ClusterFields::EXCLUSIONS` |
| Dairy LGA | `Shomgom` | preserved in import, no rewriting |
| `Gombe / Bajoga` | Preserved as published in Sheep and Goat and Eggs | preserved in import |
| Descriptions | Beef keeps the established project text; the other eight are `NULL` | `ClusterFields::BEEF_DESCRIPTION`, `description()` |
| Charts | Copy each audited PNG to `uploads/clusters/{slug}.png`; never overwrite | `ClustersImporter::copyChart()` |

The import refuses rather than guesses: a changed source row count/total, a changed duplicate, a
missing chart, a published status other than `publish`, or a pre-existing cluster that the importer
did not create all abort that cluster with a reported failure.

## 2. Implementation files created

All created/modified on November 7, 2026 between 19:17 and 19:34 local **[FS]**:

| File | Status | Size | Purpose |
|------|--------|------|---------|
| `app/Migration/Clusters/ClusterFields.php` | new | 8,382 B | Source IDs, destination identity, approved per-cluster expectations, exclusion rule, Beef description, table parsing and whitespace normalisation |
| `app/Migration/Clusters/ClustersImporter.php` | new | 23,563 B | Per-cluster transactional import, chart copy, row sync, provenance mapping, dry-run planning |
| `app/Migration/Clusters/ClusterImportReport.php` | new | 3,979 B | Report counters and problem details |
| `app/Console/Commands/ImportWordPressCommand.php` | modified | — | Adds `--type=clusters`, plan/per-cluster report tables, exit codes |
| `tests/Feature/ClusterImportTest.php` | new | 18,458 B | 11 tests: import, normalisation, exclusion, spellings, provenance, idempotency, admin protection, rollback, slug conflict, missing chart |
| `tests/Feature/ClusterSourceTest.php` | new | 9,124 B | 5 end-to-end tests against the real export: totals, exclusion, chart bytes, no-op re-run, dry run |

No schema change was made for Phase 5. **[FS]** the newest migration is
`2026_11_06_000001_add_address_to_projects_table.php`; nothing cluster-related was added.

## 3. Tests executed and their actual results

Today's runs **[TEST]**, using `vendor/bin/pest` (tests use the in-memory SQLite configured in
`phpunit.xml`, so the live database was not touched — re-verified afterwards in §10):

| Command | Result | Duration |
|---------|--------|----------|
| `php vendor/bin/pest tests/Feature/ClusterImportTest.php --colors=never` | **11 passed, 122 assertions**, exit 0 | 45.18 s |
| `php vendor/bin/pest tests/Feature/ClusterSourceTest.php --colors=never` | **5 passed, 598 assertions**, exit 0 | 9.12 s |
| `php vendor/bin/pest tests/Unit --colors=never` (environment sanity check) | **46 passed, 127 assertions**, exit 0 | 8.59 s |

Total for the two cluster files: **16 tests, 720 assertions, all passing.**

Passing cluster tests and what each proves:

- `imports the cluster pages with their rows, charts and approved descriptions` (6.26 s)
- `normalises the leading nbsp and doubled spaces without changing wording` (0.25 s)
- `excludes the approved Eggs duplicate and reports it with its reason` (0.33 s)
- `preserves Shomgom and both approved Gombe / Bajoga rows` (0.24 s)
- `records provenance with source and target hashes and chart metadata` (0.23 s)
- `is a true no-op when re-imported unchanged` (1.18 s)
- `does not overwrite a cluster edited by an administrator` (0.27 s)
- `keeps the chart and beneficiary rows when an administrator saves an unchanged edit` (34.51 s)
- `leaves no partial cluster or chart behind when persistence fails` (0.31 s)
- `refuses to adopt a cluster that owns the slug but was not imported` (0.24 s)
- `fails a cluster whose approved chart is missing instead of importing it` (0.23 s)
- `imports exactly the nine audited clusters with the approved rows and totals` (2.14 s)
- `keeps the approved Eggs duplicate out and preserves the audited spellings` (0.92 s)
- `copies every audited chart byte for byte and records nine provenance mappings` (0.81 s)
- `is a no-op on a second run and leaves other content untouched` (1.64 s)
- `reports the audited plan on the command dry run and writes nothing` (3.01 s)

**Limitation:** these are today's results. No test output from the implementation session was
preserved, so the original run's results are **[NOT VERIFIED]**, and the full-suite result for
Phase 5 is **[NOT VERIFIED]** (the last recorded full suite predates this phase).
A first attempt to run both cluster files in one `php artisan test` invocation exceeded the 5-minute
tool limit with no output and was abandoned; the per-file runs above are the recorded results.

## 4. Database backup details

**[LOG]/[FS]** A pre-import backup exists:

| Property | Value |
|----------|-------|
| Path | `C:\Users\HomePC\AppData\Local\Temp\lpres-backup-20261107_192758-pre-clusters.sqlite` |
| File mtime | 2026-11-07 19:27:58 local |
| Size | 1,888,256 bytes |
| MD5 | `1e58bdaae71f489842e8305edde3cbb5` |
| `PRAGMA integrity_check` | `ok` |
| Contents | clusters **0**, cluster rows **0**, news **132**, projects **58**, team members **22**, cluster mappings **0** |

This is independently verifiable as a true pre-import snapshot: its MD5 equals the live database
MD5 recorded in the Phase 5 audit before implementation (`1e58bdaae71f489842e8305edde3cbb5`,
1,888,256 bytes). The import began ~33 seconds later (§6).

No checksum file, restore test or backup log was written alongside it; the MD5 above was computed
during this verification, and the equality with the audited pre-import hash is the proof.

## 5. Dry-run results

**No dry-run output, log or transcript was preserved. [NOT VERIFIED]**

Evidence that the dry-run path exists and is non-writing is limited to the automated test
`reports the audited plan on the command dry run and writes nothing`, which passed today (§3), and
to the command's behaviour in code: without `--confirm` it reports the plan and returns before any
`DB::beginTransaction()`, row write, cluster write or chart copy.

That test asserts the plan for the audited source and that the database and storage are unchanged.
It is not evidence that a dry run was actually performed against the live database before the
executed import.

## 6. Actual import results

No console output from the executed run was preserved. The following is derived from durable
evidence **[DB]/[FS]/[LOG]** and is consistent with a single successful run:

| Result | Value | Evidence |
|--------|-------|----------|
| Clusters created | **9** | 9 rows in `value_chain_clusters`, IDs 3–11 |
| Clusters updated | **0** | one mapping per cluster; no earlier cluster rows |
| Beneficiary rows inserted | **93** | 93 rows, IDs contiguous within one 3-second window |
| Rows updated / deleted | **0** | no pre-existing beneficiary rows |
| Beneficiaries | **15,985** | `SUM(beneficiaries)` |
| Charts copied | **9** | all nine storage files mtime 19:28 local |
| Charts reused / overwritten | 0 / 0 | no clash; no earlier same-named file |
| Provenance mappings written | **9** | `wp_import_map`, `source_type = value_chain_cluster` |
| Failures / conflicts | **0** | no failure or conflict records; no error-level log entry in the import window |

Execution window **[DB]**: every cluster `created_at` and every mapping `imported_at` falls between
`18:28:31` and `18:28:33` UTC (19:28:31–19:28:33 local); beneficiary rows were created in the same
window. The backup was taken at 19:27:58 local, 33–35 seconds earlier.

Cluster primary keys begin at **3** because the two clusters that existed earlier in the day (IDs 1
and 2, deleted before this phase — see audit §5.1) consumed those IDs. This is expected, not a
partial import.

## 7. Per-cluster verification table

**[DB]** all values below were read from the live database and compared with the audit's approved
figures.

| Cluster | Expected rows | Actual rows | Expected beneficiaries | Actual | Status |
|---------|---------------|-------------|------------------------|--------|--------|
| Beef | 11 | 11 | 2,263 | 2,263 | ✅ |
| Broiler | 11 | 11 | 5,221 | 5,221 | ✅ |
| Dairy | 11 | 11 | 818 | 818 | ✅ |
| Eggs | 10 | 10 | 566 | 566 | ✅ |
| Feed | 9 | 9 | 184 | 184 | ✅ |
| Fodder | 9 | 9 | 40 | 40 | ✅ |
| Leather | 10 | 10 | 49 | 49 | ✅ |
| Pig | 11 | 11 | 190 | 190 | ✅ |
| Sheep and Goat | 11 | 11 | 6,654 | 6,654 | ✅ |
| **Total** | **93** | **93** | **15,985** | **15,985** | ✅ |

All nine are `status = published`, hold unique slugs matching the audit, and carry
`image_path = uploads/clusters/{slug}.png`. Row content was checked field by field against the
audit tables (LGA, ward, count, order) and matches, including:

- **Eggs**: 10 rows; exactly one `Kwami / Malam Sidi / 30`; no duplicate **[DB]**. The exclusion is
  recorded in the mapping with its position (11), values and reason.
- **Shomgom**: 8 rows across the nine clusters use `Shomgom`; `Shongom` appears **0** times **[DB]**.
  (Audit §10 estimated "5 times" — that estimate was wrong; 8 is correct. The data is right, the
  audit's count sentence is a documentation slip.)
- **Gombe / Bajoga**: present in Sheep and Goat (187) and Eggs (79) **[DB]**, preserved as published.
- Whitespace: no ward value has leading whitespace or a doubled internal space; `Bajoga West`,
  `Bajoga East` are single-spaced.
- Order: `sort_index` runs 0..n−1 per cluster in source order.

Descriptions **[DB]**:

- Beef: 294 characters, byte-for-byte the established text in
  `database/seeders/BeefClusterSeeder.php` ("The Beef cluster is one of the commodity value chains…").
- The other eight: `NULL`.

## 8. Chart storage verification

**[FS]** all nine files exist at the expected public-storage paths, and each is byte-identical
(MD5) to its audited source in `public/images/lpres/clusters/`:

| Cluster | Storage path | Bytes | MD5 (source = storage) |
|---------|--------------|-------|------------------------|
| Beef | `uploads/clusters/beef.png` | 20,784 | `6b167ffa387100c9c452420e080aec6c` |
| Broiler | `uploads/clusters/broiler.png` | 21,364 | `1ad5c9f80c5c5901a2bda0ed4d5170b9` |
| Dairy | `uploads/clusters/dairy.png` | 21,820 | `d21c1a7270f722f75443d11b00e1940e` |
| Eggs | `uploads/clusters/eggs.png` | 21,746 | `09c1c2cb26d368da860af48437d1a06f` |
| Feed | `uploads/clusters/feed.png` | 18,522 | `e8d6af630bde0b9f49afde4d63dc06fe` |
| Fodder | `uploads/clusters/fodder.png` | 17,023 | `e905cb8f15035867c4f0d13d9696ac6c` |
| Leather | `uploads/clusters/leather.png` | 19,777 | `7f1fe9ac4cd8d0512b78c491f6633dec` |
| Pig | `uploads/clusters/pig.png` | 20,472 | `20e64426c36394fd24b78f2ad7e455a3` |
| Sheep and Goat | `uploads/clusters/sheep-and-goat.png` | 17,206 | `ec2389e8bb9b991ad8ab1439f299b0d8` |

All nine were written at 19:28 local on November 7 **[FS]**, i.e. during the import window, and each
mapping records the same MD5 in `source_metadata.chart_md5` **[DB]**, so the copied bytes are
traceable to the audited originals.

Serving was exercised by the developer's verification server **[LOG]**: nine `HEAD` requests to
`/storage/uploads/clusters/*.png` returned `200` at 19:29:45–19:29:48.

Unrelated leftovers were **not** touched, and are not referenced by any cluster row:
`CjX7OOjUP2N6IG4U7g31cYolq3iH8XjiEzboS5Ag.jpg` (54,793 B), `RM0aIq4XDgfsGfcxQ6BuzpvdkpeHNFMeuZikMlqI.png`
(440,989 B), `beef-country-profile.jpg` (20,784 B), `beef-vista.png` (20,784 B).

## 9. WordPress provenance mapping verification

**[DB]** exactly nine `wp_import_map` rows with `source_type = value_chain_cluster`, one per audited
page, all `target_type = App\Models\ValueChainCluster`, all with non-null `source_hash` and
`target_hash`, all imported at 18:28:31–18:28:33 UTC:

| WP source ID | WP title | Target | Source hash (prefix) | Target hash (prefix) |
|--------------|----------|--------|----------------------|----------------------|
| 3846 | DAIRY - MAIN CLUSTER | #3 Dairy | `98d60bd6d9f8` | `2a85f75a0b41` |
| 3849 | BEEF - MAIN CLUSTERS | #4 Beef | `70703f4bd243` | `228b39ff1802` |
| 3852 | SHEEP AND GOAT - MAIN CLUSTERS | #5 Sheep and Goat | `a203e70b96c0` | `a66f9337584f` |
| 3855 | BROILER - MAIN CLUSTERS | #6 Broiler | `18af06cfc60e` | `aaa5cf05daaf` |
| 3858 | EGGS - MAIN CLUSTERS | #7 Eggs | `6543bbdc2ca7` | `633a6c0da15f` |
| 3861 | LEATHER - MAIN CLUSTERS | #8 Leather | `973ca6a7edee` | `54f6bdbac7ed` |
| 3864 | FEED - MAIN CLUSTERS | #9 Feed | `41c8b8fcb0ec` | `04b3b2ed219e` |
| 3867 | FODDER - MAIN CLUSTERS | #10 Fodder | `f39201b8e916` | `ea708ef5e6ce` |
| 3870 | PIG - MAIN CLUSTERS | #11 Pig | `5244c2a39073` | `bb2a00bf2e7a` |

Metadata on every row records the WP post id, type, status, title, slug, original link, published
and modified dates, chart attachment id (3847, 3850, 3853, 3856, 3859, 3862, 3865, 3868, 3872),
chart source/stored paths, chart MD5, source row count, summary-row count, imported row count,
imported beneficiaries, and the exclusion list.

The Eggs mapping alone carries a non-empty `excluded_rows`:
`{"position": 11, "lga": "Kwami", "ward": "Malam Sidi", "beneficiaries": 30, "reason": "duplicate of data row 7 … the source TOTAL row counts it twice"}`.
The other eight have `[]`. Broiler and Eggs record `summary_rows: 1`; the rest 0.

## 10. Regression checks for News, Projects and Team

**[DB]** compared with the pre-import backup (§4) and the audit's pre-import figures:

| Content | Pre-import | Post-import | Unchanged |
|---------|-----------|-------------|-----------|
| News | 132 | **132** | ✅ |
| Projects | 58 | **58** | ✅ |
| Team members | 22 | **22** | ✅ |
| Attachment mappings | 896 | **896** | ✅ |
| Post mappings | 132 | **132** | ✅ |
| Project mappings | 421 | **421** | ✅ |
| Team mappings | 22 | **22** | ✅ |
| Cluster mappings | 0 | **9** | expected new rows |

Total `wp_import_map` rows moved from 1,471 to 1,480 — exactly the nine new cluster mappings.

**[LOG]** auxiliary: `storage/logs/cluster-verify-before.txt` and `cluster-verify-after.txt` each
contain the same three MD5 values (`e5c922d9…`, `e5c699cf…`, `ded5bf79…`), recorded around the
verification. The script that generated them is not in the repository, so what they hash cannot be
independently confirmed; the identical values are consistent with unchanged content but are not
used as primary proof here.

## 11. Public and admin verification

**[DEV] Manual confirmation (reported by the developer):** the nine clusters were verified manually
after import. This report cannot audit a human's eyes; it records the developer's statement as such.

**[LOG] Corroborating evidence from the verification session:**

- `storage/logs/cluster-verify-server.log` shows the app served `/value-chains` and all nine detail
  URLs (`/beef`, `/dairy`, `/sheep-and-goat`, `/broiler`, `/eggs`, `/leather`, `/feed`, `/fodder`,
  `/pig`) between 19:29:33 and 19:31:55, each completing normally, plus nine chart `HEAD` requests
  returning `200`.
- `storage/logs/cluster-index.html` (19:31) is a saved capture of the Inertia index payload: exactly
  **9** cluster cards, each with `recordCount` and `totalBeneficiaries` of 2,263 / 5,221 / 818 / 566 /
  184 / 40 / 49 / 190 / 6,654, and image URLs pointing at `/storage/uploads/clusters/{slug}.png`.
- `storage/logs/cluster-detail.html` (19:31) is a saved Pig detail page (contains `Kumo West` and
  `Bolari East`) with its rendered beneficiary table.
- `storage/logs/browser.log` records browser visits to `/value-chains/pig` and `/login` at 18:34 UTC
  (19:34 local), i.e. the developer also opened the sign-in page after inspecting the public pages.

**[NOT VERIFIED] Not exercised or not evidenced:**

- No automated browser test, screenshot review or accessibility check was recorded.
- A draft/hidden cluster 404 path was not re-checked against the live site during this phase (it is
  covered by `PublicPagesTest`).
- **Admin editing was not verified here** and could not be, because this task forbids database
  writes. Admin behaviour is covered only by the in-memory tests listed in §3 and by the developer's
  manual statement.
- No HTTP status was captured for `/value-chains` itself in a form this report can inspect beyond
  the server log's completion lines; no console/network error capture exists.

## 12. Idempotency status

**[NOT VERIFIED] against the live database.** A second live run was deliberately not performed
(prohibited), so the strongest available claim is:

- **[TEST]** `is a true no-op when re-imported unchanged` passed (1.18 s), and
  `is a no-op on a second run and leaves other content untouched` passed (1.64 s). Both execute the
  importer twice inside an in-memory database and assert zero writes on the second pass, including
  unchanged news/projects/teams content.
- **[DB]** the live state is consistent with a single clean run: exactly nine mappings, one per
  source ID, nine clusters with IDs 3–11, and 93 rows with no duplicate `(cluster, sort_index)`
  pairs.
- The importer's design supports a no-op re-run: when both `source_hash` and `target_hash` match, it
  counts the cluster as unchanged and returns before any write or chart copy; a changed target is
  reported as administrator-edited and skipped unless `--force-overwrite-admin-edits` is passed.

What remains genuinely untested is a second `--confirm` run against this populated SQLite database.

## 13. Issues, limitations and checks that cannot be independently confirmed

1. **No execution transcript.** Neither the dry-run output nor the live import's console report was
   saved. Cluster/row/chart counts in §6 are derived from database rows, mapping metadata, file
   mtimes and the backup — not quoted from the command's own output. Consequently the exact
   reported "row inserts", "charts copied" and "unchanged" figures printed at run time are
   **[NOT VERIFIED]**, although the resulting state is unambiguous.
2. **Timestamps look offset by one hour.** `created_at`/`imported_at` read `18:28` while files were
   written at `19:28`. This is the app's UTC clock (`APP_TIMEZONE` default) versus the machine's
   UTC+1 local time, not a data anomaly.
3. **IDs start at 3.** Clusters 1–2 existed earlier on November 7 and were deleted before this phase
   (audit §5.1); the import therefore created IDs 3–11. Nothing in the row counts suggests a partial
   import.
4. **Eggs totals differ from the website by design.** The imported Eggs total is 566, not the
   published 596, because the duplicate 30 was excluded by approval. The site-wide total is 15,985,
   not 16,015.
5. **Audit wording error, data is correct.** Audit §10 says `Shomgom` "appears 5 times"; the actual
   figure is 8 LGA cells. `Shongom` appears zero times. No data action is needed; only that sentence
   in the earlier document is imprecise.
6. **No version control.** The project is not a git repository, so there is no commit, diff or
   authorship record for the implementation files; provenance is filesystem mtimes and this report.
7. **Tests were run for this report, not during the implementation.** Their pass status is current
   truth, but nothing proves the same suite was green at import time.
8. **One test-runner anomaly.** A combined `php artisan test` invocation of both cluster files
   exceeded 295 s with no output and had to be abandoned; the files were then run separately with
   `vendor/bin/pest` and both passed (§3). The cause was not investigated.
9. **Admin and error-path coverage is test-only.** Administrator-edited protection, rollback on
   failure, slug-conflict refusal and missing-chart refusal are proven only by in-memory tests.
10. **Unreferenced storage leftovers** from the earlier deleted clusters remain on disk; they are
    harmless, not referenced by any row, and were not deleted (no media changes were authorised).
11. **No production check.** This environment is local SQLite; nothing here verifies a production
    or staging database, and no deployment was performed.

## 14. Final assessment

**PASS, with the limitations above.**

- The live database contains exactly the approved outcome: **9 published clusters, 93 beneficiary
  rows, 15,985 beneficiaries**, matching every per-cluster figure in the audit **[DB]**.
- The approved exceptions are implemented exactly: Eggs holds 10 rows and one `Kwami / Malam Sidi /
  30`; Dairy uses `Shomgom` (8 occurrences site-wide, none of `Shongom`); `Gombe / Bajoga` survives
  in Sheep and Goat and Eggs; Beef alone carries the established description and the other eight are
  `NULL` **[DB]**.
- All nine charts are present at their storage paths, byte-identical to the audited originals, and
  were served successfully during verification **[FS]/[LOG]**.
- Nine provenance mappings tie each WordPress page to its cluster with hashes, chart metadata,
  row counts and the recorded exclusion **[DB]**.
- News (132), Projects (58) and Team (22) are unchanged from the verified pre-import backup, and
  mapping counts moved only by the nine new cluster rows **[DB]**.
- The pre-import backup is a verified, integrity-checked, byte-identical snapshot of the database as
  it stood 33 seconds before the import **[FS]/[LOG]**.
- Cluster tests pass today: 16 tests, 720 assertions **[TEST]**.

What keeps this from being a fully evidenced execution report is that the run left no console
transcript and no dry-run record, so its self-reported counters cannot be quoted; the second-run
idempotency claim is test-proven but not live-proven; and the developer's manual UI confirmation is
recorded as their statement, corroborated by saved captures and server logs rather than by an
automated visual check.

*No database row, migration, media file or application file was modified in producing this report.
Only this document was created.*
