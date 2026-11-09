<?php

use App\Migration\Clusters\ClusterFields;
use App\Migration\Clusters\ClustersImporter;
use App\Migration\SourceHash;
use App\Migration\WordPress\WordPressXmlParser;
use App\Models\ClusterBeneficiary;
use App\Models\User;
use App\Models\ValueChainCluster;
use App\Models\WpImportMap;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Value chain clusters import
|--------------------------------------------------------------------------
|
| These tests use a small synthetic export so the mechanics (parsing,
| exclusions, charts, transactions, idempotency, admin protection) are
| exercised without the 18 MB audited export. The real export's exact row and
| beneficiary totals are asserted in ClusterSourceTest.
|
*/

afterEach(function () {
    foreach ($GLOBALS['clusterFixtureDirectories'] ?? [] as $directory) {
        File::deleteDirectory($directory);
    }

    $GLOBALS['clusterFixtureDirectories'] = [];
});

/**
 * Build a synthetic WXR export with all nine audited cluster pages (four with
 * meaningful tables, five minimal), plus their chart source directory.
 *
 * @return array{xml: string, charts: string}
 */
function makeClustersFixture(array $excludeCharts = []): array
{
    $base = storage_path('framework/testing/clusters-'.uniqid());
    $charts = $base.'/clusters';

    File::ensureDirectoryExists($charts, 0777, true);

    // The audited set is all nine pages, so the fixture must contain all nine:
    // four with meaningful tables and five minimal ones.
    $pages = [];

    foreach (ClusterFields::CLUSTERS as $id => $definition) {
        $pages[$id] = [
            'id' => $id,
            'title' => strtoupper($definition['name']).' - MAIN CLUSTERS',
            'slug' => $definition['slug'].'-main-clusters',
            'rows' => [
                ['Akko', 'Akko', 10],
                ['Balanga', 'Talasse/Dong/Reme', 5],
            ],
            'total' => null,
        ];
    }

    $pages[3846] = [
        'id' => 3846,
        'title' => 'DAIRY - MAIN CLUSTER',
        'slug' => 'dairy-main-cluster',
        'rows' => [
            ['Akko', 'Akko', 35],
            ['Funakaye', 'Bajoga  West', 100],
            ['Shomgom', 'Kushi', 91],
        ],
        'total' => null,
    ];

    $pages[3849] = [
        'id' => 3849,
        'title' => 'BEEF - MAIN CLUSTERS',
        'slug' => 'beef-main-clusters',
        'rows' => [
            ['Gombe', 'Shamaki', 426],
            ['Funakaye', 'Bajoga West', 62],
        ],
        'total' => null,
    ];

    $pages[3858] = [
        'id' => 3858,
        'title' => 'EGGS - MAIN CLUSTERS',
        'slug' => 'eggs-main-clusters',
        'rows' => [
            ['Akko', 'Garko', 40],
            ['Balanga', 'Talasse/Dong/Reme', 41],
            ['Billiri', 'Billiri South', 12],
            ['Dukku', 'Gombe Abba', 181],
            ['Funakaye', 'Bajoga West', 17],
            ['Gombe', 'Bajoga', 79],
            ['Kwami', 'Malam Sidi', 30],
            ['Nafada', 'Barwo/Nasarawo', 28],
            ['Shomgom', 'Kushi', 24],
            ['Kaltungo', 'Kaltungo East', 25],
            // The approved duplicate of data row 7.
            ['Kwami', 'Malam Sidi', 30],
        ],
        'total' => 507,
    ];

    $pages[3867] = [
        'id' => 3867,
        'title' => 'FODDER - MAIN CLUSTERS',
        'slug' => 'fodder-main-clusters',
        'rows' => [
            ['Akko', 'Akko', 2],
            ['Balanga', 'Talasse/Dong/Reme', 4],
        ],
        'total' => null,
    ];

    $items = '';

    foreach ($pages as $page) {
        $rowsHtml = '<table><thead><tr><td>S/NO</td><td>LGA</td><td>WARD</td><td>NUMBER OF BENEFICIARIES</td></tr></thead><tbody>';

        foreach ($page['rows'] as $index => [$lga, $ward, $count]) {
            $rowsHtml .= sprintf(
                '<tr><td>%d</td><td>%s</td><td>&nbsp;%s</td><td>%d</td></tr>',
                $index + 1,
                $lga,
                $ward,
                $count,
            );
        }

        if ($page['total'] !== null) {
            $rowsHtml .= sprintf('<tr><td></td><td>TOTAL</td><td></td><td>%d</td></tr>', $page['total']);
        }

        $rowsHtml .= '</tbody></table>';

        $content = '<ol><li>'.$page['title'].'</li></ol>'.$rowsHtml
            .'<figure class="wp-block-image"><img class="wp-image-3847" src="https://example.test/wp-content/uploads/2025/01/image.png" alt=""></figure>';

        $items .= <<<XML
  <item>
    <title><![CDATA[{$page['title']}]]></title>
    <link>https://example.test/{$page['slug']}/</link>
    <dc:creator><![CDATA[admin]]></dc:creator>
    <content:encoded><![CDATA[{$content}]]></content:encoded>
    <excerpt:encoded><![CDATA[]]></excerpt:encoded>
    <wp:post_id>{$page['id']}</wp:post_id>
    <wp:post_date><![CDATA[2025-01-27 16:44:35]]></wp:post_date>
    <wp:post_modified><![CDATA[2025-01-27 16:44:35]]></wp:post_modified>
    <wp:post_name><![CDATA[{$page['slug']}]]></wp:post_name>
    <wp:status><![CDATA[publish]]></wp:status>
    <wp:post_type><![CDATA[page]]></wp:post_type>
  </item>

XML;
    }

    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
  xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
  xmlns:content="http://purl.org/rss/1.0/modules/content/"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
  <title>Cluster fixture</title>
  <link>https://example.test</link>
  <wp:wxr_version>1.2</wp:wxr_version>
{$items}</channel>
</rss>
XML;

    $path = $base.'/export.xml';
    file_put_contents($path, $xml);

    foreach (ClusterFields::CLUSTERS as $definition) {
        $slug = $definition['slug'];

        if (in_array($slug, $excludeCharts, true)) {
            continue;
        }

        // Charts for pages outside this fixture are irrelevant; only the four
        // fixture pages are ever read.
        file_put_contents($charts.'/'.$slug.'.png', 'fixture-chart-'.$slug);
    }

    $GLOBALS['clusterFixtureDirectories'][] = $base;

    return ['xml' => $path, 'charts' => $charts];
}

