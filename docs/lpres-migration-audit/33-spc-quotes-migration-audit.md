# Phase 6.1 — SPC Quotes Migration Audit

**Date**: November 8, 2026
**Environment**: `APP_ENV=local`, SQLite `database/database.sqlite`
**Mode**: read-only audit. No records written, no migrations or seeders run, no importer executed, no code, media or SPC Profile changes.
**Database checksum**: `4ae39df9e72cf969c0008915ceb7acd2` (1,921,024 bytes) — recorded before and after the audit, **identical**.

Evidence labels used throughout:

| Label | Meaning |
|-------|---------|
| **FACT** | Read directly from the WordPress export, the database, the code or the filesystem during this audit |
| **ASSUMPTION** | Inference that the source does not state |
| **RECOMMENDATION** | Proposed action, not implemented |

---

## 1. Scope and audit method

**FACT — method.** The six records were re-parsed from the original export
`docs/lpres-design-references/livestockproductivityandresiliencesupportprojectlpres.WordPress.2026-11-04.xml`
(19,522,937 bytes) with the project's own read-only parser (`App\Migration\WordPress\WordPressXmlParser`),
not from earlier summaries. Content was tag-stripped and entity-decoded only for comparison; raw HTML,
byte lengths, MD5s and Unicode code points were checked where punctuation matters. The Laravel side was
inspected with `SELECT`-only queries against the table list, `wp_import_map`, `team_members` and
`site_settings`, plus direct reads of the config, component, page, route, migration and test files.
All file comparisons used MD5.

Prior documents were treated as claims to verify, not as sources: this audit contradicts two of them
(the "stray closing parenthesis" and "exact duplicate") on the actual bytes.

No file in the project was created or modified except this report. Temporary probe scripts were written
outside the project (system temp) and are not part of the repository.

## 2. WordPress source record inventory

**FACT.** All six records exist with these properties:

| WP ID | Post type | Status | Slug | Title | Published (WP local) | Featured image |
|-------|-----------|--------|------|-------|----------------------|----------------|
| 114 | `testimonials` | `publish` | `abubakar` | Prof. U.B. Abubakar | 2023-04-07 05:59:46 | 2597 |
| 116 | `testimonials` | `publish` | `usman` | Prof. U.B. Abubakar | 2023-04-07 06:00:26 | 2597 |
| 118 | `testimonials` | `publish` | `spc` | Prof. U.B. Abubakar | 2023-04-07 06:01:21 | 2597 |
| 3114 | `testimonials` | `publish` | `prof-u-b-abubakar` | Prof. U.B. Abubakar | 2025-01-19 12:30:20 | 2597 |
| 3115 | `testimonials` | `publish` | `bello` | Prof. U.B. Abubakar | 2025-01-19 12:31:41 | 2597 |
| 3670 | `post` | `publish` | `spc-profile` | SPC Profile | 2025-01-25 11:50:13 | none (`_thumbnail_id` absent) |

Shared data on the five testimonials:

- Term: `testimonials_cat` → `quotes` (single term, no other taxonomy).
- `towngov_meta_testimonials`: `test_designation = SPC`, `testimonial_rating = 5`.
- `_thumbnail_id = 2597` (`2025/01/SPC.png`, 422×591, 297,126 bytes).
- No excerpt on any record; no `attachment_url` in the export.
- `_dp_original` (Duplicator clone provenance): 116→114, 118→116, 3114→116; 3115 none.
- `_wp_old_slug` values are unrelated permalinks (`martin-mclaughlin`, `aleesha-brown`, `kevin-martin`),
  confirming the slugs are not attributions.

3670 is a blog `post` in category **SPC Profile** (`spc-profile`, term 70), not a testimonial, and carries
no `testimonials_cat` term.

## 3. Detailed content and media comparison

**FACT.** Verbatim text, after stripping the HTML wrapper, decoding entities and collapsing whitespace
(the raw HTML is noted separately because it differs between records):

