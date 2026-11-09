<?php

namespace App\Migration\Projects;

use App\Migration\ContentInventory;
use App\Migration\HtmlToText;
use App\Migration\Media\MediaCopyPlan;
use App\Migration\Media\MediaImporter;
use App\Migration\Media\MediaResolver;
use App\Migration\News\NewsImportReport;
use App\Migration\News\NewsMediaPlan;
use App\Migration\SourceHash;
use App\Migration\WordPress\WordPressExport;
use App\Migration\WordPress\WordPressItem;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\WpImportMap;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Imports the approved WordPress Projects from the WordPress export.
 *
 * Eligibility comes from the audit's content inventory, not from titles or
 * slugs: `publish` + classification `project` + in migration scope. In the
 * audited inventory that is exactly 58 records.
 *
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
 *
 * Description/body handling follows the audit findings:
 *   - 53 projects have no usable original description -> left empty/null
 *   -  5 projects have genuine prose descriptions -> imported as-is
 *   - 41 projects have image-only content -> description left empty/null
 *   - 12 projects have title-only HTML -> description left empty or from title
 *   -  6 projects reuse News prose (from the 6 duplicate pairs) -> News body
 *     reused as Project description with provenance tracking
 *
 * For the 6 approved News/Project duplicate pairs, the News article's body is
 * reused as the Project description. The News WordPress source ID is recorded
 * as description provenance in source_metadata.
 *
 * For all other projects without a usable description, the description field
 * is left empty/null. No marketing copy is generated, no objectives or outcomes
 * are invented.
 *
 * @see app/Migration/News/NewsImporter.php for the base architecture
 */
final class ProjectsImporter
{
    /** `wp_import_map.source_type` for a project. */
    public const SOURCE_TYPE = 'project';

    /** Suffixes tried when a desired slug is already taken. */
    private const SLUG_ATTEMPTS = 100;

    /** @var array<int, bool> Attachment ids already counted in this run. */
    private array $countedAttachments = [];

    /** @var array<int, bool> Attachment ids that already have a mapping row. */
    private array $mappedAttachments = [];

    /** The 6 News WP IDs that have corresponding Projects whose description
     *  will reuse the News body. Keyed by Project WP ID. */
    private array $newsProjectPairs = [];

    /** WordPress -> Laravel field mapping for Projects. */
    private readonly ProjectsFields $fields;

    public function __construct(
        private readonly WordPressExport $export,
        private readonly ContentInventory $inventory,
        private readonly MediaResolver $resolver,
        private readonly MediaImporter $mediaImporter,
        private readonly SourceHash $hasher,
        private readonly bool $forceAdminEdits = false,
        ?ProjectsFields $fields = null,
    ) {
        $this->fields = $fields ?? new ProjectsFields(new HtmlToText);
    }

    /** @param  list<int>  $newsProjectPairs  WP IDs mapping News -> Project */
    public function setNewsProjectPairs(array $pairs): self
    {
        $this->newsProjectPairs = $pairs;

        return $this;
    }