/**
 * Importer for the synthetic fixture, with the audit totals disabled because
 * the fixture intentionally holds fewer rows than the real pages.
 */
function clustersImporterFor(string $xml, string $charts, bool $forceAdminEdits = false): ClustersImporter
{
    return new ClustersImporter(
        export: (new WordPressXmlParser)->parse($xml),
        hasher: new SourceHash,
        forceAdminEdits: $forceAdminEdits,
        chartSourceDirectory: $charts,
        expectedCounts: [],
    );
}

beforeEach(function () {
    Storage::fake('public');
    $GLOBALS['clusterFixtureDirectories'] = [];
});

it('imports the cluster pages with their rows, charts and approved descriptions', function () {
    $fixture = makeClustersFixture();

    $report = clustersImporterFor($fixture['xml'], $fixture['charts'])->run(true);

    expect($report->failed)->toBe(0)
        ->and($report->failedDetails)->toBe([])
        ->and($report->created)->toBe(9)
        ->and($report->createdRows)->toBe(27)
        // Four detailed pages plus five minimal ones; the Eggs duplicate is excluded.
        ->and($report->beneficiaries)->toBe(1272)
        ->and($report->excludedRows)->toBe(1);

    $dairy = ValueChainCluster::query()->where('slug', 'dairy')->firstOrFail();
    $beef = ValueChainCluster::query()->where('slug', 'beef')->firstOrFail();
    $eggs = ValueChainCluster::query()->where('slug', 'eggs')->firstOrFail();
    $fodder = ValueChainCluster::query()->where('slug', 'fodder')->firstOrFail();

    expect($dairy->name)->toBe('Dairy')
        ->and($beef->name)->toBe('Beef')
        ->and($eggs->name)->toBe('Eggs')
        ->and($fodder->name)->toBe('Fodder')
        ->and($dairy->status)->toBe('published')
        ->and($eggs->status)->toBe('published')
        ->and($dairy->description)->toBeNull()
        ->and($eggs->description)->toBeNull()
        ->and($fodder->description)->toBeNull()
        ->and($beef->description)->toBe(ClusterFields::BEEF_DESCRIPTION);

    // Charts live where the public pages read them, and are byte-copied.
    foreach (array_column(ClusterFields::CLUSTERS, 'slug') as $slug) {
        $path = 'uploads/clusters/'.$slug.'.png';

        expect(Storage::disk('public')->exists($path))->toBeTrue()
            ->and(Storage::disk('public')->get($path))->toBe('fixture-chart-'.$slug);
    }

    expect($dairy->image_path)->toBe('uploads/clusters/dairy.png')
        ->and($beef->image_path)->toBe('uploads/clusters/beef.png')
        ->and($eggs->image_path)->toBe('uploads/clusters/eggs.png');

    expect(ClusterBeneficiary::query()->count())->toBe(27);
});

