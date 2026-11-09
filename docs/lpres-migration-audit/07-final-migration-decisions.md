# 07 — Final Migration Decisions

Audit date: **2026-11-04**
Scope: **read-only**. No Laravel code, migration, model, database record, WordPress XML or downloaded
media file was created, modified, renamed or deleted. No import, seeder or artisan command was run.
No live site was contacted.

Supersedes nothing: reports `00`–`06` are left exactly as generated. Corrections discovered while
preparing this document are listed in **§H** rather than edited into them.

---

## 0. How to read this document

- **FACT** — read directly from the WXR export, the media manifest, the files on disk, or the live
  SQLite schema. Every fact is reproducible with the scripts in `tools/` (§J).
- **RECOMMENDATION** — my judgement. Nothing here is implemented; each item needs your approval.
- Every unresolved item from report 00 is answered in **§G** with an explicit action.

---

## A. The seven duplicate pairs

### A.1 Headline finding

**None of the seven pairs is a content duplicate.** In every pair the prose exists only in the news
post; the project (or page) holds the photographs plus the project metadata, with an empty or
title-only body.

| Pair | Post created | Other created | Prose chars (post / other) | Embedded images (post / other) | Byte-identical shared | Unique union | Recommendation |
|---|---|---|---|---|---|---|---|
| #4618 / #5535 | 2025-02-05 | 2025-02-24 | 2,578 / 71 | 12 / 12 | 8 | 15 | Merge — news is the text, project keeps the gallery |
| #4878 / #3415 | 2025-02-06 | 2025-01-23 | 2,910 / 0 | 4 / 4 | 2 | 6 | Merge — news is the text, project keeps the gallery |
| #5451 / #5502 | 2025-02-24 10:08 | 2025-02-24 11:26 | 4,627 / 73 | 12 / 12 | 11 | 12 | Merge — and de-duplicate: same featured image |
| #5907 / #6028 | 2025-04-25 | 2025-04-28 | 3,188 / 0 | 18 / 18 | 6 | 28 | Merge — news is the text, project keeps the gallery |
| #5963 / #6057 | 2025-04-25 | 2025-04-28 | 3,690 / 0 | 12 / 18 | 9 | 19 | Merge — news is the text, project keeps the gallery |
| #5992 / #6085 | 2025-04-25 | 2025-04-28 | 3,444 / 0 | 16 / 20 | 13 | 22 | Merge — news is the text, project keeps the gallery |
| #7349 / #7338 | 2026-05-14 19:32 | 2026-05-14 19:10 | 5,168 / 0 | 6 / 0 | 1 | 7 | **Keep the post only** — drop the empty page |

"Byte-identical shared" counts distinct images whose MD5 matches across the two records. The
filename-level overlap is zero in most pairs because the same photograph was re-uploaded with a
`-1` or `-2` suffix.

### A.2 Pair-by-pair

**A2.1 — #4618 (post) / #5535 (project).** Identical title and slug. Post published 2025-02-05,
project created 2025-02-24 — 19 days later, after the article went live, which is consistent with
the project being a portfolio copy of the story. The project body is `<h4>` + the title (71 chars)
followed by 12 images and nothing else. 8 of the project's images are byte-identical to the post's.
FACT: the project adds `project_name`, `project_date=2024`, `project_address=Gombe`; the post has no
project metadata.

**A2.2 — #4878 (post) / #3415 (project).** The project was created **first** (2025-01-23), the post
two weeks later (2025-02-06) — the reverse of A2.1, so chronology alone cannot decide direction. The
project body is zero prose with 4 images (`11.jpg`, `a.jpg`, `d.jpg`, `s.jpg`); only 2 are
byte-identical to the post's four. FACT: the project's generic filenames and its
`_wp_old_slug = empire-state-building` metadata show it was built from imported theme demo content
and then re-titled.

**A2.3 — #5451 (post) / #5502 (project).** Post created 10:08, project 11:26 the same day. This is
the strongest duplication: **identical featured image** (attachment 5452) and 11 of 12 images
byte-identical — a union of just 12. Not merging here would produce the same 12 photos in two
galleries.

**A2.4 — #5907 (post) / #6028 (project).** Post 2025-04-25, project 2025-04-28. Project: zero prose,
18 images, only 6 shared with the post — the **largest unique union (28)**. The project genuinely
adds 12 photographs the article does not show.

**A2.5 — #5963 (post) / #6057 (project).** Post 2025-04-25, project 2025-04-28. Project: zero prose,
18 images vs the post's 12; 9 shared; union 19. The project adds 9 images.

**A2.6 — #5992 (post) / #6085 (project).** Post 2025-04-25, project 2025-04-28. Project: zero prose,
20 images vs 16; 13 shared; union 22. The project adds 7 images.

**A2.7 — #7349 (post) / #7338 (page).** Same title, same slug, **same featured image** (7347). The
page was created 22 minutes before the post and has **zero content and zero images**. This is a
stray stub, already classified `duplicate_stub` in report 02. No information exists only in the page.

### A.3 Recommendation

**RECOMMENDATION — one uniform merge rule for A2.1–A2.6:**

1. The **news post is the canonical text**. Import it into `news` as-is.
2. The **project record is kept**, because in all six cases it is the only carrier of the Project
   Category term and of `project_address` / `project_date`. Dropping it would lose the only
   structured location data the site has.
3. Set `projects.description` to the news body for these six records, and record the provenance in
   the import map (`description_source = news:<wp_id>`). This is not invented copy — it is the same
   story, published twice by the client's own team — but it **is** a duplication decision you should
   confirm, because the same ~2.5–4.6k characters will then exist in two tables.
4. De-duplicate the galleries by MD5 across the pair, so the union is stored once
   (15 / 6 / 12 / 28 / 19 / 22 images respectively). Without this, A2.3 shows the same 12 photos
   twice.
5. Slugs may stay identical: `news` and `projects` are separate tables and the public detail routes
   are different paths — `/news/{article:slug}` and `/projects/{project:slug}` (`routes/web.php`
   lines 29 and 33) — so there is no unique-index collision.

**RECOMMENDATION — A2.7:** import post #7349 only. Skip page #7338 entirely; it has no unique
content, and importing it would create a second record under a slug the news table already uses.

**Alternative if you prefer no cross-table duplication:** keep only the news articles and drop all
six project records. That is simpler and avoids repeating the narrative, but it removes six projects
from the Projects section and loses their categories and locations.

---

## B. Testimonials

### B.1 Facts

All five are post type `testimonials` in category `quotes`, status `publish`, **all titled
"Prof. U.B. Abubakar"**, and all illustrated with the **same** featured image (attachment 2597,
`2025/01/SPC.png`).

| ID | Slug | Quote (verbatim) | Chars | Verdict |
|---|---|---|---|---|
| #114 | `abubakar` | Resilient livestock systems aren't just about animals; they're about people, innovation, and sustainable development. | 117 | Unique |
| #116 | `usman` | From pasture to plate, every step in the livestock value chain strengthens our economy and supports sustainable growth**"** | 120 | Unique; stray closing quote |
| #118 | `spc` | The L-PRES Project: Bridging gaps, fostering resilience, and driving progress in the livestock sector | 101 | **Duplicated by #3115** |
| #3114 | `prof-u-b-abubakar` | Investing in livestock productivity today ensures food security, economic stability, and environmental sustainability for tomorrow.**"** | 132 | Unique; stray closing quote |
| #3115 | `bello` | The L-PRES Project: Bridging gaps, fostering resilience, and driving progress in the livestock sector.**"** | 103 | **Same words as #118 (100% overlap)** |

Distinctness, measured by pairwise word overlap: #118 ↔ #3115 share **100%** of their words — they
differ only by a trailing period and a stray `"`. Every other pair overlaps by 7–14%, i.e. ordinary
English filler. So there are **4 distinct quotes, not 5**.

**Attribution is inconsistent.** The display title is identical on all five, but the slugs name three
different people: `abubakar`, `usman`, `bello`. The export does not say which is correct. All five
carry no testimonial-specific metadata (the populated `meta` keys are the shared theme bundle, all
empty values).

