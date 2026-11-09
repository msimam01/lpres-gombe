<?php

use App\Migration\Clusters\ClusterFields;
use App\Migration\Clusters\ClustersImporter;
use App\Migration\SourceHash;
use App\Migration\WordPress\WordPressXmlParser;
use App\Models\ClusterBeneficiary;
use App\Models\News;
use App\Models\Project;
use App\Models\TeamMember;
use App\Models\ValueChainCluster;
use App\Models\WpImportMap;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Value chain clusters against the audited WordPress export
|--------------------------------------------------------------------------
|
| The cluster set is fixed and its figures were audited row by row, so these
| assertions use the real export rather than a fixture: nine pages, 94 source
| data rows, 93 distinct rows, 15,985 beneficiaries.
|
*/

function auditedClustersImporter(): ClustersImporter
{
    $export = (new WordPressXmlParser)->parse(config('lpres_migration.xml'));

    return new ClustersImporter(export: $export, hasher: new SourceHash);
}

beforeEach(function () {
    Storage::fake('public');
});

it('imports exactly the nine audited clusters with the approved rows and totals', function () {
    $report = auditedClustersImporter()->run(true);

    expect($report->failed)->toBe(0)
        ->and($report->failedDetails)->toBe([])
        ->and($report->conflicts)->toBe(0)
        ->and($report->missingCharts)->toBe([])
        ->and($report->created)->toBe(9)
        ->and($report->sourceRows)->toBe(94)
        ->and($report->excludedRows)->toBe(1)
        ->and($report->summaryRows)->toBe(2)
        ->and($report->rowsToImport)->toBe(93)
        ->and($report->beneficiaries)->toBe(15985)
        ->and($report->createdRows)->toBe(93);

    $expected = [
        'dairy' => ['Dairy', 11, 818],
        'beef' => ['Beef', 11, 2263],
        'sheep-and-goat' => ['Sheep and Goat', 11, 6654],
        'broiler' => ['Broiler', 11, 5221],
        'eggs' => ['Eggs', 10, 566],
        'leather' => ['Leather', 10, 49],
        'feed' => ['Feed', 9, 184],
        'fodder' => ['Fodder', 9, 40],
        'pig' => ['Pig', 11, 190],
    ];

    expect(ValueChainCluster::query()->count())->toBe(9)
        ->and(ClusterBeneficiary::query()->count())->toBe(93)
        ->and((int) ClusterBeneficiary::query()->sum('beneficiaries'))->toBe(15985);

    foreach ($expected as $slug => [$name, $rows, $total]) {
        $cluster = ValueChainCluster::query()->where('slug', $slug)->first();

        expect($cluster)->not->toBeNull()
            ->and($cluster->name)->toBe($name)
            ->and($cluster->status)->toBe('published')
            ->and($cluster->image_path)->toBe('uploads/clusters/'.$slug.'.png')
            ->and($cluster->beneficiaries()->count())->toBe($rows)
            ->and((int) $cluster->beneficiaries()->sum('beneficiaries'))->toBe($total)
            // Public ordering is alphabetical; every cluster is published.
            ->and($cluster->description)->toBe($slug === 'beef' ? ClusterFields::BEEF_DESCRIPTION : null);
    }

    // The audit's own per-cluster figures and this run agree exactly.
    foreach (ClusterFields::EXPECTED as $wpId => $counts) {
        $definition = ClusterFields::CLUSTERS[$wpId];
        $cluster = ValueChainCluster::query()->where('slug', $definition['slug'])->firstOrFail();

        expect($cluster->beneficiaries()->count())->toBe($counts['rows'])
            ->and((int) $cluster->beneficiaries()->sum('beneficiaries'))->toBe($counts['beneficiaries']);
    }
});

it('keeps the approved Eggs duplicate out and preserves the audited spellings', function () {
    auditedClustersImporter()->run(true);

    $eggs = ValueChainCluster::query()->where('slug', 'eggs')->firstOrFail();

    expect($eggs->beneficiaries()->where('lga', 'Kwami')->where('ward', 'Malam Sidi')->count())->toBe(1)
        ->and($eggs->beneficiaries()->where('lga', 'Kwami')->where('ward', 'Malam Sidi')->value('beneficiaries'))->toBe(30)
        ->and($eggs->beneficiaries()->where('lga', 'Gombe')->where('ward', 'Bajoga')->value('beneficiaries'))->toBe(79)
        ->and(ValueChainCluster::query()->where('slug', 'sheep-and-goat')->firstOrFail()
            ->beneficiaries()->where('lga', 'Gombe')->where('ward', 'Bajoga')->value('beneficiaries'))->toBe(187);

    // The source's own spelling is preserved; the old Laravel-only spelling
    // never appears.
    expect(ClusterBeneficiary::query()->where('lga', 'Shomgom')->count())->toBe(8)
        ->and(ClusterBeneficiary::query()->where('lga', 'Shongom')->count())->toBe(0)
        ->and(ClusterBeneficiary::query()->where('lga', 'Shomgom')->where('ward', 'Kushi')->count())->toBe(3);

    // Whitespace is normalised; wording and counts are untouched.
    expect(ClusterBeneficiary::query()->where('ward', 'Bajoga West')->count())->toBe(6)
        ->and(ClusterBeneficiary::query()->where('ward', 'Bajoga East')->count())->toBe(2);

    foreach (ClusterBeneficiary::query()->get() as $row) {
        expect($row->lga)->not->toContain("\u{00A0}")
            ->and($row->ward)->not->toContain("\u{00A0}")
            ->and($row->lga)->toBe(trim($row->lga))
            ->and($row->ward)->toBe(trim($row->ward));
    }
});

