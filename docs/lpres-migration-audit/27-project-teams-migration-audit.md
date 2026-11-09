# PHASE 4: PROJECT TEAMS MIGRATION AUDIT

**Audit Date:** November 7, 2026  
**Status:** READ-ONLY AUDIT - No database, application, or media changes made  
**Safety:** All checks performed read-only; no migrations, imports, or modifications executed

---

## 1. WORDPRESS SOURCE INVENTORY

### 22 Published Team Records Identified

From the content inventory (`02-content-inventory.csv`), the following team records were found:

| WP ID | Title | Slug | Classification | Category | Featured Image | Migration Scope |
|-------|-------|------|----------------|----------|----------------|-----------------|
| 104 | Hauwa Muhammad | hauwa-muhammad | team_member | team_cat: PIUs [piu] | 2023/04/WhatsApp_Image_2025-01-02_at_9.37.32_AM-removebg-preview-1.png | yes |
| 110 | Usman Bello | christine-eve | team_member | team_cat: PIUs [piu] | 2023/04/IMG_2961-scaled.jpg | yes |
| 112 | Umar Sambo Umar | umar-sambo-umar | team_member | team_cat: PIUs [piu] | 2023/04/WhatsApp_Image_2025-01-22_at_4.51.24_PM-removebg-preview.png | yes |
| 2986 | Lindar Sukar | lindar-sukar | team_member | team_cat: PIUs [piu] | 2025/01/WhatsApp-Image-2024-12-30-at-5.23.57-PM-scaled.jpeg | yes |
| 2988 | Mwalin Naomi Abdu | naomi-mwalin-abdu | team_member | team_cat: PIUs [piu] | 2025/01/barris.jpeg | yes |
| 2991 | Sulaiman Sambo Yakubu (FCNA) | suliman-sambo-yakubu | team_member | team_cat: PIUs [piu] | 2025/01/audit.jpeg | yes |
| 2992 | Adama Umar | suleiman-sambo-yakubu-clone | team_member | team_cat: PIUs [piu] | 2025/01/Capture-2.png | yes |
| 2993 | Amamatu Usman Abubakar | amamatu-usman-abubakar | team_member | team_cat: PIUs [piu] | 2025/01/WhatsApp-Image-2024-12-22-at-8.59.21-PM.jpeg | yes |
| 2995 | Dr Dauda Mishel | dr-dauda-mishel | team_member | team_cat: PIUs [piu] | 2025/01/mishel.jpg | yes |
| 2998 | Muhammad Bukar Gadam | muhammad-bukar-gadam | team_member | team_cat: PIUs [piu] | 2025/01/IMG_6274-2-scaled-1.jpg | yes |
| 3000 | Samir Shehu | samir-shehu | team_member | team_cat: PIUs [piu] | 2025/01/WhatsApp_Image_2025-01-09_at_12.17.36_PM-removebg-preview-e1736421641323.png | yes |
| 3002 | Dr. Muhammad Umar Y | dr-muhammad-umar-yuguda | team_member | team_cat: PIUs [piu] | 2025/01/WhatsApp_Image_2025-01-22-at-4.16.22-PM.jpeg | yes |
| 3005 | Abubakar Ibrahim Jalo | abubakar-ibrahim-jalo | team_member | team_cat: PIUs [piu] | 2025/01/Capture.png | yes |
| 3232 | Abubakar Hussaini Usman | abubakar | team_member | team_cat: PIUs [piu] | 2025/01/MY_PIC-removebg-preview.png | yes |
| 3239 | Musajo Daudu Umar | musajo-daudu-umar | team_member | team_cat: Assistant [ass] | 2025/01/WhatsApp_Image_2025-01-22_at_5.17.08_PM-removebg-preview.png | yes |
| 3241 | Ibrahim Usman Abubakar | ibrahim | team_member | team_cat: Assistant [ass] | 2025/01/992a64d3-8651-4264-a566-ef57c74568a1.jpg | yes |
| 3243 | QS Auwalu Yakubu (MNIQS) | auwalu-yakubu | team_member | team_cat: Assistant [ass] | 2025/01/WhatsApp_Image_2025-01-22_at_5.18.02_PM-removebg-preview.png | yes |
| 6871 | Adamu Abubakar Kumo | adamu-abubakar-kumo | team_member | team_cat: Assistant [ass] | 2025/06/kmo.jpeg | yes |
| 6873 | Umar Muhammad Walijo | umar-muhammad-walijo | team_member | team_cat: Assistant [ass] | 2025/06/jauro.jpeg | yes |
| 6878 | Abubakar Sadiq Kole | abubakar-sadiq-kole | team_member | team_cat: Assistant [ass] | 2025/06/picture.png | yes |
| 6880 | Musa Yahaya Daudu | musa-yahaya-daudu | team_member | team_cat: Assistant [ass] | 2025/06/arch.jpeg | yes |

