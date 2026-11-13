<?php

use App\Models\Project;
use App\Models\ProjectCategory;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

function mapProject(array $attributes = []): Project
{
    static $sequence = 0;
    $sequence++;

    return Project::query()->create(array_merge([
        'title' => "Map project {$sequence}",
        'slug' => "map-project-{$sequence}",
        'description' => 'A project description that must never reach the marker feed.',
        'status' => 'published',
        'location_visibility' => 'hidden',
    ], $attributes));
}

function mapDistanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $earth = 6371000.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);

    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

    return 2 * $earth * asin(min(1.0, sqrt($a)));
}

function mapMarkers(TestResponse $response): Illuminate\Support\Collection
{
    return collect($response->json('data'))->keyBy('slug');
}

it('serves only published projects that have a public point', function () {
    mapProject([
        'title' => 'Exact project', 'slug' => 'exact-project', 'lga' => 'Akko', 'ward' => 'Pindiga',
        'latitude' => '10.2800000', 'longitude' => '11.1700000', 'location_visibility' => 'exact',
    ]);
    mapProject([
        'title' => 'Approximate project', 'slug' => 'approximate-project', 'lga' => 'Gombe',
        'latitude' => '10.3000000', 'longitude' => '11.2000000', 'location_visibility' => 'approximate',
    ]);
    mapProject([
        'title' => 'LGA only project', 'slug' => 'lga-only-project', 'lga' => 'Dukku',
        'latitude' => '10.5000000', 'longitude' => '11.5000000', 'location_visibility' => 'lga_only',
    ]);
    mapProject([
        'title' => 'Hidden project', 'slug' => 'hidden-project', 'lga' => 'Kwami',
        'latitude' => '10.6000000', 'longitude' => '11.6000000', 'location_visibility' => 'hidden',
    ]);
    mapProject([
        'title' => 'Exact but unmapped', 'slug' => 'exact-unmapped', 'lga' => 'Billiri', 'location_visibility' => 'exact',
    ]);
    mapProject([
        'title' => 'Draft project', 'slug' => 'draft-project', 'status' => 'draft', 'lga' => 'Akko',
        'latitude' => '10.2800000', 'longitude' => '11.1700000', 'location_visibility' => 'exact',
    ]);

    $response = $this->getJson('/our-projects/map-data')->assertOk();
    $markers = mapMarkers($response);

    expect($markers)->toHaveCount(2)
        ->and($markers->keys()->all())->toEqualCanonicalizing(['exact-project', 'approximate-project'])
        ->and($markers['exact-project']['latitude'])->toBe(10.28)
        ->and($markers['exact-project']['longitude'])->toBe(11.17)
        ->and($markers['exact-project']['lga'])->toBe('Akko')
        ->and($markers['exact-project']['href'])->toContain('/projects/exact-project');

    // Thin serialisation: no description, gallery, address or model payload.
    expect(array_keys($markers['exact-project']))->toEqualCanonicalizing([
        'id', 'title', 'slug', 'href', 'category', 'lga', 'latitude', 'longitude', 'precision', 'directionsUrl',
    ]);

    $response->assertHeader('Cache-Control');

    expect(strtolower($response->headers->get('Cache-Control')))->toContain('public');
});

it('displaces approximate coordinates deterministically without leaking the stored point', function () {
    mapProject([
        'title' => 'Approximate only', 'slug' => 'approx-only', 'lga' => 'Nafada',
        'latitude' => '10.3000000', 'longitude' => '11.2000000', 'location_visibility' => 'approximate',
    ]);

    $first = mapMarkers($this->getJson('/our-projects/map-data'))['approx-only'];
    $second = mapMarkers($this->getJson('/our-projects/map-data'))['approx-only'];

    expect($first['latitude'])->toBe($second['latitude'])
        ->and($first['longitude'])->toBe($second['longitude'])
        ->and($first['precision'])->toBe('approximate');

    $distance = mapDistanceMeters(10.3, 11.2, $first['latitude'], $first['longitude']);

    expect($distance)->toBeGreaterThan(400.0)->toBeLessThan(1600.0);

    $payload = $this->getJson('/our-projects/map-data')->getContent();

    expect($payload)->not->toContain('10.3000000')
        ->and($payload)->not->toContain('11.2000000')
        ->and($first['directionsUrl'])->not->toContain('10.3000000');
});

it('filters map data by lga and category and ignores unknown values', function () {
    $category = ProjectCategory::query()->create(['name' => 'Water', 'slug' => 'water']);
    $other = ProjectCategory::query()->create(['name' => 'Roads', 'slug' => 'roads']);

    mapProject([
        'title' => 'Akko water', 'slug' => 'akko-water', 'lga' => 'Akko', 'project_category_id' => $category->id,
        'latitude' => '10.2800000', 'longitude' => '11.1700000', 'location_visibility' => 'exact',
    ]);
    mapProject([
        'title' => 'Gombe roads', 'slug' => 'gombe-roads', 'lga' => 'Gombe', 'project_category_id' => $other->id,
        'latitude' => '10.2900000', 'longitude' => '11.1800000', 'location_visibility' => 'exact',
    ]);

    expect(mapMarkers($this->getJson('/our-projects/map-data?lga=Akko')))->toHaveCount(1);
    expect(mapMarkers($this->getJson('/our-projects/map-data?lga=Akko'))->keys()->all())->toBe(['akko-water']);

    expect(mapMarkers($this->getJson('/our-projects/map-data?category=water')))->toHaveCount(1);
    expect(mapMarkers($this->getJson('/our-projects/map-data?category=water'))->keys()->all())->toBe(['akko-water']);

    // Invalid values fall back to the unfiltered result instead of erroring.
    $this->getJson('/our-projects/map-data?lga=Nowhere')->assertOk();
    expect(mapMarkers($this->getJson('/our-projects/map-data?lga=Nowhere')))->toHaveCount(2);
    expect(mapMarkers($this->getJson('/our-projects/map-data?category=missing')))->toHaveCount(2);

    // Both filters together.
    expect(mapMarkers($this->getJson('/our-projects/map-data?lga=Gombe&category=water')))->toHaveCount(0);
});