it('copies every audited chart byte for byte and records nine provenance mappings', function () {
    $report = auditedClustersImporter()->run(true);

    expect($report->chartCopies)->toBe(9)
        ->and(WpImportMap::query()->where('source_type', 'value_chain_cluster')->count())->toBe(9);

    foreach (ClusterFields::CLUSTERS as $wpId => $definition) {
        $slug = $definition['slug'];
        $stored = 'uploads/clusters/'.$slug.'.png';
        $original = public_path('images/lpres/clusters/'.$slug.'.png');

        expect(Storage::disk('public')->exists($stored))->toBeTrue()
            ->and(Storage::disk('public')->get($stored))->toBe(file_get_contents($original));

        $mapping = WpImportMap::lookup('value_chain_cluster', $wpId);

        expect($mapping)->not->toBeNull()
            ->and($mapping->target_type)->toBe(ValueChainCluster::class)
            ->and($mapping->source_hash)->toHaveLength(64)
            ->and($mapping->target_hash)->toHaveLength(64)
            ->and($mapping->sourceMeta('chart_attachment_id'))->toBe($definition['attachment_id'])
            ->and($mapping->sourceMeta('chart_md5'))->toBe(md5_file($original))
            ->and($mapping->sourceMeta('wp_post_type'))->toBe('page')
            ->and($mapping->sourceMeta('imported_from'))->toBe('lpres:import-wordpress --type=clusters');
    }

    // The seven rows that did not need excluding carry an empty exclusion list;
    // Eggs carries its single documented exclusion.
    expect(WpImportMap::lookup('value_chain_cluster', 3846)->sourceMeta('excluded_rows'))->toBe([])
        ->and(WpImportMap::lookup('value_chain_cluster', 3858)->sourceMeta('excluded_rows'))->toHaveCount(1)
        ->and(WpImportMap::lookup('value_chain_cluster', 3855)->sourceMeta('summary_rows'))->toBe(1)
        ->and(WpImportMap::lookup('value_chain_cluster', 3846)->sourceMeta('summary_rows'))->toBe(0);
});

it('is a no-op on a second run and leaves other content untouched', function () {
    auditedClustersImporter()->run(true);

    $files = Storage::disk('public')->allFiles();
    $mappings = WpImportMap::query()->orderBy('id')->get(['id', 'source_hash', 'target_hash', 'imported_at'])->toArray();

    $second = auditedClustersImporter()->run(true);

    expect($second->failed)->toBe(0)
        ->and($second->created)->toBe(0)
        ->and($second->updated)->toBe(0)
        ->and($second->unchanged)->toBe(9)
        ->and($second->createdRows)->toBe(0)
        ->and($second->updatedRows)->toBe(0)
        ->and($second->deletedRows)->toBe(0)
        ->and($second->chartCopies)->toBe(0)
        ->and($second->potentialDatabaseWrites())->toBe(0);

    expect(ValueChainCluster::query()->count())->toBe(9)
        ->and(ClusterBeneficiary::query()->count())->toBe(93)
        ->and(Storage::disk('public')->allFiles())->toEqual($files)
        ->and(WpImportMap::query()->orderBy('id')->get(['id', 'source_hash', 'target_hash', 'imported_at'])->toArray())
        ->toEqual($mappings);

    // Cluster work never touches the other imported content.
    expect(News::query()->count())->toBe(0)
        ->and(Project::query()->count())->toBe(0)
        ->and(TeamMember::query()->count())->toBe(0);
});

it('reports the audited plan on the command dry run and writes nothing', function () {
    $this->artisan('lpres:import-wordpress', ['--type' => 'clusters', '--dry-run' => true])
        ->expectsOutputToContain('Eligible cluster pages')
        ->expectsOutputToContain('Source data rows')
        ->expectsOutputToContain('Rows to import')
        ->expectsOutputToContain('Excluded rows')
        ->expectsOutputToContain('nothing was written')
        ->assertExitCode(0);

    expect(ValueChainCluster::query()->count())->toBe(0)
        ->and(ClusterBeneficiary::query()->count())->toBe(0)
        ->and(WpImportMap::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});