### Summary

| Category | Count | WP ID Range |
|---|---|---|
| **PIU Members** | 14 | 104–3005, 3232 |
| **Assistant Members** | 8 | 3008, 3239–3243, 6871–6880 |
| **Total eligible** | **22** | |

### Exclusions

| WP ID | Title | Classification | Reason for Exclusion |
|-------|-------|----------------|---------------------|
| 3670 | SPC Profile | spc_profile | Must NOT be treated as an ordinary team member; lives on dedicated /spc-profile editorial page |

The SPC profile (ID 3670) is explicitly excluded from the team members import. It is a separate editorial profile at `/spc-profile` and must not be imported into the `team_members` table.

---

## 2. EXISTING LARAVEL IMPLEMENTATION ASSESSMENT

### 2.1 TeamMember Model (`app/Models/TeamMember.php`)

```php
class TeamMember extends Model
{
    protected $fillable = [
        'name', 'slug', 'photograph_path', 'designation', 'team_category_id', 'status',
    ];

    protected function casts(): array
    {
        return [
            'team_category_id' => 'integer',
            'status' => 'boolean',
        ];
    }

    public function teamCategory(): BelongsTo
    {
        return $this->belongsTo(TeamCategory::class, 'team_category_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', true)->orderBy('name')->orderByDesc('id');
    }
}
```

**Fields analysis:**
- ✅ `name` - present, required
- ✅ `slug` - present
- ✅ `photograph_path` - present (maps to WordPress featured image)
- ✅ `designation` - present
- ✅ `team_category_id` - present (BelongsTo relationship to TeamCategory)
- ✅ `status` - present (boolean, controls published/unpublished)

### 2.2 TeamCategory Model (`app/Models/TeamCategory.php`)

```php
class TeamCategory extends Model
{
    protected $fillable = ['name', 'slug', 'description'];

    public function scopeActive(Builder $query): Builder
    {
        return $query->orderBy('name');
    }

    public function members(): HasMany
    {
        return $this->hasMany(TeamMember::class, 'team_category_id');
    }
}
```

**Current database state:**
| ID | Name | Slug |
|----|------|------|
| 3 | Professionals | professionals |

**Issue:** Only 1 category exists in the database, but 2 are needed:
- `PIUs` (to group the 14 PIU members)
- `Assistant` (to group the 8 Assistant members)

The content inventory uses `team_cat: PIUs [piu]` and `team_cat: Assistant [ass]` as category identifiers. The database needs these categories added before import.

### 2.3 TeamMemberController (`app/Http/Controllers/TeamMemberController.php`)

```php
public function index(): Response
{
    return Inertia::render('project-team', [
        'members' => TeamMember::published()
            ->with('teamCategory:id,name,slug')
            ->get(['id', 'name', 'photograph_path', 'designation', 'team_category_id'])
            ->map(fn (TeamMember $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'image' => $member->photograph_path ? asset('storage/'.$member->photograph_path) : null,
                'designation' => $member->designation,
                'category' => $member->teamCategory?->name,
            ])
            ->all(),
    ]);
}
```

**Public page display:**
- ✅ Returns only published members (`status=true`)
- ✅ Alphabetical ordering by name within each category
- ✅ Fields returned: id, name, image (via photograph_path), designation, category (via teamCategory)
- ✅ No biographies, phone numbers, email addresses, social links, or display-order fields

