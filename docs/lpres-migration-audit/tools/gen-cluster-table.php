<?php

/**
 * READ-ONLY generator: emits the section-F reconciliation table as markdown.
 * Writes only to the file passed as argv[1] inside the audit folder.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$an = json_decode(file_get_contents($root.'/work/analysis.json'), true);

$pdo = new PDO('sqlite:'.dirname(dirname($root)).'/database/database.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Existing Laravel clusters and their rows.
$clusters = [];
foreach ($pdo->query('SELECT id, slug, name, description, image_path FROM value_chain_clusters') as $c) {
    $clusters[$c['slug']] = $c + ['rows' => []];
}
foreach ($pdo->query('SELECT b.value_chain_cluster_id id, c.slug, b.lga, b.ward, b.beneficiaries, b.sort_index
                      FROM cluster_beneficiaries b JOIN value_chain_clusters c ON c.id = b.value_chain_cluster_id
                      ORDER BY c.id, b.sort_index') as $r) {
    $clusters[$r['slug']]['rows'][] = $r;
}

/** Normalise for matching: lowercase, collapse whitespace, unify Shomgom/Shongom. */
function norm(string $s): string
{
    $s = strtolower(trim(preg_replace('/\s+/u', ' ', $s) ?? ''));

    return str_replace('shongom', 'shomgom', $s);
}

// Map source cluster titles to Laravel slugs.
$slugMap = [
    'DAIRY - MAIN CLUSTER' => 'dairy',
    'BEEF - MAIN CLUSTERS' => 'beef',
    'SHEEP AND GOAT - MAIN CLUSTERS' => 'sheep-and-goat',
    'BROILER - MAIN CLUSTERS' => 'broiler',
    'EGGS - MAIN CLUSTERS' => 'eggs',
    'LEATHER - MAIN CLUSTERS' => 'leather',
    'FEED - MAIN CLUSTERS' => 'feed',
    'FODDER - MAIN CLUSTERS' => 'fodder',
    'PIG - MAIN CLUSTERS' => 'pig',
];

$out = '';
$totals = ['match' => 0, 'insert' => 0, 'conflict' => 0];

foreach ($an['clusters'] as $src) {
    $slug = $slugMap[$src['title']] ?? null;
    $db = $slug !== null ? ($clusters[$slug] ?? null) : null;

    $sum = 0;
    foreach ($src['rows'] as $r) {
        $sum += (int) $r['beneficiaries'];
    }

    $header = sprintf("### %s → `%s`\n\n", $src['title'], $slug ?? '(unmapped)');
    if ($db) {
        $header .= sprintf(
            'Source: **%d rows, %d beneficiaries**. Laravel: cluster present (`%s`, id %d), **%d rows, %d beneficiaries**. ',
            $src['row_count'], $sum, $db['slug'], $db['id'], count($db['rows']),
            array_sum(array_map(fn ($r) => (int) $r['beneficiaries'], $db['rows']))
        );
        $header .= $db['slug'] === 'dairy'
            ? "⚠️ Partial — 9 source rows absent.\n\n"
            : "✅ Complete.\n\n";
    } else {
        $header .= sprintf("Source: **%d rows, %d beneficiaries**. Laravel: **cluster does not exist** (0 rows).\n\n",
            $src['row_count'], $sum);
    }

    $out .= $header;
    $out .= "| # | LGA | Ward | Beneficiaries | Existing Laravel value | Required action |\n";
    $out .= "|---|---|---|---|---|---|\n";

    // Index DB rows for lookup.
    $dbIndex = [];
    if ($db) {
        foreach ($db['rows'] as $r) {
            $dbIndex[norm($r['lga']).'|'.norm($r['ward'])] = $r;
        }
    }

    foreach ($src['rows'] as $i => $r) {
        $key = norm($r['lga']).'|'.norm($r['ward']);
        $hit = $dbIndex[$key] ?? null;

        if ($hit === null) {
            $existing = '— (absent)';
            $action = $db ? 'Insert row' : 'Insert with new cluster';
            $totals['insert']++;
        } else {
            $existing = (string) $hit['beneficiaries'];
            if ((int) $hit['beneficiaries'] === (int) $r['beneficiaries']) {
                $action = 'Exists, value matches — no action';
                $totals['match']++;
            } else {
                $action = 'Value differs — review before overwrite';
                $totals['conflict']++;
            }
            // Spelling / whitespace notes.
            if ($hit['lga'] !== $r['lga']) {
                $action .= sprintf('; LGA spelling differs ("%s" in Laravel vs "%s" in source)', $hit['lga'], $r['lga']);
            }
            if ($hit['ward'] !== $r['ward'] && norm($hit['ward']) === norm($r['ward'])) {
                $action .= '; ward whitespace differs';
            }
        }

        $out .= sprintf("| %d | %s | %s | %s | %s | %s |\n",
            $i + 1,
            $r['lga'] === 'Funakaye' && str_contains($r['ward'], '  ') ? $r['lga'].' *(note: double space in source ward)*' : $r['lga'],
            $r['ward'],
            $r['beneficiaries'],
            $existing,
            $action
        );
    }

    $out .= sprintf("\n**Cluster total: %d** (source states %s).\n\n", $sum, $src['total_beneficiaries'] ?? '—');
}

// Rows present in Laravel but not in source.
$out .= "### Rows present in Laravel but not in the WordPress source\n\n";
$found = false;
foreach ($clusters as $slug => $c) {
    $src = null;
    foreach ($an['clusters'] as $s) {
        if (($slugMap[$s['title']] ?? null) === $slug) {
            $src = $s;
        }
    }
    if ($src === null) {
        continue;
    }
    $srcKeys = [];
    foreach ($src['rows'] as $r) {
        $srcKeys[norm($r['lga']).'|'.norm($r['ward'])] = true;
    }
    foreach ($c['rows'] as $r) {
        if (! isset($srcKeys[norm($r['lga']).'|'.norm($r['ward'])])) {
            $out .= sprintf("- `%s` — %s / %s = %d (no matching source row)\n", $slug, $r['lga'], $r['ward'], $r['beneficiaries']);
            $found = true;
        }
    }
}
if (! $found) {
    $out .= "- None. Every Laravel beneficiary row has a matching source row.\n";
}

$out .= sprintf("\n**Row tally: %d exact matches, %d inserts required, %d value conflicts.**\n",
    $totals['match'], $totals['insert'], $totals['conflict']);

file_put_contents($argv[1], $out);
echo $out;
