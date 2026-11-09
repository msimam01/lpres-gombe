<?php

namespace App\Migration;

use App\Migration\WordPress\WordPressItem;

/**
 * Stable fingerprint of a WordPress record's importable fields.
 *
 * Only fields the migration actually writes are hashed — not Elementor
 * payloads, SEO plugin keys or theme settings. That way "the source changed"
 * means "the content we imported changed", which is what the overwrite guard
 * needs. The hash is deterministic: repeated parses of the same export produce
 * the same value, and reordering taxonomy terms does not alter it.
 */
final class SourceHash
{
    public function forItem(WordPressItem $item): string
    {
        $terms = array_map(
            fn (array $term) => $term['domain'].':'.$term['nicename'],
            $item->terms,
        );

        sort($terms);

        return $this->forPayload([
            'source_type' => $item->type,
            'source_id' => $item->sourceId,
            'status' => $item->status,
            'title' => $item->title,
            'slug' => $item->slug,
            'published_at' => $item->publishedAt,
            'modified_at' => $item->modifiedAt,
            'content' => $item->content,
            'excerpt' => $item->excerpt,
            'terms' => $terms,
            'thumbnail_id' => $item->thumbnailId(),
            'attachment_url' => $item->attachmentUrl,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function forPayload(array $payload): string
    {
        $json = json_encode(
            $this->canonicalise($payload),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
        );

        return hash('sha256', $json === false ? serialize($payload) : $json);
    }

    /**
     * Recursively sort associative keys so key order never changes the hash.
     */
    private function canonicalise(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item) => $this->canonicalise($item), $value);
    }
}
