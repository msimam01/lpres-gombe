<?php

/**
 * READ-ONLY report generator.
 *
 * Reads ../work/analysis.json and writes reports 01-05 into
 * docs/lpres-migration-audit/. Never touches the Laravel app or the sources.
 *
 * Usage: php report.php
 */

declare(strict_types=1);

ini_set('memory_limit', '-1');

$outDir = dirname(__DIR__);
$data = json_decode(file_get_contents(__DIR__.'/../work/analysis.json'), true, 512, JSON_THROW_ON_ERROR);

$records = $data['records'];
$attachments = $data['attachments'];
$manifest = $data['manifest'];
$disk = $data['disk'];
$issues = $data['issues'];
$clusters = $data['clusters'];

// Cluster chart images were never added to the download manifest, but nine
// matching PNGs already exist in the Laravel app's public images folder. Each
// mapping below is confirmed by an exact byte-size match at report time.
const CLUSTER_CHARTS = [
    '3847' => 'dairy.png', '3850' => 'beef.png', '3853' => 'sheep-and-goat.png',
    '3856' => 'broiler.png', '3859' => 'eggs.png', '3862' => 'leather.png',
    '3865' => 'feed.png', '3868' => 'fodder.png', '3872' => 'pig.png',
];
$projectRoot = dirname(__DIR__, 3);
$clusterChartVerified = [];
foreach (CLUSTER_CHARTS as $attId => $file) {
    $abs = $projectRoot.'/public/images/lpres/clusters/'.$file;
    $att = null;
    foreach ($attachments as $candidate) {
        if ($candidate['id'] === (string) $attId) {
            $att = $candidate;
            break;
        }
    }
    $clusterChartVerified[$attId] = is_file($abs) && $att !== null && $att['filesize_meta'] === filesize($abs)
        ? 'public/images/lpres/clusters/'.$file
        : null;
}

$manifestByPath = [];
foreach ($manifest as $i => $m) {
    $manifestByPath[$m['relative_path']] = $i;
}
$attById = [];
foreach ($attachments as $a) {
    $attById[$a['id']] = $a;
}

// Migration scope decision per classification.
$scope = [
    'news' => 'yes',
    'project' => 'yes',
    'team_member' => 'yes',
    'value_chain_cluster' => 'yes',
    'value_chain_description' => 'yes',
    'informational' => 'yes',
    'testimonial' => 'yes',
    'spc_profile' => 'yes',
    'news_draft' => 'no',
    'project_draft' => 'no',
    'needs_review' => 'review',
    'listing_page' => 'rebuild',
    'media_page' => 'review',
    'static_page_content' => 'absorb',
    'duplicate_stub' => 'no',
    'junk' => 'no',
    'demo_page' => 'no',
    'demo_content' => 'no',
    'demo_event' => 'no',
    'commerce' => 'no',
    'donation_plumbing' => 'no',
    'legal' => 'no',
    'in_scope' => 'review',
];

/** Media role for an attachment on a content record. */
function mediaRole(array $r, bool $isFeatured): string
{
    if ($isFeatured) {
        return match ($r['classification']) {
            'team_member' => 'team_photo',
            'testimonial' => 'testimonial_photo',
            'demo_event' => 'event_image',
            'value_chain_cluster' => 'cluster_chart',
            default => 'featured',
        };
    }

    return match ($r['classification']) {
        'project' => 'project_gallery',
        'value_chain_cluster' => 'cluster_chart',
        'team_member' => 'team_photo_secondary',
        'spc_profile' => 'spc_profile_image',
        'informational' => 'page_image',
        default => 'embedded',
    };
}

