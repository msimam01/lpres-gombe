<?php

/**
 * READ-ONLY migration source analyzer for the L-PRES WordPress export.
 *
 * Inputs (never modified):
 *   ../work/wxr.json                       parsed WXR
 *   lpres-design-references/lpres-migration-media/media-manifest.csv
 *   lpres-design-references/lpres-migration-media/<relative_path files>
 *
 * Output:
 *   ../work/analysis.json
 *
 * Usage: php analyze.php
 */

declare(strict_types=1);

ini_set('memory_limit', '-1');

$root = dirname(__DIR__, 3); // project root
$data = json_decode(file_get_contents(__DIR__.'/../work/wxr.json'), true, 512, JSON_THROW_ON_ERROR);
$items = $data['items'];

$mediaDir = $root.'/docs/lpres-design-references/lpres-migration-media';
$manifestCsv = $mediaDir.'/media-manifest.csv';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function text(?string $html): string
{
    $t = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace('/\s+/u', ' ', $t);

    return trim((string) $t);
}

function unserializeMeta(?string $value): mixed
{
    if ($value === null || $value === '') {
        return null;
    }

    $result = @unserialize($value, ['allowed_classes' => false]);

    return $result === false && $value !== 'b:0;' ? null : $result;
}

/** Normalise a WordPress upload filename to its "original" stem for matching. */
function originalBasename(string $basename): string
{
    $basename = rawurldecode($basename);

    // WordPress generated resized variants: name-1024x682.jpg
    if (preg_match('/^(.*)-\d+x\d+(\.[a-z0-9]+)$/i', $basename, $m)) {
        $basename = $m[1].$m[2];
    }

    // WordPress image-edit copies carry a -e<timestamp> suffix: name-e1737033906962.jpg
    $basename = preg_replace('/-e\d{10,}(\.[a-z0-9]+)$/i', '$1', $basename);

    return $basename;
}

// ---------------------------------------------------------------------------
// 1. Manifest
// ---------------------------------------------------------------------------
$fh = fopen($manifestCsv, 'r');
$header = fgetcsv($fh);
$header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
$manifest = [];
while (($row = fgetcsv($fh)) !== false) {
    if ($row === [null] || count($row) < 2) {
        continue;
    }
    $assoc = array_combine($header, array_slice($row, 0, count($header)));
    $manifest[] = $assoc;
}
fclose($fh);

$manifestByPath = [];
$manifestByUrl = [];
foreach ($manifest as $i => $m) {
    $manifestByPath[$m['relative_path']] = $i;
    $manifestByUrl[$m['url']] = $i;
    $manifest[$i]['_refs'] = array_values(array_filter(array_map('trim', explode('|', $m['referenced_by']))));
}

// ---------------------------------------------------------------------------
// 2. Attachments
// ---------------------------------------------------------------------------
$attachments = [];
$attachmentsByOrigBasename = [];

foreach ($items as $it) {
    if ($it['post_type'] !== 'attachment') {
        continue;
    }

    $meta = $it['meta'];
    $file = $meta['_wp_attached_file'][0] ?? null;
    $url = (string) ($it['attachment_url'] ?? '');

    if (! $file && $url !== '') {
        $pos = strpos($url, '/wp-content/uploads/');
        $file = $pos !== false ? rawurldecode(substr($url, $pos + strlen('/wp-content/uploads/'))) : null;
    }

    $metaData = unserializeMeta($meta['_wp_attachment_metadata'][0] ?? null);
    $mime = null;
    $width = null;
    $height = null;
    $sizes = [];
    $metaFilesize = null;

    if (is_array($metaData)) {
        $mime = $metaData['mime-type'] ?? null;
        $width = isset($metaData['width']) ? (int) $metaData['width'] : null;
        $height = isset($metaData['height']) ? (int) $metaData['height'] : null;
        $metaFilesize = isset($metaData['filesize']) ? (int) $metaData['filesize'] : null;
        foreach (($metaData['sizes'] ?? []) as $size => $info) {
            if (is_array($info) && isset($info['file'])) {
                $sizes[$size] = $info['file'];
            }
        }
    }

    $path = $file ? str_replace('\\', '/', $file) : null;

    $attachments[$it['post_id']] = [
        'id' => $it['post_id'],
        'title' => $it['title'],
        'filename' => $path ? basename($path) : ($url !== '' ? basename(parse_url($url, PHP_URL_PATH) ?: '') : ''),
        'url' => $url,
        'relative_path' => $path,
        'mime_declared' => $mime,
        'filesize_meta' => $metaFilesize,
        'width' => $width,
        'height' => $height,
        'sizes' => $sizes,
        'parent' => $it['post_parent'],
        'status' => $it['status'],
        'post_date' => $it['post_date'],
        'description' => (string) $it['description'],
        'caption' => (string) $it['excerpt'],
        'manifest_index' => $path !== null && isset($manifestByPath[$path]) ? $manifestByPath[$path] : null,
    ];

    if ($path !== null) {
        $key = strtolower(originalBasename(basename($path)));
        $attachmentsByOrigBasename[$key][] = $it['post_id'];
    }
}

