<?php

namespace App\Migration\Media;

use App\Migration\MediaManifest;
use App\Migration\WordPress\WordPressExport;
use App\Migration\WordPress\WordPressItem;

/**
 * Turns a WordPress attachment reference into the downloaded file on disk.
 *
 * Resolution chain (the only supported one):
 *
 *   attachment id  ->  export attachment record (`wp:attachment_url`)
 *                  ->  media manifest row (by URL, normalised)
 *                  ->  manifest `relative_path` inside the media directory
 *
 * WordPress stores a `-1024x682` variant in the article body while the
 * attachment record holds `-scaled` and the downloaded file drops both
 * suffixes. Matching raw URL strings therefore fails on most images, so URLs are
 * normalised (resize/scale suffix removed) before lookup. The manifest's
 * `relative_path` is used verbatim afterwards; a basename is never guessed
 * because the manifest contains duplicate basenames in different month folders.
 */
final class MediaResolver
{
    /**
     * @param  array<int, WordPressItem>  $attachments
     * @param  array<string, string>  $manifestByUrl
     * @param  array<string, string>  $manifestByNormalisedPath
     * @param  array<string, list<int>>  $attachmentIdsByNormalisedPath
     */
    private function __construct(
        private readonly array $attachments,
        private readonly array $manifestByUrl,
        private readonly array $manifestByNormalisedPath,
        private readonly array $attachmentIdsByNormalisedPath,
        private readonly string $mediaDirectory,
    ) {}

    public static function fromExport(
        WordPressExport $export,
        MediaManifest $manifest,
        string $mediaDirectory,
    ): self {
        $attachments = [];
        $attachmentIdsByPath = [];

        foreach ($export->items() as $item) {
            if ($item->type !== 'attachment') {
                continue;
            }

            $attachments[$item->sourceId] = $item;

            if ($item->attachmentUrl !== '') {
                $attachmentIdsByPath[self::normaliseUrl($item->attachmentUrl)][] = $item->sourceId;
            }
        }

        $manifestByUrl = [];
        $manifestByPath = [];

        foreach ($manifest->rows as $row) {
            $url = $row['url'] ?? '';
            $relative = $row['relative_path'] ?? '';

            if ($url === '' || $relative === '') {
                continue;
            }

            $manifestByUrl[$url] = $relative;
            $manifestByPath[self::normaliseUrl($url)] = $relative;
        }

        return new self(
            $attachments,
            $manifestByUrl,
            $manifestByPath,
            $attachmentIdsByPath,
            $mediaDirectory,
        );
    }

    public function attachment(int $attachmentId): ?WordPressItem
    {
        return $this->attachments[$attachmentId] ?? null;
    }

    /**
     * The featured image of an article, via `_thumbnail_id`.
     */
    public function resolveFeatured(WordPressItem $item): MediaLookup
    {
        $thumbnailId = $item->thumbnailId();

        if ($thumbnailId === null) {
            return MediaLookup::absent('the article has no featured image in WordPress');
        }

        if (preg_match('/^\d+$/', $thumbnailId) !== 1) {
            return MediaLookup::missing("the featured image id \"{$thumbnailId}\" is not numeric");
        }

        return $this->resolveAttachment((int) $thumbnailId);
    }

    /**
     * An `<img>` URL from an article body, resolved through its attachment
     * record.
     */
    public function resolveUrl(string $url): MediaLookup
    {
        $normalised = self::normaliseUrl($url);
        $ids = $this->attachmentIdsByNormalisedPath[$normalised] ?? [];

        if ($ids === []) {
            return MediaLookup::missing("no attachment record matches {$url}");
        }

        // The audit found no ambiguous attachment URLs; if the export ever
        // contains two, refuse rather than pick one arbitrarily.
        if (count($ids) > 1) {
            return MediaLookup::missing(
                'the URL matches more than one attachment ('.implode(', ', $ids)."): {$url}",
            );
        }

        return $this->resolveAttachment($ids[0]);
    }

