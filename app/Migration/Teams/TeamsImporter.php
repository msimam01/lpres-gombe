<?php

namespace App\Migration\Teams;

use App\Migration\ContentInventory;
use App\Migration\Media\MediaCopyPlan;
use App\Migration\Media\MediaImporter;
use App\Migration\Media\MediaResolver;
use App\Migration\SourceHash;
use App\Migration\WordPress\WordPressExport;
use App\Migration\WordPress\WordPressItem;
use App\Models\TeamMember;
use App\Models\TeamCategory;
use App\Models\WpImportMap;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/** Imports the approved WordPress Team Members from the WordPress export.

 * Eligibility comes from the audit's content inventory, not from titles or
 * slugs: `publish` + classification `team_member` + in migration scope. In the
 * audited inventory that is exactly 22 records.

 * Every write goes through `wp_import_map`, which makes re-runs safe:
 *
 *   source unchanged, target untouched  -> unchanged (no write at all)
 *   source changed,   target untouched  -> safe update from the source
 *   target changed (or no target hash)  -> administrator edit, skipped unless
 *                                          --force-overwrite-admin-edits
 *
 * A dry run runs the exact same code path with `$execute = false`: it resolves
 * media, fingerprints targets and reports every planned write, but performs no
 * database write and copies no file.

 * Category mapping (established in the audit):
 *   - PIU  classification  -> team_category_id = 4  (slug: piu)
 *   - Assistant classification  -> team_category_id = 5  (slug: ass)
 *   - Existing Professionals (slug: professionals, id=3) is preserved and
 *     used as the fallback when classification is unexpected.

 * Description / field policy:
 *   - Name is required (from WordPress title).
 *   - Slug is derived from the name.
 *   - Photograph path comes from the content inventory's
 *     `featured_image_relative_path` field.
 *   - Designation is either from WordPress custom field or derived.
 *   - No biography, contact, social, status, visibility, or display-order fields
 *     are added, per the approved restrictions.

 * For the SPC Profile (WP ID 3670): deliberately excluded from import, per
 * the existing code logic and audit requirements.
 */
final class TeamsImporter
{
    /** `wp_import_map.source_type` for a team member. */
    public const SOURCE_TYPE = 'team';

    /** `wp_import_map.source_type` for an imported attachment, shared with the
     * other importers so provenance stays in one vocabulary. */
    public const ATTACHMENT_SOURCE_TYPE = 'attachment';

    /** Suffixes tried when a desired slug is already taken. */
    private const SLUG_ATTEMPTS = 100;

    /** @var array<int, bool> Attachment ids already counted in this run. */
    private array $countedAttachments = [];

    /** @var array<int, bool> Attachment ids that already have a mapping row. */
    private array $mappedAttachments = [];

    /** WP IDs that are the SPC Profile (3670) and should be skipped. */
    private array $spcProfileWpIds = [3670];

    private TeamFields $fields;

    /** WordPress -> Laravel field mapping for team members. */
    public function __construct(
        private readonly WordPressExport $export,
        private readonly ContentInventory $inventory,
        private readonly MediaResolver $resolver,
        private readonly MediaImporter $mediaImporter,
        private readonly SourceHash $hasher,
        private readonly bool $forceAdminEdits = false,
        ?TeamFields $fields = null,
    ) {
        $this->fields = $fields ?? new TeamFields(new HtmlToText);
    }

