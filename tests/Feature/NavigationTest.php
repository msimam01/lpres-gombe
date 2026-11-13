<?php

use App\Models\User;
use App\Models\ValueChainCluster;
use Inertia\Testing\AssertableInertia as Assert;

it('shares published clusters with the header navigation', function () {
    ValueChainCluster::query()->create(['name' => 'Dairy', 'slug' => 'dairy', 'status' => 'published']);
    ValueChainCluster::query()->create(['name' => 'Leather', 'slug' => 'leather', 'status' => 'draft']);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('navClusters', 1)
            ->where('navClusters.0.label', 'Dairy')
            ->where('navClusters.0.href', route('value-chains.show', 'dairy')));
});

it('adds a newly created cluster to the navigation without a code change', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.clusters.store'), ['name' => 'Aquaculture'])
        ->assertRedirect();

    expect(ValueChainCluster::query()->where('slug', 'aquaculture')->firstOrFail()->status)->toBe('published');

    $this->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->where('navClusters.0.label', 'Aquaculture')
            ->where('navClusters.0.href', route('value-chains.show', 'aquaculture')));

    // Hiding the cluster removes it from the navigation again.
    ValueChainCluster::query()->where('slug', 'aquaculture')->update(['status' => 'draft']);

    $this->get('/')->assertInertia(fn (Assert $page) => $page->has('navClusters', 0));
});

it('keeps navigation labels on the value chain page with its own cluster list', function () {
    ValueChainCluster::query()->create(['name' => 'Dairy', 'slug' => 'dairy', 'status' => 'published']);

    // /value-chains sends its own `clusters` prop; the navigation list must
    // not collide with it or the dropdown rows lose their labels.
    $this->get('/value-chains')->assertInertia(fn (Assert $page) => $page
        ->where('navClusters.0.label', 'Dairy')
        ->where('navClusters.0.href', route('value-chains.show', 'dairy'))
        ->has('clusters', 1)
        ->where('clusters.0.name', 'Dairy'));
});

it('renders both header menus from the shared cluster list', function () {
    expect(file_get_contents(base_path('resources/js/components/site/site-nav.tsx')))
        ->toContain('navigationWithClusters')
        ->and(file_get_contents(base_path('resources/js/components/site/site-menu.tsx')))
        ->toContain('navigationWithClusters');
});
