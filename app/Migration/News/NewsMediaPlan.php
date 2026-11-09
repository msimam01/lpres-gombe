<?php

namespace App\Migration\News;

use App\Migration\Media\MediaCopyPlan;

/**
 * The media plan for one article: its featured image and the images embedded
 * in its body, already resolved to target storage paths (planned in a dry run,
 * copied during execution).
 *
 * Three flags carry the decisions the importer must not make twice:
 *  - `featuredDecided`  the source tells us the article's final featured image
 *                       (present, or genuinely none). When false the media is
 *                       declared but unusable, so an existing value is kept.
 *  - `galleryComplete`  every embedded image resolved. When false an update
 *                       keeps the gallery it already has instead of dropping
 *                       the images that could not be re-imported.
 *  - `attachments`      WordPress attachment id => provenance (and stored
 *                       path), so `wp_import_map` can map attachment ids to
 *                       the imported files.
 */
final readonly class NewsMediaPlan
{
    /**
     * @param  list<MediaCopyPlan>  $galleryPlans
     * @param  list<string>  $galleryPaths
     * @param  array<int, array{source_url: string, manifest_path: string, stored_path: string|null, md5: string|null}>  $attachments
     */
    public function __construct(
        public ?MediaCopyPlan $featuredPlan,
        public bool $featuredDecided,
        public ?string $featuredPath,
        public array $galleryPlans,
        public array $galleryPaths,
        public bool $galleryComplete,
        public array $attachments,
    ) {}

    public static function none(): self
    {
        return new self(null, false, null, [], [], true, []);
    }
}
