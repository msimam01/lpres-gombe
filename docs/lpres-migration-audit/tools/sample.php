<?php

declare(strict_types=1);

/** READ-ONLY sample inspector. Usage: php sample.php <post_id> [chars] */
$data = json_decode(file_get_contents(__DIR__.'/../work/wxr.json'), true, 512, JSON_THROW_ON_ERROR);
$id = (string) ($argv[1] ?? '');
$chars = (int) ($argv[2] ?? 2500);

foreach ($data['items'] as $it) {
    if ($it['post_id'] !== $id) {
        continue;
    }

    echo "post_id: {$it['post_id']}\n";
    echo "type: {$it['post_type']}  status: {$it['status']}\n";
    echo "title: {$it['title']}\n";
    echo "slug: {$it['post_name']}\n";
    echo "link: {$it['link']}\n";
    echo "date: {$it['post_date']}  modified: {$it['post_modified']}\n";
    echo "parent: {$it['post_parent']}  menu_order: {$it['menu_order']}\n";
    echo 'terms: '.json_encode($it['terms'], JSON_UNESCAPED_SLASHES)."\n";
    echo 'excerpt ('.strlen((string) $it['excerpt']).'): '.substr((string) $it['excerpt'], 0, 400)."\n";
    echo 'content len: '.strlen((string) $it['content'])."\n";
    echo "------- CONTENT (first {$chars}) -------\n";
    echo substr((string) $it['content'], 0, $chars)."\n";
    echo "------- END CONTENT -------\n";

    foreach ($it['meta'] as $k => $vals) {
        foreach ($vals as $v) {
            $len = strlen($v);
            printf("META %-38s len=%-7d %s\n", $k, $len, $len > 300 ? substr($v, 0, 300).'...' : $v);
        }
    }
}