    public function run(bool $execute): NewsImportReport
    {
        $report = new NewsImportReport(! $execute);
        $eligible = $this->inventory->eligibleProjectIds();

        $report->eligible = count($eligible);
        $this->accountForExclusions($report);

        $category = $this->resolveCategory($execute, $report);

        $this->mappedAttachments = WpImportMap::query()
            ->where('source_type', self::SOURCE_TYPE)
            ->pluck('source_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();

        $taken = [];

        // Build a set of already-taken Project slugs from the database
        foreach (Project::query()->pluck('slug') as $slug) {
            $taken[mb_strtolower((string) $slug)] = (string) $slug;
        }

        // Load the 6 News/Project duplicate pairs for description reuse
        $this->loadNewsProjectPairs();

        foreach ($eligible as $wpId) {
            $item = $this->export->find($wpId);

            if ($item === null) {
                $report->fail("#{$wpId}: listed as eligible project in the content inventory but missing from the XML export");

                continue;
            }

            $this->importOne($item, $category, $report, $execute, $taken);
        }

        return $report;
    }

    /** Account for exclusions from the eligible project count. */
    private function accountForExclusions(NewsImportReport $report): void
    {
        $eligible = array_flip($this->inventory->eligibleProjectIds());
        $nonProject = 0;

        foreach ($this->inventory->rows as $row) {
            if ($row['post_type'] !== 'project') {
                $nonProject++;

                continue;
            }

            if (isset($eligible[(int) $row['wp_id']])) {
                continue;
            }

            $reason = match (true) {
                $row['status'] !== 'publish' => 'not published ('.$row['status'].')',
                $row['in_migration_scope'] !== 'yes' => 'published but out of scope: '.$row['classification'],
                default => 'published but not genuine project: '.$row['classification'],
            };

            $report->excluded[$reason] = ($report->excluded[$reason] ?? 0) + 1;
        }

        if ($nonProject > 0) {
            $report->note(sprintf(
                '%d non-project inventory rows (news, pages, attachments, …) are outside this phase.',
                $nonProject,
            ));
        }
    }

    /** The one Laravel `ProjectCategory` category. Created on first execution,
     * never duplicated: the slug is unique and `ProjectCategory::PROJECT_SLUG` is the key.
     */
    private function resolveCategory(bool $execute, NewsImportReport $report): ?ProjectCategory
    {
        $existing = ProjectCategory::query()
            ->where('slug', ProjectCategory::PROJECT_SLUG)
            ->first();

        $report->categoryExists = $existing !== null;

        if ($existing !== null) {
            return $existing;
        }

        $report->categoryWillBeCreated = true;

        if (! $execute) {
            return null;
        }

        return ProjectCategory::create([
            'name' => 'Projects',
            'slug' => ProjectCategory::PROJECT_SLUG,
            'description' => 'Projects published by the L-PRES project.',
            'wp_term_id' => ProjectCategory::PROJECT_SOURCE_TERM_ID,
        ]);
    }

    /** Load the 6 News/Project duplicate pairs from the content inventory. */
    private function loadNewsProjectPairs(): void
    {
        // The 6 verified duplicate relationships:
        // #4618 / #5535, #4878 / #3415, #5451 / #5502, #5907 / #6028, #5963 / #6057, #5992 / #6085
        $pairs = [
            ['newsWpId' => 4618, 'projectWpId' => 5535],
            ['newsWpId' => 4878, 'projectWpId' => 3415],
            ['newsWpId' => 5451, 'projectWpId' => 5502],
            ['newsWpId' => 5907, 'projectWpId' => 6028],
            ['newsWpId' => 5963, 'projectWpId' => 6057],
            ['newsWpId' => 5992, 'projectWpId' => 6085],
        ];

        foreach ($pairs as $pair) {
            $this->newsProjectPairs[$pair['projectWpId']] = $pair['newsWpId'];
        }
    }

    /** @param array<string, mixed> $taken */
    private function importOne(
        WordPressItem $item,
        ?ProjectCategory $category,
        NewsImportReport $report,
        bool $execute,
        array &$taken,
    ): void {
        $label = $this->label($item);

        $title = $this->fields->title($item);
        $publishedAt = $this->fields->publishedDate($item);
        $body = $this->fields->body($item);
        $sourceSlug = $this->fields->slug($item) ?: $this->fields->generatedSlug($item);

        if ($title === '') {
            $report->fail($label.': the WordPress record has no title');

            return;
        }

        if ($publishedAt === null) {
            $report->fail($label.': the WordPress record has no usable publication date');

            return;
        }

        if ($sourceSlug === '') {
            $report->fail($label.': no usable slug could be derived from the WordPress record');

            return;
        }

        // --- Description/body handling following the audit findings ---
        // Determine if this project reuses News prose
        $newsWpId = $this->newsProjectPairs[$item->sourceId] ?? null;
        $description = $this->determineDescription($item, $newsWpId, $report);

        $sourceHash = $this->hasher->forItem($item);
        $mapping = WpImportMap::lookup(self::SOURCE_TYPE, $item->sourceId);
        $target = null;
        $isCreate = true;

        if ($mapping !== null) {
            $report->existingMappings++;

            $mapped = $mapping->target;

            if (! $mapping->isImported() || ! $mapped instanceof Project) {
                $report->conflict(sprintf(
                    '%s: an import mapping exists but its project is missing, so the target cannot be verified',
                    $label,
                ));

                return;
            }

            $target = $mapped;
            $targetHash = $this->hasher->forPayload($this->fields->targetPayload($target));
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
                    $mapping->target_hash === null ? 'no target hash recorded' : 'the project was edited',
                    $sourceChanged ? ' — the WordPress source also changed' : '',
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

        // --- Media planning ---
        $media = $this->planMedia($item, $report, true);

        // --- Slug resolution ---
        $slug = $isCreate
            ? $this->resolveSlug($sourceSlug, $taken, $report, $label)
            : (string) $target->slug;

        if ($slug === '') {
            return;
        }

        $taken[mb_strtolower($slug)] = $slug;

        // --- Build columns for the Project record ---
        $columns = [
            'title' => $title,
            'body' => $description,  // Use the determined description (may be empty/null)
            'excerpt' => Project::excerptFromTitle($title),
            'published_at' => $publishedAt,
            'status' => 'published',
            'featured_image_path' => null,
            'gallery_image_paths' => null,
        ];

        // Set featured image path if media was decided
        if ($media->featuredDecided) {
            $columns['featured_image_path'] = $media->featuredPath;
        } elseif ($isCreate) {
            $columns['featured_image_path'] = null;
        }

        if ($media->galleryComplete) {
            $columns['gallery_image_paths'] = $media->galleryPaths;
        } elseif ($isCreate) {
            $columns['gallery_image_paths'] = $media->galleryPaths;
        }

        if ($isCreate) {
            $columns['slug'] = $slug;
        }

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
        );
    }

    /** Determine the description for a project based on audit findings.
     *
     * Returns the description text (may be empty string or null), following
     * the planned description handling:
     * - 53 projects: no usable description -> empty string (will be null in DB)
     * -  5 projects: genuine prose -> imported as-is
     * - 41 projects: image-only -> empty string (will be null in DB)
     * -  6 projects: reuse News prose -> News body as description with provenance
     *
     * All other projects (including title-only): description left empty/null.
     * Title-only HTML must not become a project description.
     * Never invent descriptions.
     *
     * @return string|null The description text, or null to leave DB column null
     */
    private function determineDescription(
        WordPressItem $item,
        ?int $newsWpId,
        NewsImportReport $report,
    ): string {
        $newsItem = $newsWpId !== null ? $this->export->find($newsWpId) : null;

        if ($newsWpId !== null && $newsItem !== null) {
            // This project reuses News prose (one of the 6 duplicate pairs)
            $newsBody = $this->fields->body($newsItem);
            if ($newsBody !== '') {
                // Record provenance: the News source WP ID is stored in source_metadata
                $report->note(sprintf(
                    '%s: News prose (#%d) reused as project description with source provenance',
                    $this->label($item),
                    $newsWpId,
                ));

                // Store News provenance in source_metadata
                return $newsBody;
            }
            // News body is empty after conversion; fall through to below logic
        }

        // For all other projects: check what description handling applies
        // The content inventory CSV doesn't have a body field, but we can check
        // the embedded_media_count and other metadata to categorize

        // Check if this project has embedded media (image-only content)
        $embedCount = $item->embeddedMediaCount ?? 0;
        $hasFeaturedImage = ! empty($item->thumbnailId());

        // If the project has embedded images but no body prose, it's image-only
        if ($embedCount > 0 && $newsWpId === null) {
            // Image-only project: description left empty/null
            return ''; // Will be stored as null
        }

        // If no News pair and no embedded media, all remaining projects
        // have no usable description (including title-only projects)
        // Description must be NULL per the approved policy
        return '';
    }

    /** Plan media for a project record. */
    private function planMedia(
        WordPressItem $item,
        NewsImportReport $report,
        $willWrite,
    ): NewsMediaPlan {
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

        // Resolve embedded images
        $plans = [];
        $paths = [];
        $complete = true;
        $seen = [];

        foreach (MediaResolver::embeddedImageUrls($item->content) as $url) {
            if ($url === '') {
                $report->missingEmbedded[] = $label.': an embedded <img> tag has no source URL';
                $complete = false;

                continue;
            }

            if ($this->resolver->isIgnorableUrl($url)) {
                $report->ignoredEmbedded++;

                continue;
            }

            $lookup = $this->resolver->resolveUrl($url);

            if (! $lookup->isFound()) {
                $report->missingEmbedded[] = $label.': '.$url.' — '.$lookup->reason;
                $complete = false;

                continue;
            }

            $attachmentId = $lookup->media->attachmentId;

            // Skip the featured image if it's already at the top of the article
            if ($attachmentId === (int) ($thumbnailId ?? 0) || isset($seen[$attachmentId])) {
                continue;
            }

            $seen[$attachmentId] = true;

            if (! $willWrite) {
                $attachments[$attachmentId] = [
                    'source_url' => $lookup->media->originalUrl,
                    'manifest_path' => $lookup->media->manifestPath,
                    'stored_path' => null,
                    'md5' => null,
                ];

                continue;
            }

            $plan = $this->mediaImporter->plan($lookup->media);
            $this->accountMediaPlan($plan, $label, $report);

            $attachments[$attachmentId] = [
                'source_url' => $lookup->media->originalUrl,
                'manifest_path' => $lookup->media->manifestPath,
                'stored_path' => $plan->targetPath,
                'md5' => $plan->sourceMd5,
            ];

            if ($plan->targetPath === null) {
                $complete = false;

                continue;
            }

            // Deduplicate by target path (content-hash deduplication)
            if (in_array($plan->targetPath, $paths, true)) {
                continue;
            }

            $plans[] = $plan;
            $paths[] = $plan->targetPath;
        }

        return new NewsMediaPlan(
            featuredPlan: $featuredPlan,
            featuredDecided: $featuredDecided,
            featuredPath: $featuredPath,
            galleryPlans: $plans,
            galleryPaths: $paths,
            galleryComplete: $complete,
            attachments: $attachments,
        );
    }

    /** @param array<string, mixed> $columns */
    private function apply(
        WordPressItem $item,
        ?Project $target,
        ?ProjectCategory $category,
        array $columns,
        NewsMediaPlan $media,
        ?WpImportMap $mapping,
        string $sourceHash,
        NewsImportReport $report,
        bool $execute,
    ): void {
        $label = $this->label($item);
        $offset = $this->mediaImporter->createdCount();

        try {
            if ($execute) {
                DB::beginTransaction();
            }

            [$featuredPath, $galleryPaths] = $this->storeMedia($media, $execute);

            if ($execute) {
                if (array_key_exists('featured_image_path', $columns)) {
                    $columns['featured_image_path'] = $featuredPath;
                }

                if (array_key_exists('gallery_image_paths', $columns)) {
                    $columns['gallery_image_paths'] = $galleryPaths;
                }

                $this->persist($item, $target, $category, $columns, $media, $mapping, $sourceHash);

                DB::commit();
            }

            if ($target === null) {
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

    /** Store planned media files and return stored paths.
     * When execute is false (dry run), returns the planned paths without copying.
     * When execute is true, copies the files and returns the stored paths.
     *
     * @return array{0: string|null, 1: list<string>}
     */
    private function storeMedia(NewsMediaPlan $media, bool $execute): array
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

        $galleryPaths = [];

        foreach ($media->galleryPlans as $plan) {
            if ($plan->targetPath !== null && $plan->willCopy()) {
                $stored = $this->mediaImporter->copy($plan);

                if ($stored === null) {
                    throw new RuntimeException('a gallery image could not be copied to storage');
                }

                $galleryPaths[] = $stored;
            } else {
                $galleryPaths[] = $plan->targetPath;
            }
        }

        return [$featuredPath, $galleryPaths];
    }

    /** @param array<string, mixed> $columns */
    private function persist(
        WordPressItem $item,
        ?Project $target,
        ?ProjectCategory $category,
        array $columns,
        NewsMediaPlan $media,
        ?WpImportMap $mapping,
        string $sourceHash,
    ): void {
        if ($target === null) {
            $target = Project::create($columns);
        } else {
            $target->update($columns);
        }

        if ($category !== null) {
            $target->project_category_id = $category->id;
        }

        $mapping ??= new WpImportMap([
            'source_type' => self::SOURCE_TYPE,
            'source_id' => $item->sourceId,
        ]);

        $mapping->fill([
            'source_type' => self::SOURCE_TYPE,
            'source_id' => $item->sourceId,
            'target_type' => Project::class,
            'target_id' => $target->id,
            'source_hash' => $sourceHash,
            'source_metadata' => $this->metadata($item, $category, $media),
            'imported_at' => now(),
        ]);

        $mapping->save();

        // The target fingerprint is taken from the stored record, after every
        // column and the category pivot are in place, so a later run compares
        // like with like.
        $target->refresh();

        $mapping->forceFill([
            'target_hash' => $this->hasher->forPayload($this->fields->targetPayload($target)),
        ])->save();

        foreach ($media->attachments as $attachmentId => $attachment) {
            if ($attachment['stored_path'] === null) {
                continue;
            }

            WpImportMap::query()->updateOrCreate(
                ['source_type' => self::SOURCE_TYPE, 'source_id' => $attachmentId],
                [
                    'target_type' => null,
                    'target_id' => null,
                    'source_hash' => $attachment['md5'],
                    'source_metadata' => [
                        'stored_path' => $attachment['stored_path'],
                        'source_url' => $attachment['source_url'],
                        'manifest_path' => $attachment['manifest_path'],
                        'imported_from' => 'lpres:import-wordpress --type=projects',
                    ],
                    'imported_at' => now(),
                ],
            );
        }
    }

    private function label(WordPressItem $item): string
    {
        return '#'.$item->sourceId.' '.Str::limit($this->fields->title($item), 70);
    }

    private function accountMediaPlan(MediaCopyPlan $plan, string $label, NewsImportReport $report): void
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
            $plan->media->manifestPath,
        );
    }

    private function countAttachmentMappings(NewsMediaPlan $media, NewsImportReport $report): void
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

    /** @return array<string, mixed> Provenance stored on the mapping row */
    private function metadata(
        WordPressItem $item,
        ?ProjectCategory $category,
        NewsMediaPlan $media,
    ): array {
        $termsById = [];
        $termsByName = [];

        foreach ($this->export->categories as $term) {
            if (($term['term_id'] ?? '') !== '') {
                $termsById[(string) $term['term_id']] = $term;
            }

            $termsByName[mb_strtolower($term['name'] ?? '')] = $term;
            $termsByName[mb_strtolower($term['slug'] ?? '')] = $term;
        }

        $categories = [];

        foreach ($item->terms as $term) {
            if ($term['domain'] !== 'category') {
                continue;
            }

            $source = $termsByName[mb_strtolower($term['nicename'])]
                ?? $termsByName[mb_strtolower($term['name'])]
                ?? null;

            $categories[] = [
                'term_id' => $source !== null && ($source['term_id'] ?? '') !== ''
                    ? (int) $source['term_id']
                    : null,
                'name' => $term['name'],
                'slug' => $term['nicename'],
            ];
        }

        $embedded = [];

        foreach ($media->attachments as $attachmentId => $attachment) {
            if ($attachmentId !== (int) ($item->thumbnailId() ?? 0)) {
                $embedded[] = $attachmentId;
            }
        }

        $descriptionProvenance = [];
        if (isset($this->newsProjectPairs[$item->sourceId])) {
            $descriptionProvenance = [
                'news_wp_post_id' => $this->newsProjectPairs[$item->sourceId],
                'description_source' => 'news_prose_reuse',
            ];
        }

        return [
            'wp_post_id' => $item->sourceId,
            'wp_post_type' => $item->type,
            'wp_status' => $item->status,
            'wp_slug' => $item->slug,
            'wp_link' => $item->link,
            'wp_author' => $item->creator,
            'wp_categories' => $categories,
            'laravel_project_category_ids' => $category !== null ? [$category->id] : [],
            'featured_attachment_id' => $item->thumbnailId() !== null && ctype_digit((string) $item->thumbnailId())
                ? (int) $item->thumbnailId()
                : null,
            'embedded_attachment_ids' => $embedded,
            'description_provenance' => $descriptionProvenance,
        ];
    }

    /** Resolve the project slug, adding a numeric suffix only when another
     * article already owns it. Every collision is reported, never silent.
     *
     * @param  array<string, string>  $taken
     */
    private function resolveSlug(string $desired, array &$taken, NewsImportReport $report, string $label): string
    {
        $desired = trim($desired);
        $key = mb_strtolower($desired);

        if (! isset($taken[$key])) {
            $taken[$key] = $desired;

            return $desired;
        }

        for ($attempt = 2; $attempt <= self::SLUG_ATTEMPTS; $attempt++) {
            $candidate = $desired.'-'.$attempt;

            if (isset($taken[mb_strtolower($candidate)])) {
                continue;
            }

            $taken[mb_strtolower($candidate)] = $candidate;
            $report->slugConflicts[] = sprintf(
                '%s: slug "%s" is already used; imported as "%s"',
                $label,
                $desired,
                $candidate,
            );

            return $candidate;
        }

        $report->fail($label.': no free variant of the slug "'.$desired.'" could be found');

        return '';
    }
}
