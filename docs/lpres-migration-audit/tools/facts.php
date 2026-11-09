<?php

declare(strict_types=1);

/** READ-ONLY: prints the authoritative numbers used in 00-summary.md. */
$a = json_decode(file_get_contents(__DIR__.'/../work/analysis.json'), true, 512, JSON_THROW_ON_ERROR);

$scope = [
    'news' => 'yes', 'project' => 'yes', 'team_member' => 'yes', 'value_chain_cluster' => 'yes',
    'value_chain_description' => 'yes', 'informational' => 'yes', 'testimonial' => 'yes',
    'spc_profile' => 'yes', 'needs_review' => 'review', 'listing_page' => 'rebuild',
    'media_page' => 'review', 'static_page_content' => 'absorb',
];

echo "=== TYPE / STATUS ===\n";
$total = 0;
foreach ($a['type_status'] as $type => $statuses) {
    ksort($statuses);
    $bits = [];
    foreach ($statuses as $s => $c) {
        $bits[] = "$s=$c";
        $total += $c;
    }
    printf("  %-22s %-6d %s\n", $type, array_sum($statuses), implode(' ', $bits));
}
echo "  TOTAL ITEMS: {$total}\n";

echo "\n=== CLASSIFICATION (all 351 analysed records) ===\n";
$cls = [];
foreach ($a['records'] as $r) {
    $cls[$r['classification']]['n'] = ($cls[$r['classification']]['n'] ?? 0) + 1;
    $cls[$r['classification']]['scope'] = $scope[$r['classification']] ?? 'no';
    $cls[$r['classification']]['pub'] = ($cls[$r['classification']]['pub'] ?? 0) + ($r['status'] === 'publish' ? 1 : 0);
}
ksort($cls);
$sum = 0;
foreach ($cls as $k => $v) {
    printf("  %-26s total=%-3d published=%-3d scope=%s\n", $k, $v['n'], $v['pub'], $v['scope']);
    $sum += $v['n'];
}
echo "  SUM: {$sum}\n";

echo "\n=== MEDIA COVERAGE (published, in-scope classes) ===\n";
$g = [];
foreach ($a['records'] as $r) {
    if ($r['status'] !== 'publish') {
        continue;
    }
    $c = $r['classification'];
    if (! isset($g[$c])) {
        $g[$c] = ['n' => 0, 'feat' => 0, 'emb_recs' => 0, 'emb' => 0, 'missing' => 0];
    }
    $g[$c]['n']++;
    if ($r['featured_id']) {
        $g[$c]['feat']++;
    }
    if ($r['embedded_count'] > 0) {
        $g[$c]['emb_recs']++;
    }
    $g[$c]['emb'] += $r['embedded_count'];
}
foreach ($g as $c => $v) {
    printf("  %-26s records=%-3d with_featured=%-3d with_embedded=%-3d embedded_images=%d\n", $c, $v['n'], $v['feat'], $v['emb_recs'], $v['emb']);
}

echo "\n=== TOTALS ===\n";
$inScope = 0;
foreach ($a['records'] as $r) {
    if (($scope[$r['classification']] ?? 'no') === 'yes') {
        $inScope++;
    }
}
echo "  in-scope records (scope=yes): {$inScope}\n";
echo "  manifest rows: {$a['stats']['manifest_rows']}\n";
echo "  unique manifest urls: {$a['stats']['unique_manifest_urls']}\n";
echo "  files on disk: {$a['stats']['files_exist']}\n";
echo "  empty files: {$a['stats']['files_empty']}\n";
printf("  total bytes: %d (%.2f MB)\n", $a['stats']['bytes_total'], $a['stats']['bytes_total'] / 1048576);
echo "  attachments total: {$a['stats']['attachments_total']}, downloaded: {$a['stats']['attachments_in_manifest']}\n";
echo "  duplicate file groups: {$a['stats']['duplicate_groups']} ({$a['stats']['duplicate_files']} redundant files)\n";
echo "  embedded upload urls: {$a['stats']['embedded_unique_urls']}, unmatched: {$a['stats']['embedded_unmatched_urls']}\n";
echo "  issues: {$a['stats']['issues']}\n";
echo "  records analysed: {$a['stats']['records_total']}\n";

echo "\n=== ISSUES BY SEVERITY ===\n";
$sev = [];
foreach ($a['issues'] as $i) {
    $sev[$i['severity']] = ($sev[$i['severity']] ?? 0) + 1;
}
print_r($sev);

echo "\n=== NEWS WITH NO CATEGORY / DRAFTS ===\n";
foreach ($a['records'] as $r) {
    if ($r['classification'] === 'news_draft') {
        echo "  draft post #{$r['id']}: {$r['title']} (slug {$r['slug']})\n";
    }
}
foreach ($a['records'] as $r) {
    if ($r['classification'] === 'project_draft') {
        echo "  draft project #{$r['id']}: {$r['title']}\n";
    }
}

echo "\n=== SCALE OF RICH TEXT ===\n";
$news = [];
foreach ($a['records'] as $r) {
    if ($r['classification'] === 'news') {
        $news[] = $r['content_text_len'];
    }
}
sort($news);
printf("  news text chars: min=%d median=%d max=%d mean=%d\n", $news[0], $news[(int) (count($news) / 2)], end($news), array_sum($news) / count($news));