| ID | Verbatim text | Chars | Words | HTML wrapper | Content MD5 |
|----|---------------|-------|-------|--------------|-------------|
| 114 | `Resilient livestock systems aren't just about animals; they're about people, innovation, and sustainable development.` | 117 | 14 | `<blockquote>` | `cf9ebea6c7614fcbc331afb160c9e63b` |
| 116 | `From pasture to plate, every step in the livestock value chain strengthens our economy and supports sustainable growth."` | 120 | 18 | `<blockquote>` | `df1a71afdb5bbc933ddbf0120327ae82` |
| 118 | `The L-PRES Project: Bridging gaps, fostering resilience, and driving progress in the livestock sector` | 101 | 14 | `<h4 class="elementor-heading-title elementor-size-default">` | `30c7fc4a5376d7391f9606284dc27601` |
| 3114 | `Investing in livestock productivity today ensures food security, economic stability, and environmental sustainability for tomorrow."` | 132 | 15 | `<blockquote>` wrapping an `<h4>` with newlines | `38f78554b6e5afce4bfc7f6e18ce9061` |
| 3115 | `The L-PRES Project: Bridging gaps, fostering resilience, and driving progress in the livestock sector."` | 103 | 14 | `<blockquote>` | `5ecf37a590171a495b889b13d3c17c46` |

Notes recorded from the raw bytes:

- 114 uses straight apostrophes (U+0027), not typographic ones. It is the only one of the five with clean
  punctuation: final full stop, no stray character.
- 116 ends with `growth.` followed by a single `"` (U+0022), i.e. final code points `U+2E U+22`.
- 118 contains an internal line break between `livestock` and `sector` in the export; normalised to one
  space above. It has **no** final full stop and no stray character.
- 3114 is wrapped in a `<blockquote>` that contains leading/trailing newlines around an `<h4>`, and ends
  `tomorrow."` (`U+2E U+22`).
- 3115 ends `sector."` (`U+2E U+22`).
- **No record contains a parenthesis.** `(` and `)` counts are 0 in the raw content and in the stripped
  text of all five testimonials (see §7).

Media and metadata:

| Item | Value |
|------|-------|
| Attachment 2597 | `2025/01/SPC.png`, alt empty, published 2025-01-16 10:37:42 |
| Dimensions / bytes | 422×591, 297,126 bytes |
| Derivative in metadata | `SPC-91x91.png` (91×91, 13,113 bytes) among 20 registered sizes |
| Downloaded original | `docs/lpres-design-references/lpres-migration-media/2025/01/SPC.png` — 297,126 bytes, MD5 `251da7460857fc8e1fed6cbf2f98bce3` |
| Public copy used by the site | `public/images/lpres/spc-prof-u-b-abubakar.png` — same bytes and MD5 |
| Storage copy from the team import | `storage/app/public/uploads/team-members/2025/01/SPC.png` — same bytes and MD5 |
| 91×91 asset | `public/images/lpres/spc-quote-91.png` — 13,113 bytes, MD5 `9f9f8725225de2c2d0a62b9a71ce9414`; **no code reference found** |
| Carousel background | `public/images/lpres/testimonial-bg-1.jpg` and the media original `docs/.../testimonial-bg-1.jpg` — both 206,974 bytes, MD5 `6b5769a8d9548c31ead7d8bea8617074` |

## 4. Laravel schema and existing data inspection

**FACT — there is no testimonials table and no testimonials model.** The database has 29 tables
(`cache`, `cache_locks`, `cluster_beneficiaries`, `contact_enquiries`, `events`, `failed_jobs`, `galleries`,
`gallery_images`, `job_batches`, `jobs`, `media_items`, `migrations`, `news`, `news_categories`,
`news_category`, `newsletter_subscribers`, `password_reset_tokens`, `project_categories`,
`project_components`, `projects`, `sessions`, `site_settings`, `sqlite_sequence`, `team_categories`,
`team_members`, `users`, `value_chain_clusters`, `visual_reports`, `wp_import_map`). `app/Models` contains
no testimonial model, and no controller, route or admin module mentions quotes or testimonials.