**Text defects.** #116, #3114 and #3115 end with an unbalanced `"` (a copy-paste artefact); #118 has
no terminal full stop. All five are 101–132 characters — single-sentence pull-quotes, not
beneficiary testimonials with names, roles or photographs.

### B.2 Suitability for the existing SPC Quotes section

FACT: `resources/js/components/site/spc-quotes.tsx` already renders a rotating "SPC's Quotes" panel
fed by `spcQuotes.quotes` in `resources/js/config/site.ts`. That array currently holds **two** quotes:

- *"The L-PRES Project: bridging gaps, fostering resilience, and driving progress in the livestock
  sector."* — this is WordPress #118/#3115.
- *"Investing in livestock productivity today ensures food security, economic stability, and
  environmental sustainability for tomorrow."* — this is WordPress #3114.

So **two of the five are already migrated** (lightly re-punctuated), and the section exists and
works. The five records are suitable for it: same speaker, same portrait, quote-length text.

### B.3 Recommendation

**RECOMMENDATION:**

1. Add the two genuinely new quotes to `spcQuotes.quotes` in `config/site.ts` — **#114** and **#116**.
   Drop the stray `"` from #116.
2. **Do not add #118 or #3114** — both are already present.
3. **Do not add #3115** — exact word-for-word duplicate of #118.
4. Net result: 4 quotes in the existing carousel, with no database table and no new component.
5. Ignore the slugs (`usman`, `bello`); they are leftover permalinks, not attributions. Keep the
   single credit "Prof. U.B. Abubakar — SPC".
6. Do **not** build a testimonials content model. Four fixed pull-quotes from one person do not
   justify a table, and the client can edit them in `config/site.ts`.

**Decision needed:** confirm that a 4-quote carousel attributed entirely to the SPC is the intended
result, or whether the client expected beneficiary testimonials that this export does not contain.

---

## C. News categories

### C.1 Facts — usage by the 132 genuine news articles

| Term ID | Name | Slug | Articles | Verdict |
|---|---|---|---|---|
| 21 | Latest News | `latest-news` | **130** | Near-synonym of *News* |
| 67 | News | `news` | **89** | Canonical candidate |
| 1 | Uncategorized | `uncategorized` | **9** | Default/empty value |
| 16 | City News | `city-news` | 0 | Theme demo term |
| 17 | Community | `community` | 0 | Theme demo term |
| 18 | Culture | `culture` | 0 | Theme demo term |
| 19 | Devlopement | `devlopement` | 0 | Theme demo term, misspelt "Development" |
| 20 | Government | `government` | 0 | Theme demo term |
| 70 | SPC Profile | `spc-profile` | 0 | Unused; the SPC is a page, not news |

Only **three** categories are used, and all three are effectively the same bucket:

| Combination | Articles |
|---|---|
| Latest News + News | 86 |
| Latest News | 37 |
| Latest News + Uncategorized | 4 |
| Latest News + News + Uncategorized | 3 |
| Uncategorized only | 2 |

- 93 articles carry 2+ categories; 39 carry exactly one; **0 are uncategorised**.
- **No article carries a tag.** The 5 channel tags (City, Culture, Development, Government, Life) are
  unused, and all belong to the theme demo.

### C.2 Duplicates and spelling variations

- **"Latest News" vs "News" is a redundant pair**, not two concepts: 86 of 130 articles carry both,
  and no article carries *News* without *Latest News*. They are two terms for one bucket.
- **"Devlopement"** is a misspelling of "Development" (also present as a tag). Unused by news, so it
  does not need fixing for migration — but it must not be created in Laravel.
- No other duplicates, case variants or plural/singular pairs exist among the terms that news uses.

### C.3 Recommendation

**RECOMMENDATION — one category, and it is a schema addition:**

1. Add `news_categories` (`id`, `name`, `slug`, `wp_term_id`, `display_order`, `status`,
   timestamps) plus a `news_category` pivot or nullable `news.category_id`. Report 06 already flags
   that `news` has no category column at all (`news` columns are: title, slug, featured_image_path,
   excerpt, body, published_at, status).
2. Seed exactly **one** row: `News` / `news` / `wp_term_id = 67`.
3. Map every one of the 132 articles to it. Record the article's **original term IDs**
   (`21`, `67`, `1` as applicable) in the import map so the provenance is recoverable, rather than
   creating three near-identical categories.
4. **Do not create** *Latest News*, *Uncategorized*, *City News*, *Community*, *Culture*,
   *Devlopement*, *Government* or *SPC Profile*.
5. **Do not import tags.** Zero news articles use them.

**If you want no schema change:** skip categories entirely. All 132 articles would be uncategorised,
which is visually identical to today, because the public news index does not filter by category.
That is acceptable but loses the term IDs — which is why I recommend the table.

---

## D. Project content

### D.1 Facts

Of the **58 published projects**:

| Body state | Count |
|---|---|
| Zero prose characters (images only) | **41** |
| Title only in `<h4>` (42–107 chars, i.e. the heading restated) | **12** |
| Real description (>120 chars) | **5** |

So **53 of 58 projects have no usable description**, not 41 — the earlier figure counted only the
strictly empty ones.

The five that do have prose are all short:

| ID | Chars | Title |
|---|---|---|
| #1053 | 767 | Rehabilitation of Gombe Slaughter Slab |
| #1300 | 401 | Construction of Drainage Channels at Gombe Slaughter Slab |
| #1305 | 125 | Bajoga Slaughter Slab |
| #3323 | 171 | Livestock Market Kashere |
| #5703 | 130 | Inauguration and Training of State and LGA Committees on Conflict Mediation… |

**Recoverable information (FACT):**

- Across the 53 prose-less projects there are **314 embedded images**.
- **Zero of them have a caption. Zero have a non-empty `alt` attribute.** I checked every embedded
  image against its attachment record's `caption` and `description` fields and scanned the raw HTML
  for `alt="…"`; there is no descriptive text anywhere in the source.
- **51 of 53 have `project_address`**, 51 have `project_date`, and all 53 have a Project Category.
  Examples: `Gwalfade, Yamaltu Deba LGA (LONG: LAT:)`, `11 LGAs`, `Kwale, Yamaltu Deba LGA`.
- Where the project is a duplicate of a news article (A2.1–A2.6), the **article text is recoverable**
  and can serve as the project description.

**Therefore: a meaningful description can be recovered for 6 projects (from their news articles) and
for none of the rest.** Photographs, a location and a category are the only facts the source holds.

### D.2 The 41 image-only projects

`Images` is the count of embedded images; the filenames are exactly as stored in the manifest.
`Structured metadata` is everything the record actually contains beyond title/slug.