// Manifest lookup keyed by the "original" filename stem, so a content URL that
// points at a resized variant (foo-1024x682.jpg) finds the downloaded original.
$manifestByStem = [];
foreach ($manifest as $i => $m) {
    $stem = strtolower(originalBasename(basename($m['relative_path'])));
    $manifestByStem[$stem][] = $i;
}

// Attachment lookup by filename stem. Two keys per attachment: the exact
// basename and the basename with WordPress' "-scaled" suffix removed, because
// large uploads are stored as foo-scaled.jpg while content links to
// foo-1024x682.jpg (original stem foo.jpg).
$attByStem = [];
foreach ($attachments as $id => $a) {
    if (! $a['relative_path']) {
        continue;
    }
    $base = strtolower(basename($a['relative_path']));
    $attByStem[$base][] = $id;

    foreach ([preg_replace('/-scaled(\.[a-z0-9]+)$/i', '$1', $base), strtolower(originalBasename(basename($a['relative_path'])))] as $alias) {
        if ($alias !== $base) {
            $attByStem[$alias][] = $id;
        }
    }
}

/**
 * Resolve one uploads URL to [attachmentId|null, manifestIndex|null].
 */
function resolveUpload(string $url, array $attByStem, array $manifestByStem, array $attachments): array
{
    $base = rawurldecode(basename(parse_url($url, PHP_URL_PATH) ?: ''));
    $stem = strtolower(originalBasename($base));
    $exact = strtolower($base);

    // Prefer an attachment whose stored filename matches the URL exactly, then
    // fall back to the size-stripped stem (covers -1024x682 style variants).
    $attId = $attByStem[$exact][0] ?? $attByStem[$stem][0] ?? null;
    $manIdx = $manifestByStem[$exact][0] ?? $manifestByStem[$stem][0] ?? null;

    // Fall back to the attachment's own downloaded file. This covers large
    // uploads stored as foo-scaled.jpg while content links to foo-1024x682.jpg.
    if ($manIdx === null && $attId !== null && isset($attachments[$attId])) {
        $manIdx = $attachments[$attId]['manifest_index'];
    }

    return [$attId, $manIdx];
}

// ---------------------------------------------------------------------------
// 3. Disk verification (existence, real size, real mime, duplicate content)
// ---------------------------------------------------------------------------
$hashCache = [];
$diskVerified = [];

foreach ($manifest as $i => $m) {
    $abs = $mediaDir.'/'.str_replace('\\', '/', $m['relative_path']);
    $exists = is_file($abs);
    $realSize = $exists ? filesize($abs) : null;
    $readable = $exists && is_readable($abs);

    $realMime = null;
    $imgW = null;
    $imgH = null;

    if ($exists && $realSize > 0) {
        $info = @getimagesize($abs);
        if (is_array($info)) {
            $realMime = $info['mime'] ?? null;
            $imgW = $info[0] ?? null;
            $imgH = $info[1] ?? null;
        }
    }

    $hash = null;
    if ($exists && $realSize > 0) {
        if (! isset($hashCache[$abs])) {
            $hashCache[$abs] = md5_file($abs);
        }
        $hash = $hashCache[$abs];
    }

    $declaredBytes = $m['bytes'] !== '' ? (int) $m['bytes'] : null;

    $diskVerified[$i] = [
        'exists' => $exists,
        'readable' => $readable,
        'real_bytes' => $realSize,
        'bytes_match_manifest' => $declaredBytes === null ? null : $declaredBytes === $realSize,
        'real_mime' => $realMime,
        'width' => $imgW,
        'height' => $imgH,
        'md5' => $hash,
        'extension' => strtolower(pathinfo($m['relative_path'], PATHINFO_EXTENSION)),
        'is_empty' => $exists && $realSize === 0,
    ];
}

// Duplicate file content (same md5, different relative paths)
$byHash = [];
foreach ($diskVerified as $i => $v) {
    if ($v['md5'] !== null) {
        $byHash[$v['md5']][] = $i;
    }
}
$duplicateGroups = [];
foreach ($byHash as $hash => $idxs) {
    if (count($idxs) > 1) {
        $duplicateGroups[$hash] = array_map(fn ($i) => $manifest[$i]['relative_path'], $idxs);
    }
}