**FACT — SPC Quotes are static front-end content.**
`resources/js/config/site.ts` lines 323–342 export `spcQuotes` with:

- `heading: "SPC's Quotes"`;
- `background: '/images/lpres/testimonial-bg-1.jpg'`;
- `avatar: '/images/lpres/spc-prof-u-b-abubakar.png'`, `avatarAlt: 'Prof. U.B Abubakar, Coordinator of Gombe State L-PRES'`;
- `quotes` — exactly **two** entries, `id: 1` and `id: 2`, both `author: 'Prof. U.B. Abubakar'`, `role: 'SPC'`:
  1. `The L-PRES Project: bridging gaps, fostering resilience, and driving progress in the livestock sector.`
  2. `Investing in livestock productivity today ensures food security, economic stability, and environmental sustainability for tomorrow.`

`resources/js/components/site/spc-quotes.tsx` renders them: autoplay every 6 s, pauses on hover/focus,
respects reduced motion, star rating, avatar, background image, and de-duplicates by `id`. It is imported
only by `resources/js/pages/home.tsx` (line 16, rendered at line 59), so the quotes appear on the homepage
only.

**FACT — WP 118 and WP 3114 are already represented, as text.** After case/punctuation/whitespace
normalisation, config quote 1 is identical to WP 118 and config quote 2 is identical to WP 3114
(both comparisons returned `normalised_equal = YES`). The differences are presentation-level:

| | WordPress | Config |
|---|---|---|
| 118 | `...Project: Bridging gaps...livestock sector` (capital B, no final full stop) | `...Project: bridging gaps...livestock sector.` (lowercase b, full stop added) |
| 3114 | `...tomorrow."` (stray quote) | `...tomorrow.` (quote removed) |

**FACT — no provenance.** `wp_import_map` contains 1,480 rows with source types `attachment` (896),
`post` (132), `project` (421), `team` (22) and `value_chain_cluster` (9). There is no `testimonial`
source type, no `page` mapping at all, and a lookup by source ID returns **no mapping** for 114, 116, 118,
3114, 3115 or 3670. The 132 `post` mappings are news articles; none points at 3670.

**FACT — the candidate quotes are not present anywhere.** A repository-wide search for
`Resilient livestock systems` and `From pasture to plate` found no occurrence in `app`, `database`,
`resources`, `tests` or the built assets.

**FACT — the SPC Profile is a separate, hardcoded page.** `routes/web.php:28` maps `/spc-profile` to
`Inertia::render('team/spc-profile')`. `resources/js/pages/team/spc-profile.tsx` holds the title, the
portrait `/images/lpres/spc-prof-u-b-abubakar.png`, and 7 sections with 28 paragraphs. It is linked from
the Team page card (`resources/js/pages/project-team.tsx:115`). `HomepageTest` and `PublicPagesTest`
assert the route works and is not team-CRUD managed. No database row, no mapping, no admin form.

**FACT — `database/seeders/data/spc-profile.json` is an unused artifact.** It holds 37 parsed blocks
(1 image, 1 `h2`, 35 paragraph blocks) totalling 1,639 words; no code references the file.

**FACT — no admin surface.** There is no admin screen, model, controller or route for quotes or the
profile; editing either requires a code change and rebuild. `site_settings` holds only
`contact_address`, `contact_email`, `contact_phone`, `social_youtube`, `logo_path`.

**Schema assessment.** There is nothing to store a quote in today. That is not a blocker: the required
outcome (two more quotes in the existing carousel) needs **no schema change**, because the carousel reads
a static array. A database-backed design would require a new table, model, admin module, controller change
and a rewrite of the public component — see §10/§12.

