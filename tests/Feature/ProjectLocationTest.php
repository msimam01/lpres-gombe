<?php

use App\Models\Project;
use App\Models\User;

/**
 * Administrators are the users who can currently reach the admin panel; this
 * file exercises the project location data model, not authorization.
 */
function projectEditor(): User
{
    return User::factory()->admin()->create();
}

/** @return array<string, mixed> */
function projectPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Location validation project',
        'description' => 'A project used to exercise location validation.',
        'status' => 'draft',
        'location_visibility' => 'hidden',
    ], $overrides);
}

it('stores coordinates, LGA, ward, description and visibility', function () {
    $this->actingAs(projectEditor())
        ->post(route('admin.projects.store'), projectPayload([
            'title' => 'Cattle route demarcation',
            'status' => 'published',
            'lga' => 'Akko',
            'ward' => 'Akko',
            'address' => 'Near the central market',
            'location_visibility' => 'exact',
            'latitude' => '10.2891234',
            'longitude' => '11.1671234',
        ]))
        ->assertRedirect();

    $project = Project::query()->where('slug', 'cattle-route-demarcation')->firstOrFail();

    expect($project->lga)->toBe('Akko')
        ->and($project->ward)->toBe('Akko')
        ->and($project->address)->toBe('Near the central market')
        ->and($project->location_visibility)->toBe('exact')
        ->and($project->latitude)->toBe('10.2891234')
        ->and($project->longitude)->toBe('11.1671234')
        ->and($project->hasCoordinates())->toBeTrue();
});

it('rejects coordinates outside the legal range', function () {
    $this->actingAs(projectEditor())
        ->post(route('admin.projects.store'), projectPayload([
            'latitude' => '91',
            'longitude' => '11.1671234',
        ]))
        ->assertSessionHasErrors('latitude');

    $this->actingAs(projectEditor())
        ->post(route('admin.projects.store'), projectPayload([
            'title' => 'Bad longitude',
            'latitude' => '10.2891234',
            'longitude' => '-181',
        ]))
        ->assertSessionHasErrors('longitude');

    $this->actingAs(projectEditor())
        ->post(route('admin.projects.store'), projectPayload([
            'title' => 'Non numeric',
            'latitude' => 'north',
            'longitude' => 'east',
        ]))
        ->assertSessionHasErrors(['latitude', 'longitude']);

    expect(Project::query()->count())->toBe(0);
});

it('requires latitude and longitude to be supplied together', function () {
    $this->actingAs(projectEditor())
        ->post(route('admin.projects.store'), projectPayload([
            'title' => 'Latitude only',
            'latitude' => '10.2891234',
        ]))
        ->assertSessionHasErrors('longitude');

    $this->actingAs(projectEditor())
        ->post(route('admin.projects.store'), projectPayload([
            'title' => 'Longitude only',
            'longitude' => '11.1671234',
        ]))
        ->assertSessionHasErrors('latitude');

    expect(Project::query()->count())->toBe(0);
});

it('accepts empty coordinate inputs as "not recorded"', function () {
    $this->actingAs(projectEditor())
        ->post(route('admin.projects.store'), projectPayload([
            'title' => 'No coordinates yet',
            'latitude' => '',
            'longitude' => '',
        ]))
        ->assertRedirect();

    $project = Project::query()->where('slug', 'no-coordinates-yet')->firstOrFail();

    expect($project->latitude)->toBeNull()
        ->and($project->longitude)->toBeNull()
        ->and($project->hasCoordinates())->toBeFalse();
});

it('rejects an LGA outside the approved list and an unknown visibility', function () {
    $this->actingAs(projectEditor())
        ->post(route('admin.projects.store'), projectPayload([
            'title' => 'Unknown LGA',
            'lga' => 'Shomgom',
        ]))
        ->assertSessionHasErrors('lga');

    $this->actingAs(projectEditor())
        ->post(route('admin.projects.store'), projectPayload([
            'title' => 'Unknown visibility',
            'location_visibility' => 'public',
        ]))
        ->assertSessionHasErrors('location_visibility');

    expect(Project::query()->count())->toBe(0);
});

