<?php

use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Regression: the browser never sends an empty gallery-kept array (Inertia
 * drops empty arrays from multipart form data), so a create with only fresh
 * uploads arrives as files + the "gallery edited" sentinel. Previously the
 * server skipped syncing and the project saved with galleries = null.
 */
it('saves first-time project gallery uploads sent as sentinel plus files', function () {
    Storage::fake('public');
    $realJpeg = file_get_contents(public_path('images/lpres/lpres-logo.jpeg'));
    $category = ProjectCategory::create(['name' => 'Infrastructure', 'slug' => 'infrastructure']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.projects.store'), [
            'title' => 'Fodder field expansion',
            'description' => 'New fodder fields across LGAs.',
            'project_category_id' => (string) $category->id,
            'gallery_has_gallery_image_paths' => '1',
            'files_gallery_image_paths' => [
                UploadedFile::fake()->createWithContent('g1.jpg', $realJpeg),
                UploadedFile::fake()->createWithContent('g2.jpg', $realJpeg),
            ],
            'status' => 'published',
        ])->assertRedirect();

    $project = Project::query()->where('slug', 'fodder-field-expansion')->firstOrFail();

    $paths = (array) $project->gallery_image_paths;

    expect(count($paths))->toBe(2)
        ->and(Storage::disk('public')->exists($paths[0]))->toBeTrue()
        ->and(Storage::disk('public')->exists($paths[1]))->toBeTrue();

    // The public detail page renders them.
    $this->get('/projects/fodder-field-expansion')->assertOk()->assertInertia(fn ($page) => $page
        ->component('projects/show')
        ->has('project.gallery', 2));
});