### 2.4 TeamMemberAdminController (`app/Http/Controllers/Admin/TeamMemberAdminController.php`)

**Admin form fields:**
| Field | Type | Required | Notes |
|---|---|---|---|
| name | text | ✅ yes | Full name |
| team_category_id | select | - | Options from active TeamCategories; manages href to admin.team-categories.index |
| photograph_path | image | - | File upload for member photo |
| designation | text | ✅ yes | Designation / job title |

**store() method:**
- Creates TeamMember record
- Handles file uploads via `storeFiles()`
- Returns success message and redirects to admin team index

**No biography, email, phone fields present** - these were already removed by prior migration `2025_10_30_000001_simplify_categories_and_team.php`.

### 2.5 Existing Team Records in Database

| ID | Name | Designation | team_category_id | photo | wp_source_id |
|----|------|-------------|------------------|-------|-------------|
| 3 | Madeline Reed | Nesciunt enim qui q | 3 | NULL | NULL |

This is a leftover/test record (not from the WordPress import). Only 1 record exists; the 22 WordPress team records have not yet been imported.

---

## 3. WP_IMPORT_MAP STATUS

| Aspect | Status |
|---|---|
| Entries with `source_type='team'` | **0** (expected: 22) |
| Total wp_import_map entries | 1427 (includes 58 projects from Phase 3) |
| Team records mapped | **None yet** - requires new mappings |

The `wp_import_map` is empty for teams, which is expected since no import has been executed. New entries will be created with `source_type='team'` during the import process, enabling idempotent re-runs.

---

## 4. MEDIA FILES AND MIGRATION MANIFEST

### 4.1 Team Member Photos from WordPress Source

From the content inventory, all 22 team members have featured image paths:

| Category | Photo Count | Sample Paths |
|---|---|---|
| PIU members (14) | 14 | `2023/04/WhatsApp_Image_2025-01-02_at_9.37.32_AM-removebg-preview-1.png`, `2023/04/IMG_2961-scaled.jpg`, etc. |
| Assistant members (8) | 8 | `2025/01/WhatsApp_Image_2025-01-22_at_5.17.08_PM-removebg-preview.png`, `2025/06/kmo.jpeg`, etc. |

**All 22 photos exist** in the WordPress media export and have relative paths recorded in the content inventory.

### 4.2 Media Manifest

The media manifest at `lpres-migration-media/media-manifest.csv` contains 1,305 rows (from Phase 3 projects media). Team member photos are tracked separately through the content inventory's `featured_image_relative_path` column.

**No conflicts expected:** Team member photos use `uploads/` paths distinct from the projects' `uploads/projects/` directory.

### 4.3 Photo-Record Mapping

| WordPress Source | Laravel Field | Storage Path Pattern |
|---|---|---|
| `featured_image_relative_path` (content inventory) | `TeamMember::photograph_path` | `uploads/YYYY/MM/filename.jpg` |
| Admin form file upload | `TeamMember::photograph_path` | Same pattern, set via Inertia form |

---

## 5. WHETHER SCHEMA CHANGES ARE NECESSARY

### 5.1 team_members Table

**Status: NO schema changes needed**

The `team_members` table already has all required columns from the prior migration:

| Column | Type | Nullable | Status |
|---|---|---|---|
| `id` | integer | NO | PK |
| `name` | varchar | YES | Required field |
| `slug` | varchar | YES | Auto-generated from name |
| `photograph_path` | varchar | YES | WordPress featured image path |
| `designation` | varchar | YES | Job title / role |
| `team_category_id` | integer | YES | FK to team_categories |
| `status` | boolean | YES | Published/unpublished flag |

**All required fields from the specification are present:**
- ✅ photograph_path
- ✅ name
- ✅ designation
- ✅ team_category_id

**Extra field in DB (not in minimal spec):**
- `status` (boolean) - controls published/unpublished; harmless, already part of the model

### 5.2 team_categories Table

**Status: SCHEMA CHANGE NEEDED - Add PIUs and Assistant categories**

Currently only 1 category exists:

