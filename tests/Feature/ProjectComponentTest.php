<?php

use App\Models\ProjectComponent;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Real JPEG bytes — GD is unavailable locally, so fake images are built from
 * an existing bundled asset (matching the rest of the suite).
 */
function componentJpeg(string $name = 'photo.jpg'): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        $name,
        file_get_contents(public_path('images/lpres/lpres-logo.jpeg')),
    );
}

function componentEditor(): User
{
    return User::factory()->admin()->create();
}

it('lists only published components on the homepage, in display order', function () {
    $later = ProjectComponent::query()->create([
        'title' => 'Livestock value chain enhancement',
        'slug' => 'livestock-value-chain-enhancement',
        'description' => 'Second component summary.',
        'display_order' => 20,
        'status' => true,
    ]);

    $earlier = ProjectComponent::query()->create([
        'title' => 'Institutional and innovation system strengthening',
        'slug' => 'institutional-and-innovation-system-strengthening',
        'description' => 'First component summary.',
        'display_order' => 5,
        'status' => true,
    ]);

    ProjectComponent::query()->create([
        'title' => 'Hidden component', 'slug' => 'hidden-component', 'display_order' => 1, 'status' => false,
    ]);

    // A record without a slug cannot be linked to, so it is never published.
    ProjectComponent::query()->create([
        'title' => 'Slugless component', 'slug' => null, 'display_order' => 0, 'status' => true,
    ]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('home')
            ->has('dashboardComponents', 2)
            ->where('dashboardComponents.0.title', $earlier->title)
            ->where('dashboardComponents.0.href', route('project-components.show', $earlier->slug))
            ->where('dashboardComponents.0.description', 'First component summary.')
            ->where('dashboardComponents.0.image', null)
            ->where('dashboardComponents.1.title', $later->title)
            ->where('dashboardComponents.1.href', route('project-components.show', $later->slug)));
});