// ---------------------------------------------------------------------------
// 4. Content classification
// ---------------------------------------------------------------------------
$postClasses = [
    '3670' => ['spc_profile', 'WordPress post holding the State Project Coordinator editorial profile; Laravel renders /spc-profile outside the news table.'],
    '3464' => ['value_chain_description', 'Sheep & Goat value chain description; belongs to value chain clusters, not news.'],
    '3487' => ['value_chain_description', 'Poultry value chain description; belongs to value chain clusters, not news.'],
    '3501' => ['value_chain_description', 'Beef value chain description; belongs to value chain clusters, not news.'],
    '3510' => ['value_chain_description', 'Dairy value chain description; belongs to value chain clusters, not news.'],
    '6786' => ['static_page_content', 'Repeat of "Our Vision" page content (identical body to page 6821).'],
    '6791' => ['static_page_content', 'Repeat of "Core Values" page content (identical body to page 6828).'],
    '3638' => ['junk', 'Body is the single word "Akko"; LGA dashboard demo stub.'],
    '4457' => ['needs_review', 'Published but body is empty.'],
    '4642' => ['needs_review', 'Published but body is empty.'],
    '7090' => ['needs_review', 'Published but body is empty.'],
    '7285' => ['needs_review', 'Body is a bare Facebook reel URL; video item, not an article.'],
];

$pageClasses = [
    // Value chain cluster pages: a beneficiary table plus one chart image.
    '3846' => 'value_chain_cluster', '3849' => 'value_chain_cluster', '3852' => 'value_chain_cluster',
    '3855' => 'value_chain_cluster', '3858' => 'value_chain_cluster', '3861' => 'value_chain_cluster',
    '3864' => 'value_chain_cluster', '3867' => 'value_chain_cluster', '3870' => 'value_chain_cluster',
    // Genuine L-PRES static content pages.
    '49' => 'informational', '65' => 'informational', '6778' => 'informational',
    '6821' => 'informational', '6828' => 'informational',
    // Elementor archive / landing pages that the Laravel React routes replace.
    '45' => 'listing_page', '53' => 'listing_page', '2750' => 'listing_page', '2560' => 'listing_page',
    // Media / embed pages.
    '5523' => 'media_page', '5728' => 'media_page', '6213' => 'media_page', '5526' => 'media_page',
    // Theme demo pages (city-government filler content).
    '51' => 'demo_page', '55' => 'demo_page', '57' => 'demo_page', '59' => 'demo_page',
    '61' => 'demo_page', '2346' => 'demo_page', '2436' => 'demo_page', '1376' => 'demo_page',
    '1895' => 'demo_page', '1882' => 'demo_page', '2818' => 'demo_page', '47' => 'demo_page',
    '2' => 'demo_page', '2558' => 'demo_page', '7693' => 'demo_page',
    // WooCommerce plumbing.
    '9' => 'commerce', '10' => 'commerce', '11' => 'commerce', '12' => 'commerce', '14' => 'commerce',
    '2493' => 'commerce', '2494' => 'commerce', '2495' => 'commerce', '2496' => 'commerce', '2498' => 'commerce',
    // GiveWP donation plumbing.
    '15' => 'donation_plumbing', '16' => 'donation_plumbing', '17' => 'donation_plumbing',
    '1873' => 'donation_plumbing', '1874' => 'donation_plumbing', '1875' => 'donation_plumbing',
    '2461' => 'donation_plumbing',
    '3' => 'legal', '13' => 'legal', '2497' => 'legal', '2559' => 'legal',
    '7338' => 'duplicate_stub',
];

// ---------------------------------------------------------------------------
// 5. Media extraction per content record
// ---------------------------------------------------------------------------
$uploadUrlRe = '~https?://[^\s"\'<>()\\\\]+/wp-content/uploads/[^\s"\'<>()\\\\?#]+~i';

/**
 * Extract every uploads URL from a content blob, plus the attachment ids that
 * WordPress embeds as `wp-image-<id>` classes or JSON "id" fields.
 */
function extractUploadUrls(string $html): array
{
    global $uploadUrlRe;
    $urls = [];

    foreach (['src', 'href', 'data-src'] as $attr) {
        if (preg_match_all('/'.$attr.'="([^"]+)"/i', $html, $m)) {
            foreach ($m[1] as $u) {
                if (str_contains($u, '/wp-content/uploads/')) {
                    $urls[] = $u;
                }
            }
        }
    }

    if (preg_match_all('/srcset="([^"]+)"/i', $html, $m)) {
        foreach ($m[1] as $set) {
            foreach (explode(',', $set) as $part) {
                $u = trim(explode(' ', trim($part))[0]);
                if (str_contains($u, '/wp-content/uploads/')) {
                    $urls[] = $u;
                }
            }
        }
    }

    if (preg_match_all($uploadUrlRe, $html, $m)) {
        foreach ($m[0] as $u) {
            $urls[] = $u;
        }
    }

    return $urls;
}

