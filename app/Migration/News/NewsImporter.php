<?php

namespace App\Migration\News;

use App\Migration\ContentInventory;
use App\Migration\Media\MediaCopyPlan;
use App\Migration\Media\MediaImporter;
use App\Migration\Media\MediaResolver;
use App\Migration\SourceHash;
use App\Migration\WordPress\WordPressExport;
use App\Migration\WordPress\WordPressItem;
use App\Models\News;
use App\Models\NewsCategory;
use App\Models\WpImportMap;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Imports the audited genuine news articles from the WordPress export.
 *
 * Eligibility comes from the audit's content inventory, not from titles or
 * slugs: `publish` + classification `news` + in migration scope. In the audited
 * inventory that is exactly 132 records out of 144 published ordinary posts.
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
 */
final class NewsImporter
{
    /** `wp_import_map.source_type` for an article. */
    public const SOURCE_TYPE = 'post';

    /** `wp_import_map.source_type` for an attachment. */
    public const ATTACHMENT_TYPE = 'attachment';

    /** Suffixes tried when a desired slug is already taken. */
    private const SLUG_ATTEMPTS = 100;

    /** @var array<int, bool> Attachment ids already counted in this run. */
    private array $countedAttachments = [];

    /** @var array<int, bool> Attachment ids that already have a mapping row. */
    private array $mappedAttachments = [];

    public function __construct(
        private readonly WordPressExport $export,
        private readonly ContentInventory $inventory,
        private readonly MediaResolver $resolver,
        private readonly MediaImporter $mediaImporter,
        private readonly NewsFields $fields,
        private readonly SourceHash $hasher,
        private readonly bool $forceAdminEdits = false,
    ) {}