it('filters the public listing on the server and shares the same filter state with the map', function () {
    $category = ProjectCategory::query()->create(['name' => 'Water', 'slug' => 'water']);

    mapProject([
        'title' => 'Akko water', 'slug' => 'akko-water', 'lga' => 'Akko', 'project_category_id' => $category->id,
        'latitude' => '10.2800000', 'longitude' => '11.1700000', 'location_visibility' => 'exact',
    ]);
    mapProject(['title' => 'Unmapped project', 'slug' => 'unmapped-project', 'lga' => 'Gombe']);

    $this->get('/our-projects')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/index')
            ->has('projects', 2)
            ->where('filters.lga', null)
            ->where('filters.category', null)
            ->has('filterOptions.lgas', 11)
            ->has('filterOptions.categories', 1)
            ->where('filterOptions.categories.0.slug', 'water')
            ->where('map.dataUrl', url('/our-projects/map-data'))
            ->where('map.tileUrl', null)
            // Newest first: the unmapped project is listed, the mapped one is flagged.
            ->where('projects.0.isMapped', false)
            ->where('projects.1.isMapped', true));

    $this->get('/our-projects?lga=Akko&category=water')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/index')
            ->has('projects', 1)
            ->where('projects.0.title', 'Akko water')
            ->where('filters.lga', 'Akko')
            ->where('filters.category', 'water'));

    // Invalid query values never empty the listing.
    $this->get('/our-projects?lga=Nowhere&category=missing')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('projects', 2)
            ->where('filters.lga', null)
            ->where('filters.category', null));
});

it('shows only cleared location details on the project page', function () {
    $exact = mapProject([
        'title' => 'Exact detail', 'slug' => 'exact-detail', 'lga' => 'Akko', 'ward' => 'Pindiga',
        'address' => 'Beside the livestock market',
        'latitude' => '10.2800000', 'longitude' => '11.1700000', 'location_visibility' => 'exact',
    ]);

    $this->get('/projects/exact-detail')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/show')
            ->where('project.location.visibility', 'exact')
            ->where('project.location.lga', 'Akko')
            ->where('project.location.ward', 'Pindiga')
            ->where('project.location.description', 'Beside the livestock market')
            ->where('project.location.isApproximate', false)
            ->where('project.location.coordinates.latitude', 10.28)
            ->where('project.location.coordinates.longitude', 11.17)
            ->where('project.location.directionsUrl', 'https://www.google.com/maps/dir/?api=1&destination=10.2800000,11.1700000')
            ->missing('project.latitude'));

    mapProject([
        'title' => 'Approximate detail', 'slug' => 'approximate-detail', 'lga' => 'Gombe',
        'latitude' => '10.3000000', 'longitude' => '11.2000000', 'location_visibility' => 'approximate',
    ]);

    $this->get('/projects/approximate-detail')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('project.location.visibility', 'approximate')
            ->where('project.location.isApproximate', true)
            ->where('project.location.lga', 'Gombe')
            ->where('project.location.coordinates.precision', 'approximate')
            ->where('project.location.coordinates.latitude', fn ($value) => is_numeric($value) && abs((float) $value - 10.3) > 0.001)
            ->where('project.location.directionsUrl', fn ($value) => is_string($value) && ! str_contains($value, '10.3000000')));

    mapProject([
        'title' => 'LGA only detail', 'slug' => 'lga-only-detail', 'lga' => 'Dukku', 'ward' => 'Dukku',
        'address' => 'Secret landmark',
        'latitude' => '10.5000000', 'longitude' => '11.5000000', 'location_visibility' => 'lga_only',
    ]);

    $this->get('/projects/lga-only-detail')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('project.location.visibility', 'lga_only')
            ->where('project.location.lga', 'Dukku')
            ->where('project.location.ward', null)
            ->where('project.location.description', null)
            ->where('project.location.coordinates', null)
            ->where('project.location.directionsUrl', null));

    mapProject([
        'title' => 'Hidden detail', 'slug' => 'hidden-detail', 'lga' => 'Kwami',
        'latitude' => '10.6000000', 'longitude' => '11.6000000', 'location_visibility' => 'hidden',
    ]);

    $this->get('/projects/hidden-detail')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('project.location.visibility', 'hidden')
            ->where('project.location.lga', null)
            ->where('project.location.coordinates', null)
            ->where('project.location.directionsUrl', null));

    // Stored coordinates never appear anywhere on the rendered page.
    $html = $this->get('/projects/hidden-detail')->getContent();

    expect($html)->not->toContain('10.6000000')->and($html)->not->toContain('11.6000000');

    expect($exact->latitude)->toBe('10.2800000');
    expect($exact->location_visibility)->toBe('exact');
});