## 5. Source-to-destination reconciliation

| WP ID | Source classification | Current Laravel state | Verdict | Action |
|-------|----------------------|-----------------------|---------|--------|
| 114 | Genuine, unique testimonial (14 words) | Not present anywhere | **Import** | Add as quote `id: 3`, verbatim |
| 116 | Genuine, unique testimonial (18 words) with one stray `"` | Not present anywhere | **Import, corrected** | Add as quote `id: 4`, remove only the trailing U+0022 |
| 118 | Genuine testimonial | Displayed as config quote 1 (normalised) | **Retain** | No change |
| 3114 | Genuine testimonial with stray `"` | Displayed as config quote 2 (normalised) | **Retain** | No change |
| 3115 | Word-for-word copy of 118 | Not present | **Exclude** | Duplicate of 118/quote 1 |
| 3670 | Dedicated SPC biography (`post`, category SPC Profile) | Separate hardcoded `/spc-profile` page | **Exclude from quotes** | No change; keep as profile |

Result if approved: **4 quotes displayed** — the two existing ones untouched plus 114 and 116. No conflict
with existing content, because neither new quote repeats existing wording (14 and 18 words, no overlap
with 118/3114 beyond ordinary English).

## 6. Duplicate verification for WP 118 and WP 3115

**FACT.** The pair is a duplicate **in wording**, not a byte-identical copy:

| Test | Result |
|------|--------|
| Word overlap | 14 vs 14 words, 14 shared → **100%** |
| Identical after lowercasing and stripping punctuation | **Yes** |
| Identical raw content | **No** — MD5 `30c7fc4a…` (118) vs `5ecf37a5…` (3115) |
| HTML structure | 118 is a bare `<h4 class="elementor-heading-title elementor-size-default">`; 3115 is `<blockquote>` |
| Text difference | 3115 adds a final full stop **and** a trailing `"`; 118 has neither and contains an internal line break before `sector` |
| Dates | 118 published 2023-04-07 06:01:21; 3115 published 2025-01-19 12:31:41 |
| Media | Both use thumbnail 2597 only; no other media |
| Status | Both `publish` |

**RECOMMENDATION.** Exclusion of 3115 is still correct: it adds no wording, and the wording it repeats is
already displayed. The reason recorded in the migration should say "identical wording to WP 118 (14/14
words), differing only by a final full stop and a stray quote" — not "exact duplicate".

## 7. WP 116 punctuation finding

**FACT — there is no closing parenthesis.** All five testimonials contain zero `(` and zero `)` characters,
in both the raw HTML and the stripped text. The bytes at the end of 116 are:

```
... sustainable growth. "      →     U+2E (.) U+22 (")
```

The defect is a **stray double-quote character (U+0022)** appended after the final full stop, inside the
`<blockquote>`, at the very end of the text. It is the last character of the record's content. The same
artefact appears in 3114 and 3115; 118 and 114 are unaffected.