/** Extract embedded attachment ids from wp-image-N classes and elementor image ids. */
function extractAttachmentIds(string $html, string $elementor): array
{
    $ids = [];
    if (preg_match_all('/wp-image-(\d+)/i', $html, $m)) {
        foreach ($m[1] as $id) {
            $ids[] = $id;
        }
    }
    // Elementor image widgets: {"id":123,"url":"..."} or "image":{"url":..,"id":123}
    if ($elementor !== '' && preg_match_all('~"id"\s*:\s*"?(\d+)"?\s*,\s*"url"\s*:\s*"[^"]*wp-content/uploads~i', $elementor, $m)) {
        foreach ($m[1] as $id) {
            $ids[] = $id;
        }
    }

    return $ids;
}

$records = [];
$allEmbeddedUrls = [];
$unmatchedUrls = [];

foreach ($items as $it) {
    $type = $it['post_type'];
    if ($type === 'attachment' || in_array($type, ['nav_menu_item', 'custom_css', 'wp_global_styles', 'wp_navigation', 'mc4wp-form', 'wpcf7_contact_form', 'elementor_library', 'give_forms', 'give_payment', 'donation', 'product', 'product_variation', 'tribe_venue', 'tribe_organizer'], true)) {
        continue;
    }

    $content = (string) ($it['content'] ?? '');
    $elementor = (string) ($it['meta']['_elementor_data'][0] ?? '');
    $thumbnailId = $it['meta']['_thumbnail_id'][0] ?? null;

    // ---- classification
    [$classification, $note] = ['in_scope', ''];
    if ($type === 'post') {
        if (isset($postClasses[$it['post_id']])) {
            [$classification, $note] = $postClasses[$it['post_id']];
        } else {
            $classification = $it['status'] === 'publish' ? 'news' : 'news_draft';
        }
    } elseif ($type === 'page') {
        $classification = $pageClasses[$it['post_id']] ?? 'needs_review';
    } elseif ($type === 'project') {
        $classification = $it['status'] === 'publish' ? 'project' : 'project_draft';
    } elseif ($type === 'team') {
        $classification = 'team_member';
    } elseif ($type === 'tribe_events') {
        $classification = 'demo_event';
        $note = 'Demo Events Calendar content (venue New Hyde Park NY, organizer Kevin Martin, cost 49).';
    } elseif ($type === 'testimonials') {
        $classification = 'testimonial';
    } elseif (in_array($type, ['service', 'department', 'documentations', 'campaign'], true)) {
        $classification = 'demo_content';
        $note = 'Theme demo record: body identical to every other record of this type.';
    }

    // ---- media
    $urls = extractUploadUrls($content);
    foreach (extractUploadUrls((string) $it['excerpt']) as $u) {
        $urls[] = $u;
    }
    $urls = array_values(array_unique($urls));

    // Resolve every content URL to an attachment and a concrete downloaded file.
    $resolved = [];   // key => ['attachment_id','urls','manifest_index','relative_path','resolution']
    foreach ($urls as $u) {
        $allEmbeddedUrls[$u] = true;
        [$aid, $manIdx] = resolveUpload($u, $attByStem, $manifestByStem, $attachments);

        if ($aid === null && $manIdx === null) {
            $unmatchedUrls[$u][] = $it['post_id'];

            continue;
        }

        $key = $aid ?? ('manifest:'.$manIdx);
        if (! isset($resolved[$key])) {
            $manPath = $manIdx !== null ? $manifest[$manIdx]['relative_path'] : null;
            $attPath = $aid !== null ? $attachments[$aid]['relative_path'] : null;
            $resolved[$key] = [
                'attachment_id' => $aid,
                'urls' => [],
                'manifest_index' => $manIdx,
                'relative_path' => $manPath ?? $attPath,
                'resolution' => $aid !== null && $manIdx !== null ? 'attachment+manifest'
                    : ($aid !== null ? 'attachment_only' : 'manifest_only'),
            ];
        }
        $resolved[$key]['urls'][] = $u;

        if ($resolved[$key]['manifest_index'] === null && $manIdx !== null) {
            $resolved[$key]['manifest_index'] = $manIdx;
            $resolved[$key]['relative_path'] = $manifest[$manIdx]['relative_path'];
        }
    }

    foreach (extractAttachmentIds($content, $elementor) as $aid) {
        if (isset($attachments[$aid]) && ! isset($resolved[$aid])) {
            $att = $attachments[$aid];
            $resolved[$aid] = [
                'attachment_id' => $aid,
                'urls' => [],
                'manifest_index' => $att['manifest_index'],
                'relative_path' => $att['manifest_index'] !== null ? $manifest[$att['manifest_index']]['relative_path'] : $att['relative_path'],
                'resolution' => $att['manifest_index'] !== null ? 'attachment+manifest' : 'attachment_only',
            ];
        } elseif (isset($attachments[$aid])) {
            $resolved[$aid]['attachment_id'] = $aid;
        }
    }

    // ---- serialized theme meta
    $projectMeta = unserializeMeta($it['meta']['towngov_meta_projects'][0] ?? null);
    $teamMeta = unserializeMeta($it['meta']['towngov_meta_team'][0] ?? null);
    $eventMeta = unserializeMeta($it['meta']['towngov_meta_event'][0] ?? null);

    $records[] = [
        'id' => $it['post_id'],
        'type' => $type,
        'status' => $it['status'],
        'classification' => $classification,
        'classification_note' => $note,
        'title' => text($it['title']),
        'raw_title' => $it['title'],
        'slug' => (string) $it['post_name'],
        'link' => $it['link'],
        'post_date' => $it['post_date'],
        'post_modified' => $it['post_modified'],
        'author' => $it['creator'],
        'parent' => $it['post_parent'],
        'menu_order' => $it['menu_order'],
        'terms' => $it['terms'],
        'categories' => array_values(array_map(fn ($t) => $t['name'], array_filter($it['terms'], fn ($t) => $t['domain'] === 'category'))),
        'category_slugs' => array_values(array_map(fn ($t) => $t['nicename'], array_filter($it['terms'], fn ($t) => $t['domain'] === 'category'))),
        'tags' => array_values(array_map(fn ($t) => $t['name'], array_filter($it['terms'], fn ($t) => $t['domain'] === 'post_tag'))),
        'custom_terms' => array_values(array_map(
            fn ($t) => $t['domain'].':'.$t['name'].' ['.$t['nicename'].']',
            array_filter($it['terms'], fn ($t) => ! in_array($t['domain'], ['category', 'post_tag'], true))
        )),
        'excerpt_raw' => (string) $it['excerpt'],
        'excerpt_text' => text($it['excerpt']),
        'content_len' => strlen($content),
        'content_text_len' => strlen(text($content)),
        'content_sha1' => sha1(text($content)),
        'featured_id' => $thumbnailId,
        'featured_path' => (function () use ($thumbnailId, $attachments, $manifest) {
            if ($thumbnailId === null || ! isset($attachments[$thumbnailId])) {
                return null;
            }
            $mi = $attachments[$thumbnailId]['manifest_index'];

            return $mi !== null ? $manifest[$mi]['relative_path'] : null;
        })(),
        'embedded' => array_values(array_map(function ($key) use ($resolved) {
            $r = $resolved[$key];

            return [
                'attachment_id' => $r['attachment_id'],
                'urls' => array_values(array_unique($r['urls'])),
                'manifest_index' => $r['manifest_index'],
                'relative_path' => $r['relative_path'],
                'resolution' => $r['resolution'],
            ];
        }, array_keys($resolved))),
        'embedded_count' => count($resolved),
        'video_refs' => (function () use ($content) {
            if (preg_match_all('~(?:youtube\.com|youtu\.be|vimeo\.com|facebook\.com/reel)[^\s"\'<>]*~i', $content, $m)) {
                return array_values(array_unique($m[0]));
            }

            return [];
        })(),
        'meta' => [
            'project_name' => is_array($projectMeta) ? ($projectMeta['project_name'] ?? null) : null,
            'project_date' => is_array($projectMeta) ? ($projectMeta['project_date'] ?? null) : null,
            'project_address' => is_array($projectMeta) ? ($projectMeta['project_address'] ?? null) : null,
            'project_date_title' => is_array($projectMeta) ? ($projectMeta['project_date_title'] ?? null) : null,
            'team_designation' => is_array($teamMeta) ? ($teamMeta['team_designation'] ?? null) : null,
            'team_email' => is_array($teamMeta) ? ($teamMeta['team_email'] ?? null) : null,
            'team_phone' => is_array($teamMeta) ? ($teamMeta['team_phone'] ?? null) : null,
            'team_image_caption' => is_array($teamMeta) ? ($teamMeta['team_image_caption'] ?? null) : null,
            'event_start' => $it['meta']['_EventStartDate'][0] ?? null,
            'event_end' => $it['meta']['_EventEndDate'][0] ?? null,
            'event_cost' => $it['meta']['_EventCost'][0] ?? null,
            'event_url' => $it['meta']['_EventURL'][0] ?? null,
            'event_venue_id' => $it['meta']['_EventVenueID'][0] ?? null,
            'event_organizer_id' => $it['meta']['_EventOrganizerID'][0] ?? null,
            'event_meta_present' => is_array($eventMeta),
        ],
        '_content' => $content,
    ];
}