| ID | Title | Images | Image filenames | Structured metadata | Description recoverable? |
|---|---|---|---|---|---|
| 522 | Ongoing Re-Surveying and Re-Demarcation of Cattle Routes | 8 | WhatsApp-Image-2024-12-11-at-6.40.54-PM.jpeg, WhatsApp-Image-2024-12-11-at-6.40.53-PM-1.jpeg, WhatsApp-Image-2024-12-11-at-6.40.53-PM.jpeg, WhatsApp-Image-2024-12-11-at-6.40.50-PM.jpeg, WhatsApp-Image-2024-12-11-at-6.40.57-PM.jpeg, WhatsApp-Image-2024-12-11-at-6.40.58-PM-1.jpeg, WhatsApp-Image-2024-12-11-at-6.40.57-PM-1.jpeg, WhatsApp-Image-2024-12-11-at-6.40.58-PM.jpeg | addr: 11 LGAs; year: 2024; cat: Cattle Routes | No — images + metadata only |
| 1306 | Gwalfade, Y/Deba LGA | 1 | Untitled-design-1.jpg | addr: Gwalfade, Yamaltu Deba LGA (LONG: LAT:); year: 2024; cat: Water Facility | No — images + metadata only |
| 3195 | Live birds Market Kwadom | 4 | WhatsApp-Image-2025-01-23-at-11.32.01-AM-1.jpeg, WhatsApp-Image-2025-01-23-at-11.32.05-AM-2.jpeg, WhatsApp-Image-2025-01-23-at-11.32.05-AM-1.jpeg, WhatsApp-Image-2025-01-23-at-11.32.05-AM.jpeg | addr: Kwadom, Yamaltu Deba LGA; year: 2023; cat: Live birds market | No — images + metadata only |
| 3364 | Live birds Market Talase | 2 | WhatsApp-Image-2025-01-23-at-12.20.01-PM.jpeg, WhatsApp-Image-2025-01-23-at-12.20.03-PM.jpeg | addr: Talase, Balanga LGA; year: 2023; cat: Live birds market | No — images + metadata only |
| 3381 | Training of Livestock Farmers, Value Chain Actors: | 4 | 6.jpg, 66.jpg, 3.jpg, 2.jpg | addr: Gombe; year: 2024; cat: Trainings | No — images + metadata only |
| 3400 | L-PRES Project Sensitizes Livestock Farmers On Adoption Of Artificial Insemination, Improved Breeding. | 3 | 2112.jpg, 12.jpg, 11.jpg | addr: Gombe; year: 2024; cat: Trainings | No — images + metadata only |
| 3415 | Gombe L-PRES Trains 168 Farmers on Financial Management and Cooperative Governance | 4 | 11.jpg, d.jpg, s.jpg, a.jpg | addr: Gombe; year: 2024; cat: Trainings | **Yes** — news post #4878 (2,910 chars) |
| 3426 | Training Of 300 Butchers, Meat Processors and Sellers on Hygienic Meat Handling. | 4 | ii.jpg, jjj.jpg, jj.jpg, j.jpg | addr: Gombe; year: 2024; cat: Trainings | No — images + metadata only |
| 3449 | Kurugu Live birds market | 4 | WhatsApp-Image-2024-12-01-at-8.13.49-PM-1.jpeg, WhatsApp-Image-2024-12-01-at-8.13.49-PM-2.jpeg, WhatsApp-Image-2024-12-01-at-8.13.49-PM.jpeg, WhatsApp-Image-2024-12-01-at-8.13.48-PM.jpeg | addr: Kurugu, Kwami LGA; year: 2024; cat: Live birds market | No — images + metadata only |
| 3541 | Farmer Field School Wajari | 2 | Picture3.jpg, Picture4.jpg | addr: Yamaltu Deba; year: 2024; cat: Farmer Field School | No — images + metadata only |
| 3580 | Water Harvesting Point Gerengi, Funakaye LGA | 6 | WhatsApp-Image-2025-01-24-at-2.55.26-PM.jpeg, WhatsApp-Image-2025-01-24-at-2.55.10-PM.jpeg, WhatsApp-Image-2025-01-24-at-2.55.24-PM.jpeg, WhatsApp-Image-2025-01-24-at-2.55.20-PM.jpeg, WhatsApp-Image-2025-01-24-at-2.55.17-PM.jpeg, WhatsApp-Image-2025-01-24-at-2.55.05-PM.jpeg | addr: Gerengi, Funakaye LGA; year: 2024; cat: Water Havesting Point | No — images + metadata only |
| 3602 | Water Harvesting Point Lamido Buba Siddikiyo, Funakaye | 4 | Picture8-1.jpg, Picture7.jpg, Picture5-1.jpg, Picture6.jpg | addr: Lamido Buba Siddikiyo, Funakaye; year: 2024; cat: Water Havesting Point | No — images + metadata only |
| 3612 | Water Harvesting Point Wawa Zange, Dukku LGA | 3 | Picture8-1.jpg, Picture7.jpg, Picture5-1.jpg | addr: Water Harvesting Point Wawa Zange, Dukku LGA; year: 2024; cat: Water Havesting Point | No — images + metadata only |
| 3943 | Rehabilitation of Gombe Cold Chain Room | 5 | WhatsApp-Image-2024-10-10-at-2.35.37-PM.jpeg, WhatsApp-Image-2024-10-10-at-2.35.39-PM-1.jpeg, WhatsApp-Image-2024-10-10-at-2.35.41-PM.jpeg, WhatsApp-Image-2024-10-10-at-2.35.40-PM.jpeg, WhatsApp-Image-2024-10-10-at-2.35.39-PM.jpeg | cat: Animals Health Facilities | No — images + metadata only |
| 5277 | Dawo, Kwami LGA | 0 | *none* | addr: Dawo, kwami LGA (LONG: LAT:); year: 2024; cat: Water Facility | No — images + metadata only |
| 5279 | Taffare, Billiri LGA | 0 | *none* | addr: Taffare, Billiri LGA (LONG: LAT:); year: 2024; cat: Water Facility | No — images + metadata only |
| 5281 | Amtanar, Shongom LGA | 0 | *none* | addr: Amtara, SHongom LGA (LONG: LAT:); year: 2024; cat: Water Facility | No — images + metadata only |
| 5283 | Yankari, Akko LGA | 6 | WhatsApp-Image-2025-02-10-at-1.39.59-PM.jpeg, WhatsApp-Image-2025-02-10-at-1.40.00-PM.jpeg, WhatsApp-Image-2025-02-10-at-1.40.00-PM-3.jpeg, WhatsApp-Image-2025-02-10-at-1.40.00-PM-2.jpeg, WhatsApp-Image-2025-02-10-at-1.39.59-PM-rotated.jpeg, WhatsApp-Image-2025-02-10-at-1.40.00-PM-rotated.jpeg | addr: Yankari, Akko LGA (LONG: LAT:); year: 2024; cat: Water Facility | No — images + metadata only |
| 5294 | Wuro Ladde, Gombe LGA | 6 | WhatsApp-Image-2025-02-10-at-1.43.50-PM-1.jpeg, WhatsApp-Image-2025-02-10-at-1.43.50-PM-2.jpeg, WhatsApp-Image-2025-02-10-at-1.43.49-PM-1.jpeg, WhatsApp-Image-2025-02-10-at-1.43.50-PM.jpeg, WhatsApp-Image-2025-02-10-at-1.43.50-PM-1-rotated.jpeg, WhatsApp-Image-2025-02-10-at-1.43.50-PM-2-rotated.jpeg | addr: Wuro Ladde,  Gombe LGA (LONG: LAT:); year: 2024; cat: Water Facility | No — images + metadata only |
| 5341 | Garin Dawa, Akko LGA | 4 | IMG_9479-1.jpg, IMG_9488.jpg, IMG_9490-1.jpg, IMG_9494.jpg | addr: Garin Dawa, Akko LGA (LONG: LAT:); year: 2024; cat: Water Facility | No — images + metadata only |
| 5356 | Wuro Arsi, Funakaye LGA | 4 | IMG_9343.jpg, IMG_9349.jpg, IMG_9360-1.jpg, IMG_9334.jpg | addr: Wuro Arsi, Funakaye LGA (LONG: LAT:); year: 2024; cat: Water Facility | No — images + metadata only |
| 5367 | Wuro Na'i, Funakaye LGA | 3 | IMG_9380.jpg, IMG_9374.jpg, IMG_9389-1.jpg | addr: Wuro Na'i, Funakaye LGA (LONG: LAT:); year: 2024; cat: Water Facility | No — images + metadata only |
| 5380 | Gabukka, Gombe LGA | 2 | IMG_9419.jpg, IMG_9422.jpg | addr: Gabukka, Gombe LGA (LONG: LAT:); year: 2024; cat: Water Facility | No — images + metadata only |
| 5389 | Kerenget, Kaltungo LGA | 4 | IMG_9463.jpg, IMG_9468.jpg, IMG_9435.jpg, IMG_9474.jpg | addr: Kerenget, Kaltungo LGA (LONG: LAT:); year: 2024; cat: Water Facility | No — images + metadata only |
| 6028 | Gombe L-PRES Trains 67 Livestock Farmers on Business Models For Pasture Development | 18 | 491680249_972889905006125_4092557401761914345_n-1.jpg, 491702348_972888685006247_9041971669497863384_n-1.jpg, 492030542_972890155006100_4748174230972640373_n-1.jpg, 491832497_972890208339428_3619344843514608227_n-1.jpg, 490762573_972889745006141_5501635652054915027_n-1.jpg, 490891243_972890011672781_5503094791072470194_n-1.jpg, 491595414_972890111672771_1842155500460423682_n.jpg, 490853147_972890061672776_1430005688893869254_n.jpg, 491578679_972889978339451_4504012560185785543_n.jpg, 491797322_972889941672788_8738256698167260248_n.jpg, 491756821_972889851672797_3545691227317800097_n-1.jpg, 491824632_972889788339470_6720370715809418746_n-1.jpg, 490762573_972889745006141_5501635652054915027_n-1-1.jpg, 491755039_972889688339480_4672509285411844582_n-1.jpg, 490914703_972888988339550_111773984323232368_n-1.jpg, 490776215_972888828339566_7681769574425302164_n-1.jpg, 490935213_972888761672906_8011938799039571149_n.jpg, 491812501_972888718339577_8369049323128701968_n-1.jpg | addr: Gombe; year: 2025; cat: Trainings | **Yes** — news post #5907 (3,188 chars) |
| 6057 | Waste to Wealth: Gombe L-PRES Trains Butchers on Abattoir Waste Management | 18 | 491948000_973806171581165_2955175928382735288_n.jpg, 492095591_973806361581146_3414736148552848905_n-3.jpg, 491831927_973806111581171_1845448939028653908_n.jpg, 491835026_973805868247862_3228324025260379937_n.jpg, 491369027_973805781581204_8160597014910308707_n-1.jpg, 491381102_973805711581211_4017402542821216497_n.jpg, 490975524_973804278248021_1652050173199663406_n-1.jpg, 491864733_973804231581359_8843973773138793623_n-2.jpg, 491939689_973804184914697_1144178899061546679_n.jpg, 491337374_973804101581372_7133629857096722874_n-1.jpg, 492188457_973803974914718_7808947815272310593_n-1.jpg, 491042319_973804038248045_2648159751217411771_n-1.jpg, 491736516_973803921581390_2135688818514681750_n-1.jpg, 491505838_973803864914729_8067699006513273120_n.jpg, 491935546_973803801581402_5576936770084475138_n.jpg, 491489865_973803748248074_8951727083575908420_n.jpg, 491977355_973803704914745_3760366854619672000_n-2.jpg, 491735583_973803501581432_6727908356551839181_n.jpg | addr: Gombe; year: 2025; cat: Trainings | **Yes** — news post #5963 (3,690 chars) |
| 6085 | Gombe L-PRES Trains 67 Livestock Farmers on Adoption of Modern Technology to Boost Productivity | 20 | 492330503_975023434792772_4676785086770539387_n-1.jpg, 491938183_975023391459443_7710162950337156287_n-1.jpg, 491735447_975023331459449_6569905182431132620_n-2.jpg, 491705733_975023278126121_6374439867535866907_n.jpg, 491383609_975023238126125_2461662998453116046_n-1.jpg, 491745919_975023101459472_3965750496097082013_n-1.jpg, 491665963_975022891459493_7335611445949227764_n-1.jpg, 491682086_975022808126168_8776769944187410636_n-1.jpg, 491464786_975022748126174_8954842793041921360_n-1.jpg, 491973597_975022854792830_1484998555290200028_n.jpg, 492396587_975021941459588_902877831615982793_n.jpg, 491935508_975022711459511_288922739212646180_n.jpg, 492364169_975023058126143_256501400457020754_n-2.jpg, 492715796_975023014792814_1675820895498836314_n-1.jpg, 491708648_975022978126151_5941539279488398124_n-1.jpg, 491467290_975022934792822_2912634526987665441_n-1.jpg, 491665963_975022891459493_7335611445949227764_n-2.jpg, 491839994_975022551459527_402773813691262835_n-1.jpg, 491935508_975022711459511_288922739212646180_n-1.jpg, 492163545_975022451459537_7196649230641269596_n.jpg | addr: Gombe; year: 2025; cat: Trainings | **Yes** — news post #5992 (3,444 chars) |
| 6409 | Bajoga, Funakaye LGA | 3 | IMG_7344.jpg, IMG_7343-1.jpg, IMG_7342-1.jpg | addr: Bajoga, Funakaye LGA (LONG: LAT:); year: 2024; cat: Water Facility | No — images + metadata only |
| 6428 | Barmus, Kaltungo LGA | 1 | IMG-20250603-WA0008.jpg | addr: Barmus, Kaltungo LGA (LONG: LAT:); year: 2024; cat: Water Facility | No — images + metadata only |
| 6434 | Damagaram, Kwami LGA | 4 | IMG_7587.jpg, IMG_7589.jpg, IMG_7585.jpg, IMG_7602.jpg | addr: Damagaram, Kwami LGA (LONG: LAT:); year: 2024; cat: Water Facility | No — images + metadata only |
| 6444 | Dogon Layi, Kwami LGA | 4 | IMG_7593.jpg, IMG_7592.jpg, IMG_7590.jpg, IMG_7597.jpg | addr: Dogon Layi, Kwami LGA (LONG: LAT:); year: 2024; cat: Water Facility | No — images + metadata only |
| 6460 | Manawashin Bege, Funakaye LGA | 4 | Screenshot-from-2025-06-19-15-32-43.png, Screenshot-from-2025-06-19-15-34-26.png, Screenshot-from-2025-06-19-15-34-53.png, Screenshot-from-2025-06-19-15-33-16.png | addr: Manawashin Bege, Funakaye LGA (LONG:     LAT:); year: 2024; cat: Water Facility | No — images + metadata only |
| 6479 | Pandaringa, Billiri LGA | 3 | IMG-20250603-WA0065.jpg, IMG-20250603-WA0067-1.jpg, IMG-20250603-WA0068.jpg | addr: Pandaringa, Billiri LGA (LONG:     LAT:); year: 2024; cat: Water Facility | No — images + metadata only |
| 6489 | Sihiddo, Billiri LGA | 4 | IMG-20250603-WA0019.jpg, IMG-20250603-WA0021.jpg, IMG-20250603-WA0029.jpg, IMG-20250603-WA0038.jpg | addr: Sihiddo, Billiri LGA (LONG:     LAT:); year: 2024; cat: Water Facility | No — images + metadata only |
| 6501 | Ture-Kwe, Kaltungo LGA | 4 | IMG-20250603-WA0007.jpg, IMG-20250603-WA0026.jpg, IMG-20250603-WA0056.jpg, IMG-20250603-WA0061.jpg | addr: Ture-Kwe, Kaltungo LGA (LONG:     LAT:); year: 2024; cat: Water Facility | No — images + metadata only |
| 6518 | Wuro Gyebe, Kwami LGA | 4 | IMG_7611.jpg, IMG_7609.jpg, IMG_7614.jpg, IMG_7621.jpg | addr: Wuro Gyebe, Kwami LGA (LONG: LAT); year: 2024; cat: Water Facility | No — images + metadata only |
| 6530 | Wuro Kufa, Yamaltu Deba LGA | 4 | IMG-20250603-WA0016.jpg, IMG-20250603-WA0037.jpg, IMG-20250603-WA0004-1.jpg, IMG-20250603-WA0034.jpg | addr: Wuro Kufa, Yamaltu Deba LGA (LONG: LAT); year: 2024; cat: Water Facility | No — images + metadata only |
| 6540 | Wuro Petila, Funakaye LGA | 4 | Screenshot-from-2025-06-19-16-34-02.png, Screenshot-from-2025-06-19-16-33-45.png, Screenshot-from-2025-06-19-16-34-30.png, IMG_7584-1.jpg | addr: Wuro Petila, Funakaye LGA (LONG: LAT); year: 2024; cat: Water Facility | No — images + metadata only |
| 6551 | Wuro Raja, Funakaye LGA | 4 | Screenshot-from-2025-06-20-13-30-37.png, Screenshot-from-2025-06-20-13-31-04.png, Screenshot-from-2025-06-20-13-31-22.png, Screenshot-from-2025-06-20-13-31-42.png | addr: Wuro Raja, Funakaye LGA (LONG:  LAT); year: 2024; cat: Water Facility | No — images + metadata only |
| 6560 | Yelwan Kunji, Yamaltu Daba LGA | 4 | IMG-20250603-WA0043.jpg, IMG-20250603-WA0044.jpg, IMG-20250603-WA0045-1.jpg, IMG_7344.jpg | addr: Yelwan Kunji, Yamaltu Daba LGA (LONG: LAT); year: 2024; cat: Water Facility | No — images + metadata only |
| 6570 | Youlde Lamu, Y/Deba LGA | 4 | IMG-20250603-WA0025.jpg, IMG-20250603-WA0033.jpg, IMG-20250603-WA0035.jpg, IMG-20250603-WA0024-1.jpg | addr: Youlde-LAmu, Yamaltu Deba (LONG:      LAT:); year: 2024; cat: Water Facility | No — images + metadata only |
### D.3 The 12 title-only projects