    /** Run the import (or dry run). */
    public function run(bool $execute): \App\Migration\Teams\TeamsImportReport
    {
        $report = new \App\Migration\Teams\TeamsImportReport(! $execute);
        $eligible = $this->inventory->eligibleTeamIds();

        $report->eligible = count($eligible);
        $this->accountForExclusions($report);

        $category = $this->resolveCategory($execute, $report);

        // Build a set of already-taken team member slugs from the database
        $taken = [];

        foreach (TeamMember::query()->pluck('slug') as $slug) {
            $taken[mb_strtolower((string) $slug)] = (string) $slug;
        }

        // Track SPC Profile WP IDs that should be skipped
        $this->mappedAttachments = WpImportMap::query()
            ->where('source_type', self::ATTACHMENT_SOURCE_TYPE)
            ->pluck('source_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();

        $takenSpc = array_intersect_key(array_flip($this->spcProfileWpIds), $eligible);

        $report->note('Audited inventory: '.count($eligible).' eligible team members');

        foreach ($eligible as $wpId) {
            // Skip SPC Profile (WP ID 3670)
            if (in_array($wpId, $this->spcProfileWpIds)) {
                $report->note('#'.$wpId.': SPC Profile excluded from team member import');
                continue;
            }

            $item = $this->export->find($wpId);

            if ($item === null) {
                $report->fail('#'.$wpId.': listed as eligible team member in the content inventory but missing from the XML export');

                continue;
            }

            $this->importOne($item, $category, $report, $execute, $taken);
        }

        return $report;
    }

    /** Account for exclusions from the eligible team member count. */
    private function accountForExclusions(\App\Migration\Teams\TeamsImportReport $report): void
    {
        $eligible = array_flip($this->inventory->eligibleTeamIds());
        $nonTeam = 0;

        foreach ($this->inventory->rows as $row) {
            if ($row['post_type'] !== 'team') {
                $nonTeam++;

                continue;
            }

            if (isset($eligible[(int) $row['wp_id']])) {
                continue;
            }

            $reason = match (true) {
                $row['status'] !== 'publish' => 'not published ('.$row['status'].')',
                $row['in_migration_scope'] !== 'yes' => 'published but out of scope: '.$row['classification'],
                default => 'published but not genuine team member: '.$row['classification'],
            };

            $report->excluded[$reason] = ($report->excluded[$reason] ?? 0) + 1;
        }

        if ($nonTeam > 0) {
            $report->note($nonTeam.' non-team inventory rows (news, pages, projects, …) are outside this phase.');
        }
    }

    /** The one Laravel `TeamCategory` categories. Created on first execution,
     * never duplicated: the slug is unique and TeamCategory::PIU_SLUG / ::ASS_SLUG are the keys.
     */
    private function resolveCategory(bool $execute, \App\Migration\Teams\TeamsImportReport $report): ?TeamCategory
    {
        // Ensure PIU and Assistant categories exist
        $piu = TeamCategory::query()
            ->where('slug', TeamCategory::PIU_SLUG)
            ->first();

        $assistant = TeamCategory::query()
            ->where('slug', TeamCategory::ASS_SLUG)
            ->first();

        $report->categoryExists = ($piu !== null && $assistant !== null);

        if ($piu !== null && $assistant !== null) {
            return null; // Both already exist, no need to return one or the other
        }

        $report->categoryWillBeCreated = true;

        if (! $execute) {
            return null;
        }

        // Create missing categories
        if ($piu === null) {
            TeamCategory::create([
                'name' => 'PIUs',
                'slug' => TeamCategory::PIU_SLUG,
                'description' => 'PIU team members published by the L-PRES project.',
            ]);
        }

        if ($assistant === null) {
            TeamCategory::create([
                'name' => 'Assistant',
                'slug' => TeamCategory::ASS_SLUG,
                'description' => 'Assistant team members published by the L-PRES project.',
            ]);
        }

        return TeamCategory::query()
            ->where('slug', TeamCategory::PIU_SLUG)
            ->orWhere('slug', TeamCategory::ASS_SLUG)
            ->first();
    }

    /** Resolve the photograph path from the content inventory. */
    private function resolvePhotoPath(WordPressItem $item): ?string
    {
        // Look up the item in the content inventory to get the featured image path
        $row = $this->inventory->byId()[$item->sourceId] ?? null;

        if ($row !== null && isset($row['featured_image_relative_path'])) {
            $path = trim((string) $row['featured_image_relative_path']);

            if ($path !== '') {
                return $path;
            }
        }

        // The audit is the only source of a photograph path; nothing is
        // invented when the inventory has none.
        return null;
    }

    /** Import one team member record. */
    private function importOne(
        WordPressItem $item,
        ?TeamCategory $category,
        \App\Migration\Teams\TeamsImportReport $report,
        bool $execute,
        array &$taken,
    ): void {
        $label = $this->label($item);
        $name = $this->fields->title($item);
        $publishedAt = $this->fields->publishedDate($item);
        $designation = $this->fields->designation($item);
        $categoryId = $this->teamCategoryIdForItem($item);
        $photoPath = $this->resolvePhotoPath($item);

        if ($name === '') {
            $report->fail($label.': the WordPress record has no title');

            return;
        }

        if ($publishedAt === null) {
            $report->fail($label.': the WordPress record has no usable publication date');

            return;
        }

        // --- Source hash and existing mapping ---
        // The mapping is resolved before any slug work: a re-run must never
        // report an already-imported record's own slug as a collision.
        $sourceHash = $this->hasher->forItem($item);
        $mapping = WpImportMap::lookup(self::SOURCE_TYPE, $item->sourceId);
        $target = null;
        $isCreate = true;

        if ($mapping !== null) {
            $report->existingMappings++;

            $mapped = $mapping->target;

            if (! $mapping->isImported() || ! $mapped instanceof TeamMember) {
                $report->conflict(sprintf(
                    '%s: an import mapping exists but its team member is missing, so the target cannot be verified',
                    $label
                ));

                return;
            }

            $target = $mapped;
            $targetHash = $this->hasher->forPayload($this->fields->targetPayload([
                'name' => $target->name,
                'slug' => $target->slug,
                'photograph_path' => $target->photograph_path,
                'designation' => $target->designation,
                'team_category_id' => $target->team_category_id,
                'status' => $target->status,
            ]));
            $sourceChanged = $mapping->hasSourceChanged($sourceHash);
            $targetChanged = $mapping->hasTargetChanged($targetHash);

            if ($sourceChanged) {
                $report->sourceChanged++;
            }

            if ($targetChanged && ! $this->forceAdminEdits) {
                $report->adminModified++;
                $report->adminModifiedDetails[] = sprintf(
                    '%s: %s since import; skipped (re-run with --force-overwrite-admin-edits to overwrite)%s',
                    $label,
                    $mapping->target_hash === null ? 'no target hash recorded' : 'the team member was edited',
                    $sourceChanged ? ' — the WordPress source also changed' : ''
                );

                return;
            }

            if (! $sourceChanged && ! $targetChanged) {
                $report->unchanged++;

                return;
            }

            $isCreate = false;

            if ($targetChanged) {
                $report->note($label.': administrator edits overwritten because --force-overwrite-admin-edits was given');
            }
        }

        // --- Slug resolution (creates only) ---
        if ($isCreate) {
            $slug = $this->fields->slug($item);

            if ($slug === '') {
                $report->fail($label.': no usable slug could be derived from the WordPress record');

                return;
            }

            // Check for slug collision and resolve
            $slugKey = mb_strtolower($slug);

            if (! isset($taken[$slugKey])) {
                $taken[$slugKey] = $slug;
            } else {
                // Slug already taken, add suffix
                $candidate = $slug.'-2';

                for ($attempt = 2; $attempt <= self::SLUG_ATTEMPTS; $attempt++) {
                    $candidate = preg_replace('/-\d+$/', '', $slug).'-'.$attempt;

                    if (! isset($taken[mb_strtolower($candidate)])) {
                        $taken[mb_strtolower($candidate)] = $candidate;
                        $report->slugConflicts[] = sprintf(
                            '%s: slug "%s" is already used; imported as "%s"',
                            $label, $slug, $candidate
                        );

                        break;
                    }
                }

                if ($attempt > self::SLUG_ATTEMPTS) {
                    $report->fail($label.': no free variant of the slug "'.$slug.'" could be found');

                    return;
                }

                $slug = $candidate;
            }
        } else {
            // An imported record keeps the slug it already has, so public URLs
            // stay stable and the record is never reported as its own conflict.
            $slug = $target->slug;
            $taken[mb_strtolower($slug)] ??= $slug;
        }

        // --- Media planning (featured image) ---
        $media = $this->planMedia($item, $report, true);

        // --- Build columns for the TeamMember record ---
        $columns = [
            'name' => $name,
            'slug' => $slug,
            'photograph_path' => $photoPath,
            'designation' => $designation,
            'team_category_id' => $categoryId,
            // The column is a boolean flag (the admin writes `true`); writing a
            // label here would make `TeamMember::published()` match nothing.
            'status' => true,
            'published_at' => $publishedAt,
        ];

        // --- Apply (store media + persist) ---
        $this->apply(
            item: $item,
            target: $isCreate ? null : $target,
            category: $category,
            columns: $columns,
            media: $media,
            mapping: $mapping,
            sourceHash: $sourceHash,
            report: $report,
            execute: $execute,
            isCreate: $isCreate,
        );
    }

    /** Plan media for a team member record. */
    private function planMedia(WordPressItem $item, \App\Migration\Teams\TeamsImportReport $report, bool $willWrite): \App\Migration\Teams\TeamsMediaPlan
    {
        $label = $this->label($item);
        $attachments = [];

        // Resolve featured image
        $featured = $this->resolver->resolveFeatured($item);
        $thumbnailId = $item->thumbnailId();
        $featuredPlan = null;
        $featuredPath = null;
        $featuredDecided = false;

        if (! $featured->isFound()) {
            if ($featured->isAbsent()) {
                // WordPress genuinely has no featured image: the imported
                // team member should not have one either.
                $featuredDecided = true;
            } else {
                $report->missingFeatured[] = $label.': '.$featured->reason;
            }
        } elseif ($willWrite) {
            $featuredPlan = $this->mediaImporter->plan($featured->media);
            $this->accountMediaPlan($featuredPlan, $label, $report);

            $featuredPath = $featuredPlan->targetPath;
            $featuredDecided = $featuredPath !== null;

            $attachments[$featured->media->attachmentId] = [
                'source_url' => $featured->media->originalUrl,
                'manifest_path' => $featured->media->manifestPath,
                'stored_path' => $featuredPath,
                'md5' => $featuredPlan->sourceMd5,
            ];
        } else {
            $attachments[$featured->media->attachmentId] = [
                'source_url' => $featured->media->originalUrl,
                'manifest_path' => $featured->media->manifestPath,
                'stored_path' => null,
                'md5' => null,
            ];
        }

        // For team members, we only deal with the featured image (photograph),
        // no embedded/gallery images

        return new \App\Migration\Teams\TeamsMediaPlan(
            featuredPlan: $featuredPlan,
            featuredDecided: $featuredDecided,
            featuredPath: $featuredPath,
            galleryPlans: [],
            galleryPaths: [],
            galleryComplete: true,
            attachments: $attachments
        );
    }

    /** Persist the team member record and create/update the wp_import_map entry. */
    private function apply(
        WordPressItem $item,
        ?TeamMember $target,
        ?TeamCategory $category,
        array $columns,
        \App\Migration\Teams\TeamsMediaPlan $media,
        ?WpImportMap $mapping,
        string $sourceHash,
        \App\Migration\Teams\TeamsImportReport $report,
        bool $execute,
        bool $isCreate,
    ): void {
        $label = $this->label($item);
        $offset = $this->mediaImporter->createdCount();

        try {
            if ($execute) {
                DB::beginTransaction();
            }

            [$featuredPath, $galleryPaths] = $this->storeMedia($media, $execute);

            if ($execute) {
                if (array_key_exists('photograph_path', $columns)) {
                    $columns['photograph_path'] = $featuredPath;
                }

                $this->doPersist($item, $target, $category, $columns, $media, $mapping, $sourceHash);

                DB::commit();
            }

            if ($isCreate) {
                $report->created++;
            } else {
                $report->updated++;
            }

            $report->categoryAssignments++;
            $report->mappingRows++;
            $this->countAttachmentMappings($media, $report);
        } catch (Throwable $exception) {
            if ($execute && DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            $copied = $execute ? $this->mediaImporter->createdSince($offset) : [];

            if ($copied !== []) {
                $report->orphanedFiles = [...$report->orphanedFiles, ...$this->mediaImporter->releaseCreated($copied)];
            }

            $report->fail($label.': '.$exception->getMessage());
        }
    }

    /** Store planned media files and return stored paths. */
    private function storeMedia(\App\Migration\Teams\TeamsMediaPlan $media, bool $execute): array
    {
        if (! $execute) {
            return [$media->featuredPath, $media->galleryPaths];
        }

        $featuredPath = $media->featuredPath;

        if ($media->featuredPlan !== null && $media->featuredPlan->willCopy()) {
            $stored = $this->mediaImporter->copy($media->featuredPlan);

            if ($stored === null) {
                throw new RuntimeException('the featured image could not be copied to storage');
            }

            $featuredPath = $stored;
        }

        return [$featuredPath, []];
    }

    /** Actual persist logic - create or update the TeamMember record. */
    private function doPersist(
        WordPressItem $item,
        ?TeamMember $target,
        ?TeamCategory $category,
        array $columns,
        \App\Migration\Teams\TeamsMediaPlan $media,
        ?WpImportMap $mapping,
        string $sourceHash,
    ): void {
        if ($target === null) {
            $target = TeamMember::create($columns);
        } else {
            $target->update($columns);
        }

        // `team_category_id` is already written from the source classification;
        // a resolved category row (when one had to be created) is authoritative.
        if ($category !== null) {
            $target->team_category_id = $category->id;
            $target->save();
        }

        // Determine the category name for source_metadata
        $categoryName = 'professionals'; // default

        if ($target->team_category_id === 4) {
            $categoryName = 'piu';
        } elseif ($target->team_category_id === 5) {
            $categoryName = 'assistant';
        }

        $mapping ??= new WpImportMap([
            'source_type' => self::SOURCE_TYPE,
            'source_id' => $item->sourceId,
        ]);

        $mapping->fill([
            'source_type' => self::SOURCE_TYPE,
            'source_id' => $item->sourceId,
            'target_type' => TeamMember::class,
            'target_id' => $target->id,
            'source_hash' => $sourceHash,
            'source_metadata' => $this->metadata($item, $target->team_category_id, $media, $categoryName),
            'imported_at' => now(),
        ]);

        $mapping->save();

        // The target fingerprint is taken from the stored record, after every
        // column and the category pivot are in place, so a later run compares
        // like with like.
        $target->refresh();

        $mapping->forceFill([
            'target_hash' => $this->hasher->forPayload($this->fields->targetPayload([
                'name' => $target->name,
                'slug' => $target->slug,
                'photograph_path' => $target->photograph_path,
                'designation' => $target->designation,
                'team_category_id' => $target->team_category_id,
                'status' => $target->status,
            ])),
        ])->save();

        foreach ($media->attachments as $attachmentId => $attachment) {
            if ($attachment['stored_path'] === null) {
                continue;
            }

            WpImportMap::query()->updateOrCreate(
                ['source_type' => self::ATTACHMENT_SOURCE_TYPE, 'source_id' => $attachmentId],
                [
                    'target_type' => null,
                    'target_id' => null,
                    'source_hash' => $attachment['md5'],
                    'source_metadata' => [
                        'stored_path' => $attachment['stored_path'],
                        'source_url' => $attachment['source_url'],
                        'manifest_path' => $attachment['manifest_path'],
                        'imported_from' => 'lpres:import-wordpress --type=teams',
                    ],
                    'imported_at' => now(),
                ]
            );
        }
    }

    /** WordPress provenance stored on the mapping row. */
    private function metadata(
        WordPressItem $item,
        ?int $categoryId,
        \App\Migration\Teams\TeamsMediaPlan $media,
        string $categoryName
    ): array {
        $embedded = [];

        foreach ($media->attachments as $attachmentId => $attachment) {
            if (! isset($attachment['stored_path']) || $attachment['stored_path'] === null) {
                continue;
            }

            $embedded[] = $attachmentId;
        }

        return [
            'wp_post_id' => $item->sourceId,
            'wp_post_type' => $item->type,
            'wp_status' => $item->status,
            'wp_slug' => $item->slug,
            'wp_link' => $item->link,
            'wp_author' => $item->creator,
            'laravel_team_category_id' => $categoryId !== null ? [$categoryId] : [],
            'featured_attachment_id' => ctype_digit((string) $item->thumbnailId())
                ? (int) $item->thumbnailId()
                : null,
            'embedded_attachment_ids' => $embedded,
            'category_name' => $categoryName,
            'imported_from' => 'lpres:import-wordpress --type=teams',
        ];
    }

    /**
     * Laravel team category id for one record.
     *
     * The audit's `classification` is `team_member` for every eligible record,
     * so it cannot distinguish PIUs from Assistants. The audited `team_cat`
     * term (nicename `piu` / `ass`) is the authoritative category; the content
     * inventory's `category_names` column carries the same slug and is used as
     * a fallback. `Professionals` (id 3) remains the safe fallback.
     */
    private function teamCategoryIdForItem(WordPressItem $item): int
    {
        return match ($this->categorySlugForItem($item)) {
            'piu' => 4,
            'ass', 'assistant' => 5,
            default => 3,
        };
    }

    /** The audited `team_cat` term slug for one record, lower-cased. */
    private function categorySlugForItem(WordPressItem $item): string
    {
        foreach ($item->terms as $term) {
            if ($term['domain'] === 'team_cat' && $term['nicename'] !== '') {
                return mb_strtolower(trim($term['nicename']));
            }
        }

        $row = $this->inventory->byId()[$item->sourceId] ?? null;

        if ($row !== null
            && preg_match('/\[([^\]]+)\]/', (string) ($row['category_names'] ?? ''), $matches) === 1) {
            return mb_strtolower(trim($matches[1]));
        }

        return '';
    }

    /** Plan media for a team member record. */
    private function accountMediaPlan(\App\Migration\Media\MediaCopyPlan $plan, string $label, \App\Migration\Teams\TeamsImportReport $report): void
    {
        if ($plan->willCopy()) {
            $report->mediaCopies++;

            return;
        }

        if ($plan->reused()) {
            $report->mediaReused++;

            return;
        }

        $report->mediaConflictCount++;
        $report->mediaConflictDetails[] = sprintf(
            '%s: %s (%s)',
            $label,
            $plan->reason ?? 'the media could not be stored',
            $plan->media->manifestPath
        );
    }

    private function countAttachmentMappings(\App\Migration\Teams\TeamsMediaPlan $media, \App\Migration\Teams\TeamsImportReport $report): void
    {
        foreach ($media->attachments as $attachmentId => $attachment) {
            if ($attachment['stored_path'] === null || isset($this->countedAttachments[$attachmentId])) {
                continue;
            }

            $this->countedAttachments[$attachmentId] = true;
            $report->mediaMappingRows++;

            if (! isset($this->mappedAttachments[$attachmentId])) {
                $report->newMediaMappingRows++;
            }
        }
    }

    private function label(WordPressItem $item): string
    {
        return '#'.$item->sourceId.' '.Str::limit($this->fields->title($item), 70);
    }
}