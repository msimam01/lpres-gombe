<?php

/**
 * READ-ONLY consistency check for report 07.
 * Recomputes the aggregate figures quoted in the report and confirms the
 * literal strings are present in the generated markdown.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$an = json_decode(file_get_contents($root.'/work/analysis.json'), true);
$wxr = json_decode(file_get_contents($root.'/work/wxr.json'), true);
$doc = file_get_contents($root.'/07-final-migration-decisions.md');

$items = [];
foreach ($wxr['items'] as $it) {
    $items[(string) $it['post_id']] = $it;
}

$fails = 0;
function check(string $label, string $needle, string $haystack, bool $present = true): void
{
    global $fails;
    $has = str_contains($haystack, $needle);
    $ok = $has === $present;
    if (! $ok) {
        $fails++;
    }
    printf("[%s] %-52s %s\n", $ok ? ' OK ' : 'FAIL', $label, $ok ? '' : ($present ? "missing: {$needle}" : "unexpected: {$needle}"));
}

// --- Cluster totals -------------------------------------------------------
$srcRows = 0;
$srcTotal = 0;
foreach ($an['clusters'] as $c) {
    $srcRows += $c['row_count'];
    foreach ($c['rows'] as $r) {
        $srcTotal += (int) $r['beneficiaries'];
    }
}
check('source cluster count = 9', '| Clusters | **9** |', $doc);
check('source beneficiary rows = 94', '| Beneficiary rows | **94** |', $doc);
check('source beneficiaries = 16,015', '**16,015**', $doc);
printf("      recomputed: %d clusters, %d rows, %d beneficiaries\n", count($an['clusters']), $srcRows, $srcTotal);

check('laravel beneficiaries = 2,756', '**2,756**', $doc);
check('row tally 13 exact / 81 inserts', '13 exact matches, 81 inserts required, 0 value conflicts', $doc);
if ($srcRows !== 94 || $srcTotal !== 16015 || $srcRows - 13 !== 81) {
    $fails++;
    echo "[FAIL] cluster arithmetic does not reconcile\n";
}

// --- Dairy shortfall ------------------------------------------------------
$dairy = null;
foreach ($an['clusters'] as $c) {
    if (str_contains($c['title'], 'DAIRY')) {
        $dairy = $c;
    }
}
$dairyTotal = 0;
foreach ($dairy['rows'] as $r) {
    $dairyTotal += (int) $r['beneficiaries'];
}
check('dairy total = 818', '818', $doc);
check('dairy missing = 325', '**325 beneficiaries**', $doc);

if ($dairyTotal - 493 !== 325 || count($dairy['rows']) - 2 !== 9) {
    $fails++;
    echo "[FAIL] dairy arithmetic: {$dairyTotal} total, ", count($dairy['rows']), " rows\n";
}

// --- Projects -------------------------------------------------------------
$projs = array_filter($an['records'], fn ($r) => $r['type'] === 'project' && $r['status'] === 'publish');
$zero = $titleOnly = $real = 0;
$imgs53 = 0;
$addr = 0;
foreach ($projs as $r) {
    if ((int) $r['content_text_len'] === 0) {
        $zero++;
    } elseif ($r['content_text_len'] <= 120) {
        $titleOnly++;
    } else {
        $real++;
    }
    if ($r['content_text_len'] <= 120) {
        $imgs53 += $r['embedded_count'];
        if (! empty($r['meta']['project_address'])) {
            $addr++;
        }
    }
}
check('41 zero-prose projects', '| Zero prose characters (images only) | **41** |', $doc);
check('12 title-only projects', '| Title only in `<h4>` (42–107 chars, i.e. the heading restated) | **12** |', $doc);
check('5 projects with prose', '| Real description (>120 chars) | **5** |', $doc);
check('53 without description', '**53 of 58 projects have no usable description**', $doc);
check('314 images across 53', '**314 embedded images**', $doc);
check('51 of 53 have address', '**51 of 53 have `project_address`**', $doc);
printf("      recomputed: zero=%d title-only=%d real=%d images(53)=%d with-address(53)=%d\n", $zero, $titleOnly, $real, $imgs53, $addr);
if ($zero !== 41 || $titleOnly !== 12 || $real !== 5 || $imgs53 !== 314) {
    $fails++;
    echo "[FAIL] project arithmetic does not reconcile\n";
}

// --- News -----------------------------------------------------------------
$news = array_filter($an['records'], fn ($r) => $r['classification'] === 'news');
$noP = 0;
$h2 = 0;
foreach ($news as $r) {
    $c = $items[(string) $r['id']]['content'] ?? '';
    if (substr_count($c, '<p') === 0) {
        $noP++;
    }
    if (substr_count($c, '<h2') > 0) {
        $h2++;
    }
}
check('132 news articles', '132 genuine news articles', $doc);
check('90 bodies without <p>', '90 of 132 news bodies contain **no `<p>` at all**', $doc);
check('115 with <h2>', '115 begin with an `<h2>`', $doc);
printf("      recomputed: %d news, %d without <p>, %d with <h2>\n", count($news), $noP, $h2);
if (count($news) !== 132 || $noP !== 90) {
    $fails++;
    echo "[FAIL] news arithmetic does not reconcile\n";
}

// Category usage.
$catCounts = [];
foreach ($news as $r) {
    foreach ($r['categories'] as $c) {
        $catCounts[$c] = ($catCounts[$c] ?? 0) + 1;
    }
}
foreach (['Latest News' => 130, 'News' => 89, 'Uncategorized' => 9] as $name => $expected) {
    $actual = $catCounts[$name] ?? 0;
    $row = sprintf('| %s |', $name);
    $ok = $actual === $expected && str_contains($doc, "| **{$expected}** |");
    if (! $ok) {
        $fails++;
    }
    printf("[%s] category %-14s expected %3d, recomputed %3d\n", $ok ? ' OK ' : 'FAIL', $name, $expected, $actual);
}

// Tags on news.
$tagged = 0;
foreach ($news as $r) {
    if (! empty($r['tags'])) {
        $tagged++;
    }
}
check('no news article carries a tag', '**No article carries a tag.**', $doc);
if ($tagged !== 0) {
    $fails++;
    echo "[FAIL] {$tagged} news articles carry tags\n";
}

// --- Duplicate pairs ------------------------------------------------------
$md5 = [];
foreach ($an['manifest'] as $i => $m) {
    $md5[$m['relative_path']] = $an['disk'][$i]['md5'] ?? null;
}
$recs = [];
foreach ($an['records'] as $r) {
    $recs[(string) $r['id']] = $r;
}
$expectedShared = ['4618|5535' => 8, '4878|3415' => 2, '5451|5502' => 11, '5907|6028' => 6, '5963|6057' => 9, '5992|6085' => 13, '7349|7338' => 1];
foreach ($expectedShared as $pair => $exp) {
    [$a, $b] = explode('|', $pair);
    $set = function (string $id) use ($recs, $md5): array {
        $out = [];
        foreach ($recs[$id]['embedded'] as $e) {
            $p = $e['relative_path'] ?? null;
            if ($p && ! empty($md5[$p])) {
                $out[$md5[$p]] = 1;
            }
        }
        $f = $recs[$id]['featured_path'] ?? null;
        if ($f && ! empty($md5[$f])) {
            $out[$md5[$f]] = 1;
        }

        return $out;
    };
    $shared = count(array_intersect_key($set($a), $set($b)));
    $ok = $shared === $exp;
    if (! $ok) {
        $fails++;
    }
    printf("[%s] pair %s/%s shared images expected %d, recomputed %d\n", $ok ? ' OK ' : 'FAIL', $a, $b, $exp, $shared);
}

// --- Testimonials ---------------------------------------------------------
$t = array_values(array_filter($an['records'], fn ($r) => $r['type'] === 'testimonials'));
$titles = array_unique(array_map(fn ($r) => trim($r['title']), $t));
check('5 testimonials', '| #3115 | `bello`', $doc);
printf("[%s] testimonials: %d records, %d distinct titles\n", count($t) === 5 ? ' OK ' : 'FAIL', count($t), count($titles));
if (count($t) !== 5) {
    $fails++;
}

echo "\n", $fails === 0 ? "ALL CHECKS PASSED\n" : "{$fails} CHECK(S) FAILED\n";
exit($fails === 0 ? 0 : 1);