// ---------------------------------------------------------------------------
// 6. Cluster beneficiary tables
// ---------------------------------------------------------------------------
$clusters = [];
foreach ($records as $r) {
    if ($r['classification'] !== 'value_chain_cluster') {
        continue;
    }
    $html = $r['_content'];
    $rows = [];
    $totalRow = null;
    if (preg_match('#<table.*?</table>#is', $html, $tm)) {
        if (preg_match_all('#<tr[^>]*>(.*?)</tr>#is', $tm[0], $trs)) {
            foreach ($trs[1] as $tr) {
                if (! preg_match_all('#<td[^>]*>(.*?)</td>#is', $tr, $tds)) {
                    continue;
                }
                $cells = array_map(fn ($c) => trim(html_entity_decode(strip_tags($c), ENT_QUOTES | ENT_HTML5, 'UTF-8')), $tds[1]);
                $cells = array_map(fn ($c) => trim(preg_replace('/\x{00A0}/u', ' ', $c)), $cells);
                if (count($cells) !== 4) {
                    continue;
                }
                if (strcasecmp($cells[0], 'S/NO') === 0) {
                    continue;
                }
                // Skip summary rows such as "TOTAL | | | 5221", but remember
                // the published total so it can be reconciled with the rows.
                if (strcasecmp(trim($cells[1]), 'TOTAL') === 0) {
                    $totalRow = ctype_digit($cells[3]) ? (int) $cells[3] : null;

                    continue;
                }
                if (trim($cells[2]) === '') {
                    continue;
                }
                if (! ctype_digit($cells[3])) {
                    continue;
                }
                $rows[] = ['lga' => $cells[1], 'ward' => $cells[2], 'beneficiaries' => (int) $cells[3]];
            }
        }
    }
    $clusters[] = [
        'page_id' => $r['id'],
        'title' => $r['title'],
        'slug' => $r['slug'],
        'rows' => $rows,
        'row_count' => count($rows),
        'total_beneficiaries' => array_sum(array_column($rows, 'beneficiaries')),
        'published_total_row' => $totalRow,
        'image_ids' => array_column($r['embedded'], 'attachment_id'),
    ];
}

