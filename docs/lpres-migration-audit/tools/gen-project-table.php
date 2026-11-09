<?php

/**
 * READ-ONLY generator for the section-D table in report 07.
 * Emits markdown for every published project whose body has no prose.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$an = json_decode(file_get_contents($root.'/work/analysis.json'), true);
$wxr = json_decode(file_get_contents($root.'/work/wxr.json'), true);

$items = [];
foreach ($wxr['items'] as $it) {
    $items[(string) $it['post_id']] = $it;
}

$projs = array_values(array_filter($an['records'], fn ($r) => $r['type'] === 'project' && $r['status'] === 'publish'));

$postBySlug = [];
foreach ($an['records'] as $r) {
    if ($r['type'] === 'post' && $r['status'] === 'publish' && $r['slug'] !== '') {
        $postBySlug[$r['slug']] = $r;
    }
}

function termName(string $raw): string
{
    // Format: "project_cat:Label [slug]"
    if (preg_match('/^[a-z_]+:(.*?)\s*\[[^\]]*\]$/i', $raw, $m)) {
        return $m[1];
    }

    return $raw;
}

$md = '';
$zeroCount = 0;

foreach ($projs as $r) {
    if ((int) $r['content_text_len'] !== 0) {
        continue;
    }
    $zeroCount++;

    $id = (string) $r['id'];
    $files = [];
    foreach ($r['embedded'] as $e) {
        $files[] = basename((string) $e['relative_path']);
    }

    $meta = [];
    if (! empty($r['meta']['project_address'])) {
        $meta[] = 'addr: '.trim(implode(' ', array_map('strval', (array) $r['meta']['project_address'])));
    }
    if (! empty($r['meta']['project_date'])) {
        $meta[] = 'year: '.trim(implode(' ', array_map('strval', (array) $r['meta']['project_date'])));
    }
    if ($r['custom_terms']) {
        $meta[] = 'cat: '.implode(', ', array_map('termName', $r['custom_terms']));
    }

    $match = $postBySlug[$r['slug']] ?? null;
    if ($match && $match['id'] !== $id) {
        $rec = '**Yes** — news post #'.$match['id'].' ('.number_format((int) $match['content_text_len']).' chars)';
    } elseif ($match && $match['id'] === $id) {
        $rec = 'No';
    } else {
        $rec = 'No — images + metadata only';
    }

    $title = str_replace('|', '\\|', trim($r['title']));
    $md .= sprintf("| %s | %s | %d | %s | %s | %s |\n",
        $id,
        $title !== '' ? $title : '*(empty title)*',
        count($files),
        $files ? implode(', ', $files) : '*none*',
        $meta ? implode('; ', $meta) : '—',
        $rec
    );
}

file_put_contents($argv[1], $md);
echo "rows: {$zeroCount}\n";
echo substr($md, 0, 700), "\n...\n";