These have a body consisting of `<h4>` + the project title and images — no prose at all.

| ID | Chars | Title |
|---|---|---|
| #515 | 106 | Rehabilitation & Upgrade of Gombe Vet Clinic to Veterinary Hospital |
| #1307 | 83 | Kumo Slaughter Slab |
| #3349 | 91 | Livestock Market Bajoga |
| #4022 | 73 | Dukku Veterinary Clinic |
| #5305 | 82 | Kaltungo Veterinary Clinic |
| #5502 | 73 | Gombe Govt Trains 684 PWDs, Women, Retirees, and Youth on Goat Production |
| #5535 | 71 | Gombe L-PRES Organizes Two Day Workshop For Livestock Extension Workers |
| #5565 | 81 | L-PRES: Gombe State Govt Trains 45 One-Health Officers to Enhance Health… |
| #5588 | 101 | Five day training on Communication and Stakeholder Engagement |
| #5646 | 92 | Training on Usage of Farm Residue as Livestock feed… |
| #5676 | 107 | Training of Community Livestock Service Providers on Good Husbandry Practices |
| #6592 | 42 | Gombe Veterinary Diagnostic Laboratory |

### D.4 Recommendation

**RECOMMENDATION:**

1. **Import all 58 projects.** A project with images, a category and a location is a valid portfolio
   entry; `resources/js/pages/projects/show.tsx` renders the description block conditionally
   (`{project.description && …}`), so an empty description degrades cleanly to a gallery.