it('normalises the leading nbsp and doubled spaces without changing wording', function () {
    $fixture = makeClustersFixture();

    clustersImporterFor($fixture['xml'], $fixture['charts'])->run(true);

    $dairy = ValueChainCluster::query()->where('slug', 'dairy')->firstOrFail();
    $rows = $dairy->beneficiaries()->get();

    expect($rows->pluck('ward')->all())->toBe(['Akko', 'Bajoga West', 'Kushi'])
        ->and($rows->pluck('lga')->all())->toBe(['Akko', 'Funakaye', 'Shomgom'])
        ->and($rows->pluck('beneficiaries')->all())->toBe([35, 100, 91])
        ->and($rows->pluck('sort_index')->all())->toBe([0, 1, 2]);

    foreach ($rows as $row) {
        expect($row->ward)->not->toContain("\u{00A0}")
            ->and($row->lga)->not->toContain("\u{00A0}");
    }
});

it('excludes the approved Eggs duplicate and reports it with its reason', function () {
    $fixture = makeClustersFixture();

    $report = clustersImporterFor($fixture['xml'], $fixture['charts'])->run(true);

    $eggs = ValueChainCluster::query()->where('slug', 'eggs')->firstOrFail();

    expect($eggs->beneficiaries()->count())->toBe(10)
        ->and((int) $eggs->beneficiaries()->sum('beneficiaries'))->toBe(477)
        // Exactly one Kwami / Malam Sidi / 30 row survives.
        ->and($eggs->beneficiaries()->where('lga', 'Kwami')->where('ward', 'Malam Sidi')->count())->toBe(1)
        ->and($report->excludedRows)->toBe(1)
        ->and($report->exclusionDetails)->toHaveCount(1)
        ->and($report->exclusionDetails[0])->toContain('#3858')
        ->and($report->exclusionDetails[0])->toContain('duplicate of data row 7')
        // The TOTAL row is skipped as a summary, not imported.
        ->and($report->summaryRows)->toBe(1)
        ->and($eggs->beneficiaries()->where('lga', 'TOTAL')->exists())->toBeFalse();
});

