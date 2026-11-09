<?php

/**
 * READ-ONLY WordPress WXR (eXtended RSS) parser.
 *
 * Reads the exported XML, handles the WordPress namespaces, and writes a
 * structured intermediate JSON file for the audit report generators.
 *
 * It never writes to the XML file or to the Laravel application.
 *
 * Usage: php parse-wxr.php <input.xml> <output.json>
 */

declare(strict_types=1);

ini_set('memory_limit', '-1');
ini_set('libxml_dynamic_memory', '1');

$input = $argv[1] ?? null;
$output = $argv[2] ?? null;

if (! $input || ! $output) {
    fwrite(STDERR, "Usage: php parse-wxr.php <input.xml> <output.json>\n");
    exit(2);
}

if (! is_readable($input)) {
    fwrite(STDERR, "Input XML not readable: {$input}\n");
    exit(2);
}

$previous = libxml_use_internal_errors(true);

$xml = simplexml_load_file($input, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_PARSEHUGE);

if ($xml === false) {
    fwrite(STDERR, "XML parse failed:\n");
    foreach (libxml_get_errors() as $error) {
        fwrite(STDERR, sprintf("  [%d] %s (line %d)\n", $error->level, trim($error->message), $error->line));
    }
    exit(1);
}

$warnings = array_map(
    static fn ($e) => ['level' => $e->level, 'message' => trim($e->message), 'line' => $e->line],
    libxml_get_errors()
);
libxml_clear_errors();
libxml_use_internal_errors($previous);

/** Namespace map used throughout. */
const NS = [
    'wp' => 'http://wordpress.org/export/1.2/',
    'content' => 'http://purl.org/rss/1.0/modules/content/',
    'excerpt' => 'http://wordpress.org/export/1.2/excerpt/',
    'dc' => 'http://purl.org/dc/elements/1.1/',
];

/**
 * Read a namespaced child as a trimmed string.
 */
function nsText(SimpleXMLElement $node, string $ns, string $tag): ?string
{
    $child = $node->children(NS[$ns])->{$tag};

    if ($child === null || count($child) === 0) {
        // Could be a genuinely empty element; distinguish "absent" vs "empty".
        $exists = $node->xpath('./'.$ns.':'.$tag);

        return $exists ? '' : null;
    }

    return trim((string) $child);
}

/** Read a plain (non-namespaced) child. */
function plainText(SimpleXMLElement $node, string $tag): ?string
{
    if (! isset($node->{$tag})) {
        return null;
    }

    return trim((string) $node->{$tag});
}

$channel = $xml->channel;

if ($channel === null) {
    fwrite(STDERR, "No <channel> element found; not a WXR export.\n");
    exit(1);
}

// ---------------------------------------------------------------------------
// Channel metadata + taxonomy definitions
// ---------------------------------------------------------------------------
$site = [
    'title' => plainText($channel, 'title'),
    'link' => plainText($channel, 'link'),
    'description' => plainText($channel, 'description'),
    'pubDate' => plainText($channel, 'pubDate'),
    'language' => plainText($channel, 'language'),
    'wxr_version' => nsText($channel, 'wp', 'wxr_version'),
    'base_site_url' => nsText($channel, 'wp', 'base_site_url'),
    'base_blog_url' => nsText($channel, 'wp', 'base_blog_url'),
];

$authors = [];
foreach ($channel->children(NS['wp'])->author as $author) {
    $authors[] = [
        'author_id' => (string) $author->author_id,
        'login' => (string) $author->author_login,
        'email' => (string) $author->author_email,
        'display' => (string) $author->author_display_name,
        'first_name' => (string) $author->author_first_name,
        'last_name' => (string) $author->author_last_name,
    ];
}

$categories = [];
foreach ($channel->children(NS['wp'])->category as $cat) {
    $categories[] = [
        'term_id' => (string) $cat->term_id,
        'slug' => (string) $cat->category_nicename,
        'parent' => (string) $cat->category_parent,
        'name' => (string) $cat->cat_name,
    ];
}

$tags = [];
foreach ($channel->children(NS['wp'])->tag as $tag) {
    $tags[] = [
        'term_id' => (string) $tag->term_id,
        'slug' => (string) $tag->tag_slug,
        'name' => (string) $tag->tag_name,
    ];
}

// Custom taxonomy terms declared at channel level: <wp:term><wp:term_taxonomy>...
$terms = [];
foreach ($channel->children(NS['wp'])->term as $term) {
    $terms[] = [
        'term_id' => (string) $term->term_id,
        'slug' => (string) $term->term_slug,
        'parent' => (string) $term->term_parent,
        'name' => (string) $term->term_name,
        'taxonomy' => (string) $term->term_taxonomy,
    ];
}