2. **Import every image as a gallery image**, never as a description — the source has no captions, so
   nothing is lost by not fabricating one.
3. For the six duplicate pairs, reuse the news body as the description and record the provenance
   (see A.3).
4. For the other 47, **leave `description` null**. Do **not** synthesise text from the title, the
   category or the address. This is the single largest content gap in the migration and the client
   should be told: 47 project pages will be photograph + location + category only.
5. Populate `project_date` / `project_address` from the `towngov_meta_projects` values — they are
   real data (a year and a location string) and give the sparse pages something factual to show.
6. A handful of addresses contain empty coordinate placeholders (`(LONG: LAT:)`). Strip those rather
   than display them.

**Decision needed:** accept 47 image-only project pages, or commission descriptions from the client
before import.

---

## E. Static pages

### E.1 Facts — the five genuine pages

| WP ID | Title | Chars | Existing Laravel destination | Copy status |
|---|---|---|---|---|
| #49 | About | 1,354 | `resources/js/pages/about.tsx` (`aboutCopy`) | **Already migrated** — two intro paragraphs, Coverage/Scope, Target Population, four value chains |
| #65 | Contact | 224 | `resources/js/pages/contact.tsx` | **Already migrated** — reads address/email/phone from `site_settings` |
| #6778 | Our Mission | 295 | `resources/js/pages/mission.tsx` → `config/site.ts` | **Conflict** — different text |
| #6821 | Our Vision | 228 | `resources/js/pages/vision.tsx` → `config/site.ts` | **Identical** |
| #6828 | CORE VALUES | 867 | `resources/js/pages/core-values.tsx` | **Partial** — 5 values vs 10 with definitions |

Detail on the two that do not match:

- **Mission.** WordPress: *"To transform the livestock sector in Gombe State by providing an enabling
  environment for quality service delivery, value addition, and innovative solutions — ensuring
  improved livelihoods, economic diversification, and long-term sustainability for all stakeholders
  in the livestock value chain."* Current `config/site.ts`: *"A Livestock Industry where value
  addition and quality services enable sustainable, diversified and productive livestock economy in
  Nigeria."* These are different statements with different scope (Gombe State vs Nigeria). Both are
  plausible official copy; the export cannot tell me which is current.
- **Core Values.** WordPress lists **10** values, each with a one-line definition (Professionalism,
  Teamwork, Innovation, Quality Service, Productivity, Resilience, Value Addition, Self-Sufficiency,
  Wealth Creation, Inclusiveness). The site currently shows **5** (Professionalism, Teamwork,
  Innovation, Value Addition, Wealth Creation), each with a bespoke blurb. The 5 are a subset of the
  10 by name, but the blurbs are not the WordPress definitions.

`site_settings` currently holds exactly five keys: `contact_address`, `contact_email`,
`contact_phone`, `social_youtube`, `logo_path`. Mission/Vision/Values are **code**, not settings —
and the Contact values already match WordPress character-for-character.

### E.2 Recommendation

**RECOMMENDATION — no new content model for any of the five pages.**

| Page | Action |
|---|---|
| About | Do nothing. The copy is already in `about.tsx` and is a cleaned version of #49. Do **not** import it a second time. |
| Contact | Do nothing. Details already in `site_settings` and identical to WordPress. |
| Vision | Do nothing. Identical text already in `config/site.ts`. |
| Mission | **Choose one text.** If WordPress is authoritative, change one string in `config/site.ts` — a one-line manual edit, not an import. |
| Core Values | **Decide 5 or 10.** If 10, extend `VALUE_ITEMS` in `core-values.tsx` with the WordPress names and definitions — a manual component edit. |

**If the client wants to edit About / Mission / Vision / Values without a deploy**, the cheapest
route is three more `site_settings` keys (`mission_text`, `vision_text`, `core_values_json`) read by
the existing `SettingsController` — not a new `pages` table. A `pages` model with a body column
would duplicate what React already renders and would need a new admin CRUD, a route resolver and
SEO handling for four short strings.

**Decision needed:** which Mission text is current, and whether Core Values should show 5 or 10.

Note: Mission/Vision/Core Values also exist as **posts** #6786 and #6791 with byte-identical bodies
to the pages. Do not import those as news.

---

## F. Cluster reconciliation

### F.1 Facts — database state vs source

| | Source (WordPress) | Laravel (live SQLite) |
|---|---|---|
| Clusters | **9** | **2** (`beef` id 1, `dairy` id 2) |
| Beneficiary rows | **94** | **13** |
| Beneficiaries total | **16,015** | **2,756** |

Per cluster:

| Source cluster | Laravel cluster | Source rows / total | Laravel rows / total | Status |
|---|---|---|---|---|
| DAIRY — MAIN CLUSTER | `dairy` | 11 / 818 | 2 / 493 | **Partial — 9 rows missing** |
| BEEF — MAIN CLUSTERS | `beef` | 11 / 2,263 | 11 / 2,263 | **Complete — exact** |
| SHEEP AND GOAT — MAIN CLUSTERS | *(absent)* | 11 / 6,654 | 0 / 0 | Missing |
| BROILER — MAIN CLUSTERS | *(absent)* | 11 / 5,221 | 0 / 0 | Missing |
| EGGS — MAIN CLUSTERS | *(absent)* | 11 / 596 | 0 / 0 | Missing |
| LEATHER — MAIN CLUSTERS | *(absent)* | 10 / 49 | 0 / 0 | Missing |
| FEED — MAIN CLUSTERS | *(absent)* | 9 / 184 | 0 / 0 | Missing |
| FODDER — MAIN CLUSTERS | *(absent)* | 9 / 40 | 0 / 0 | Missing |
| PIG — MAIN CLUSTERS | *(absent)* | 11 / 190 | 0 / 0 | Missing |