it('preserves Shomgom and both approved Gombe / Bajoga rows', function () {
    $fixture = makeClustersFixture();

    clustersImporterFor($fixture['xml'], $fixture['charts'])->run(true);

    expect(ValueChainCluster::query()->where('slug', 'dairy')->firstOrFail()
        ->beneficiaries()->where('lga', 'Shomgom')->where('ward', 'Kushi')->value('beneficiaries'))->toBe(91)
        ->and(ValueChainCluster::query()->where('slug', 'eggs')->firstOrFail()
            ->beneficiaries()->where('lga', 'Gombe')->where('ward', 'Bajoga')->value('beneficiaries'))->toBe(79)
        ->and(ValueChainCluster::query()->where('slug', 'beef')->firstOrFail()
            ->beneficiaries()->where('lga', 'Gombe')->where('ward', 'Shamaki')->value('beneficiaries'))->toBe(426);
});

it('records provenance with source and target hashes and chart metadata', function () {
    $fixture = makeClustersFixture();

    clustersImporterFor($fixture['xml'], $fixture['charts'])->run(true);

    expect(WpImportMap::query()->where('source_type', 'value_chain_cluster')->count())->toBe(9);

    $mapping = WpImportMap::lookup('value_chain_cluster', 3858);
    $eggs = ValueChainCluster::query()->where('slug', 'eggs')->firstOrFail();

    expect($mapping)->not->toBeNull()
        ->and($mapping->target_type)->toBe(ValueChainCluster::class)
        ->and($mapping->target_id)->toBe($eggs->id)
        ->and($mapping->source_hash)->toHaveLength(64)
        ->and($mapping->target_hash)->toHaveLength(64)
        ->and($mapping->sourceMeta('wp_title'))->toBe('EGGS - MAIN CLUSTERS')
        ->and($mapping->sourceMeta('chart_attachment_id'))->toBe(3859)
        ->and($mapping->sourceMeta('chart_stored_path'))->toBe('uploads/clusters/eggs.png')
        ->and($mapping->sourceMeta('chart_md5'))->toBe(md5('fixture-chart-eggs'))
        ->and($mapping->sourceMeta('source_row_count'))->toBe(11)
        ->and($mapping->sourceMeta('imported_row_count'))->toBe(10)
        ->and($mapping->sourceMeta('imported_beneficiaries'))->toBe(477)
        ->and($mapping->sourceMeta('excluded_rows'))->toHaveCount(1)
        ->and($mapping->sourceMeta('imported_from'))->toBe('lpres:import-wordpress --type=clusters');
});

it('is a true no-op when re-imported unchanged', function () {
    $fixture = makeClustersFixture();

    clustersImporterFor($fixture['xml'], $fixture['charts'])->run(true);
    $files = Storage::disk('public')->allFiles();

    $second = clustersImporterFor($fixture['xml'], $fixture['charts'])->run(true);

    expect($second->failed)->toBe(0)
        ->and($second->created)->toBe(0)
        ->and($second->updated)->toBe(0)
        ->and($second->unchanged)->toBe(9)
        ->and($second->existingMappings)->toBe(9)
        ->and($second->createdRows)->toBe(0)
        ->and($second->updatedRows)->toBe(0)
        ->and($second->deletedRows)->toBe(0)
        ->and($second->chartCopies)->toBe(0)
        // Unchanged clusters are not even planned, so no chart is examined.
        ->and($second->chartReused)->toBe(0)
        ->and($second->mappingRows)->toBe(0)
        ->and($second->potentialDatabaseWrites())->toBe(0);

    expect(ValueChainCluster::query()->count())->toBe(9)
        ->and(ClusterBeneficiary::query()->count())->toBe(27)
        ->and(Storage::disk('public')->allFiles())->toEqual($files);
});

it('does not overwrite a cluster edited by an administrator', function () {
    $fixture = makeClustersFixture();

    clustersImporterFor($fixture['xml'], $fixture['charts'])->run(true);

    ValueChainCluster::query()->where('slug', 'dairy')->update(['name' => 'Renamed By Admin']);

    $second = clustersImporterFor($fixture['xml'], $fixture['charts'])->run(true);

    expect($second->failed)->toBe(0)
        ->and($second->updated)->toBe(0)
        ->and($second->unchanged)->toBe(8)
        ->and($second->adminModified)->toBe(1)
        ->and($second->adminModifiedDetails)->not->toBe([]);

    expect(ValueChainCluster::query()->where('slug', 'dairy')->value('name'))->toBe('Renamed By Admin');

    $forced = clustersImporterFor($fixture['xml'], $fixture['charts'], forceAdminEdits: true)->run(true);

    expect($forced->updated)->toBe(1)
        ->and($forced->adminModified)->toBe(0)
        ->and(ValueChainCluster::query()->where('slug', 'dairy')->value('name'))->toBe('Dairy');
});