function csvOpen(string $path): array
{
    $fh = fopen($path, 'w');
    fwrite($fh, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads accents correctly.

    return [$fh, $path];
}

function csvRow($fh, array $row): void
{
    fputcsv($fh, $row, ',', '"', '\\');
}

// ---------------------------------------------------------------------------
// 01 - post type inventory
// ---------------------------------------------------------------------------
$path01 = $outDir.'/01-post-type-inventory.csv';
[$fh] = csvOpen($path01);
csvRow($fh, ['post_type', 'status', 'record_count']);

$typeStatus = $data['type_status'];
ksort($typeStatus);
$grand = 0;
foreach ($typeStatus as $type => $statuses) {
    ksort($statuses);
    $subtotal = array_sum($statuses);
    $grand += $subtotal;
    foreach ($statuses as $status => $count) {
        csvRow($fh, [$type, $status, $count]);
    }
    csvRow($fh, [$type, '(subtotal)', $subtotal]);
}
csvRow($fh, ['TOTAL', '(all statuses)', $grand]);
fclose($fh);

// ---------------------------------------------------------------------------
// 02 - content inventory
// ---------------------------------------------------------------------------
$path02 = $outDir.'/02-content-inventory.csv';
[$fh] = csvOpen($path02);
csvRow($fh, [
    'wp_id', 'post_type', 'status', 'title', 'slug', 'publication_date', 'modified_date',
    'category_names', 'tags', 'featured_attachment_id', 'featured_image_relative_path',
    'classification', 'in_migration_scope', 'embedded_media_count',
]);

$sorted = $records;
usort($sorted, function ($a, $b) {
    return [$a['type'], (int) $a['id']] <=> [$b['type'], (int) $b['id']];
});

foreach ($sorted as $r) {
    csvRow($fh, [
        $r['id'],
        $r['type'],
        $r['status'],
        $r['title'],
        $r['slug'],
        $r['post_date'],
        $r['post_modified'],
        implode('; ', array_merge($r['categories'], $r['custom_terms'])),
        implode('; ', $r['tags']),
        $r['featured_id'] ?? '',
        $r['featured_path'] ?? '',
        $r['classification'],
        $scope[$r['classification']] ?? 'review',
        $r['embedded_count'],
    ]);
}
fclose($fh);

// ---------------------------------------------------------------------------
// 03 - content to media mapping
// ---------------------------------------------------------------------------
$path03 = $outDir.'/03-content-media-mapping.csv';
[$fh] = csvOpen($path03);
csvRow($fh, [
    'wp_content_id', 'content_title', 'content_type', 'media_role',
    'wp_attachment_id', 'original_url', 'local_relative_path', 'download_status',
]);

// Reverse index: attachment id => content records referencing it.
$referencedBy = [];
$mapRows = 0;
$unresolvedRows = 0;

usort($sorted, function ($a, $b) {
    return [(int) $a['id']] <=> [(int) $b['id']];
});

foreach ($sorted as $r) {
    if (! in_array($scope[$r['classification']] ?? 'review', ['yes', 'review', 'absorb'], true)) {
        continue;
    }

    if ($r['featured_id'] && isset($attById[$r['featured_id']])) {
        $a = $attById[$r['featured_id']];
        $rel = $r['featured_path'];
        $idx = $rel !== null ? ($manifestByPath[$rel] ?? null) : null;
        $status = $idx !== null ? ($disk[$idx]['exists'] ? 'downloaded' : 'missing_file') : 'not_downloaded';
        if ($status === 'not_downloaded' && isset($clusterChartVerified[$a['id']]) && $clusterChartVerified[$a['id']] !== null) {
            $rel = $clusterChartVerified[$a['id']];
            $status = 'available_in_repo';
        }

        csvRow($fh, [
            $r['id'], $r['title'], $r['type'], mediaRole($r, true),
            $a['id'], $a['url'], $rel ?? '', $status,
        ]);
        $mapRows++;
        if ($status !== 'downloaded') {
            $unresolvedRows++;
        }
        $referencedBy[$a['id']]['featured'][] = $r['id'];
    }

    foreach ($r['embedded'] as $e) {
        $rel = $e['relative_path'];
        $idx = $rel !== null ? ($manifestByPath[$rel] ?? null) : null;
        $status = $idx !== null ? ($disk[$idx]['exists'] ? 'downloaded' : 'missing_file') : 'not_downloaded';
        if ($status === 'not_downloaded' && ($e['attachment_id'] ?? null) !== null && isset($clusterChartVerified[$e['attachment_id']]) && $clusterChartVerified[$e['attachment_id']] !== null) {
            $rel = $clusterChartVerified[$e['attachment_id']];
            $status = 'available_in_repo';
        }
        $url = $e['urls'][0] ?? '';

        csvRow($fh, [
            $r['id'], $r['title'], $r['type'], mediaRole($r, false),
            $e['attachment_id'] ?? '', $url, $rel ?? '', $status,
        ]);
        $mapRows++;
        if ($status !== 'downloaded') {
            $unresolvedRows++;
        }
        if ($e['attachment_id'] !== null) {
            $referencedBy[$e['attachment_id']]['embedded'][] = $r['id'];
        }
    }
}
fclose($fh);

// ---------------------------------------------------------------------------
// 04 - attachment inventory
// ---------------------------------------------------------------------------
$path04 = $outDir.'/04-attachment-inventory.csv';
[$fh] = csvOpen($path04);
csvRow($fh, [
    'wp_attachment_id', 'filename', 'original_url', 'local_relative_path',
    'mime_type', 'file_size_bytes', 'width', 'height', 'download_status',
    'referencing_records', 'used_as',
]);

$attSorted = $attachments;
usort($attSorted, fn ($a, $b) => (int) $a['id'] <=> (int) $b['id']);

foreach ($attSorted as $a) {
    $idx = $a['manifest_index'];
    $rel = $idx !== null ? $manifest[$idx]['relative_path'] : null;

    if ($idx !== null) {
        $status = $disk[$idx]['exists'] ? 'downloaded' : 'missing_file';
    } else {
        $status = 'not_downloaded';
        if (isset($clusterChartVerified[$a['id']]) && $clusterChartVerified[$a['id']] !== null) {
            $rel = $clusterChartVerified[$a['id']];
            $status = 'available_in_repo';
        }
    }

    $refs = $referencedBy[$a['id']] ?? [];
    $ids = array_values(array_unique(array_merge($refs['featured'] ?? [], $refs['embedded'] ?? [])));
    sort($ids);

    $usedAs = [];
    if (! empty($refs['featured'])) {
        $usedAs[] = 'featured';
    }
    if (! empty($refs['embedded'])) {
        $usedAs[] = 'embedded';
    }

    $realSize = $idx !== null ? $disk[$idx]['real_bytes'] : null;
    $realMime = $idx !== null ? $disk[$idx]['real_mime'] : null;
    $realW = $idx !== null ? $disk[$idx]['width'] : null;
    $realH = $idx !== null ? $disk[$idx]['height'] : null;

    csvRow($fh, [
        $a['id'],
        $a['filename'],
        $a['url'],
        $rel ?? '',
        $realMime ?? $a['mime_declared'] ?? '',
        $realSize ?? $a['filesize_meta'] ?? '',
        $realW ?? $a['width'] ?? '',
        $realH ?? $a['height'] ?? '',
        $status,
        implode(' ', array_map(fn ($i) => '#'.$i, $ids)),
        implode('+', $usedAs),
    ]);
}
fclose($fh);

// ---------------------------------------------------------------------------
// 05 - unmatched and issues
// ---------------------------------------------------------------------------
$path05 = $outDir.'/05-unmatched-and-issues.csv';
[$fh] = csvOpen($path05);
csvRow($fh, ['issue_category', 'severity', 'subject', 'detail']);

$order = ['missing_file' => 0, 'empty_file' => 1, 'size_mismatch' => 2, 'extension_mismatch' => 3,
    'unrecognised_image' => 4, 'missing_featured_attachment' => 5, 'featured_not_downloaded' => 6,
    'embedded_not_downloaded' => 7, 'unmatched_embedded_url' => 8, 'duplicate_media' => 9,
    'duplicate_title' => 10, 'duplicate_stub' => 11, 'empty_body' => 12, 'image_only_body' => 13,
    'thin_body' => 14, 'cluster_duplicate_row' => 15, 'cluster_total_mismatch' => 16,
    'cluster_existing_data_conflict' => 17, 'document_not_downloaded' => 18,
    'slug_anomaly' => 19, 'slug_missing' => 20];

$allIssues = $issues;

// Cluster-specific issues derived from the parsed tables.
foreach ($clusters as $c) {
    $seen = [];
    foreach ($c['rows'] as $i => $row) {
        $key = mb_strtolower(trim($row['lga']).'|'.preg_replace('/\s+/u', ' ', trim($row['ward'])));
        if (isset($seen[$key])) {
            $allIssues[] = [
                'category' => 'cluster_duplicate_row',
                'severity' => 'medium',
                'subject' => "#{$c['page_id']} {$c['title']}",
                'detail' => 'Row '.($i + 1)." duplicates row {$seen[$key]} ({$row['lga']} / {$row['ward']} / {$row['beneficiaries']}). The published TOTAL ignores the duplicate.",
            ];
        } else {
            $seen[$key] = $i + 1;
        }
    }

    if ($c['published_total_row'] !== null && $c['published_total_row'] !== $c['total_beneficiaries']) {
        $allIssues[] = [
            'category' => 'cluster_total_mismatch',
            'severity' => 'medium',
            'subject' => "#{$c['page_id']} {$c['title']}",
            'detail' => "Published TOTAL {$c['published_total_row']} but detail rows sum to {$c['total_beneficiaries']}.",
        ];
    }
}

// Documents (PDF) that exist in the export but were never downloaded. The
// media manifest contains only JPG/PNG/JPEG/AVIF, so no PDF has a local copy.
$docIds = ['5532' => '#5523 Newsletter', '6169' => '#5523 Newsletter', '5738' => '#5728 Magazine'];
foreach ($attachments as $a) {
    if (isset($docIds[$a['id']])) {
        $allIssues[] = [
            'category' => 'document_not_downloaded',
            'severity' => 'high',
            'subject' => "{$docIds[$a['id']]} -> {$a['relative_path']}",
            'detail' => "PDF attachment {$a['id']} is referenced by the page but is absent from the media manifest and the download folder.",
        ];
    }
}

// Attachment 2702 is the one featured image missing from the media folder, but
// an exact byte-size copy sits in the design-references folder root.
foreach ($attachments as $a) {
    if ($a['id'] === '2702') {
        $allIssues[] = [
            'category' => 'featured_not_downloaded',
            'severity' => 'medium',
            'subject' => '#2750 Project -> '.$a['relative_path'],
            'detail' => 'Not in the media manifest, but a file named 2-e1737033906962.avif with the identical byte size ('.$a['filesize_meta'].') exists at docs/lpres-design-references/2-e1737033906962.avif, outside the media folder.',
        ];
    }
}

// Existing Laravel cluster rows that already hold data for a cluster the
// WordPress export also supplies (read from database/database.sqlite).
$allIssues[] = [
    'category' => 'cluster_existing_data_conflict',
    'severity' => 'medium',
    'subject' => 'Dairy cluster (existing Laravel row id 2)',
    'detail' => 'Laravel already has slug "dairy" with only 2 of the 11 beneficiary rows (Balanga/Bambam 402, Shongom/Kushi 91). WordPress source has 11 rows totalling 818 and spells the LGA "Shomgom". Decide whether the importer fills the missing 9 rows.',
];

// Slug anomalies
foreach ($data['slug_anomalies'] as $s) {
    $allIssues[] = [
        'category' => $s['slug'] === '' ? 'slug_missing' : 'slug_anomaly',
        'severity' => $s['slug'] === '' ? 'high' : 'low',
        'subject' => "#{$s['id']} {$s['title']}",
        'detail' => $s['slug'] === ''
            ? 'Published record has an empty slug.'
            : "Slug \"{$s['slug']}\" bears no relation to the title (leftover theme demo slug).",
    ];
}

usort($allIssues, function ($a, $b) use ($order) {
    return [$order[$a['category']] ?? 99, $a['subject']] <=> [$order[$b['category']] ?? 99, $b['subject']];
});

foreach ($allIssues as $iss) {
    csvRow($fh, [$iss['category'], $iss['severity'], $iss['subject'], $iss['detail']]);
}
fclose($fh);

// ---------------------------------------------------------------------------
// Cross-check
// ---------------------------------------------------------------------------
$countLines = function (string $path): int {
    $n = 0;
    $fh = fopen($path, 'r');
    while (fgetcsv($fh) !== false) {
        $n++;
    }
    fclose($fh);

    return $n - 1; // exclude header
};

echo "=== REPORTS WRITTEN ===\n";
printf("01-post-type-inventory.csv      %d data rows\n", $countLines($path01));
printf("02-content-inventory.csv        %d data rows\n", $countLines($path02));
printf("03-content-media-mapping.csv    %d data rows (unresolved: %d)\n", $countLines($path03), $unresolvedRows);
printf("04-attachment-inventory.csv     %d data rows\n", $countLines($path04));
printf("05-unmatched-and-issues.csv     %d data rows\n", $countLines($path05));

echo "\n=== SCOPE COUNTS ===\n";
$sc = [];
foreach ($records as $r) {
    $s = $scope[$r['classification']] ?? 'review';
    $sc[$s] = ($sc[$s] ?? 0) + 1;
}
ksort($sc);
print_r($sc);

echo "\n=== DOWNLOAD STATUS TOTALS ===\n";
echo 'files present: '.count(array_filter($disk, fn ($d) => $d['exists'])).' / '.count($disk)."\n";
echo 'attachments downloaded: '.count(array_filter($attachments, fn ($a) => $a['manifest_index'] !== null)).' / '.count($attachments)."\n";