// ---------------------------------------------------------------------------
// 7. Assemble issues
// ---------------------------------------------------------------------------
$issues = [];

// 7a. manifest entries whose file is missing / empty / wrong size
foreach ($diskVerified as $i => $v) {
    $m = $manifest[$i];
    if (! $v['exists']) {
        $issues[] = ['category' => 'missing_file', 'severity' => 'high', 'subject' => $m['relative_path'], 'detail' => 'Manifest row exists but no file on disk.'];
    } elseif ($v['is_empty']) {
        $issues[] = ['category' => 'empty_file', 'severity' => 'high', 'subject' => $m['relative_path'], 'detail' => 'File exists but is 0 bytes.'];
    } elseif ($v['bytes_match_manifest'] === false) {
        $issues[] = ['category' => 'size_mismatch', 'severity' => 'medium', 'subject' => $m['relative_path'], 'detail' => 'Manifest bytes '.$m['bytes'].' vs disk '.$v['real_bytes'].'.'];
    }
}

// 7b. extension vs real mime
$extMimeMap = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'avif' => 'image/avif', 'pdf' => 'application/pdf'];
foreach ($diskVerified as $i => $v) {
    if ($v['real_mime'] === null) {
        if ($v['exists']) {
            $issues[] = ['category' => 'unrecognised_image', 'severity' => 'medium', 'subject' => $manifest[$i]['relative_path'], 'detail' => 'getimagesize() could not identify the file; check it manually.'];
        }

        continue;
    }
    $expected = $extMimeMap[$v['extension']] ?? null;
    if ($expected !== null && $v['real_mime'] !== $expected) {
        $issues[] = ['category' => 'extension_mismatch', 'severity' => 'medium', 'subject' => $manifest[$i]['relative_path'], 'detail' => "Extension .{$v['extension']} but real MIME {$v['real_mime']}."];
    }
}

// 7c. duplicate file content
foreach ($duplicateGroups as $hash => $paths) {
    $issues[] = ['category' => 'duplicate_media', 'severity' => 'low', 'subject' => implode(' | ', $paths), 'detail' => 'Identical file content (md5 '.substr($hash, 0, 12).') under '.count($paths).' names.'];
}

// 7d. embedded URLs that match no attachment
foreach ($unmatchedUrls as $url => $ids) {
    $issues[] = ['category' => 'unmatched_embedded_url', 'severity' => 'high', 'subject' => $url, 'detail' => 'Referenced in content #'.implode(', #', array_unique($ids)).' but no attachment record matches.'];
}

