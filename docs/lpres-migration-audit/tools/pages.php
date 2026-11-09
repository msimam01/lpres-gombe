<?php

declare(strict_types=1);

$d = json_decode(file_get_contents(__DIR__.'/../work/wxr.json'), true, 512, JSON_THROW_ON_ERROR);

$kw = ['lorem ipsum', 'mayor', 'city visitor', 'birth certificate', 'photography new contest',
    'your little help', 'tax return', 'polution', 'driving license', 'holiday', 'save soil'];

foreach ($d['items'] as $it) {
    if ($it['post_type'] !== 'page') {
        continue;
    }
    $t = html_entity_decode(strip_tags((string) $it['content']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace('/\s+/u', ' ', trim($t));
    $low = mb_strtolower($t);
    $hits = [];
    foreach ($kw as $k) {
        if (str_contains($low, $k)) {
            $hits[] = $k;
        }
    }
    $lpres = substr_count($low, 'l-pres') + substr_count($low, 'lpres');
    printf(
        "#%-5s %-6s len=%-7d lpres=%-3d demo=[%s] %-24s :: %s\n",
        $it['post_id'],
        $it['status'],
        strlen($t),
        $lpres,
        implode(',', $hits),
        mb_substr($it['title'], 0, 24),
        mb_substr($t, 0, 95)
    );
}