**RECOMMENDATION.** When 116 is imported, remove only that trailing U+0022 and keep the full stop:
`From pasture to plate, every step in the livestock value chain strengthens our economy and supports sustainable growth.`
No other character, word or punctuation mark should change. The same correction is not applied to 3114 or
3115 because neither is being imported (3114's displayed copy already omits the quote).

## 8. SPC Profile separation

**FACT.** WP 3670 differs from the testimonials in every structural respect: post type `post` (not
`testimonials`), category SPC Profile (not `testimonials_cat/quotes`), no featured image, no SPC rating or
designation metadata, 14,871-byte body, approximately 1,644 words, and a biography layout of one `h2`,
seven section headings, 28 body paragraphs and one inline portrait (attachment 2597).

**FACT.** The Laravel app already presents this as a dedicated page at `/spc-profile`, with the same
headline, the same seven section headings, 28 paragraphs and the identical portrait file. Prior phases
recorded the exclusion explicitly (`27-project-teams-migration-audit.md` §3670, `28` and `30` results).

**RECOMMENDATION.** WP 3670 stays out of SPC Quotes and out of this import. Nothing in the profile page,
route, tests or `spc-profile.json` should be touched.

## 9. Media availability and reuse assessment

**FACT.** All five testimonials share one image, so no per-quote media exists to migrate:

- The original download exists: `docs/lpres-design-references/lpres-migration-media/2025/01/SPC.png`,
  297,126 bytes, MD5 `251da7460857fc8e1fed6cbf2f98bce3`; the media manifest records it as
  `downloaded, 200, 297126`, referenced by `page:53` (Team) and `post:3670` (SPC Profile).
- The public avatar `public/images/lpres/spc-prof-u-b-abubakar.png` is **byte-identical** to that original
  and is already the carousel avatar and the profile portrait.
- The 91×91 derivative recorded in WordPress metadata exists as `public/images/lpres/spc-quote-91.png`
  (same 13,113 bytes) but is not referenced by any code.
- The background `testimonial-bg-1.jpg` exists in both the media tree and `public/images/lpres`, byte-identical.

**RECOMMENDATION.** No copying, moving or generating of media is required. The two new quotes reuse the
existing avatar and background already configured in `spcQuotes`. No image should be added to
`storage/app/public/uploads`, and none of the existing files should be touched.

## 10. Proposed import scope and rules

**Scope: 2 new quotes, 2 retained, 2 excluded, 0 database rows, 0 media files.**

### Recommended option A — static config (consistent with the current architecture)

1. Add exactly two entries to `spcQuotes.quotes` in `resources/js/config/site.ts`:
   - `id: 3` — WP 114, verbatim: `Resilient livestock systems aren't just about animals; they're about people, innovation, and sustainable development.`
   - `id: 4` — WP 116 with the trailing U+0022 removed: `From pasture to plate, every step in the livestock value chain strengthens our economy and supports sustainable growth.`
2. Leave `id: 1` and `id: 2` byte-for-byte unchanged, including their existing capitalisation/punctuation.
3. Keep the single attribution `author: 'Prof. U.B. Abubakar'`, `role: 'SPC'`; do not invent per-quote
   authors from the slugs `usman`/`bello`.
4. Record provenance as a comment above the array (WP 114, 116, 118, 3114; 3115 duplicate excluded;
   3670 profile excluded) plus the audit reference — there is no database target to map.
5. No schema change, no importer command, no `wp_import_map` rows.

**Why no `wp_import_map`:** that table maps a WordPress record to a Laravel **row** (`target_type` +
`target_id`, `target_hash`) and treats `imported_at` as proof a record was written. With static config
there is no target row; writing mapping rows with null targets would create dangling "pending" mappings
that contradict the table's contract. Provenance belongs in the config comment and this document.

### Alternative option B — database-backed testimonials (only if requested)

Requires a new `testimonials` table (text, author, role, status, display order, image path), a model, an
admin module, a controller/Inertia payload change and a rewrite of `spc-quotes.tsx` to read props. That
would alter the existing presentation flow, needs a migration, and is materially larger than this phase.
It would also allow `source_type = 'testimonial'` mappings with source/target hashes and
administrator-edit protection, mirroring the clusters importer. Not recommended here.

### If implementation is approved later (option A)

- Same normalisation used by this audit for any automated duplicate check: strip tags, decode entities,
  collapse whitespace, lowercase, strip punctuation. Under it, 118 ≡ 3115 and 118 ≡ quote 1, 3114 ≡ quote 2.
  Normalised-text MD5s for future diffing: 114 `91db82c4b4a799fc9c72819b94978291`,
  116 (with stray quote) `72054f2097a32578e9add0232372025b`, 118 `968f811028b0810fbbd6c4ec573fd1b9`,
  3114 `96bbd81d20d85538d5090f0e4e2c311e`, 3115 `9d2668f32d44c5a6906d4ba4c18354d2`.
- Duplicate guard: refuse to add a quote whose normalised text equals an existing one (this is what blocks
  a third copy of 118).
- Idempotency: the array is fixed and declarative; re-running the edit cannot duplicate entries. A test can
  assert exactly four quotes with unique ids and no normalised duplicates.
- Conflict handling: not applicable — there is no admin editing, so nothing can be edited behind the
  importer's back. (Under option B, a `target_hash` comparison like the clusters importer would apply.)
- Verification: homepage renders four quotes; ids 1 and 2 unchanged; carousel still autoplays and pauses;
  `npm run build` succeeds; existing homepage/profile tests still pass.

## 11. Risks, conflicts and unresolved questions

1. **The brief's "stray closing parenthesis" does not exist.** The actual defect is a trailing double quote
   (U+0022) in 116. Removing a parenthesis would be a no-op and would leave the real artefact in place.
2. **"Exact duplicate" overstates 118/3115.** They are word-identical with punctuation/HTML differences.
   The exclusion decision stands; the written reason should change.
3. **`wp_import_map` cannot honestly represent config-only quotes.** Provenance via that table is
   incompatible with the recommended approach and needs an explicit decision (§12, D3).
4. **Existing quote 1 is not verbatim WP 118.** It lowercases "bridging" and adds a full stop. The brief
   says not to change existing testimonials, so this divergence should be left alone and recorded, not
   "corrected".
5. **WP 118 contains an internal line break** before "sector". It has no effect today because only the
   normalised text is displayed; any future raw import must normalise whitespace.
6. **Attribution is ambiguous in the source.** All five titles are "Prof. U.B. Abubakar", but slugs name
   `usman` and `bello`. There is no per-quote author field. Keeping one attribution avoids invention.
7. **No admin editing.** Both the quotes and the profile are code-managed; content changes require a
   deployment. If the client expects to edit quotes in the dashboard, option B is the only route.
8. **No automated coverage of quote content.** Existing tests cover `/spc-profile` and the homepage route,
   but no test asserts how many quotes render; a future implementation should add one.
9. **Minor orphan assets** (`spc-quote-91.png`, `spc-profile.json`) are unused. They were left untouched;
   deleting them is out of scope.

## 12. Explicit decisions requiring approval

| # | Decision | Recommended answer |
|---|----------|--------------------|
| D1 | Import scope: add 2 quotes (114, 116), retain 2 (118, 3114), exclude 3115 and 3670 | **Yes — 2 new, 4 total** |
| D2 | Correct 116 by removing **only** the trailing U+0022, keeping the full stop (there is no parenthesis) | **Yes** |
| D3 | Provenance approach: config comment + this audit (no DB), instead of `wp_import_map` rows | **Config comment** — mapping rows would be dangling |
| D4 | Keep a single attribution "Prof. U.B. Abubakar — SPC"; ignore slugs | **Yes** |
| D5 | Leave existing quotes 1 and 2 untouched even though quote 1 differs from WP 118 in case and final stop | **Yes** |
| D6 | No media changes: reuse `public/images/lpres/spc-prof-u-b-abubakar.png`; leave `spc-quote-91.png` and `spc-profile.json` in place | **Yes** |
| D7 | WP 118's internal line break is not introduced into the displayed text | **Yes** |

---

## Read-only confirmation

- Database MD5 before the audit: `4ae39df9e72cf969c0008915ceb7acd2`.
- Database MD5 after the audit: `4ae39df9e72cf969c0008915ceb7acd2` — **identical**; file size 1,921,024 bytes.
- No insert, update or delete was issued; no migration, seeder or importer was run.
- No application code, model, controller, route, test, config or media file was created, modified, moved or
  deleted.
- `wp_import_map` still holds 1,480 rows with the same five source types; no `testimonial` or `page`
  mappings exist.
- Only this document was written.
