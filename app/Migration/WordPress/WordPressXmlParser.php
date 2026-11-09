<?php

namespace App\Migration\WordPress;

use SimpleXMLElement;

/**
 * Parses a WordPress eXtended RSS (WXR) export.
 *
 * Handles the WordPress namespaces explicitly rather than relying on prefixed
 * tag names (SimpleXML would otherwise return empty values), and accepts both
 * WXR 1.2 and the older 1.1 namespace URIs. External entities are never loaded:
 * LIBXML_NOENT is not used and network access is disabled with LIBXML_NONET.
 *
 * Parsing is side-effect free — the export file is only ever read.
 */
final class WordPressXmlParser
{
    public const NS_WP_1_2 = 'http://wordpress.org/export/1.2/';

    public const NS_WP_1_1 = 'http://wordpress.org/export/1.1/';

    public const NS_CONTENT = 'http://purl.org/rss/1.0/modules/content/';

    public const NS_EXCERPT_1_2 = 'http://wordpress.org/export/1.2/excerpt/';

    public const NS_EXCERPT_1_1 = 'http://wordpress.org/export/1.1/excerpt/';

    public const NS_DC = 'http://purl.org/dc/elements/1.1/';

    /**
     * @throws WordPressExportException
     */
    public function parse(string $path): WordPressExport
    {
        $this->assertReadable($path);

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $xml = simplexml_load_file(
            $path,
            SimpleXMLElement::class,
            LIBXML_NOCDATA | LIBXML_NONET | LIBXML_PARSEHUGE,
        );

        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            throw WordPressExportException::malformed($path, $this->describeErrors($errors));
        }

        if (! isset($xml->channel) || $xml->channel->count() === 0) {
            throw WordPressExportException::notWxr($path);
        }

        $channel = $xml->channel;

        $wpNamespaces = $this->resolveNamespaces(
            $channel,
            'wxr_version',
            [self::NS_WP_1_2, self::NS_WP_1_1],
        );

        if ($wpNamespaces === []) {
            throw WordPressExportException::notWxr($path);
        }

        $excerptNamespaces = str_starts_with($wpNamespaces[0], self::NS_WP_1_1)
            ? [self::NS_EXCERPT_1_1, self::NS_EXCERPT_1_2]
            : [self::NS_EXCERPT_1_2, self::NS_EXCERPT_1_1];

        $items = [];

        foreach ($channel->item as $index => $item) {
            $items[] = $this->parseItem($item, (int) $index, $path, $wpNamespaces, $excerptNamespaces);
        }

