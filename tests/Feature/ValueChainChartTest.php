<?php

use App\Models\ClusterBeneficiary;
use App\Models\User;
use App\Models\ValueChainCluster;
use Inertia\Testing\AssertableInertia as Assert;

function chartTestAdmin(): User
{
    return User::factory()->admin()->create();
}

function chartTestCluster(string $name, string $slug): ValueChainCluster
{
    return ValueChainCluster::query()->create([
        'name' => $name,
        'slug' => $slug,
        'description' => $name.' cluster description.',
        'status' => 'published',
    ]);
}

/** @param array<int, array{lga: string, ward: string, beneficiaries: int}> $rows */
function chartTestRows(ValueChainCluster $cluster, array $rows): void
{
    foreach (array_values($rows) as $index => $row) {
        $cluster->beneficiaries()->create($row + ['sort_index' => $index]);
    }
}

it('builds the chart from the viewed cluster records only', function () {
    $beef = chartTestCluster('Beef', 'beef');
    chartTestRows($beef, [
        ['lga' => 'Gombe', 'ward' => 'Bolari East', 'beneficiaries' => 120],
        ['lga' => 'Akko', 'ward' => 'Pindiga', 'beneficiaries' => 80],
    ]);

    $dairy = chartTestCluster('Dairy', 'dairy');
    chartTestRows($dairy, [
        ['lga' => 'Kwami', 'ward' => 'Malam Sidi', 'beneficiaries' => 999],
    ]);

    $this->get('/value-chains/beef')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('value-chains/show')
            ->where('cluster.name', 'Beef')
            ->where('cluster.chart.title', 'Beneficiary distribution — Beef')
            ->has('cluster.chart.series', 2)
            ->where('cluster.chart.series.0.label', 'Gombe, Bolari East')
            ->where('cluster.chart.series.0.value', 120)
            ->where('cluster.chart.series.1.label', 'Akko, Pindiga')
            ->where('cluster.chart.series.1.value', 80)
            ->where('cluster.totalBeneficiaries', 200)
            // The historical uploaded chart image is no longer part of the payload.
            ->missing('cluster.chartImage'));

    // The other cluster keeps its own independent series.
    $this->get('/value-chains/dairy')->assertInertia(fn (Assert $page) => $page
        ->has('cluster.chart.series', 1)
        ->where('cluster.chart.series.0.label', 'Kwami, Malam Sidi')
        ->where('cluster.chart.series.0.value', 999)
        ->where('cluster.totalBeneficiaries', 999));
});

it('keeps the chart total identical to the table rows', function () {
    $cluster = chartTestCluster('Sheep and Goat', 'sheep-and-goat');
    chartTestRows($cluster, [
        ['lga' => 'Dukku', 'ward' => 'Hashidu', 'beneficiaries' => 45],
        ['lga' => 'Funakaye', 'ward' => 'Bajoga', 'beneficiaries' => 15],
        ['lga' => 'Billiri', 'ward' => 'Tudun Kuka', 'beneficiaries' => 40],
    ]);

    $this->get('/value-chains/sheep-and-goat')->assertInertia(function (Assert $page) {
        $page->component('value-chains/show')
            ->has('cluster.records', 3)
            ->has('cluster.chart.series', 3)
            ->where('cluster.totalBeneficiaries', 100)
            ->where('cluster.records.0.sNo', 1)
            ->where('cluster.records.2.sNo', 3);

        $rowsTotal = collect($page->toArray()['props']['cluster']['records'] ?? [])->sum('beneficiaries');
        $barsTotal = collect($page->toArray()['props']['cluster']['chart']['series'] ?? [])->sum('value');

        expect($rowsTotal)->toBe(100)
            ->and($barsTotal)->toBe(100)
            ->and($page->toArray()['props']['cluster']['totalBeneficiaries'])->toBe(100);
    });
});

it('combines rows that share an LGA and ward without touching the stored rows', function () {
    $cluster = chartTestCluster('Dairy', 'dairy');
    chartTestRows($cluster, [
        ['lga' => 'Gombe', 'ward' => 'Bolari East', 'beneficiaries' => 30],
        ['lga' => 'Gombe', 'ward' => 'Bolari East', 'beneficiaries' => 45],
        ['lga' => 'Akko', 'ward' => 'Kumo', 'beneficiaries' => 10],
    ]);

    $this->get('/value-chains/dairy')->assertInertia(fn (Assert $page) => $page
        ->has('cluster.records', 3)
        ->has('cluster.chart.series', 2)
        ->where('cluster.chart.series.0.label', 'Gombe, Bolari East')
        ->where('cluster.chart.series.0.value', 75)
        ->where('cluster.chart.series.1.value', 10)
        ->where('cluster.totalBeneficiaries', 85));

    // The stored records themselves are unchanged.
    expect($cluster->beneficiaries()->count())->toBe(3)
        ->and($cluster->beneficiaries()->sum('beneficiaries'))->toBe(85);
});

it('shows zero counts and an empty chart for clusters without records', function () {
    $zero = chartTestCluster('Pig', 'pig');
    chartTestRows($zero, [
        ['lga' => 'Gombe', 'ward' => 'Jekadafari', 'beneficiaries' => 0],
        ['lga' => 'Yamaltu', 'ward' => 'Deba', 'beneficiaries' => 12],
    ]);

    $empty = chartTestCluster('Fodder', 'fodder');

    $this->get('/value-chains/pig')->assertInertia(fn (Assert $page) => $page
        ->has('cluster.chart.series', 2)
        ->where('cluster.chart.series.0.value', 0)
        ->where('cluster.chart.series.1.value', 12)
        ->where('cluster.totalBeneficiaries', 12));

    $this->get('/value-chains/fodder')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('value-chains/show')
        ->has('cluster.chart.series', 0)
        ->has('cluster.records', 0)
        ->where('cluster.totalBeneficiaries', 0));
});

