<?php

/**
 * READ-ONLY duplicate-pair comparison (audit section A).
 *
 * Compares the seven reported post/project and post/page pairs on title, slug,
 * dates, body text, images and metadata. Writes nothing except to stdout.
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

/** Strip tags/entities to comparable prose. */
function plain(?string $html): string
{
    if ($html === null) {
        return '';
    }
    $t = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = str_replace(["\xC2\xA0", "\u{200B}"], ' ', $t);

    return trim(preg_replace('/\s+/u', ' ', $t) ?? '');
}

/** Word-set Jaccard similarity, 0..1. */
function jaccard(string $a, string $b): float
{
    $wa = array_unique(preg_split('/[^a-z0-9]+/i', strtolower($a), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    $wb = array_unique(preg_split('/[^a-z0-9]+/i', strtolower($b), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    if (! $wa && ! $wb) {
        return 1.0;
    }
    $inter = count(array_intersect($wa, $wb));
    $union = count(array_unique(array_merge($wa, $wb)));

    return $union === 0 ? 0.0 : $inter / $union;
}

function metaSummary(array $rec, array $wanted): string
{
    $out = [];
    foreach ($wanted as $k) {
        $v = $rec['meta'][$k] ?? null;
        if ($v === null) {
            continue;
        }
        if (is_array($v)) {
            $v = implode(' | ', array_map('strval', $v));
        }
        $v = trim(preg_replace('/\s+/', ' ', (string) $v));
        if ($v !== '') {
            $out[] = $k.'='.(strlen($v) > 90 ? substr($v, 0, 90).'…' : $v);
        }
    }

    return $out ? implode('; ', $out) : '(none set)';
}

$pairs = [
    ['4618', '5535'],
    ['4878', '3415'],
    ['5451', '5502'],
    ['5907', '6028'],
    ['5963', '6057'],
    ['5992', '6085'],
    ['7349', '7338'],
];

$wantedMeta = ['project_name', 'project_date', 'project_address', 'team_designation',
    'event_start', 'event_end', 'event_venue_id', 'event_organizer_id', '_thumbnail_id'];

$summaryRows = [];

foreach ($pairs as [$aId, $bId]) {
    $a = $recs[$aId] ?? null;
    $b = $recs[$bId] ?? null;

    echo str_repeat('=', 100), "\n";
    echo "PAIR  #{$aId} ({$a['type']})  vs  #{$bId} ({$b['type']})\n";
    echo str_repeat('=', 100), "\n";

    if (! $a || ! $b) {
        echo "  MISSING RECORD\n\n";

        continue;
    }

    $rawA = $items[$aId]['content'] ?? '';
    $rawB = $items[$bId]['content'] ?? '';
    $txtA = plain($rawA);
    $txtB = plain($rawB);

    $rows = [
        'title' => [$a['title'], $b['title']],
        'slug' => [$a['slug'], $b['slug']],
        'status' => [$a['status'], $b['status']],
        'post_date' => [$a['post_date'], $b['post_date']],
        'post_modified' => [$a['post_modified'], $b['post_modified']],
        'categories' => [implode(', ', $a['categories']), implode(', ', $b['categories'])],
        'raw HTML len' => [(string) $a['content_len'], (string) $b['content_len']],
        'text len' => [(string) $a['content_text_len'], (string) $b['content_text_len']],
        'text sha1' => [substr((string) $a['content_sha1'], 0, 12), substr((string) $b['content_sha1'], 0, 12)],
        'featured id' => [(string) ($a['featured_id'] ?? '—'), (string) ($b['featured_id'] ?? '—')],
        'featured path' => [(string) ($a['featured_path'] ?? '—'), (string) ($b['featured_path'] ?? '—')],
        'images embedded' => [(string) $a['embedded_count'], (string) $b['embedded_count']],
        'excerpt' => [substr($a['excerpt_text'] ?? '', 0, 60) ?: '—', substr($b['excerpt_text'] ?? '', 0, 60) ?: '—'],
    ];

    foreach ($rows as $label => $pair) {
        printf("  %-16s A: %s\n", $label, $pair[0]);
        printf("  %-16s B: %s\n", '', $pair[1]);
    }

    printf("  %-16s A: %s\n", 'meta', metaSummary($a, $wantedMeta));
    printf("  %-16s B: %s\n", '', metaSummary($b, $wantedMeta));

    $jac = jaccard($txtA, $txtB);
    $identical = $txtA !== '' && $txtA === $txtB;
    printf("  %-16s %s (jaccard %.3f)\n", 'TEXT MATCH', $identical ? 'IDENTICAL' : ($jac > 0.9 ? 'NEAR-IDENTICAL' : ($jac > 0.6 ? 'PARTIAL OVERLAP' : 'DIFFERENT')), $jac);
    printf("  %-16s A first 160: %s\n", 'preview', substr($txtA, 0, 160));
    printf("  %-16s B first 160: %s\n", '', substr($txtB, 0, 160));

    // Image sets.
    $imgA = array_map(fn ($e) => $e['relative_path'] ?? '?', $a['embedded']);
    $imgB = array_map(fn ($e) => $e['relative_path'] ?? '?', $b['embedded']);
    $onlyA = array_values(array_diff($imgA, $imgB));
    $onlyB = array_values(array_diff($imgB, $imgA));
    $same = array_values(array_intersect($imgA, $imgB));

    printf("  %-16s shared=%d onlyA=%d onlyB=%d\n", 'IMAGE SETS', count($same), count($onlyA), count($onlyB));
    if ($onlyA) {
        echo '    only A: ', implode(', ', array_map(fn ($p) => basename($p), array_slice($onlyA, 0, 5))), (count($onlyA) > 5 ? ' …' : ''), "\n";
    }
    if ($onlyB) {
        echo '    only B: ', implode(', ', array_map(fn ($p) => basename($p), array_slice($onlyB, 0, 5))), (count($onlyB) > 5 ? ' …' : ''), "\n";
    }

    // Does A's text contain B's, or vice versa? Helps decide merge direction.
    $contains = $txtA !== '' && $txtB !== '' && (str_contains($txtA, $txtB) || str_contains($txtB, $txtA));
    printf("  %-16s %s\n", 'SUBSTRING', $contains ? 'one body contains the other' : 'neither contains the other');

    // Paragraph counts.
    $parasA = count(array_filter(preg_split('/\n\s*\n/', strip_tags($rawA)) ?: [], fn ($p) => trim($p) !== ''));
    $parasB = count(array_filter(preg_split('/\n\s*\n/', strip_tags($rawB)) ?: [], fn ($p) => trim($p) !== ''));
    printf("  %-16s A=%d  B=%d\n", 'paragraphs', $parasA, $parasB);

    echo "\n";

    $summaryRows[] = [
        'a' => $aId, 'a_type' => $a['type'], 'b' => $bId, 'b_type' => $b['type'],
        'title' => $a['title'], 'text' => $identical ? 'identical' : sprintf('%.3f', $jac),
        'imgs' => count($same).' shared / '.count($onlyA).' onlyA / '.count($onlyB).' onlyB',
    ];
}

echo "SUMMARY\n";
foreach ($summaryRows as $s) {
    printf("  #%s(%s) ↔ #%s(%s): text=%s, images=%s\n", $s['a'], $s['a_type'], $s['b'], $s['b_type'], $s['text'], $s['imgs']);
}
