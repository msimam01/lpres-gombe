<?php

declare(strict_types=1);

/** READ-ONLY media reference probe. */
$data = json_decode(file_get_contents(__DIR__.'/../work/wxr.json'), true, 512, JSON_THROW_ON_ERROR);
$items = $data['items'];

$urlRe = '#https?://[^\s"\'<>()\\\\]+wp-content/uploads/[^\s"\'<>()\\\\]+#i';

$stats = [
    'content_with_img' => 0, 'content_with_uploads' => 0,
    'elem_with_uploads' => 0, 'desc_with_uploads' => 0,
    'excerpt_with_uploads' => 0,
];
$contentImgs = 0;
$elemImgs = 0;
$oembedHosts = [];

foreach ($items as $it) {
    $content = (string) $it['content'];
    $desc = (string) $it['description'];
    $excerpt = (string) $it['excerpt'];
    $elem = (string) ($it['meta']['_elementor_data'][0] ?? '');

    if (stripos($content, '<img') !== false) {
        $stats['content_with_img']++;
    }
    $contentImgs += preg_match_all('#<img[^>]*>#i', $content);
    $elemImgs += preg_match_all('#<img[^>]*>#i', $elem);

    if (preg_match($urlRe, $content)) {
        $stats['content_with_uploads']++;
    }
    if (preg_match($urlRe, $elem)) {
        $stats['elem_with_uploads']++;
    }
    if (preg_match($urlRe, $desc)) {
        $stats['desc_with_uploads']++;
    }
    if (preg_match($urlRe, $excerpt)) {
        $stats['excerpt_with_uploads']++;
    }

    foreach ($it['meta'] as $k => $vals) {
        if (str_starts_with($k, '_oembed_')) {
            foreach ($vals as $v) {
                if (preg_match('#src="([^"]+)"#', $v, $m)) {
                    $host = parse_url($m[1], PHP_URL_HOST) ?: '?';
                    $oembedHosts[$host] = ($oembedHosts[$host] ?? 0) + 1;
                }
            }
        }
    }
}

print_r($stats);
echo "img tags in content: {$contentImgs}\n";
echo "img tags in elementor: {$elemImgs}\n";
echo "oembed embed hosts:\n";
print_r($oembedHosts);

// --- one attachment record in full ---
echo "\n=== SAMPLE ATTACHMENT ===\n";
foreach ($items as $it) {
    if ($it['post_type'] === 'attachment' && $it['post_id'] === '3069') {
        foreach (['post_id', 'title', 'post_name', 'status', 'attachment_url', 'post_parent', 'post_date', 'description'] as $f) {
            echo "{$f}: ".substr((string) $it[$f], 0, 300)."\n";
        }
        foreach ($it['meta'] as $k => $vals) {
            foreach ($vals as $v) {
                echo "  META {$k} len=".strlen($v).' '.substr($v, 0, 700)."\n";
            }
        }
    }
}

// --- does content always begin with the title? ---
echo "\n=== CONTENT STARTS WITH TITLE? (type=post, status=publish) ===\n";
$withTitle = 0;
$total = 0;
$examples = [];
foreach ($items as $it) {
    if ($it['post_type'] !== 'post' || $it['status'] !== 'publish') {
        continue;
    }
    $total++;
    $t = html_entity_decode((string) $it['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $c = ltrim(strip_tags((string) $it['content']));
    $c = html_entity_decode($c, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $norm = fn ($s) => preg_replace('/\s+/u', ' ', $s);
    if (mb_strpos($norm($c), $norm(trim($t))) === 0) {
        $withTitle++;
    } elseif (count($examples) < 5) {
        $examples[] = "  #{$it['post_id']} TITLE=".$norm(trim($t)).' | CONTENT='.mb_substr($norm($c), 0, 120);
    }
}
echo "content begins with exact title: {$withTitle} / {$total}\n";
echo implode("\n", $examples)."\n";