**Row tally: 13 exact matches, 81 inserts required, 0 value conflicts.** Every beneficiary row
already in Laravel matches its source row exactly — there is no corrupt existing data, only missing
data.

### F.2 The Dairy and Shomgom/Shongom findings

1. **Dairy is 60% empty.** It holds only Balanga/Bambam = 402 and Kushi = 91 (493 total) against 11
   source rows totalling **818**. The 9 missing rows account for **325 beneficiaries** and are
   listed individually in F.3.
2. **Shomgom vs Shongom.** The WordPress export spells the LGA **Shomgom** in all nine cluster
   tables. `database/seeders/BeefClusterSeeder.php` line 34 carries the comment *"Original-site
   spelling 'Shomgom' is preserved deliberately"* — so **Shomgom is the project's own documented
   convention**. The Dairy row in the live database uses **"Shongom"**, which therefore deviates from
   both the source and the app's stated policy. The database is internally inconsistent: `beef` says
   Shomgom, `dairy` says Shongom.
3. **The Dairy cluster's description is literally `LOL`** — a placeholder left in the live database.
   Its `image_path` is `uploads/clusters/CjX7OOjUP2N6IG4U7g31cYolq3iH8XjiEzboS5Ag.jpg`, a random
   upload name, while Beef has a real description and `uploads/clusters/beef-country-profile.jpg`.
4. **All nine cluster chart images already exist** in `public/images/lpres/clusters/` as
   `beef.png`, `dairy.png`, `sheep-and-goat.png`, `broiler.png`, `eggs.png`, `leather.png`,
   `feed.png`, `fodder.png`, `pig.png` — matching every source slug. Creating the 7 missing clusters
   needs no new media.

### F.3 Source data defects (FACT, do not silently correct)

- **EGGS contains a duplicated row.** Rows 7 and 11 are both `Kwami / Malam Sidi / 30`. The table has
  11 rows but only **10 distinct LGA+Ward pairs**, and the stated total of 596 — which the sum
  reproduces — therefore **double-counts 30 beneficiaries**. Every other cluster starts with an Akko
  row; EGGS starts with Akko/Garko and then repeats Kwami at the end, which suggests the last row is
  a stray duplicate rather than a second ward.
- **`Gombe / Bajoga`** appears in SHEEP AND GOAT (187) and EGGS (79). Bajoga sits in Funakaye
  elsewhere in the same tables (Funakaye/Bajoga West, Funakaye/Bajoga East). This looks like a
  mis-keyed LGA, but the export is the only evidence available — do not "fix" it without the client.
- **`Bajoga  West`** contains a double space in SHEEP AND GOAT, BROILER, EGGS, FEED and FODDER.
  Laravel's Beef row stores the single-space form.
- **LEATHER has no Akko row** (10 rows); **FEED and FODDER have 9 rows each** (no Gombe row). These
  may be genuinely zero, or cells omitted from the source table; the source does not say.

### F.4 Row-by-row comparison

`Existing Laravel value` is the value currently in `cluster_beneficiaries`, matched on
LGA + Ward with whitespace collapsed and Shomgom/Shongom unified.
### DAIRY - MAIN CLUSTER → `dairy`

Source: **11 rows, 818 beneficiaries**. Laravel: cluster present (`dairy`, id 2), **2 rows, 493 beneficiaries**. ⚠️ Partial — 9 source rows absent.

| # | LGA | Ward | Beneficiaries | Existing Laravel value | Required action |
|---|---|---|---|---|---|
| 1 | Akko | Akko | 35 | — (absent) | Insert row |
| 2 | Balanga | Bambam | 402 | 402 | Exists, value matches — no action |
| 3 | Billiri | Billiri South | 45 | — (absent) | Insert row |
| 4 | Dukku | Gombe Abba | 74 | — (absent) | Insert row |
| 5 | Funakaye | Bodor/Tilde | 38 | — (absent) | Insert row |
| 6 | Gombe | Shamaki | 24 | — (absent) | Insert row |
| 7 | Kaltungo | Awak | 15 | — (absent) | Insert row |
| 8 | Kwami | Malam Sidi | 78 | — (absent) | Insert row |
| 9 | Nafada | Barwo/Nasarawo | 12 | — (absent) | Insert row |
| 10 | Shomgom | Kushi | 91 | 91 | Exists, value matches — no action; LGA spelling differs ("Shongom" in Laravel vs "Shomgom" in source) |
| 11 | Yamaltu Deba | Kuri/Lano/Lambam | 4 | — (absent) | Insert row |

**Cluster total: 818** (source states 818).

### BEEF - MAIN CLUSTERS → `beef`

Source: **11 rows, 2263 beneficiaries**. Laravel: cluster present (`beef`, id 1), **11 rows, 2263 beneficiaries**. ✅ Complete.

| # | LGA | Ward | Beneficiaries | Existing Laravel value | Required action |
|---|---|---|---|---|---|
| 1 | Akko | Akko | 69 | 69 | Exists, value matches — no action |
| 2 | Balanga | Talasse/Dong/Reme | 134 | 134 | Exists, value matches — no action |
| 3 | Billiri | Billiri South | 372 | 372 | Exists, value matches — no action |
| 4 | Dukku | Gombe Abba | 227 | 227 | Exists, value matches — no action |
| 5 | Funakaye *(note: double space in source ward)* | Bajoga  West | 62 | 62 | Exists, value matches — no action; ward whitespace differs |
| 6 | Gombe | Shamaki | 426 | 426 | Exists, value matches — no action |
| 7 | Kaltungo | Kaltungo West | 287 | 287 | Exists, value matches — no action |
| 8 | Kwami | Malam Sidi | 148 | 148 | Exists, value matches — no action |
| 9 | Nafada | Barwo/Nasarawo | 281 | 281 | Exists, value matches — no action |
| 10 | Shomgom | Lapan | 128 | 128 | Exists, value matches — no action |
| 11 | Yamaltu Deba | Deba | 129 | 129 | Exists, value matches — no action |

**Cluster total: 2263** (source states 2263).

### SHEEP AND GOAT - MAIN CLUSTERS → `sheep-and-goat`

Source: **11 rows, 6654 beneficiaries**. Laravel: **cluster does not exist** (0 rows).

| # | LGA | Ward | Beneficiaries | Existing Laravel value | Required action |
|---|---|---|---|---|---|
| 1 | Akko | Garko | 361 | — (absent) | Insert with new cluster |
| 2 | Balanga | Talasse/Dong/Reme | 622 | — (absent) | Insert with new cluster |
| 3 | Billiri | Billiri South | 489 | — (absent) | Insert with new cluster |
| 4 | Dukku | Gombe Abba | 530 | — (absent) | Insert with new cluster |
| 5 | Funakaye *(note: double space in source ward)* | Bajoga  West | 1177 | — (absent) | Insert with new cluster |
| 6 | Gombe | Bajoga | 187 | — (absent) | Insert with new cluster |
| 7 | Kaltungo | Kaltungo West | 664 | — (absent) | Insert with new cluster |
| 8 | Kwami | Bojude | 377 | — (absent) | Insert with new cluster |
| 9 | Nafada | Barwo/Nasarawo | 1174 | — (absent) | Insert with new cluster |
| 10 | Shomgom | Filiya | 218 | — (absent) | Insert with new cluster |
| 11 | Yamaltu Deba | Deba | 855 | — (absent) | Insert with new cluster |

**Cluster total: 6654** (source states 6654).

### BROILER - MAIN CLUSTERS → `broiler`

Source: **11 rows, 5221 beneficiaries**. Laravel: **cluster does not exist** (0 rows).

