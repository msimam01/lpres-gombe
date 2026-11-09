<?php

namespace App\Migration\WordPress;

/**
 * One WordPress record from the export, reduced to the fields the migration
 * can actually use.
 *
 * Deliberately immutable: the importer reads a source record, hashes it and
 * maps it, and nothing should be able to mutate it along the way.
 */
final readonly class WordPressItem
{
    /**
     * @param  list<array{domain: string, nicename: string, name: string}>  $terms
     * @param  array<string, list<string>>  $meta
     */
    public function __construct(
        public int $sourceId,
        public string $type,
        public string $status,
        public string $title,
        public string $slug,
        public string $link = '',
        public string $publishedAt = '',
        public string $modifiedAt = '',
        public string $content = '',
        public string $excerpt = '',
        public array $terms = [],
        public array $meta = [],
        public string $attachmentUrl = '',
        public int $parentId = 0,
        public string $creator = '',
    ) {}

    public function isPublished(): bool
    {
        return $this->status === 'publish';
    }

    /**
     * The date part of `wp:post_date`, or null when absent/invalid.
     */
    public function publishedDate(): ?string
    {
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $this->publishedAt, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /**
     * First value of a `wp:postmeta` key.
     */
    public function metaValue(string $key): ?string
    {
        $values = $this->meta[$key] ?? null;

        return $values[0] ?? null;
    }

    /**
     * `_thumbnail_id`, the attachment used as the featured image.
     */
    public function thumbnailId(): ?string
    {
        $value = $this->metaValue('_thumbnail_id');

        return $value !== null && $value !== '' ? $value : null;
    }

    /**
     * Term names in the `category` taxonomy.
     *
     * @return list<string>
     */
    public function categories(): array
    {
        return $this->termNames('category');
    }

    /**
     * Term names in the `post_tag` taxonomy.
     *
     * @return list<string>
     */
    public function tags(): array
    {
        return $this->termNames('post_tag');
    }

    /**
     * @return list<string>
     */
    public function termNames(string $domain): array
    {
        $names = [];

        foreach ($this->terms as $term) {
            if ($term['domain'] === $domain && $term['name'] !== '') {
                $names[] = $term['name'];
            }
        }

        return $names;
    }
}
