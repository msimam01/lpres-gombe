<?php

/**
 * READ-ONLY probe for audit sections B (testimonials) and C (news categories).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$wxr = json_decode(file_get_contents($root.'/work/wxr.json'), true);
$an = json_decode(file_get_contents($root.'/work/analysis.json'), true);

$items = [];
foreach ($wxr['items'] as $it) {
    $items[(string) $it['post_id']] = $it;
}
$recs = [];
foreach ($an['records'] as $r) {
    $recs[(string) $r['id']] = $r;
}

function plain(?string $html): string
{
    if ($html === null) {
        return '';
    }
    $t = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = str_replace(["\xC2\xA0", "\u{200B}"], ' ', $t);

    return trim(preg_replace('/\s+/u', ' ', $t) ?? '');
}

function md5of(array $an, string $path): ?string
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach ($an['manifest'] as $i => $m) {
            $map[$m['relative_path']] = $an['disk'][$i]['md5'] ?? null;
        }
    }

    return $map[$path] ?? null;
}

// ---------------------------------------------------------------------------
echo "=========== B. TESTIMONIALS ===========\n\n";

$testimonials = array_values(array_filter($an['records'], fn ($r) => $r['type'] === 'testimonials'));

foreach ($testimonials as $r) {
    $id = (string) $r['id'];
    $raw = $items[$id]['content'] ?? '';
    $txt = plain($raw);

    echo "--- #{$id} ---\n";
    echo '  title      : ', $r['title'], "\n";
    echo '  slug       : ', $r['slug'], "\n";
    echo '  status     : ', $r['status'], '  date: ', $r['post_date'], '  modified: ', $r['post_modified'], "\n";
    echo '  categories : ', implode(', ', $r['categories']), "\n";
    echo '  excerpt    : ', $r['excerpt_text'] !== '' ? $r['excerpt_text'] : '(empty)', "\n";
    echo '  quote text : ', $txt !== '' ? $txt : '(EMPTY)', "\n";
    echo '  text len   : ', strlen($txt), '  html len: ', strlen($raw), "\n";
    echo '  sha1       : ', substr(md5($txt), 0, 12), "  (md5 of normalised text)\n";
    echo '  featured   : ', $r['featured_id'] ?? '—', '  ', $r['featured_path'] ?? '—', "\n";
    echo '  images     : ', $r['embedded_count'], "\n";
    echo '  meta keys  : ', implode(', ', array_keys($r['meta'])), "\n";
    foreach ($r['meta'] as $k => $v) {
        $vv = implode(' | ', array_map('strval', (array) $v));
        if (trim($vv) !== '') {
            echo "     {$k} = ", substr(trim(preg_replace('/\s+/', ' ', $vv)), 0, 120), "\n";
        }
    }
    echo "\n";
}

// Quote distinctness.
$texts = [];
foreach ($testimonials as $r) {
    $texts[(string) $r['id']] = plain($items[(string) $r['id']]['content'] ?? '');
}
echo "PAIRWISE QUOTE SIMILARITY (md5 equal / word-overlap):\n";
$ids = array_keys($texts);
for ($i = 0; $i < count($ids); $i++) {
    for ($j = $i + 1; $j < count($ids); $j++) {
        $a = $texts[$ids[$i]];
        $b = $texts[$ids[$j]];
        $wa = array_unique(preg_split('/[^a-z0-9]+/i', strtolower($a), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $wb = array_unique(preg_split('/[^a-z0-9]+/i', strtolower($b), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $inter = count(array_intersect($wa, $wb));
        $union = count(array_unique(array_merge($wa, $wb))) ?: 1;
        printf("  #%s ↔ #%s : %s  overlap=%d words (%.0f%%)\n", $ids[$i], $ids[$j],
            $a === $b ? 'EXACT DUPLICATE' : 'distinct', $inter, 100 * $inter / $union);
    }
}

echo "\nAuthor/attribution values across the five:\n";
$titles = [];
foreach ($testimonials as $r) {
    $titles[trim($r['title'])] = ($titles[trim($r['title'])] ?? 0) + 1;
}
foreach ($titles as $t => $c) {
    echo "  \"{$t}\" × {$c}\n";
}

// ---------------------------------------------------------------------------
echo "\n\n=========== C. NEWS CATEGORIES ===========\n\n";

// Channel-defined category terms (authoritative ids).
$terms = [];
foreach ($wxr['categories'] as $c) {
    $terms[$c['slug']] = $c;
}

$news = array_values(array_filter($an['records'], fn ($r) => $r['classification'] === 'news'));
echo 'Genuine news articles: ', count($news), "\n\n";

$counts = [];
$slugCounts = [];
foreach ($news as $r) {
    foreach ($r['categories'] as $name) {
        $counts[$name] = ($counts[$name] ?? 0) + 1;
    }
    foreach ($r['category_slugs'] as $s) {
        $slugCounts[$s] = ($slugCounts[$s] ?? 0) + 1;
    }
}
arsort($counts);

echo "Category usage by name (news only):\n";
printf("  %-18s %6s  %-28s %s\n", 'NAME', 'COUNT', 'SLUG', 'TERM ID');
foreach ($counts as $name => $n) {
    // Find term by name.
    $slug = '?';
    $tid = '?';
    foreach ($terms as $s => $t) {
        if (trim($t['name']) === $name) {
            $slug = $s;
            $tid = $t['term_id'];
            break;
        }
    }
    printf("  %-18s %6d  %-28s %s\n", $name, $n, $slug, $tid);
}

echo "\nAll channel category terms (including unused):\n";
printf("  %-6s %-20s %-28s %s\n", 'ID', 'NAME', 'SLUG', 'USED BY NEWS');
foreach ($wxr['categories'] as $c) {
    printf("  %-6s %-20s %-28s %s\n", $c['term_id'], $c['name'], $c['slug'], $counts[trim($c['name'])] ?? 0);
}

echo "\nDispatch:\n";
$multi = array_filter($news, fn ($r) => count($r['categories']) > 1);
$none = array_filter($news, fn ($r) => count($r['categories']) === 0);
$one = array_filter($news, fn ($r) => count($r['categories']) === 1);
echo '  exactly 1 category: ', count($one), "\n";
echo '  2+ categories     : ', count($multi), "\n";
echo '  uncategorised     : ', count($none), "\n";

$combos = [];
foreach ($news as $r) {
    $k = implode(' + ', $r['categories']) ?: '(none)';
    $combos[$k] = ($combos[$k] ?? 0) + 1;
}
arsort($combos);
echo "\nCategory combinations:\n";
foreach ($combos as $k => $v) {
    printf("  %-40s %d\n", $k, $v);
}

// Tag usage on news.
$tagCounts = [];
foreach ($news as $r) {
    foreach ($r['tags'] as $t) {
        $tagCounts[$t] = ($tagCounts[$t] ?? 0) + 1;
    }
}
echo "\nTag usage on news:\n";
if (! $tagCounts) {
    echo "  (no tags applied to any news article)\n";
} else {
    arsort($tagCounts);
    foreach ($tagCounts as $t => $c) {
        echo "  {$t} × {$c}\n";
    }
}

// Category usage across ALL content, to spot irrelevant terms.
$allCounts = [];
foreach ($an['records'] as $r) {
    foreach ($r['categories'] as $name) {
        $allCounts[$name] = ($allCounts[$name] ?? 0) + 1;
    }
}
ksort($allCounts);
echo "\nCategory usage across all 351 records:\n";
foreach ($allCounts as $n => $c) {
    printf("  %-18s %d\n", $n, $c);
}