| # | LGA | Ward | Beneficiaries | Existing Laravel value | Required action |
|---|---|---|---|---|---|
| 1 | Akko | Garko | 1897 | — (absent) | Insert with new cluster |
| 2 | Balanga | Talasse/Dong/Reme | 304 | — (absent) | Insert with new cluster |
| 3 | Billiri | Billiri South | 384 | — (absent) | Insert with new cluster |
| 4 | Dukku | Waziri South/Central | 376 | — (absent) | Insert with new cluster |
| 5 | Funakaye *(note: double space in source ward)* | Bajoga  West | 134 | — (absent) | Insert with new cluster |
| 6 | Gombe | Shamaki | 607 | — (absent) | Insert with new cluster |
| 7 | Kaltungo | Kaltungo West | 394 | — (absent) | Insert with new cluster |
| 8 | Kwami | Malam Sidi | 185 | — (absent) | Insert with new cluster |
| 9 | Nafada | Barwo/Nasarawo | 606 | — (absent) | Insert with new cluster |
| 10 | Shomgom | Filiya | 151 | — (absent) | Insert with new cluster |
| 11 | Yamaltu Deba | Deba | 183 | — (absent) | Insert with new cluster |

**Cluster total: 5221** (source states 5221).

### EGGS - MAIN CLUSTERS → `eggs`

Source: **11 rows, 596 beneficiaries**. Laravel: **cluster does not exist** (0 rows).

| # | LGA | Ward | Beneficiaries | Existing Laravel value | Required action |
|---|---|---|---|---|---|
| 1 | Akko | Garko | 40 | — (absent) | Insert with new cluster |
| 2 | Balanga | Talasse/Dong/Reme | 41 | — (absent) | Insert with new cluster |
| 3 | Billiri | Billiri South | 12 | — (absent) | Insert with new cluster |
| 4 | Dukku | Gombe Abba | 181 | — (absent) | Insert with new cluster |
| 5 | Funakaye *(note: double space in source ward)* | Bajoga  West | 17 | — (absent) | Insert with new cluster |
| 6 | Gombe | Bajoga | 79 | — (absent) | Insert with new cluster |
| 7 | Kwami | Malam Sidi | 30 | — (absent) | Insert with new cluster |
| 8 | Nafada | Barwo/Nasarawo | 117 | — (absent) | Insert with new cluster |
| 9 | Shomgom | Kushi | 24 | — (absent) | Insert with new cluster |
| 10 | Kaltungo | Kaltungo East | 25 | — (absent) | Insert with new cluster |
| 11 | Kwami | Malam Sidi | 30 | — (absent) | Insert with new cluster |

**Cluster total: 596** (source states 596).

### LEATHER - MAIN CLUSTERS → `leather`

Source: **10 rows, 49 beneficiaries**. Laravel: **cluster does not exist** (0 rows).

| # | LGA | Ward | Beneficiaries | Existing Laravel value | Required action |
|---|---|---|---|---|---|
| 1 | Balanga | Talasse/Dong/Reme | 2 | — (absent) | Insert with new cluster |
| 2 | Billiri | Bare | 3 | — (absent) | Insert with new cluster |
| 3 | Dukku | Waziri North | 7 | — (absent) | Insert with new cluster |
| 4 | Funakaye | Bajoga West | 1 | — (absent) | Insert with new cluster |
| 5 | Gombe | Shamaki | 15 | — (absent) | Insert with new cluster |
| 6 | Kaltungo | Kaltungo West | 3 | — (absent) | Insert with new cluster |
| 7 | Kwami | Malam Sidi | 1 | — (absent) | Insert with new cluster |
| 8 | Nafada | Barwo/Nasarawo | 5 | — (absent) | Insert with new cluster |
| 9 | Shomgom | Kushi | 5 | — (absent) | Insert with new cluster |
| 10 | Yamaltu Deba | Deba | 7 | — (absent) | Insert with new cluster |

**Cluster total: 49** (source states 49).

### FEED - MAIN CLUSTERS → `feed`

Source: **9 rows, 184 beneficiaries**. Laravel: **cluster does not exist** (0 rows).

| # | LGA | Ward | Beneficiaries | Existing Laravel value | Required action |
|---|---|---|---|---|---|
| 1 | Akko | Akko | 14 | — (absent) | Insert with new cluster |
| 2 | Balanga | Talasse/Dong/Reme | 11 | — (absent) | Insert with new cluster |
| 3 | Billiri | Billiri South | 21 | — (absent) | Insert with new cluster |
| 4 | Dukku | Gombe Abba | 15 | — (absent) | Insert with new cluster |
| 5 | Funakaye *(note: double space in source ward)* | Bajoga  West | 13 | — (absent) | Insert with new cluster |
| 6 | Kaltungo | Kaltungo West | 5 | — (absent) | Insert with new cluster |
| 7 | Kwami | Gadam | 15 | — (absent) | Insert with new cluster |
| 8 | Nafada | Barwo/Nasarawo | 85 | — (absent) | Insert with new cluster |
| 9 | Yamaltu Deba | Kuri/Lano/Lambam | 5 | — (absent) | Insert with new cluster |

**Cluster total: 184** (source states 184).

### FODDER - MAIN CLUSTERS → `fodder`

Source: **9 rows, 40 beneficiaries**. Laravel: **cluster does not exist** (0 rows).

| # | LGA | Ward | Beneficiaries | Existing Laravel value | Required action |
|---|---|---|---|---|---|
| 1 | Akko | Akko | 2 | — (absent) | Insert with new cluster |
| 2 | Balanga | Talasse/Dong/Reme | 4 | — (absent) | Insert with new cluster |
| 3 | Billiri | Billiri South | 2 | — (absent) | Insert with new cluster |
| 4 | Dukku | Gombe Abba | 1 | — (absent) | Insert with new cluster |
| 5 | Funakaye | Bajoga East | 5 | — (absent) | Insert with new cluster |
| 6 | Gombe | Shamaki | 1 | — (absent) | Insert with new cluster |
| 7 | Kwami | Malam Sidi | 3 | — (absent) | Insert with new cluster |
| 8 | Nafada | Barwo/Nasarawo | 20 | — (absent) | Insert with new cluster |
| 9 | Shomgom | Filiya | 2 | — (absent) | Insert with new cluster |

**Cluster total: 40** (source states 40).

### PIG - MAIN CLUSTERS → `pig`

Source: **11 rows, 190 beneficiaries**. Laravel: **cluster does not exist** (0 rows).

| # | LGA | Ward | Beneficiaries | Existing Laravel value | Required action |
|---|---|---|---|---|---|
| 1 | Akko | Kumo West | 37 | — (absent) | Insert with new cluster |
| 2 | Balanga | Bambam | 5 | — (absent) | Insert with new cluster |
| 3 | Billiri | Billiri South | 16 | — (absent) | Insert with new cluster |
| 4 | Dukku | Waziri North | 5 | — (absent) | Insert with new cluster |
| 5 | Funakaye | Bajoga East | 1 | — (absent) | Insert with new cluster |
| 6 | Gombe | Bolari East | 1 | — (absent) | Insert with new cluster |
| 7 | Kaltungo | Kaltungo West | 83 | — (absent) | Insert with new cluster |
| 8 | Kwami | Malam Sidi | 2 | — (absent) | Insert with new cluster |
| 9 | Nafada | Barwo/Nasarawo | 1 | — (absent) | Insert with new cluster |
| 10 | Shomgom | Lapan | 38 | — (absent) | Insert with new cluster |
| 11 | Yamaltu Deba | Deba | 1 | — (absent) | Insert with new cluster |

**Cluster total: 190** (source states 190).

### Rows present in Laravel but not in the WordPress source

- None. Every Laravel beneficiary row has a matching source row.

**Row tally: 13 exact matches, 81 inserts required, 0 value conflicts.**
### F.5 Recommendation

**RECOMMENDATION:**

1. **Insert the 81 missing rows**, including the 9 Dairy rows. Preserve source `sort_index` order.
2. **Do not modify the 13 existing rows** — they already match. The scheduled import should treat
   an identical `(cluster, lga, ward)` + value as "already imported" and skip it, which makes the
   importer idempotent for clusters.
3. **Fix the Dairy LGA spelling to `Shomgom`** as part of the import (one `UPDATE`), to match the
   documented seeder policy and the other eight tables. This is a correction of an inconsistency, not
   an overwrite of client data — but confirm it, since it touches an existing row.
4. **Replace the Dairy description `LOL`** with the WordPress value-chain description for Dairy, and
   leave Beef's real description alone. Do not overwrite Beef.
