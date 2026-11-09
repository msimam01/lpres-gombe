<?php

namespace App\Migration\Teams;

use App\Migration\HtmlToText;
use App\Migration\WordPress\WordPressItem;
use Illuminate\Support\Str;

/** WordPress -> Laravel field mapping for one team member record.

 * Deliberately narrow: it only produces values the existing `team_members columns` can hold,
 * plus the payload used to fingerprint the imported target.

 *  - Name is entity-decoded from the WordPress title.
 *  - Slug is normalised from the name, truncated to a reasonable length.
 *  - Photograph path is resolved from the content inventory's featured_image_relative_path.
 *  - Designation comes from the WordPress custom field or is derived.
 *  - team_category_id is mapped from the classification (PIU -> 4, Assistant -> 5).
 *  - No biography, contact, social, status, visibility, or display-order fields are added.
 */
final class TeamFields
{
    public function __construct(private readonly HtmlToText $html) {}

    /** The team member's name, from the WordPress post title. */
    public function title(WordPressItem $item): string
    {
        return trim(html_entity_decode($item->title, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /** The team member's slug, derived from the name. */
    public function slug(WordPressItem $item): string
    {
        $name = $this->title($item);
        return Str::slug(Str::limit($name, 100, ''));
    }

    /** Generated slug fallback from the title. */
    public function generatedSlug(WordPressItem $item): string
    {
        return $this->slug($item);
    }

    /** The publication date from WordPress. */
    public function publishedDate(WordPressItem $item): ?string
    {
        return $item->publishedAt !== '' ? date('Y-m-d', strtotime($item->publishedAt)) : null;
    }

    /**
     * The designation / role for the team member.
     *
     * The theme stores it inside the serialized `towngov_meta_team` post meta
     * (`team_designation`). Nothing is invented when the field is absent.
     */
    public function designation(WordPressItem $item): string
    {
        $raw = $item->meta['towngov_meta_team'][0] ?? null;

        if (! is_string($raw) || $raw === '') {
            return '';
        }

        $meta = @unserialize($raw, ['allowed_classes' => false]);

        if (! is_array($meta) || ! isset($meta['team_designation'])) {
            return '';
        }

        return trim(html_entity_decode((string) $meta['team_designation'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /** The team category ID based on the classification.
     *  PIU -> 4, Assistant -> 5
     */
    public function teamCategoryId(string $classification): int
    {
        return match ($classification) {
            'piu' => 4,
            'assistant' => 5,
            default => 3, // Professionals as fallback
        };
    }

    /** The fields the importer writes, as stored on the Laravel record. Used
     * to fingerprint the imported target so administrator edits are detectable.
     *
     * @return array<string, mixed>
     */
    public function targetPayload(array $columns): array
    {
        return [
            'name' => $columns['name'] ?? '',
            'slug' => $columns['slug'] ?? '',
            'photograph_path' => $columns['photograph_path'] ?? null,
            'designation' => $columns['designation'] ?? '',
            'team_category_id' => $columns['team_category_id'] ?? 3,
            'status' => $columns['status'] ?? 'published',
        ];
    }
}