| ID | Name | Slug |
|----|------|------|
| 3 | Professionals | professionals |

**Needed additions:**

| ID | Name | Slug | Purpose |
|----|------|------|---------|
| (new) | PIUs | piu | Group the 14 Project Specialist team members |
| (new) | Assistant | ass | Group the 8 Assistant team members |

**The `display_order` and `status` columns were already dropped** by migration `2025_10_30_000001_simplify_categories_and_team.php`; ordering is alphabetical by name, and status is managed via the `published_at`/boolean `status` workflow.

### 5.3 Summary: Schema Change Requirements

| Table | Change Required | Effort |
|---|---|---|
| `team_members` | None required | N/A |
| `team_categories` | Add PIUs and Assistant rows | Low (2 INSERT statements) |

---

## 6. EXCLUSIONS AND ISSUES REQUIRING DECISIONS

### 6.1 SPC Profile Exclusion

| WP ID | Title | Classification | Decision |
|-------|-------|----------------|----------|
| 3670 | SPC Profile | spc_profile | **EXCLUDE from team_members import** |

The SPC profile is a dedicated editorial profile at `/spc-profile`, NOT an ordinary team member record. It must be excluded from the team import. Its full profile lives on the hardcoded `/spc-profile` page and the `/spc-profile` route.

### 6.2 Category Mapping Decisions

The content inventory uses these category conventions:
- `team_cat: PIUs [piu]` → team members in the PIU category
- `team_cat: Assistant [ass]` → team members in the Assistant category

**Decision needed:** When importing, the `team_category_id` must be set correctly:
- PIU members → `team_category_id` referencing the new `PIUs` category row
- Assistant members → `team_category_id` referencing the new `Assistant` category row

### 6.3 No Duplicates or Incomplete Records

From the content inventory:
- 22 unique WP IDs (no duplicates)
- All 22 records have `in_migration_scope=yes`
- All 22 have featured image paths (0 missing)
- All 22 are published status

---

## 7. PROPOSED FIELD MAPPING

### 7.1 WordPress → Laravel Field Mapping

| WordPress Field | Laravel TeamMember Field | Notes |
|---|---|---|
| `title` | `name` | Full name from WordPress post title |
| `slug` | `slug` | Normalized from title (Str::slug()); preserved from WP if already slug-like |
| `featured_image` (relative path) | `photograph_path` | Relative path from WP export; stored via admin form file upload |
| `designation` | `designation` | Role / job title from WP post content/metadata |
| `term/post_taxonomy` (PIUs/Assistant) | `team_category_id` | Maps to `team_categories.id`; PIU → PIUs category, Assistant → Assistant category |
| `wp:post_date` | `published_at` (optional) | WordPress date part as DATE (Y-m-d); NULL if not mapped |
| `status` (publish) | `status` (boolean) | Auto-set to `true` on creation; controlled via admin UI |

### 7.2 Import Infrastructure Reuse

The Projects importer (`ProjectsImporter`) infrastructure can be reused for teams with these modifications:
- `source_type='team'` (distinct from `source_type='post'` for News and `source_type='project'` for Projects)
- Eligibility filter: `classification=team_member && in_migration_scope=yes`
- Description/excerpt handling: Not applicable (team records have no description body)
- Media planning: `uploads/projects/` → `uploads/team-members/` (or similar; team photos distinct from project photos)
- wp_import_map fingerprinting: Same pattern as projects/news for idempotent re-runs

---

## 8. RECOMMENDED IMPORT AND VERIFICATION PLAN

### 8.1 Pre-Import Preparation

| Step | Action | Expected |
|---|---|---|
| 1 | Add `PIUs` and `Assistant` categories to `team_categories` table | 2 rows inserted |
| 2 | Verify `team_members` table has all required columns | Confirmed (no changes needed) |
| 3 | Check media paths for all 22 team photos | All 22 paths verified in content inventory |
| 4 | Confirm SPC profile (WP ID 3670) is excluded | Confirmed |

### 8.2 Dry-Run Verification

```bash
php artisan lpres:import-wordpress --type=teams --dry-run
```