it('defaults new records to hidden visibility', function () {
    $project = Project::query()->create([
        'title' => 'Unmapped project',
        'slug' => 'unmapped-project',
        'status' => 'published',
    ]);

    expect($project->fresh()->location_visibility)->toBe('hidden')
        ->and($project->fresh()->hasCoordinates())->toBeFalse();
});

it('saves and edits a project that has no coordinates', function () {
    $this->actingAs(projectEditor())
        ->post(route('admin.projects.store'), projectPayload([
            'title' => 'Ongoing re-surveying',
            'status' => 'published',
            'lga' => 'Dukku',
            'ward' => 'Bajoga',
        ]))
        ->assertRedirect();

    $project = Project::query()->where('slug', 'ongoing-re-surveying')->firstOrFail();

    $this->actingAs(projectEditor())
        ->put(route('admin.projects.update', $project->id), projectPayload([
            'title' => $project->title,
            'status' => 'published',
            'lga' => 'Dukku',
            'ward' => 'Bajoga East',
        ]))
        ->assertRedirect();

    $project->refresh();

    expect($project->ward)->toBe('Bajoga East')
        ->and($project->status)->toBe('published')
        ->and($project->latitude)->toBeNull()
        ->and($project->longitude)->toBeNull();
});

it('preserves identity, status and media when a location is added', function () {
    $project = Project::query()->create([
        'title' => 'Rehabilitation of Gombe slaughter slab',
        'slug' => 'rehabilitation-of-gombe-slaughter-slab',
        'description' => 'Original description.',
        'status' => 'published',
        'featured_image_path' => 'uploads/projects/featured.jpg',
        'gallery_image_paths' => ['uploads/projects/one.jpg', 'uploads/projects/two.jpg'],
        'address' => 'Old address',
    ]);

    $id = $project->id;

    $this->actingAs(projectEditor())
        ->put(route('admin.projects.update', $project->id), [
            'title' => $project->title,
            'description' => $project->description,
            'status' => 'published',
            'lga' => 'Gombe',
            'ward' => 'Bolari East',
            'address' => 'Updated landmark',
            'location_visibility' => 'lga_only',
        ])
        ->assertRedirect();

    $project->refresh();

    expect($project->id)->toBe($id)
        ->and($project->slug)->toBe('rehabilitation-of-gombe-slaughter-slab')
        ->and($project->status)->toBe('published')
        ->and($project->featured_image_path)->toBe('uploads/projects/featured.jpg')
        ->and($project->gallery_image_paths)->toBe(['uploads/projects/one.jpg', 'uploads/projects/two.jpg'])
        ->and($project->lga)->toBe('Gombe')
        ->and($project->address)->toBe('Updated landmark')
        ->and($project->latitude)->toBeNull();
});

it('saves an exact position without touching the LGA or ward', function () {
    $project = Project::query()->create([
        'title' => 'Veterinary hospital upgrade',
        'slug' => 'veterinary-hospital-upgrade',
        'description' => 'Upgrade works.',
        'status' => 'published',
        'lga' => 'Gombe',
        'ward' => 'Nasarawo',
    ]);

    $this->actingAs(projectEditor())
        ->put(route('admin.projects.update', $project->id), [
            'title' => $project->title,
            'description' => $project->description,
            'status' => 'published',
            'lga' => 'Gombe',
            'ward' => 'Nasarawo',
            'location_visibility' => 'exact',
            'latitude' => '10.2890000',
            'longitude' => '11.1670000',
        ])
        ->assertRedirect();

    $project->refresh();

    expect($project->latitude)->toBe('10.2890000')
        ->and($project->longitude)->toBe('11.1670000')
        ->and($project->lga)->toBe('Gombe')
        ->and($project->ward)->toBe('Nasarawo')
        ->and($project->location_visibility)->toBe('exact');
});