    public function resolveAttachment(int $attachmentId): MediaLookup
    {
        $attachment = $this->attachments[$attachmentId] ?? null;

        if ($attachment === null) {
            return MediaLookup::missing("attachment #{$attachmentId} is not in the WordPress export");
        }

        if ($attachment->attachmentUrl === '') {
            return MediaLookup::missing("attachment #{$attachmentId} has no file URL");
        }

        $relative = $this->manifestByUrl[$attachment->attachmentUrl]
            ?? $this->manifestByNormalisedPath[self::normaliseUrl($attachment->attachmentUrl)]
            ?? null;

        if ($relative === null) {
            return MediaLookup::missing(
                "attachment #{$attachmentId} ({$attachment->attachmentUrl}) is not in the media manifest",
            );
        }

        $absolute = $this->absolutePath($relative);

        if ($absolute === null) {
            return MediaLookup::missing("attachment #{$attachmentId} points outside the media directory: {$relative}");
        }

        if (! is_file($absolute)) {
            return MediaLookup::missing("the downloaded file for attachment #{$attachmentId} is missing: {$relative}");
        }

        if (! is_readable($absolute) || (int) filesize($absolute) === 0) {
            return MediaLookup::missing("the downloaded file for attachment #{$attachmentId} is unreadable or empty: {$relative}");
        }

        return MediaLookup::found(new ResolvedMedia(
            attachmentId: $attachmentId,
            originalUrl: $attachment->attachmentUrl,
            manifestPath: $relative,
            absolutePath: $absolute,
            title: $attachment->title,
            caption: $attachment->excerpt,
        ));
    }

    /**
     * Theme/plugin assets that appear in bodies but are not article media
     * (Elementor writes its own placeholder image into empty image widgets).
     */
    public function isIgnorableUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?: $url;

        return str_contains($path, '/wp-content/plugins/')
            || str_contains($path, '/wp-content/themes/')
            || str_ends_with(strtolower($path), '/placeholder.png');
    }

    /**
     * Image URLs embedded in an article body, in document order.
     *
     * `src` is preferred; `data-src` is the fallback for lazily loaded markup.
     * Data URIs and lazy-load placeholders carry no attachment, so they are
     * reported rather than silently dropped (see the caller).
     *
     * @return list<string>
     */
    public static function embeddedImageUrls(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        if (preg_match_all('/<img\b[^>]*>/i', $html, $tags) === false) {
            return [];
        }

        $urls = [];

        foreach ($tags[0] as $tag) {
            $src = self::attribute($tag, 'src') ?? self::attribute($tag, 'data-src');

            if ($src === null || $src === '') {
                $urls[] = '';

                continue;
            }

            $urls[] = $src;
        }

        return $urls;
    }

    /**
     * Decoded value of an `<img>` attribute, or null when it is absent.
     */
    private static function attribute(string $tag, string $name): ?string
    {
        $pattern = '/\b'.preg_quote($name, '/').'\s*=\s*(["\'])(.*?)\1/is';

        if (preg_match($pattern, $tag, $matches) !== 1) {
            return null;
        }

        return trim(html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * Strip the WordPress display variants that make body URLs differ from the
     * attachment record: `-1024x682` and `-scaled` / `-rotated`.
     */
    public static function normaliseUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if ($path === false || $path === null || $path === '') {
            $path = $url;
        }

        $path = rawurldecode($path);
        $path = preg_replace('/-(?:scaled|rotated)(?=\.\w+$)/i', '', $path) ?? $path;

        return preg_replace('/-\d+x\d+(?=\.\w+$)/i', '', $path) ?? $path;
    }

    /**
     * Join a manifest relative path onto the media directory, rejecting any
     * traversal outside it.
     */
    private function absolutePath(string $relative): ?string
    {
        $segments = preg_split('#[\\\\/]#', $relative) ?: [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        if ($segments === []) {
            return null;
        }

        return rtrim($this->mediaDirectory, '/\\').DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $segments);
    }
}
