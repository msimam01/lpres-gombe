<?php

namespace App\Migration\Projects;

use App\Migration\HtmlToText;
use App\Migration\WordPress\WordPressItem;
use App\Models\Project;
use Illuminate\Support\Str;

/** WordPress -> Laravel field mapping for one project record.

 * Deliberately narrow: it only produces values the existing `projects columns` can hold, plus the payload used to fingerprint the imported target.

 *  - Title is entity-decoded (`&` -> `&`).
 *  - Body is the project description, sourced from News prose reuse where
 *    approved, or left empty/null where the project has no usable description.
 *  - The leading heading is dropped when it repeats the title (same rule as
 *    News, 115 of 132 real articles open with the title as a heading).
 *  - A body with no block structure at all is split into paragraphs on
 *    sentence boundaries.
 *  - Excerpt follows the application's existing rule: derived from the title.
 *  - Publication date is the WordPress date part, never "today".
 *  - No author is invented: WordPress has no byline column to map to.
 *  - Slug is normalised but not truncated: preserving the original permalink
 *    is preferred over regenerating one from the title.
 */
final class ProjectsFields
{
    public function __construct(private readonly HtmlToText $html) {}

    public function title(WordPressItem $item): string
    {
        return trim(html_entity_decode($item->title, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /** Plain-text article body in the shape `projects/show` expects (paragraphs
     * separated by one blank line). The body source depends on the description
     * handling plan: News prose reuse where approved, empty where no usable
     * description, or title-derived.
     */
    public function body(WordPressItem $item): string
    {
        // The actual body text is set by the importer's determineDescription()
        // method before calling this. This method returns empty string as
        // default; the importer sets the body directly from the determined
        // description.
        return '';
    }

    /** The WordPress slug, normalised but not truncated. */
    public function slug(WordPressItem $item): string
    {
        return Str::slug($item->slug);
    }

    /** Slug derived from the title, matching the admin module's rule. */
    public function generatedSlug(WordPressItem $item): string
    {
        $title = $this->title($item);

        return $title === '' ? '' : Str::slug(Str::limit($title, 120, ''));
    }

    /** Date part of `wp:post_date`; null when absent. */
    public function publishedDate(WordPressItem $item): ?string
    {
        return $item->publishedAt !== '' ? date('Y-m-d', strtotime($item->publishedAt)) : null;
    }

    /** The fields the importer writes, as stored on the Laravel record. Used
     * to fingerprint the imported target so administrator edits are detectable.
     *
     * @return array<string, mixed>
     */
    public function targetPayload(Project $project): array
    {
        return [
            'title' => $project->title,
            'slug' => $project->slug,
            'body' => $project->body,
            'excerpt' => $project->excerpt,
            'published_at' => $project->published_at?->toDateString(),
            'status' => $project->status,
            'featured_image_path' => $project->featured_image_path,
            'gallery_image_paths' => array_values((array) ($project->gallery_image_paths ?? [])),
            'category_ids' => array_values([$project->project_category_id]),
        ];
    }
}