<?php

namespace App\Migration\Teams;

use App\Migration\Media\MediaCopyPlan;

/** Media plan for one team member record.

 * Carries the featured image plan and a flag indicating whether the featured
 * image was decided (present or genuinely absent). Team members have no
 * embedded/gallery images in the audit, so gallery fields are minimal.
 */
final readonly class TeamsMediaPlan
{
    public function __construct(
        public ?MediaCopyPlan $featuredPlan,
        public bool $featuredDecided,
        public ?string $featuredPath,
        public array $galleryPlans = [],
        public array $galleryPaths = [],
        public bool $galleryComplete = true,
        public array $attachments = [],
    ) {}
}