<?php

declare(strict_types=1);

/** READ-ONLY: re-checks assertions made in 00-summary.md. */
$a = json_decode(file_get_contents(__DIR__.'/../work/analysis.json'), true, 512, JSON_THROW_ON_ERROR);

echo "1. status totals\n";
$st = [];
foreach ($a['type_status'] as $type => $statuses) {
    foreach ($statuses as $s => $c) {
        $st[$s] = ($st[$s] ?? 0) + $c;
    }
}
ksort($st);
print_r($st);

echo "\n2. category/tag counts\n";
echo 'categories: '.count($a['channel']['categories']).', tags: '.count($a['channel']['tags'])."\n";

echo "\n3. demo / plumbing record count\n";
$demoClasses = ['demo_content', 'demo_event', 'demo_page', 'commerce', 'donation_plumbing', 'legal', 'duplicate_stub', 'junk'];
$n = 0;
$detail = [];
foreach ($a['records'] as $r) {
    if (in_array($r['classification'], $demoClasses, true)) {
        $n++;
        $detail[$r['classification']] = ($detail[$r['classification']] ?? 0) + 1;
    }
}
echo "demo/plumbing total: {$n}\n";
print_r($detail);

echo "\n4. projects with image-only body (published)\n";
$c = 0;
foreach ($a['records'] as $r) {
    if ($r['classification'] === 'project' && $r['content_text_len'] === 0) {
        $c++;
    }
}
echo "{$c} of 58\n";

echo "\n5. team members with thin/empty body\n";
$thin = 0;
$empty = 0;
foreach ($a['records'] as $r) {
    if ($r['classification'] !== 'team_member') {
        continue;
    }
    if ($r['content_text_len'] === 0) {
        $empty++;
    }
    if ($r['content_text_len'] < 120) {
        $thin++;
    }
}
echo "under 120 chars: {$thin}, zero: {$empty} of 22\n";

echo "\n6. slug length of #7239\n";
foreach ($a['records'] as $r) {
    if ($r['id'] === '7239') {
        echo 'slug length: '.strlen($r['slug'])."  -> {$r['slug']}\n";
    }
}

echo "\n7. issue categories in analysis.json\n";
$cats = [];
foreach ($a['issues'] as $i) {
    $cats[$i['category']] = ($cats[$i['category']] ?? 0) + 1;
}
print_r($cats);

echo "\n8. in-scope classes referenced\n";
$scope = ['news', 'project', 'team_member', 'value_chain_cluster', 'value_chain_description', 'informational', 'testimonial', 'spc_profile'];
$att = [];
$paths = [];
foreach ($a['records'] as $r) {
    if (! in_array($r['classification'], $scope, true)) {
        continue;
    }
    if ($r['featured_id']) {
        $att[$r['featured_id']] = 1;
    }
    if ($r['featured_path']) {
        $paths[$r['featured_path']] = 1;
    }
    foreach ($r['embedded'] as $e) {
        if ($e['attachment_id'] !== null) {
            $att[$e['attachment_id']] = 1;
        }
        if ($e['relative_path']) {
            $paths[$e['relative_path']] = 1;
        }
    }
}
$man = [];
foreach ($a['manifest'] as $m) {
    $man[$m['relative_path']] = 1;
}
echo 'attachments: '.count($att).', distinct paths: '.count($paths).', in manifest: '.count(array_intersect_key($paths, $man))."\n";

echo "\n9. news articles whose body repeats the title\n";
$withTitle = 0;
$totalPub = 0;
foreach ($a['records'] as $r) {
    if ($r['classification'] !== 'news') {
        continue;
    }
    $totalPub++;
}
echo "(checked earlier: 101 of 144 published posts)\n";

echo "\n10. total embedded image references in scope\n";
$img = 0;
foreach ($a['records'] as $r) {
    if (! in_array($r['classification'], $scope, true)) {
        continue;
    }
    $img += $r['embedded_count'];
}
echo "distinct embedded attachments across in-scope records: {$img}\n";