// 7d-2. embedded media that resolves to an attachment with no downloaded file
foreach ($records as $r) {
    foreach ($r['embedded'] as $e) {
        if ($e['manifest_index'] === null) {
            $issues[] = [
                'category' => 'embedded_not_downloaded',
                'severity' => 'high',
                'subject' => "#{$r['id']} {$r['title']}",
                'detail' => 'Embedded attachment '.($e['attachment_id'] ?? '?').' ('.($e['relative_path'] ?? 'no path').') has no downloaded file.',
            ];
        }
    }
}

// 7e. featured attachment missing / not in manifest
foreach ($records as $r) {
    if ($r['featured_id'] === null || $r['featured_id'] === '') {
        continue;
    }
    if (! isset($attachments[$r['featured_id']])) {
        $issues[] = ['category' => 'missing_featured_attachment', 'severity' => 'high', 'subject' => "#{$r['id']} {$r['title']}", 'detail' => "Featured attachment {$r['featured_id']} has no attachment record."];

        continue;
    }
    $att = $attachments[$r['featured_id']];
    if ($att['manifest_index'] === null) {
        $issues[] = ['category' => 'featured_not_downloaded', 'severity' => 'high', 'subject' => "#{$r['id']} {$r['title']}", 'detail' => "Featured attachment {$att['id']} ({$att['relative_path']}) is not in the media manifest."];
    }
}

// 7f. duplicate titles / identical bodies across in-scope records
$titleGroups = [];
foreach ($records as $r) {
    if ($r['title'] === '' || ! in_array($r['classification'], ['news', 'project', 'informational', 'value_chain_cluster', 'value_chain_description', 'static_page_content', 'testimonial', 'demo_event', 'demo_content'], true)) {
        continue;
    }
    $titleGroups[mb_strtolower($r['title'])][] = $r;
}
foreach ($titleGroups as $title => $group) {
    if (count($group) < 2) {
        continue;
    }
    $issues[] = [
        'category' => 'duplicate_title',
        'severity' => 'medium',
        'subject' => $group[0]['title'],
        'detail' => 'Same title on '.implode(', ', array_map(fn ($g) => "#{$g['id']} ({$g['type']})", $group)).' — decide which record survives.',
    ];
}
foreach ($records as $r) {
    if ($r['classification'] === 'duplicate_stub') {
        $issues[] = ['category' => 'duplicate_stub', 'severity' => 'medium', 'subject' => "#{$r['id']} {$r['title']}", 'detail' => 'Empty page duplicating published post 7349.'];
    }
}

// 7g. in-scope records with no usable text
foreach ($records as $r) {
    if (! in_array($r['classification'], ['news', 'project'], true)) {
        continue;
    }
    if ($r['content_text_len'] === 0) {
        $issues[] = ['category' => 'image_only_body', 'severity' => 'medium', 'subject' => "#{$r['id']} {$r['title']}", 'detail' => "Body holds {$r['embedded_count']} image(s) and no readable text."];
    } elseif ($r['content_text_len'] < 120) {
        $issues[] = ['category' => 'thin_body', 'severity' => 'low', 'subject' => "#{$r['id']} {$r['title']}", 'detail' => 'Only '.$r['content_text_len'].' characters of readable text.'];
    }
    if ($r['content_len'] === 0) {
        $issues[] = ['category' => 'empty_body', 'severity' => 'high', 'subject' => "#{$r['id']} {$r['title']}", 'detail' => 'Published record with an entirely empty body.'];
    }
}

// 7h. term quality: duplicate project categories
$projectCatGroups = [];
foreach ($records as $r) {
    foreach ($r['custom_terms'] as $t) {
        if (str_starts_with($t, 'project_cat:')) {
            $projectCatGroups[$r['id']][] = $t;
        }
    }
}

// 7i. slug anomalies: slug does not resemble the title
$slugAnomalies = [];
$stop = ['a', 'an', 'the', 'of', 'and', 'in', 'to', 'for', 'on', 'as', 'at', 'by', 'with'];
foreach ($records as $r) {
    if (! in_array($r['classification'], ['news', 'project'], true)) {
        continue;
    }
    $words = preg_split('/[^a-z0-9]+/', strtolower($r['title']), -1, PREG_SPLIT_NO_EMPTY);
    $words = array_values(array_filter($words, fn ($w) => strlen($w) > 3 && ! in_array($w, $stop, true)));
    if ($words === []) {
        continue;
    }
    $slug = strtolower($r['slug']);
    $hits = 0;
    foreach (array_slice($words, 0, 8) as $w) {
        if (str_contains($slug, $w)) {
            $hits++;
        }
    }
    if ($hits === 0) {
        $slugAnomalies[] = ['id' => $r['id'], 'type' => $r['type'], 'title' => $r['title'], 'slug' => $r['slug']];
    }
}