5. **Create the 7 missing clusters** with their chart PNGs from `public/images/lpres/clusters/`,
   their descriptions from the four value-chain description pages, and their 9–11 rows each.
6. **EGGS:** import the **10 distinct rows (566)** and drop the duplicated Kwami row, **or** import
   all 11 verbatim (596) and let the client delete it in admin. My recommendation is to import all 11
   verbatim for source fidelity and log it loudly — the client recognises their own table, and
   silently dropping a row makes the site total disagree with any figure they have published. **This
   needs your call.**
7. Import `Gombe / Bajoga`, `Bajoga  West` and the missing LEATHER/FEED/FODDER rows **as published**,
   with a note in the import log. Do not normalise names or invent missing rows.
8. Totals must be asserted after import: 9 clusters, 94 rows (or 93 if the duplicate is dropped),
   16,015 beneficiaries (or 15,985).

---

## G. Consolidated decision register

Every item still open after reports 00–06, with a recommended action. **B** = blocking for the
importer; **C** = content decision; **O** = optional.

| ID | Issue | Source fact | Recommendation | Type |
|---|---|---|---|---|
| **D1** | External WordPress ID strategy | No `wp_id` column or mapping table exists anywhere in the schema | Add a `wp_import_map` table keyed by `(post_type, wp_id)` holding `laravel_table`, `laravel_id`, `imported_at`, `source_hash`, `notes`. One table instead of 8 column additions; also the natural home for the provenance notes in A.3 and C.3 | **B** |
| **D2** | 7 duplicate pairs | Not text duplicates: prose only in the posts; project adds category, address, date and extra photos | Merge: news = text; project = gallery + metadata, gallery de-duplicated by MD5; drop page #7338 (A.3) | **C** |
| **D3** | Duplicate images | 143 byte-identical groups / 160 redundant files repo-wide; pairs share up to 11 images | De-duplicate by MD5 within a record and across a merged pair; copy each unique file once to `storage/app/public` | **B** |
| **D4** | Testimonials | 5 records, 4 distinct quotes, all one speaker, 2 already in `spcQuotes` | Add #114 and #116 to `config/site.ts`; drop #3115 (duplicate of #118); #118/#3114 already present. No table | C |
| **D5** | News categories | 3 terms used (21/67/1), 5 unused demo terms, no tags used | One `News` category (`news_categories` + pivot), `wp_term_id 67`; record original term IDs per article; drop tags | **B** |
| **D6** | Project descriptions | 53 of 58 projects have no prose; zero captions, zero alt text | Import null; reuse the 6 news bodies; import images as gallery. 47 pages will be gallery-only | C |
| **D7** | Static pages | All 5 already exist as React components; About/Contact/Vision match | No import, no new model. If editability is wanted, add `site_settings` keys | C |
| **D8** | Mission text conflict | WordPress "transform the livestock sector in Gombe State…" vs code "A Livestock Industry … in Nigeria" | Pick one; change one string in `config/site.ts` if WordPress wins | **C** |
| **D9** | Core Values | WordPress 10 values with definitions; site shows 5 with bespoke blurbs | Decide 5 or 10; if 10, extend `VALUE_ITEMS` in `core-values.tsx` | C |
| **D10** | Dairy cluster incomplete | 2 of 11 rows; 493 of 818 beneficiaries | Insert the 9 missing rows (325 beneficiaries) | **B** |
| **D11** | Shomgom / Shongom | Source says `Shomgom` in all 9 tables; seeder documents "preserved deliberately"; Dairy row says `Shongom` | Normalise Dairy to `Shomgom`; keep `Shomgom` everywhere | C |
| **D12** | Dairy description = `LOL` | Live row has placeholder text and a hash-named image | Replace with the WordPress Dairy description; leave Beef untouched | C |
| **D13** | 7 clusters missing | sheep-and-goat, broiler, eggs, leather, feed, fodder, pig absent | Create all 7; chart PNGs already exist in `public/images/lpres/clusters/` | **B** |
| **D14** | EGGS duplicated row | `Kwami / Malam Sidi / 30` twice; total double-counts 30 | Import 11 verbatim (recommended) or 10 distinct / 566 — your call, log either way | C |
| **D15** | `Gombe / Bajoga`, `Bajoga  West`, short tables | Appear inconsistent with the rest of the source | Import verbatim, log, do not correct | C |
| **D16** | HTML → plain text | Front end renders bodies escaped and splits on blank lines; 90 of 132 news bodies contain **no `<p>` at all**, and 115 begin with an `<h2>` that restates the title | Convert tags to text; strip the leading `<h2>` (it duplicates the title); split into paragraphs on block tags **and** on sentence boundaries where none exist | **B** |
| **D17** | Newsletter / Magazine | 3 PDFs + 9 images never downloaded | Out of scope unless requested; download first if in scope | O |
| **D18** | Drafts | 3 posts, 5 pages, 2 projects | Exclude; import only `publish` | C |
| **D19** | Team biographies | `biography` column was deliberately dropped from `team_members` | Accept the loss (designation + photo + category are enough), or restore the column | C |
| **D20** | Idempotency + admin edits | Requirement: never overwrite rows an admin has edited | Compare `source_hash` stored in `wp_import_map`; skip a record whose Laravel row changed after `imported_at`; `--dry-run` reports instead of writing | **B** |

---

## H. Corrections to reports 00 and 06

Reports 00 and 06 are unchanged. Three findings in them should be read with these amendments:

1. **"41 of 58 published projects have image-only bodies"** is correct for *strictly zero prose*, but
   the actionable number is **53 of 58** — 12 more have only the title in an `<h4>` (see D.1).
2. **Dairy cluster** — report 00 flagged the 2-of-11 shortfall and the Shongom spelling; it did not
   record that the live description is the placeholder string `LOL`, nor that
   `BeefClusterSeeder.php` documents Shomgom as deliberate (F.2).
3. **Mission text** — report 00 listed the pages as needing a decision but did not note that four of
   the five are already implemented in React with matching (or near-matching) copy, so only two
   copy decisions remain (E.1).

---

## I. Verified vs assumed

**Verified by direct inspection:** every count and comparison in §A–§F — text lengths and MD5s
computed from the XML, byte-identical image detection via `md5_file` over the manifest's local paths,
captions and `alt` attributes checked on all 314 images, the five static pages compared line by line
against the React components and `config/site.ts`, the testimonial quotes compared against
`spcQuotes`, the cluster comparison run against `PRAGMA table_info` + live `SELECT`s on
`database/database.sqlite`, and `BeefClusterSeeder.php` read directly.

**Assumed, not verified:** that the local SQLite database is representative of production (it holds
Faker rows elsewhere); that "Shomgom" is the client's preferred spelling (argued from the seeder
comment, not from the client); that the six duplicate projects should survive as separate records
(the recommendation in A.3, not a source fact); and that `Gombe / Bajoga` is a mis-keyed LGA
(inference from the same LGA/ward patterns — the export alone cannot prove it).

**Not determinable from this export:** which Mission text is current; whether the EGGS duplicate is a
typo or an intended second row; whether any real L-PRES event exists outside WordPress.

---

## J. Tooling

New read-only scripts added for this pass (all in `docs/lpres-migration-audit/tools/`, none writes
outside `work/`):

| Script | Purpose |
|---|---|
| `probe-dupes.php` | Seven-pair comparison: text, MD5 image sets, dates, metadata |
| `probe-content.php` | Testimonials + news category distribution and term IDs |
| `probe-projects.php` | Image-only projects: images, captions, alt text, recoverability |
| `gen-project-table.php` | Emits the §D.2 table |
| `gen-cluster-table.php` | Emits the §F.4 row-by-row table from the live database |
| `check-07.php` | Recomputes every aggregate in this report and asserts it appears in the text |

Regenerate with:

```
php docs/lpres-migration-audit/tools/gen-cluster-table.php docs/lpres-migration-audit/work/cluster-table.md
php docs/lpres-migration-audit/tools/gen-project-table.php docs/lpres-migration-audit/work/project-table.md
```