        return new WordPressExport(
            path: $path,
            bytes: (int) filesize($path),
            wxrVersion: $this->text($channel, $wpNamespaces, 'wxr_version'),
            siteTitle: $this->trimmed((string) $channel->title),
            siteUrl: $this->trimmed((string) $channel->link),
            authors: $this->parseAuthors($channel, $wpNamespaces),
            categories: $this->parseCategories($channel, $wpNamespaces),
            tags: $this->parseTags($channel, $wpNamespaces),
            terms: $this->parseTerms($channel, $wpNamespaces),
            items: $items,
        );
    }

    /**
     * @param  list<string>  $wpNamespaces
     * @param  list<string>  $excerptNamespaces
     *
     * @throws WordPressExportException
     */
    private function parseItem(
        SimpleXMLElement $item,
        int $index,
        string $path,
        array $wpNamespaces,
        array $excerptNamespaces,
    ): WordPressItem {
        $sourceId = $this->text($item, $wpNamespaces, 'post_id');

        if (preg_match('/^\d+$/', $sourceId) !== 1) {
            throw WordPressExportException::invalidItem(
                $path,
                $index,
                $sourceId === ''
                    ? 'missing wp:post_id'
                    : "wp:post_id \"{$sourceId}\" is not a numeric WordPress ID",
            );
        }

        return new WordPressItem(
            sourceId: (int) $sourceId,
            type: $this->text($item, $wpNamespaces, 'post_type'),
            status: $this->text($item, $wpNamespaces, 'status'),
            title: $this->trimmed((string) $item->title),
            slug: $this->text($item, $wpNamespaces, 'post_name'),
            link: $this->trimmed((string) $item->link),
            publishedAt: $this->text($item, $wpNamespaces, 'post_date'),
            modifiedAt: $this->text($item, $wpNamespaces, 'post_modified'),
            content: $this->text($item, [self::NS_CONTENT], 'encoded'),
            excerpt: $this->text($item, $excerptNamespaces, 'encoded'),
            terms: $this->parseItemTerms($item),
            meta: $this->parseItemMeta($item, $wpNamespaces),
            attachmentUrl: $this->text($item, $wpNamespaces, 'attachment_url'),
            parentId: (int) $this->text($item, $wpNamespaces, 'post_parent'),
            creator: $this->text($item, [self::NS_DC], 'creator'),
        );
    }

    /**
     * Taxonomy terms attached to an item. WordPress writes every taxonomy
     * (category, post_tag and custom ones) as <category domain="..." >.
     *
     * @return list<array{domain: string, nicename: string, name: string}>
     */
    private function parseItemTerms(SimpleXMLElement $item): array
    {
        $terms = [];

        foreach ($item->category as $category) {
            $domain = trim((string) $category['domain']);

            $terms[] = [
                'domain' => $domain !== '' ? $domain : 'category',
                'nicename' => trim((string) $category['nicename']),
                'name' => $this->trimmed((string) $category),
            ];
        }

        return $terms;
    }

    /**
     * @param  list<string>  $wpNamespaces
     * @return array<string, list<string>>
     */
    private function parseItemMeta(SimpleXMLElement $item, array $wpNamespaces): array
    {
        $meta = [];

        foreach ($wpNamespaces as $namespace) {
            $container = $item->children($namespace);

            if (! isset($container->postmeta)) {
                continue;
            }

            foreach ($container->postmeta as $postmeta) {
                $key = $this->trimmed((string) $postmeta->meta_key);

                if ($key === '') {
                    continue;
                }

                $meta[$key][] = (string) $postmeta->meta_value;
            }

            break;
        }

        return $meta;
    }

    /**
     * @param  list<string>  $wpNamespaces
     * @return list<array<string, string>>
     */
    private function parseAuthors(SimpleXMLElement $channel, array $wpNamespaces): array
    {
        $authors = [];

        foreach ($wpNamespaces as $namespace) {
            $container = $channel->children($namespace);

            if (! isset($container->author)) {
                continue;
            }

            foreach ($container->author as $author) {
                $authors[] = [
                    'id' => $this->trimmed((string) $author->author_id),
                    'login' => $this->trimmed((string) $author->author_login),
                    'email' => $this->trimmed((string) $author->author_email),
                    'display_name' => $this->trimmed((string) $author->author_display_name),
                ];
            }

            break;
        }

        return $authors;
    }

    /**
     * @param  list<string>  $wpNamespaces
     * @return list<array<string, string>>
     */
    private function parseCategories(SimpleXMLElement $channel, array $wpNamespaces): array
    {
        $categories = [];
        $container = $channel->children($wpNamespaces[0]);

        foreach ($container->category as $category) {
            $categories[] = [
                'term_id' => $this->trimmed((string) $category->term_id),
                'slug' => $this->trimmed((string) $category->category_nicename),
                'parent' => $this->trimmed((string) $category->category_parent),
                'name' => $this->trimmed((string) $category->cat_name),
            ];
        }

        return $categories;
    }

    /**
     * @param  list<string>  $wpNamespaces
     * @return list<array<string, string>>
     */
    private function parseTags(SimpleXMLElement $channel, array $wpNamespaces): array
    {
        $tags = [];
        $container = $channel->children($wpNamespaces[0]);

        foreach ($container->tag as $tag) {
            $tags[] = [
                'term_id' => $this->trimmed((string) $tag->term_id),
                'slug' => $this->trimmed((string) $tag->tag_slug),
                'name' => $this->trimmed((string) $tag->tag_name),
            ];
        }

        return $tags;
    }

    /**
     * Custom taxonomy terms declared at channel level (<wp:term>).
     *
     * @param  list<string>  $wpNamespaces
     * @return list<array<string, string>>
     */
    private function parseTerms(SimpleXMLElement $channel, array $wpNamespaces): array
    {
        $terms = [];
        $container = $channel->children($wpNamespaces[0]);

        foreach ($container->term as $term) {
            $terms[] = [
                'term_id' => $this->trimmed((string) $term->term_id),
                'taxonomy' => $this->trimmed((string) $term->term_taxonomy),
                'slug' => $this->trimmed((string) $term->term_slug),
                'parent' => $this->trimmed((string) $term->term_parent),
                'name' => $this->trimmed((string) $term->term_name),
            ];
        }

        return $terms;
    }

    /**
     * Namespaces that actually contain `$tag` on `$node`, preferred order
     * first. Empty when none match (i.e. this is not the document we expect).
     *
     * @param  list<string>  $candidates
     * @return list<string>
     */
    private function resolveNamespaces(SimpleXMLElement $node, string $tag, array $candidates): array
    {
        $matched = [];

        foreach ($candidates as $namespace) {
            if (isset($node->children($namespace)->{$tag})) {
                $matched[] = $namespace;
            }
        }

        return $matched === [] ? [] : $matched;
    }

    /**
     * Read a namespaced child, trying each namespace in order.
     *
     * @param  list<string>  $namespaces
     */
    private function text(SimpleXMLElement $node, array $namespaces, string $tag): string
    {
        foreach ($namespaces as $namespace) {
            $children = $node->children($namespace);

            if (isset($children->{$tag})) {
                return $this->trimmed((string) $children->{$tag});
            }
        }

        return '';
    }

    private function trimmed(string $value): string
    {
        return trim($value);
    }

    /**
     * @throws WordPressExportException
     */
    private function assertReadable(string $path): void
    {
        if (! is_file($path)) {
            throw WordPressExportException::missing($path);
        }

        if (! is_readable($path)) {
            throw WordPressExportException::unreadable($path);
        }

        if ((int) filesize($path) === 0) {
            throw WordPressExportException::empty($path);
        }
    }

    /**
     * @param  array<int, \LibXMLError>  $errors
     */
    private function describeErrors(array $errors): string
    {
        if ($errors === []) {
            return 'the file is not well-formed XML';
        }

        $first = $errors[0];

        return trim($first->message)." (line {$first->line})";
    }
}