it('updates the chart when records are created, edited or deleted', function () {
    $admin = chartTestAdmin();

    $this->actingAs($admin)
        ->post(route('admin.clusters.store'), [
            'name' => 'Broiler',
            'description' => 'Broiler cluster.',
            'status' => 'published',
            'beneficiaries' => [
                ['lga' => 'Gombe', 'ward' => 'Bolari East', 'beneficiaries' => 20],
                ['lga' => 'Akko', 'ward' => 'Kumo', 'beneficiaries' => 5],
            ],
        ])->assertRedirect();

    $cluster = ValueChainCluster::query()->where('slug', 'broiler')->firstOrFail();

    $this->get('/value-chains/broiler')->assertInertia(fn (Assert $page) => $page
        ->has('cluster.chart.series', 2)
        ->where('cluster.chart.series.0.value', 20)
        ->where('cluster.totalBeneficiaries', 25));

    // Editing a record changes its bar and the total.
    $first = $cluster->beneficiaries()->orderBy('sort_index')->firstOrFail();

    $this->actingAs($admin)
        ->put(route('admin.clusters.update', $cluster->id), [
            'name' => 'Broiler',
            'description' => 'Broiler cluster.',
            'status' => 'published',
            'beneficiaries' => [
                ['id' => $first->id, 'lga' => 'Gombe', 'ward' => 'Bolari East', 'beneficiaries' => 33],
                ['lga' => 'Akko', 'ward' => 'Kumo', 'beneficiaries' => 5],
            ],
        ])->assertRedirect();

    $this->get('/value-chains/broiler')->assertInertia(fn (Assert $page) => $page
        ->has('cluster.chart.series', 2)
        ->where('cluster.chart.series.0.value', 33)
        ->where('cluster.totalBeneficiaries', 38));

    // Deleting the record through the table removes its bar.
    $this->actingAs($admin)
        ->put(route('admin.clusters.update', $cluster->id), [
            'name' => 'Broiler',
            'description' => 'Broiler cluster.',
            'status' => 'published',
            'beneficiaries' => [
                ['id' => $first->id, 'lga' => 'Gombe', 'ward' => 'Bolari East', 'beneficiaries' => 33],
            ],
        ])->assertRedirect();

    $this->get('/value-chains/broiler')->assertInertia(fn (Assert $page) => $page
        ->has('cluster.chart.series', 1)
        ->where('cluster.chart.series.0.label', 'Gombe, Bolari East')
        ->where('cluster.totalBeneficiaries', 33));

    // Direct removal of the last record leaves a valid, empty chart payload.
    ClusterBeneficiary::query()->where('value_chain_cluster_id', $cluster->id)->delete();

    $this->get('/value-chains/broiler')->assertInertia(fn (Assert $page) => $page
        ->has('cluster.chart.series', 0)
        ->has('cluster.records', 0)
        ->where('cluster.totalBeneficiaries', 0));
});

it('offers no image upload at all on the cluster forms', function () {
    $admin = chartTestAdmin();
    $cluster = chartTestCluster('Eggs', 'eggs');

    foreach ([route('admin.clusters.create'), route('admin.clusters.edit', $cluster->id)] as $url) {
        $response = $this->actingAs($admin)->get($url);
        $response->assertOk();

        $fields = collect($response->viewData('page')['props']['module']['fields']);

        expect($fields->contains(fn (array $field) => $field['type'] === 'image'))->toBeFalse()
            ->and($fields->contains(fn (array $field) => $field['key'] === 'image_path'))->toBeFalse()
            ->and($fields->contains(fn (array $field) => str_contains(strtolower($field['label']), 'chart')))->toBeFalse()
            ->and($fields->contains(fn (array $field) => $field['key'] === 'beneficiaries'))->toBeTrue();
    }
});

it('keeps public cluster routes and listing totals working', function () {
    $cluster = chartTestCluster('Leather', 'leather');
    chartTestRows($cluster, [
        ['lga' => 'Gombe', 'ward' => 'Bolari East', 'beneficiaries' => 7],
    ]);

    $this->get('/value-chains')->assertInertia(fn (Assert $page) => $page
        ->component('value-chains/index')
        ->has('clusters', 1)
        ->where('clusters.0.recordCount', 1)
        ->where('clusters.0.totalBeneficiaries', 7)
        // The card can preview the same chart when it has no image.
        ->has('clusters.0.chart', 1)
        ->where('clusters.0.chart.0.label', 'Gombe, Bolari East')
        ->where('clusters.0.chart.0.value', 7));

    $empty = chartTestCluster('Fodder', 'fodder');

    $this->get('/value-chains')->assertInertia(fn (Assert $page) => $page
        ->has('clusters', 2)
        ->where('clusters.0.name', 'Fodder')
        ->has('clusters.0.chart', 0)
        ->where('clusters.1.name', 'Leather')
        ->has('clusters.1.chart', 1));

    expect($empty->beneficiaries()->count())->toBe(0);

    $this->get('/value-chains/leather')->assertOk();
});
