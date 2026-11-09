<?php

declare(strict_types=1);

/** READ-ONLY: validates the generated reports against each other and the disk. */
$dir = dirname(__DIR__);
$mediaDir = dirname(__DIR__, 2).'/lpres-design-references/lpres-migration-media';

function readCsv(string $path): array
{
    $fh = fopen($path, 'r');
    $h = fgetcsv($fh);
    $h[0] = preg_replace('/^\xEF\xBB\xBF/', '', $h[0]);
    $rows = [];
    while (($r = fgetcsv($fh)) !== false) {
        if ($r === [null]) {
            continue;
        }
        $rows[] = array_combine($h, array_slice($r, 0, count($h)));
    }
    fclose($fh);

    return [$h, $rows];
}

$files = [
    '00-summary.md', '01-post-type-inventory.csv', '02-content-inventory.csv',
    '03-content-media-mapping.csv', '04-attachment-inventory.csv',
    '05-unmatched-and-issues.csv', '06-migration-field-mapping.md',
];

echo "=== FILES ===\n";
foreach ($files as $f) {
    $p = $dir.'/'.$f;
    printf("  %-34s %s %s\n", $f, is_file($p) ? 'OK ' : 'MISSING', is_file($p) ? number_format(filesize($p)).' bytes' : '');
}

[$h1, $r1] = readCsv($dir.'/01-post-type-inventory.csv');
[$h2, $r2] = readCsv($dir.'/02-content-inventory.csv');
[$h3, $r3] = readCsv($dir.'/03-content-media-mapping.csv');
[$h4, $r4] = readCsv($dir.'/04-attachment-inventory.csv');
[$h5, $r5] = readCsv($dir.'/05-unmatched-and-issues.csv');

echo "\n=== HEADERS ===\n";
foreach (['01' => $h1, '02' => $h2, '03' => $h3, '04' => $h4, '05' => $h5] as $k => $h) {
    echo "  {$k}: ".implode(', ', $h)."\n";
}

echo "\n=== ROW COUNTS ===\n";
printf("  01=%d  02=%d  03=%d  04=%d  05=%d\n", count($r1), count($r2), count($r3), count($r4), count($r5));

// 01 totals must equal the WXR item count.
$grand = 0;
foreach ($r1 as $row) {
    if ($row['post_type'] === 'TOTAL') {
        $grand = (int) $row['record_count'];
        break;
    }
}
echo "  01 grand total: {$grand} (expected 2140)\n";

// 02 vs analysis records
echo '  02 unique wp_ids: '.count(array_unique(array_column($r2, 'wp_id')))."\n";

// Referential integrity: attachment ids in 03 must exist in 04.
$attIds = array_flip(array_column($r4, 'wp_attachment_id'));
$bad = 0;
foreach ($r3 as $row) {
    $id = $row['wp_attachment_id'];
    if ($id !== '' && ! isset($attIds[$id])) {
        $bad++;
    }
}
echo "  03 attachment ids missing from 04: {$bad}\n";

// Every 'downloaded' path in 03 must exist on disk.
$missing = 0;
$checked = 0;
$statuses = [];
foreach ($r3 as $row) {
    $statuses[$row['download_status']] = ($statuses[$row['download_status']] ?? 0) + 1;
    if ($row['download_status'] === 'downloaded') {
        $checked++;
        if (! is_file($mediaDir.'/'.$row['local_relative_path'])) {
            $missing++;
            echo "    MISSING ON DISK: {$row['local_relative_path']}\n";
        }
    }
}
echo "  03 downloaded rows checked: {$checked}, missing on disk: {$missing}\n";
echo '  03 statuses: '.json_encode($statuses)."\n";

// 04 statuses
$st4 = [];
foreach ($r4 as $row) {
    $st4[$row['download_status']] = ($st4[$row['download_status']] ?? 0) + 1;
}
echo '  04 statuses: '.json_encode($st4)."\n";

// 05 severities
$sev = [];
foreach ($r5 as $row) {
    $sev[$row['severity']] = ($sev[$row['severity']] ?? 0) + 1;
}
echo '  05 severities: '.json_encode($sev)."\n";

// 02 scope column consistency with 01 counts for the four core types
echo "\n=== SCOPE COLUMN IN 02 ===\n";
$sc = [];
foreach ($r2 as $row) {
    $sc[$row['in_migration_scope']] = ($sc[$row['in_migration_scope']] ?? 0) + 1;
}
print_r($sc);

// No duplicate wp_id in 02
$ids = array_column($r2, 'wp_id');
echo '  02 duplicate wp_ids: '.(count($ids) - count(array_unique($ids)))."\n";

// 04 referencing records only mention ids that exist in 02
$badRef = 0;
foreach ($r4 as $row) {
    foreach (preg_split('/\s+/', trim($row['referencing_records'])) as $ref) {
        $ref = ltrim($ref, '#');
        if ($ref !== '' && ! isset($attIds['__never__']) && ! in_array($ref, array_column($r2, 'wp_id'), true)) {
            $badRef++;
        }
    }
}
echo "  04 referencing ids not in 02: {$badRef}\n";