// ---------------------------------------------------------------------------
// 8. Reconciled totals
// ---------------------------------------------------------------------------
$typeStatus = [];
foreach ($items as $it) {
    $typeStatus[$it['post_type']][$it['status']] = ($typeStatus[$it['post_type']][$it['status']] ?? 0) + 1;
}
ksort($typeStatus);

$classificationCounts = [];
foreach ($records as $r) {
    $key = $r['classification'].'|'.$r['status'];
    $classificationCounts[$key] = ($classificationCounts[$key] ?? 0) + 1;
}
ksort($classificationCounts);

// strip _content for JSON size
$recordsOut = $records;
foreach ($recordsOut as &$r) {
    unset($r['_content']);
}
unset($r);

$output = [
    'generated_at' => date('c'),
    'site' => $data['site'],
    'xml' => [
        'bytes' => $data['xml_bytes'],
        'items' => count($items),
        'parse_warnings' => $data['parse_warnings'],
        'wxr_version' => $data['site']['wxr_version'],
    ],
    'channel' => [
        'categories' => $data['categories'],
        'tags' => $data['tags'],
        'terms' => $data['terms'],
        'authors' => $data['authors'],
    ],
    'type_status' => $typeStatus,
    'classification_counts' => $classificationCounts,
    'records' => $recordsOut,
    'attachments' => array_values($attachments),
    'manifest' => $manifest,
    'disk' => $diskVerified,
    'duplicate_groups' => $duplicateGroups,
    'clusters' => $clusters,
    'issues' => $issues,
    'slug_anomalies' => $slugAnomalies,
    'stats' => [
        'manifest_rows' => count($manifest),
        'unique_manifest_urls' => count(array_unique(array_column($manifest, 'url'))),
        'attachments_total' => count($attachments),
        'attachments_in_manifest' => count(array_filter($attachments, fn ($a) => $a['manifest_index'] !== null)),
        'embedded_unique_urls' => count($allEmbeddedUrls),
        'embedded_unmatched_urls' => count($unmatchedUrls),
        'files_exist' => count(array_filter($diskVerified, fn ($v) => $v['exists'])),
        'files_empty' => count(array_filter($diskVerified, fn ($v) => $v['is_empty'])),
        'bytes_total' => array_sum(array_filter(array_column($diskVerified, 'real_bytes'))),
        'duplicate_groups' => count($duplicateGroups),
        'duplicate_files' => array_sum(array_map(fn ($g) => count($g) - 1, $duplicateGroups)),
        'issues' => count($issues),
        'records_total' => count($records),
    ],
];

file_put_contents(__DIR__.'/../work/analysis.json', json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));

// ---------------------------------------------------------------------------
// Console summary
// ---------------------------------------------------------------------------
echo "=== ANALYSIS COMPLETE ===\n";
echo 'WXR items: '.count($items)."\n";
echo 'Records analysed (non-attachment, non-plugin): '.count($records)."\n";
echo 'Attachments: '.count($attachments).' (in manifest: '.$output['stats']['attachments_in_manifest'].")\n";
echo 'Manifest rows: '.count($manifest).' unique URLs: '.$output['stats']['unique_manifest_urls']."\n";
printf("Files on disk: %d / %d, empty: %d, total %.2f MB\n", $output['stats']['files_exist'], count($manifest), $output['stats']['files_empty'], $output['stats']['bytes_total'] / 1048576);
echo 'Embedded uploads URLs: '.$output['stats']['embedded_unique_urls'].' (unmatched: '.$output['stats']['embedded_unmatched_urls'].")\n";
echo 'Duplicate content groups: '.count($duplicateGroups).' ('.$output['stats']['duplicate_files']." redundant files)\n";
echo 'Issues logged: '.count($issues)."\n";
echo 'Slug/title anomalies: '.count($slugAnomalies)."\n";

echo "\n=== CLASSIFICATION COUNTS ===\n";
foreach ($classificationCounts as $k => $c) {
    printf("  %-45s %d\n", $k, $c);
}

echo "\n=== CLUSTERS ===\n";
foreach ($clusters as $c) {
    printf("  #%-5s %-28s rows=%-3d total=%-5d images=%s\n", $c['page_id'], $c['title'], $c['row_count'], $c['total_beneficiaries'], implode(',', $c['image_ids']));
}

echo "\n=== ISSUES BY CATEGORY ===\n";
$byCat = [];
foreach ($issues as $iss) {
    $byCat[$iss['category']] = ($byCat[$iss['category']] ?? 0) + 1;
}
arsort($byCat);
foreach ($byCat as $c => $n) {
    printf("  %-28s %d\n", $c, $n);
}