    public function run(bool $execute): NewsImportReport
    {
        $report = new NewsImportReport(! $execute);
        $eligible = $this->inventory->eligibleNewsIds();

        $report->eligible = count($eligible);
        $this->accountForExclusions($report);

        $category = $this->resolveCategory($execute, $report);

        $this->mappedAttachments = WpImportMap::query()
            ->where('source_type', self::ATTACHMENT_TYPE)
            ->pluck('source_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();

        $taken = [];

        foreach (News::query()->pluck('slug') as $slug) {
            $taken[mb_strtolower((string) $slug)] = (string) $slug;
        }

        foreach ($eligible as $wpId) {
            $item = $this->export->find($wpId);

            if ($item === null) {
                $report->fail("#{$wpId}: listed as eligible news in the content inventory but missing from the XML export");

                continue;
            }

            $this->importOne($item, $category, $report, $execute, $taken);
        }

        return $report;
    }

    /**
     * Count the audited posts that are deliberately not imported, by reason.
     */
    private function accountForExclusions(NewsImportReport $report): void
    {
        $eligible = array_flip($this->inventory->eligibleNewsIds());
        $nonPost = 0;

        foreach ($this->inventory->rows as $row) {
            if ($row['post_type'] !== 'post') {
                $nonPost++;

                continue;
            }

            if (isset($eligible[(int) $row['wp_id']])) {
                continue;
            }

            $reason = match (true) {
                $row['status'] !== 'publish' => 'not published ('.$row['status'].')',
                $row['in_migration_scope'] !== 'yes' => 'published but out of scope: '.$row['classification'],
                default => 'published but not genuine news: '.$row['classification'],
            };

            $report->excluded[$reason] = ($report->excluded[$reason] ?? 0) + 1;
        }

        if ($nonPost > 0) {
            $report->note(sprintf(
                '%d non-post inventory rows (pages, projects, attachments, …) are outside this phase.',
                $nonPost,
            ));
        }
    }

    /**
     * The one Laravel `News` category. Created on first execution, never
     * duplicated: the slug is unique and `NewsCategory::NEWS_SLUG` is the key.
     */
    private function resolveCategory(bool $execute, NewsImportReport $report): ?NewsCategory
    {
        $existing = NewsCategory::query()
            ->where('slug', NewsCategory::NEWS_SLUG)
            ->first();

        $report->categoryExists = $existing !== null;

        if ($existing !== null) {
            return $existing;
        }

        $report->categoryWillBeCreated = true;

        if (! $execute) {
            return null;
        }

        return NewsCategory::create([
            'name' => 'News',
            'slug' => NewsCategory::NEWS_SLUG,
            'description' => 'News articles published by the L-PRES project.',
            'wp_term_id' => NewsCategory::NEWS_SOURCE_TERM_ID,
        ]);
    }

    /**
     * @param  array<string, string>  $taken  Lower-cased slug => stored slug.
     */
    private function importOne(
        WordPressItem $item,
        ?NewsCategory $category,
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

        if ($body === '') {
            $report->fail($label.': the WordPress article body is empty after HTML conversion');

            return;
        }

        $sourceHash = $this->hasher->forItem($item);
        $mapping = WpImportMap::lookup(self::SOURCE_TYPE, $item->sourceId);
        $target = null;
        $isCreate = true;

        if ($mapping !== null) {
            $report->existingMappings++;

            $mapped = $mapping->target;

            if (! $mapping->isImported() || ! $mapped instanceof News) {
                $report->conflict(sprintf(
                    '%s: an import mapping exists but its article is missing, so the target cannot be verified',
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
                    $mapping->target_hash === null ? 'no target hash recorded' : 'the article was edited',
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

        $media = $this->planMedia($item, $report, true);

        $slug = $isCreate
            ? $this->resolveSlug($sourceSlug, $taken, $report, $label)
            : (string) $target->slug;

        if ($slug === '') {
            return;
        }

        $taken[mb_strtolower($slug)] = $slug;

        $columns = [
            'title' => $title,
            'body' => $body,
            'excerpt' => News::excerptFromTitle($title),
            'published_at' => $publishedAt,
            'status' => 'published',
        ];

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

    /**
     * @param  array<string, mixed>  $columns
     */
    private function apply(
        WordPressItem $item,
        ?News $target,
        ?NewsCategory $category,
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

    /**
     * @param  array<string, mixed>  $columns
     */
    private function persist(
        WordPressItem $item,
        ?News $target,
        ?NewsCategory $category,
        array $columns,
        NewsMediaPlan $media,
        ?WpImportMap $mapping,
        string $sourceHash,
    ): void {
        if ($target === null) {
            $target = News::create($columns);
        } else {
            $target->update($columns);
        }

        if ($category !== null) {
            $target->categories()->syncWithoutDetaching([$category->id]);
        }

        $mapping ??= new WpImportMap([
            'source_type' => self::SOURCE_TYPE,
            'source_id' => $item->sourceId,
        ]);

        $mapping->fill([
            'source_type' => self::SOURCE_TYPE,
            'source_id' => $item->sourceId,
            'target_type' => News::class,
            'target_id' => $target->id,
            'source_hash' => $sourceHash,
            'source_metadata' => $this->metadata($item, $category, $media),
            'imported_at' => now(),
        ]);

        $mapping->save();

        // The target fingerprint is taken from the stored record, after every
        // column and the category pivot are in place, so a later run compares
        // like with like.
        $target->refresh()->load('categories');

        $mapping->forceFill([
            'target_hash' => $this->hasher->forPayload($this->fields->targetPayload($target)),
        ])->save();

        foreach ($media->attachments as $attachmentId => $attachment) {
            if ($attachment['stored_path'] === null) {
                continue;
            }

            WpImportMap::query()->updateOrCreate(
                ['source_type' => self::ATTACHMENT_TYPE, 'source_id' => $attachmentId],
                [
                    'target_type' => null,
                    'target_id' => null,
                    'source_hash' => $attachment['md5'],
                    'source_metadata' => [
                        'stored_path' => $attachment['stored_path'],
                        'source_url' => $attachment['source_url'],
                        'manifest_path' => $attachment['manifest_path'],
                        'imported_from' => 'lpres:import-wordpress --type=news',
                    ],
                    'imported_at' => now(),
                ],
            );
        }
    }

    /**
     * Resolve the featured image and every image embedded in the article body.
     *
     * Media is resolved even for records that will not be written, so a dry run
     * reports the full set of missing images; copies are only planned for
     * records that will actually be written.
     */
    private function planMedia(WordPressItem $item, NewsImportReport $report, bool $willWrite): NewsMediaPlan
    {
        $label = $this->label($item);
        $attachments = [];

        $featured = $this->resolver->resolveFeatured($item);
        $thumbnailId = $item->thumbnailId();
        $featuredPlan = null;
        $featuredPath = null;
        $featuredDecided = false;

        if (! $featured->isFound()) {
            if ($featured->isAbsent()) {
                // WordPress genuinely has no featured image: the imported
                // article should not have one either.
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

        $featuredAttachmentId = $thumbnailId !== null && ctype_digit($thumbnailId) ? (int) $thumbnailId : null;
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

            // The featured image is already rendered at the top of the article;
            // repeating it in the inline gallery would duplicate it.
            if ($attachmentId === $featuredAttachmentId || isset($seen[$attachmentId])) {
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

            // Two attachments with identical bytes share one stored file, so
            // the gallery must not repeat it.
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

    /**
     * Copy the planned media files (execution only) and return the stored
     * paths. A copy failure aborts the record so no article can reference a
     * file that was never written.
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

        $gallery = [];

        foreach ($media->galleryPlans as $plan) {
            if (! $plan->willCopy()) {
                if ($plan->targetPath !== null) {
                    $gallery[] = $plan->targetPath;
                }

                continue;
            }

            $stored = $this->mediaImporter->copy($plan);

            if ($stored === null) {
                throw new RuntimeException("an article image could not be copied to storage: {$plan->media->manifestPath}");
            }

            $gallery[] = $stored;
        }

        return [$featuredPath, $gallery];
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

    /**
     * WordPress provenance stored on the mapping row: the original category
     * term ids and names, the author (recorded, never imported as a byline) and
     * the attachments the article uses.
     *
     * @return array<string, mixed>
     */
    private function metadata(WordPressItem $item, ?NewsCategory $category, NewsMediaPlan $media): array
    {
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

        return [
            'wp_post_id' => $item->sourceId,
            'wp_post_type' => $item->type,
            'wp_status' => $item->status,
            'wp_slug' => $item->slug,
            'wp_link' => $item->link,
            'wp_author' => $item->creator,
            'wp_categories' => $categories,
            'laravel_news_category_ids' => $category !== null ? [$category->id] : [],
            'featured_attachment_id' => $item->thumbnailId() !== null && ctype_digit((string) $item->thumbnailId())
                ? (int) $item->thumbnailId()
                : null,
            'embedded_attachment_ids' => $embedded,
        ];
    }

    /**
     * Preserve the source slug, adding a numeric suffix only when another
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

    private function label(WordPressItem $item): string
    {
        return '#'.$item->sourceId.' '.Str::limit($this->fields->title($item), 70);
    }
}