it('keeps the chart and beneficiary rows when an administrator saves an unchanged edit', function () {
    $fixture = makeClustersFixture();

    clustersImporterFor($fixture['xml'], $fixture['charts'])->run(true);

    $cluster = ValueChainCluster::query()->where('slug', 'eggs')->firstOrFail();
    $chart = $cluster->image_path;
    $rows = $cluster->beneficiaries()->get(['lga', 'ward', 'beneficiaries', 'sort_index'])->toArray();

    $this->actingAs(User::factory()->create())
        ->get('/admin/clusters/'.$cluster->id.'/edit')
        ->assertOk();

    // The edit screen submits the existing rows; saving must not delete them
    // or drop the stored chart when no new file is uploaded.
    $this->actingAs(User::factory()->create())
        ->put('/admin/clusters/'.$cluster->id, [
            'name' => $cluster->name,
            'status' => 'published',
            'beneficiaries' => array_map(fn (array $row) => [
                'id' => null,
                'lga' => $row['lga'],
                'ward' => $row['ward'],
                'beneficiaries' => (string) $row['beneficiaries'],
            ], $rows),
        ])
        ->assertRedirect();

    $cluster->refresh();

    expect($cluster->image_path)->toBe($chart)
        ->and($cluster->beneficiaries()->count())->toBe(10)
        ->and(Storage::disk('public')->exists($chart))->toBeTrue();

    // Re-importing after the save is still a no-op.
    $second = clustersImporterFor($fixture['xml'], $fixture['charts'])->run(true);

    expect($second->adminModified)->toBe(0)
        ->and($second->unchanged)->toBe(9)
        ->and($second->potentialDatabaseWrites())->toBe(0);
});

it('leaves no partial cluster or chart behind when persistence fails', function () {
    $fixture = makeClustersFixture();

    ValueChainCluster::creating(function () {
        throw new RuntimeException('simulated persistence failure');
    });

    $report = clustersImporterFor($fixture['xml'], $fixture['charts'])->run(true);

    expect($report->created)->toBe(0)
        ->and($report->failed)->toBe(9)
        ->and($report->failedDetails[0])->toContain('simulated persistence failure');

    expect(ValueChainCluster::query()->count())->toBe(0)
        ->and(ClusterBeneficiary::query()->count())->toBe(0)
        ->and(WpImportMap::query()->count())->toBe(0)
        // The chart copied before the failed transaction is removed again.
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('refuses to adopt a cluster that owns the slug but was not imported', function () {
    $fixture = makeClustersFixture();

    ValueChainCluster::query()->create([
        'name' => 'Dairy', 'slug' => 'dairy', 'status' => 'published',
    ]);

    $report = clustersImporterFor($fixture['xml'], $fixture['charts'])->run(true);

    expect($report->conflicts)->toBe(1)
        ->and($report->conflictDetails[0])->toContain('slug "dairy"')
        ->and($report->created)->toBe(8)
        ->and(ValueChainCluster::query()->where('slug', 'dairy')->firstOrFail()->description)->toBeNull();
});

it('fails a cluster whose approved chart is missing instead of importing it', function () {
    $fixture = makeClustersFixture(excludeCharts: ['fodder']);

    $report = clustersImporterFor($fixture['xml'], $fixture['charts'])->run(true);

    expect($report->failed)->toBe(1)
        ->and($report->missingCharts)->toHaveCount(1)
        ->and($report->created)->toBe(8)
        ->and(ValueChainCluster::query()->where('slug', 'fodder')->exists())->toBeFalse();
});
