<?php

declare(strict_types=1);

/** READ-ONLY duplicate / overlap detector. */
$data = json_decode(file_get_contents(__DIR__.'/../work/wxr.json'), true, 512, JSON_THROW_ON_ERROR);
$items = $data['items'];

$norm = function (?string $html): string {
    $t = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace('/\s+/u', ' ', $t);

    return trim((string) $t);
};

$scope = ['post', 'project', 'team', 'page', 'tribe_events', 'testimonials', 'service', 'department', 'documentations', 'campaign'];

$records = [];
foreach ($items as $it) {
    if (! in_array($it['post_type'], $scope, true) || $it['status'] !== 'publish') {
        continue;
    }
    $records[] = [
        'id' => $it['post_id'],
        'type' => $it['post_type'],
        'title' => $norm($it['title']),
        'slug' => (string) $it['post_name'],
        'text' => $norm($it['content']),
        'len' => strlen((string) $it['content']),
    ];
}

echo 'Records in scope: '.count($records)."\n";

// --- 1. duplicate normalised titles across records (any type) ---
echo "\n=== DUPLICATE TITLES (across published records) ===\n";
$byTitle = [];
foreach ($records as $r) {
    if ($r['title'] === '') {
        continue;
    }
    $byTitle[mb_strtolower($r['title'])][] = $r;
}
$dupeTitleGroups = 0;
foreach ($byTitle as $title => $group) {
    if (count($group) < 2) {
        continue;
    }
    $dupeTitleGroups++;
    printf("  \"%s\"\n", mb_substr($title, 0, 80));
    foreach ($group as $g) {
        printf("      #%s %-9s len=%-6d slug=%s\n", $g['id'], $g['type'], $g['len'], $g['slug']);
    }
}
echo "  groups: {$dupeTitleGroups}\n";

// --- 2. identical body text across records ---
echo "\n=== IDENTICAL BODY TEXT (>=200 chars, across records) ===\n";
$byText = [];
foreach ($records as $r) {
    if (strlen($r['text']) < 200) {
        continue;
    }
    $byText[md5($r['text'])][] = $r;
}
$n = 0;
foreach ($byText as $hash => $group) {
    if (count($group) < 2) {
        continue;
    }
    $n++;
    printf("  hash %s (%d records, %d chars)\n", substr($hash, 0, 8), count($group), strlen($group[0]['text']));
    foreach ($group as $g) {
        printf("      #%s %-9s %s\n", $g['id'], $g['type'], mb_substr($g['title'], 0, 70));
    }
}
echo "  duplicate-text groups: {$n}\n";

// --- 3. duplicate slugs within the same post type ---
echo "\n=== DUPLICATE SLUGS WITHIN A POST TYPE (published) ===\n";
$bySlug = [];
foreach ($records as $r) {
    $key = $r['type'].'|'.$r['slug'];
    $bySlug[$key][] = $r;
}
$slugDupes = 0;
foreach ($bySlug as $key => $group) {
    if (count($group) < 2) {
        continue;
    }
    $slugDupes++;
    printf("  %s -> %s\n", $key, implode(', ', array_map(fn ($g) => '#'.$g['id'], $group)));
}
echo "  duplicate slug groups: {$slugDupes}\n";

// --- 4. demo-content keyword scan ---
echo "\n=== DEMO-CONTENT KEYWORD SCAN ===\n";
$keywords = ['mayor', 'city visitor', 'birth certificate', 'tax return', 'driving license', 'polution',
    'photography new contest', 'your little help', 'lorem ipsum', 'reinventing government', 'holiday',
    'save soil', 'shopping gathering', 'connected justice', 'bounce back', 'green project'];
foreach ($records as $r) {
    $hay = mb_strtolower($r['title'].' '.$r['text']);
    $hits = [];
    foreach ($keywords as $k) {
        if (str_contains($hay, $k)) {
            $hits[] = $k;
        }
    }
    if ($hits) {
        printf("  #%-6s %-14s hits=%-40.40s %s\n", $r['id'], $r['type'], implode(',', $hits), mb_substr($r['title'], 0, 50));
    }
}

// --- 5. empty or near-empty published records ---
echo "\n=== PUBLISHED RECORDS WITH EMPTY/THIN BODY (<120 chars) ===\n";
foreach ($records as $r) {
    if (strlen($r['text']) < 120) {
        printf("  #%-6s %-14s len=%-5d slug=%-42.42s %s\n", $r['id'], $r['type'], strlen($r['text']), $r['slug'], mb_substr($r['title'], 0, 45));
    }
}