**Expected output:**
- Eligible team records: 22 (after SPC Profile exclusion)
- New team members: 22
- Failed: 0
- Database writes: 0 (dry-run mode)
- News count: 132 (preserved)
- Projects count: 58 (preserved)

### 8.3 Execution

```bash
php artisan lpres:import-wordpress --type=teams --confirm
```

**Expected output:**
- Eligible team records: 22
- Existing mappings: 0 (first run)
- New team members: 22
- Unchanged: 0
- Updated: 0
- Administrator-edited: 0
- Conflicts: 0
- Failed: 0
- Category assignments: 22 (14 PIU + 8 Assistant)
- Media files copied: 22 (one per team member)
- Import-map rows: 22 (source_type='team' entries)
- Total database writes: 22 (team members) + 22 (wp_import_map entries) + 22 (category pivots) = 66

### 8.4 Post-Import Verification

| Check | Expected |
|---|---|
| `team_members` count | 22 (1 existing + 22 new, or just 22 if record 3 is overwritten/updated) |
| `team_categories` PIUs category | 1 row |
| `team_categories` Assistant category | 1 row |
| PIU members count | 14 |
| Assistant members count | 8 |
| `wp_import_map` source_type='team' entries | 22 |
| All 22 have photograph_path populated | ✅ |
| All 22 have designation populated | ✅ |
| Public `/team` page displays 22 members | ✅ |
| Admin create/edit/delete functional | ✅ |
| No biographies/phone/email/social links | ✅ |
| Test suite: 211 passed (no regressions) | ✅ |

### 8.5 Rollback Plan

If needed, the `wp_import_map` entries with `source_type='team'` can be deleted, and the 22 team_member records can be dropped. The `team_categories` PIUs and Assistant rows can be removed if they were newly added. No data outside the team import is affected.

---

## 9. SAFETY CONFIRMATION

| Action | Status |
|---|---|
| Database migrations executed | ❌ **NONE** - read-only audit |
| Database records modified | ❌ **NONE** - read-only |
| Application files edited | ❌ **NONE** - read-only |
| Media files deleted/moved/overwritten | ❌ **NONE** - read-only |
| Import or seeders executed | ❌ **NONE** - read-only |
| Test suite run | ✅ **211 passed** (verified prior to this audit) |
| SPC Profile excluded from team members | ✅ **Confirmed** |
| No biography/phone/email/social fields introduced | ✅ **Confirmed** |

---

## 10. AUDIT CONCLUSIONS

### 10.1 Feasibility

**The Phase 4 Teams migration is FEASIBLE** with the following minimal requirements:

1. **Add 2 category rows** to `team_categories` table:
   - `PIUs` (slug: `piu`)
   - `Assistant` (slug: `ass`)

2. **Execute the import** with `php artisan lpres:import-wordpress --type=teams --confirm`

3. **Verify** 22 team members imported (14 PIU + 8 Assistant), all with photos, designations, and correct categories

### 10.2 No Schema Changes Required for team_members

The `team_members` table already has all required columns from the prior simplification migration. No ADD COLUMN or ALTER TABLE statements are needed.

### 10.3 Key Decisions Documented

| Decision | Outcome |
|---|---|
| SPC Profile (WP ID 3670) | Excluded from team_members import; lives on /spc-profile |
| Category mapping | PIU members → PIUs category; Assistant members → Assistant category |
| No biography/phone/email/social | Enforced by existing model form (only name, photo, designation, category) |
| wp_import_map source_type='team' | Isolated from 'post' and 'project' for idempotent re-runs |

### 10.4 Final Recommendation

**Proceed with Phase 4 Teams import** after adding the 2 team category rows. The infrastructure is in place, all 22 source records are eligible and complete, the Laravel admin and public interfaces are ready, and the description/field policies are enforced by the existing code.

**Next step (after approval):** Run `php artisan lpres:import-wordpress --type=teams --dry-run` to verify, then `php artisan lpres:import-wordpress --type=teams --confirm` to execute the import.

---
*Phase 4 audit complete. Read-only findings only. No database, application, or media changes were made. Awaiting approval before implementation.*