<?php

/**
 * READ-ONLY probe for audit section D: published projects whose body has no prose.
 * Reports image inventory, attachment captions/descriptions, and whether any
 * description text can be recovered from the source without invention.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$wxr = json_decode(file_get_contents($root.'/work/wxr.json'), true);
$an = json_decode(file_get_contents($root.'/work/analysis.json'), true);

$att = [];
foreach ($an['attachments'] as $a) {
    $att[(string) $a['id']] = $a;
}

$items = [];
foreach ($wxr['items'] as $it) {
    $items[(string) $it['post_id']] = $it;
}

function clean(?string $s): string
{
    $s = html_entity_decode((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return trim(preg_replace('/\s+/u', ' ', strip_tags($s)) ?? '');
}

$projects = array_values(array_filter(
    $an['records'],
    fn ($r) => $r['type'] === 'project' && $r['status'] === 'publish'
));

$imageOnly = array_values(array_filter($projects, fn ($r) => (int) $r['content_text_len'] === 0));
$withText = array_values(array_filter($projects, fn ($r) => (int) $r['content_text_len'] > 0));

printf("Published projects: %d  |  image-only (0 prose chars): %d  |  with prose: %d\n\n",
    count($projects), count($imageOnly), count($withText));

// Index published posts by slug for recovery detection.
$postBySlug = [];
foreach ($an['records'] as $r) {
    if ($r['type'] === 'post' && $r['status'] === 'publish' && $r['slug'] !== '') {
        $postBySlug[$r['slug']] = $r;
    }
}

$recoverable = 0;
$captionRecoverable = 0;

foreach ($imageOnly as $r) {
    $id = (string) $r['id'];
    $raw = $items[$id]['content'] ?? '';

    echo str_repeat('-', 96), "\n";
    echo "#{$id}  ", $r['title'], "\n";
    echo "  slug={$r['slug']}  date={$r['post_date']}  modified={$r['post_modified']}\n";
    echo '  featured: ', $r['featured_id'] ?? '—', ' ', $r['featured_path'] ?? '—', "\n";
    echo '  project_cat: ', $r['custom_terms'] ? implode(', ', array_map(fn ($t) => $t['name'] ?? json_encode($t), $r['custom_terms'])) : '(none)', "\n";

    $pmeta = [];
    foreach (['project_name', 'project_date', 'project_address'] as $k) {
        if (! empty($r['meta'][$k])) {
            $v = trim(implode(' | ', array_map('strval', (array) $r['meta'][$k])));
            if ($v !== '') {
                $pmeta[] = "{$k}=".$v;
            }
        }
    }
    echo '  project meta: ', $pmeta ? implode('; ', $pmeta) : '(none)', "\n";

    // Image inventory with attachment captions/descriptions.
    echo '  images: ', count($r['embedded']), "\n";
    $captions = [];
    foreach ($r['embedded'] as $i => $e) {
        $aid = (string) ($e['attachment_id'] ?? '');
        $path = (string) ($e['relative_path'] ?? '');
        $a = $att[$aid] ?? null;
        $cap = clean($a['caption'] ?? '');
        $desc = clean($a['description'] ?? '');
        $fn = basename($path);
        printf("    %2d. att=%-6s %-42s cap=%s\n", $i + 1, $aid !== '' ? $aid : '—', $fn,
            $cap !== '' ? '"'.substr($cap, 0, 60).'"' : '—');
        if ($cap !== '') {
            $captions[] = $cap;
        }
        if ($desc !== '' && $desc !== $cap) {
            printf("        description: \"%s\"\n", substr($desc, 0, 80));
        }
    }

    // Alt text present anywhere in the body?
    preg_match_all('/alt="([^"]*)"/', $raw, $m);
    $alts = array_filter(array_map('trim', $m[1]), fn ($x) => $x !== '');
    echo '  non-empty alt attributes: ', count($alts), $alts ? ' ('.substr(implode(' | ', array_slice($alts, 0, 3)), 0, 90).')' : '', "\n";

    // Is the same story available as a news post?
    $match = $postBySlug[$r['slug']] ?? null;
    if ($match && $match['id'] !== $id) {
        echo "  RECOVERY: same slug as published post #{$match['id']} (", $match['content_text_len'], " prose chars)\n";
        $recoverable++;
    }
    if ($captions) {
        echo '  RECOVERY: ', count($captions), " attachment caption(s) available\n";
        $captionRecoverable++;
    }
    echo "\n";
}

echo str_repeat('=', 96), "\n";
echo "Image-only projects with a same-slug news post (text recoverable by merge): {$recoverable}\n";
echo "Image-only projects with attachment captions available: {$captionRecoverable}\n";

// Distribution of image counts.
$buckets = [];
foreach ($imageOnly as $r) {
    $b = $r['embedded_count'] === 0 ? '0' : ($r['embedded_count'] === 1 ? '1' : ($r['embedded_count'] <= 5 ? '2-5' : ($r['embedded_count'] <= 10 ? '6-10' : '11+')));
    $buckets[$b] = ($buckets[$b] ?? 0) + 1;
}
ksort($buckets);
echo "\nImage count distribution (image-only projects):\n";
foreach ($buckets as $b => $c) {
    echo "  {$b} images: {$c} projects\n";
}

// Any image-only project with zero images at all?
$zero = array_filter($imageOnly, fn ($r) => $r['embedded_count'] === 0 && empty($r['featured_path']));
echo "\nProjects with neither body text nor any image: ", count($zero), "\n";
foreach ($zero as $r) {
    echo "  #{$r['id']} ", $r['title'], "\n";
}
