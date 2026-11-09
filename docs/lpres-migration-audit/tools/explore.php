<?php

declare(strict_types=1);

/** READ-ONLY exploration helper for the audit. */
$data = json_decode(file_get_contents(__DIR__.'/../work/wxr.json'), true, 512, JSON_THROW_ON_ERROR);
$items = $data['items'];

echo "=== CUSTOM TERMS BY TAXONOMY ===\n";
$byTax = [];
foreach ($data['terms'] as $t) {
    $byTax[$t['taxonomy']][] = $t;
}
ksort($byTax);
foreach ($byTax as $tax => $terms) {
    printf("%s (%d): %s\n", $tax, count($terms), implode(', ', array_map(fn ($t) => $t['name'].' ['.$t['slug'].']', $terms)));
}

echo "\n=== CATEGORIES ===\n";
foreach ($data['categories'] as $c) {
    printf("  %s [%s] parent=%s\n", $c['name'], $c['slug'], $c['parent'] !== '' ? $c['parent'] : '-');
}
echo "=== TAGS ===\n";
foreach ($data['tags'] as $t) {
    printf("  %s [%s]\n", $t['name'], $t['slug']);
}

$types = ['page', 'post', 'project', 'service', 'team', 'tribe_events', 'testimonials', 'department', 'documentations', 'campaign'];
foreach ($types as $type) {
    echo "\n=== {$type} ===\n";
    foreach ($items as $it) {
        if ($it['post_type'] !== $type) {
            continue;
        }
        $contentLen = strlen((string) $it['content']);
        $elem = isset($it['meta']['_elementor_data']) ? 'ELEMENTOR' : '';
        $thumb = $it['meta']['_thumbnail_id'][0] ?? '';
        printf(
            "  #%-6s %-8s %-70.70s slug=%-45.45s len=%-6d thumb=%-6s %s\n",
            $it['post_id'],
            $it['status'],
            (string) $it['title'],
            (string) $it['post_name'],
            $contentLen,
            $thumb,
            $elem
        );
    }
}

echo "\n=== META KEYS (count of items carrying key) BY TYPE ===\n";
$keyCounts = [];
foreach ($items as $it) {
    $type = $it['post_type'];
    foreach (array_keys($it['meta']) as $k) {
        $keyCounts[$type][$k] = ($keyCounts[$type][$k] ?? 0) + 1;
    }
}
ksort($keyCounts);
foreach ($keyCounts as $type => $keys) {
    if (in_array($type, ['attachment', 'nav_menu_item'], true)) {
        continue;
    }
    arsort($keys);
    echo "--- {$type}\n";
    foreach (array_slice($keys, 0, 45, true) as $k => $c) {
        printf("    %-52s %d\n", $k, $c);
    }
}