// ---------------------------------------------------------------------------
// Items
// ---------------------------------------------------------------------------
$items = [];

foreach ($channel->item as $item) {
    $meta = [];

    foreach ($item->children(NS['wp'])->postmeta as $pm) {
        $key = (string) $pm->meta_key;
        $value = (string) $pm->meta_value;

        if ($key === '') {
            continue;
        }

        // Keep every value; the audit needs Elementor page-builder payloads.
        $meta[$key][] = $value;
    }

    $itemCategories = []; // domain-tagged terms: category, post_tag, or custom taxonomy
    foreach ($item->category as $cat) {
        $domain = (string) $cat['domain'];
        $itemCategories[] = [
            'domain' => $domain !== '' ? $domain : 'category',
            'nicename' => (string) $cat['nicename'],
            'name' => trim((string) $cat),
        ];
    }

    $items[] = [
        'post_id' => nsText($item, 'wp', 'post_id'),
        'post_type' => nsText($item, 'wp', 'post_type'),
        'status' => nsText($item, 'wp', 'status'),
        'title' => plainText($item, 'title'),
        'link' => plainText($item, 'link'),
        'pubDate' => plainText($item, 'pubDate'),
        'creator' => nsText($item, 'dc', 'creator'),
        'guid' => plainText($item, 'guid'),
        'description' => plainText($item, 'description'),
        'content' => nsText($item, 'content', 'encoded'),
        'excerpt' => nsText($item, 'excerpt', 'encoded'),
        'post_date' => nsText($item, 'wp', 'post_date'),
        'post_date_gmt' => nsText($item, 'wp', 'post_date_gmt'),
        'post_modified' => nsText($item, 'wp', 'post_modified'),
        'post_modified_gmt' => nsText($item, 'wp', 'post_modified_gmt'),
        'comment_status' => nsText($item, 'wp', 'comment_status'),
        'ping_status' => nsText($item, 'wp', 'ping_status'),
        'post_name' => nsText($item, 'wp', 'post_name'),
        'post_parent' => nsText($item, 'wp', 'post_parent'),
        'menu_order' => nsText($item, 'wp', 'menu_order'),
        'post_password' => nsText($item, 'wp', 'post_password'),
        'is_sticky' => nsText($item, 'wp', 'is_sticky'),
        'attachment_url' => nsText($item, 'wp', 'attachment_url'),
        'terms' => $itemCategories,
        'meta' => $meta,
    ];
}

$payload = [
    'site' => $site,
    'authors' => $authors,
    'categories' => $categories,
    'tags' => $tags,
    'terms' => $terms,
    'items' => $items,
    'parse_warnings' => $warnings,
    'xml_bytes' => filesize($input),
];

$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

if ($json === false) {
    fwrite(STDERR, 'JSON encode failed: '.json_last_error_msg()."\n");
    exit(1);
}

if (file_put_contents($output, $json) === false) {
    fwrite(STDERR, "Could not write {$output}\n");
    exit(1);
}

// ---------------------------------------------------------------------------
// Console summary
// ---------------------------------------------------------------------------
$byType = [];
$byTypeStatus = [];
foreach ($items as $it) {
    $type = $it['post_type'] ?? '(none)';
    $status = $it['status'] ?? '(none)';
    $byType[$type] = ($byType[$type] ?? 0) + 1;
    $byTypeStatus[$type][$status] = ($byTypeStatus[$type][$status] ?? 0) + 1;
}
ksort($byType);

echo "Parsed: {$input}\n";
echo 'XML bytes: '.number_format((int) filesize($input))."\n";
echo 'WXR version: '.$site['wxr_version']."\n";
echo 'Site: '.$site['title'].' ('.$site['base_site_url'].")\n";
echo 'Items: '.count($items)."\n";
echo 'Channel categories: '.count($categories).', tags: '.count($tags).', custom terms: '.count($terms)."\n";
echo 'Parse warnings: '.count($warnings)."\n";
echo "--- items by post_type ---\n";
foreach ($byType as $type => $count) {
    $statuses = $byTypeStatus[$type];
    ksort($statuses);
    $bits = [];
    foreach ($statuses as $s => $c) {
        $bits[] = "{$s}={$c}";
    }
    printf("%-28s %6d   [%s]\n", $type, $count, implode(', ', $bits));
}
echo "Wrote: {$output} (".number_format(strlen($json))." bytes)\n";