it('serves a published component on its own slug page', function () {
    $component = ProjectComponent::query()->create([
        'title' => 'Crisis prevention and management',
        'slug' => 'crisis-prevention-and-management',
        'description' => "First paragraph.\n\nSecond paragraph.",
        'display_order' => 1,
        'status' => true,
    ]);

    $other = ProjectComponent::query()->create([
        'title' => 'Livestock value chain enhancement',
        'slug' => 'livestock-value-chain-enhancement',
        'display_order' => 2,
        'status' => true,
    ]);

    $this->get(route('project-components.show', $component->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('project-components/show')
            ->where('component.title', 'Crisis prevention and management')
            ->where('component.description', "First paragraph.\n\nSecond paragraph.")
            ->where('component.metaDescription', 'First paragraph. Second paragraph.')
            ->where('component.image', null)
            ->has('others', 1)
            ->where('others.0.href', route('project-components.show', $other->slug)));
});

it('returns 404 for hidden, unpublished and unknown components', function () {
    ProjectComponent::query()->create([
        'title' => 'Not yet public', 'slug' => 'not-yet-public', 'display_order' => 1, 'status' => false,
    ]);

    ProjectComponent::query()->create([
        'title' => 'No slug at all', 'slug' => null, 'display_order' => 2, 'status' => true,
    ]);

    $this->get('/components/not-yet-public')->assertNotFound();
    $this->get('/components/does-not-exist')->assertNotFound();

    // The earlier prefix permanently redirects to the canonical URL.
    $this->get('/project-components/does-not-exist')->assertStatus(301);
});

it('creates a component with a generated slug and a stored featured image', function () {
    Storage::fake('public');

    $this->actingAs(componentEditor())
        ->post(route('admin.project-components.store'), [
            'title' => 'Market access and infrastructure',
            'description' => 'Component summary.',
            'display_order' => 4,
            'status' => true,
            'image_path' => componentJpeg(),
        ])
        ->assertRedirect(route('admin.project-components.index'));

    $component = ProjectComponent::query()->where('slug', 'market-access-and-infrastructure')->firstOrFail();

    expect($component->display_order)->toBe(4)
        ->and($component->status)->toBeTrue()
        ->and($component->image_path)->not->toBeNull();

    Storage::disk('public')->assertExists($component->image_path);
});

it('validates the featured image type and keeps the old image when an update fails', function () {
    Storage::fake('public');

    $admin = componentEditor();

    $this->actingAs($admin)->post(route('admin.project-components.store'), [
        'title' => 'Validated component',
        'description' => 'Summary.',
        'display_order' => 1,
        'status' => true,
        'image_path' => componentJpeg(),
    ]);

    $component = ProjectComponent::query()->where('slug', 'validated-component')->firstOrFail();
    $storedPath = $component->image_path;

    $this->actingAs($admin)
        ->from(route('admin.project-components.edit', $component->id))
        ->put(route('admin.project-components.update', $component->id), [
            'title' => 'Validated component',
            'description' => 'Summary.',
            'display_order' => 1,
            'status' => true,
            'image_path' => UploadedFile::fake()->create('payload.exe', 10),
        ])
        ->assertSessionHasErrors('image_path');

    expect($component->fresh()->image_path)->toBe($storedPath);
    Storage::disk('public')->assertExists($storedPath);
});

it('keeps the slug on update and releases a replaced image', function () {
    Storage::fake('public');
    $admin = componentEditor();

    $this->actingAs($admin)->post(route('admin.project-components.store'), [
        'title' => 'Replaceable image component',
        'description' => 'Summary.',
        'display_order' => 1,
        'status' => true,
        'image_path' => componentJpeg(),
    ]);

    $component = ProjectComponent::query()->where('slug', 'replaceable-image-component')->firstOrFail();
    $oldPath = $component->image_path;

    $this->actingAs($admin)->put(route('admin.project-components.update', $component->id), [
        'title' => 'Replaceable image component renamed',
        'description' => 'Updated summary.',
        'display_order' => 2,
        'status' => true,
        'image_path' => componentJpeg('replacement.jpg'),
    ])->assertRedirect(route('admin.project-components.index'));

    $component->refresh();

    expect($component->slug)->toBe('replaceable-image-component')
        ->and($component->title)->toBe('Replaceable image component renamed')
        ->and($component->image_path)->not->toBe($oldPath);

    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($component->image_path);
});

it('removes the featured image from a component', function () {
    Storage::fake('public');
    $admin = componentEditor();

    $this->actingAs($admin)->post(route('admin.project-components.store'), [
        'title' => 'Removable image component',
        'description' => 'Summary.',
        'display_order' => 1,
        'status' => true,
        'image_path' => componentJpeg(),
    ]);

    $component = ProjectComponent::query()->where('slug', 'removable-image-component')->firstOrFail();
    $path = $component->image_path;

    $this->actingAs($admin)->put(route('admin.project-components.update', $component->id), [
        'title' => 'Removable image component',
        'description' => 'Summary.',
        'display_order' => 1,
        'status' => true,
        'remove_image_path' => true,
    ])->assertRedirect();

    expect($component->fresh()->image_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('deletes a component together with its image', function () {
    Storage::fake('public');
    $admin = componentEditor();

    $this->actingAs($admin)->post(route('admin.project-components.store'), [
        'title' => 'Temporary component',
        'description' => 'Summary.',
        'display_order' => 1,
        'status' => true,
        'image_path' => componentJpeg(),
    ]);

    $component = ProjectComponent::query()->where('slug', 'temporary-component')->firstOrFail();
    $path = $component->image_path;

    $this->actingAs($admin)
        ->delete(route('admin.project-components.destroy', $component->id))
        ->assertRedirect();

    expect(ProjectComponent::query()->whereKey($component->id)->exists())->toBeFalse();
    Storage::disk('public')->assertMissing($path);
    $this->get('/components/temporary-component')->assertNotFound();
});

it('never deletes a file another component still references', function () {
    Storage::fake('public');

    $shared = 'uploads/project-components/shared-photo.jpg';
    Storage::disk('public')->put($shared, 'shared-bytes');

    $first = ProjectComponent::query()->create([
        'title' => 'First sharing component', 'slug' => 'first-sharing-component',
        'image_path' => $shared, 'display_order' => 1, 'status' => true,
    ]);
    ProjectComponent::query()->create([
        'title' => 'Second sharing component', 'slug' => 'second-sharing-component',
        'image_path' => $shared, 'display_order' => 2, 'status' => true,
    ]);

    $this->actingAs(componentEditor())
        ->delete(route('admin.project-components.destroy', $first->id))
        ->assertRedirect();

    Storage::disk('public')->assertExists($shared);
});

it('publishes and hides a component from the admin list', function () {
    $component = ProjectComponent::query()->create([
        'title' => 'Toggleable component', 'slug' => 'toggleable-component', 'display_order' => 1, 'status' => false,
    ]);

    $this->get('/components/toggleable-component')->assertNotFound();

    $this->actingAs(componentEditor())
        ->post(route('admin.project-components.toggle', $component->id))
        ->assertRedirect();

    expect($component->fresh()->status)->toBeTrue();
    $this->get('/components/toggleable-component')->assertOk();

    $this->actingAs(componentEditor())
        ->post(route('admin.project-components.toggle', $component->id))
        ->assertRedirect();

    expect($component->fresh()->status)->toBeFalse();
    $this->get('/components/toggleable-component')->assertNotFound();
});

it('forbids non-administrators from managing components', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $component = ProjectComponent::query()->create([
        'title' => 'Protected component', 'slug' => 'protected-component', 'display_order' => 1, 'status' => true,
    ]);

    $this->actingAs($user)->get('/admin/project-components')->assertForbidden();
    $this->actingAs($user)->post(route('admin.project-components.store'), ['title' => 'Unauthorised'])->assertForbidden();
    $this->actingAs($user)->put(route('admin.project-components.update', $component->id), ['title' => 'Changed'])->assertForbidden();
    $this->actingAs($user)->delete(route('admin.project-components.destroy', $component->id))->assertForbidden();

    expect(ProjectComponent::query()->count())->toBe(1)
        ->and($component->fresh()->title)->toBe('Protected component');
});

it('publishes a component with no image or description using graceful fallbacks', function () {
    ProjectComponent::query()->create([
        'title' => 'Component without media',
        'slug' => 'component-without-media',
        'description' => null,
        'image_path' => null,
        'display_order' => 30,
        'status' => true,
    ]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('dashboardComponents', 1)
            ->where('dashboardComponents.0.image', null)
            ->where('dashboardComponents.0.description', null)
            ->where('dashboardComponents.0.excerpt', null));

    $this->get('/components/component-without-media')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('component.image', null)
            ->where('component.description', null)
            ->where(
                'component.metaDescription',
                fn (string $value) => str_contains($value, 'Component without media'),
            ));
});

it('fills card frames with cover while homepage news and detail pages show whole images', function () {
    $cards = [
        'resources/js/components/site/news-card.tsx',
        'resources/js/components/site/home-projects.tsx',
        'resources/js/components/site/project-components.tsx',
        'resources/js/pages/projects/index.tsx',
    ];

    foreach ($cards as $card) {
        $source = file_get_contents(base_path($card));

        // Cards are covered edge to edge, so no letterbox bands appear.
        expect($source)->toContain('object-cover')
            ->and($source)->not->toContain('object-contain');
    }

    // Homepage news keeps the whole photograph visible, never cropped.
    expect(file_get_contents(base_path('resources/js/components/site/latest-news.tsx')))
        ->toContain('object-contain');

    // Detail pages show the complete image, uncropped.
    expect(file_get_contents(base_path('resources/js/pages/project-components/show.tsx')))
        ->toContain('object-contain');
});

it('keeps component slugs unique when titles collide', function () {
    ProjectComponent::query()->create([
        'title' => 'Duplicate title', 'slug' => 'duplicate-title', 'display_order' => 1, 'status' => true,
    ]);

    $this->actingAs(componentEditor())
        ->post(route('admin.project-components.store'), [
            'title' => 'Duplicate title',
            'description' => 'Second record.',
            'display_order' => 2,
            'status' => true,
        ])
        ->assertRedirect(route('admin.project-components.index'));

    expect(ProjectComponent::query()->where('slug', 'duplicate-title-2')->exists())->toBeTrue();
});
