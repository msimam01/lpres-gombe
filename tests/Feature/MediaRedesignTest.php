<?php

use App\Models\Event;
use App\Models\Gallery;
use App\Models\MediaItem;
use App\Models\User;
use App\Models\ValueChainCluster;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function mediaAdmin(): User
{
    return User::factory()->create();
}

function realJpeg(): string
{
    return file_get_contents(public_path('images/lpres/lpres-logo.jpeg'));
}

it('creates a video item from a YouTube URL and stores the canonical watch URL', function () {
    Storage::fake('public');

    $this->actingAs(mediaAdmin())
        ->post(route('admin.media.videos.store'), [
            'title' => 'Flag-off ceremony video',
            'caption' => 'Coverage of the launch.',
            'video_url' => 'https://youtu.be/dQw4w9WgXcQ?si=xyz',
            'status' => 'published',
        ])
        ->assertRedirect();

    $item = MediaItem::query()->where('title', 'Flag-off ceremony video')->firstOrFail();
    expect($item->media_type)->toBe('video')
        ->and($item->video_url)->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
});

it('rejects non-YouTube video URLs and raw embed markup', function () {
    Storage::fake('public');

    $this->actingAs(mediaAdmin())
        ->from(route('admin.media.videos.create'))
        ->post(route('admin.media.videos.store'), [
            'title' => 'Not a video',
            'video_url' => '<iframe src="https://evil.example/embed"></iframe>',
            'status' => 'published',
        ])->assertSessionHasErrors('video_url');

    $this->actingAs(mediaAdmin())
        ->post(route('admin.media.videos.store'), [
            'title' => 'Vimeo instead',
            'video_url' => 'https://vimeo.com/1234567',
            'status' => 'published',
        ])->assertSessionHasErrors('video_url');

    expect(MediaItem::where('title', 'Not a video')->exists())->toBeFalse();
});

it('creates an image item and can edit it without re-uploading the image', function () {
    Storage::fake('public');

    $this->actingAs(mediaAdmin())
        ->post(route('admin.media.images.store'), [
            'title' => 'Dairy farm visit',
            'caption' => 'Field visit photo.',
            'image_path' => UploadedFile::fake()->createWithContent('visit.jpg', realJpeg()),
            'status' => 'published',
        ])->assertRedirect();

    $item = MediaItem::query()->where('title', 'Dairy farm visit')->firstOrFail();
    Storage::disk('public')->assertExists($item->image_path);

    // Editing without choosing a new image succeeds and keeps the file.
    $this->actingAs(mediaAdmin())
        ->put(route('admin.media.images.update', $item->id), [
            'title' => 'Dairy farm visit',
            'caption' => 'Updated caption.',
            'image_path' => '',
            'status' => 'published',
        ])->assertRedirect();

    expect($item->fresh()->image_path)->toBe($item->image_path)
        ->and($item->fresh()->caption)->toBe('Updated caption.')
        ->and(Storage::disk('public')->exists($item->image_path))->toBeTrue();
});

it('associates media with an event and shows the association on the public page', function () {
    Storage::fake('public');

    $event = Event::create([
        'title' => 'Vaccination drive', 'slug' => 'vaccination-drive',
        'description' => 'Annual drive.', 'starts_at' => now()->addWeek(), 'status' => 'published',
    ]);

    $this->actingAs(mediaAdmin())
        ->post(route('admin.media.images.store'), [
            'title' => 'Drive photo journal',
            'image_path' => UploadedFile::fake()->createWithContent('drive.jpg', realJpeg()),
            'associated' => 'event:'.$event->id,
            'status' => 'published',
        ])->assertRedirect();

    $this->get('/media')->assertOk()->assertInertia(function (Assert $page) use ($event) {
        $page->component('media')
            ->where('images.0.seenOnLabel', 'Event: Vaccination drive')
            ->where('images.0.seenOnHref', route('events.show', $event->slug));
    });
});

it('creates a gallery with multiple images, edits it keeping existing images, and renders it publicly', function () {
    Storage::fake('public');

    $this->actingAs(mediaAdmin())
        ->post(route('admin.media.galleries.store'), [
            'title' => 'Fodder field day',
            'gallery_images' => ['', ''],
            'files_images' => [
                UploadedFile::fake()->createWithContent('g1.jpg', realJpeg()),
                UploadedFile::fake()->createWithContent('g2.jpg', realJpeg()),
            ],
            'status' => 'published',
        ])->assertRedirect();

    $gallery = Gallery::query()->where('slug', 'fodder-field-day')->firstOrFail();
    expect($gallery->images)->toHaveCount(2);

    // Existing images are preserved automatically.
    $keep = $gallery->images->pluck('path')->all();

    $this->actingAs(mediaAdmin())
        ->put(route('admin.media.galleries.update', $gallery->id), [
            'title' => 'Fodder field day',
            'description' => 'Photos from the field day.',
            'gallery_images' => $keep,
            'status' => 'published',
        ])->assertRedirect();

    $gallery->refresh();
    expect($gallery->images->count())->toBe(2)
        ->and($gallery->images->sortBy('sort_index')->pluck('path')->all())->toBe($keep);

    $this->get('/media')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('media')
        ->where('galleries.0.title', 'Fodder field day')
        ->where('galleries.0.imageCount', 2));

    $this->get('/media/galleries/fodder-field-day')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('media/gallery')
        ->where('gallery.title', 'Fodder field day')
        ->has('gallery.images', 2));
});

it('renders only published media publicly and hides drafts', function () {
    MediaItem::create(['title' => 'Live image', 'media_type' => 'image', 'image_path' => 'fallback/none.jpg', 'status' => 'published']);
    MediaItem::create(['title' => 'Hidden video', 'media_type' => 'video', 'video_url' => 'https://www.youtube.com/watch?v=abcZdef456', 'status' => 'draft']);
    Gallery::create(['title' => 'Open gallery', 'slug' => 'open-gallery', 'status' => 'published']);
    Gallery::create(['title' => 'Secret gallery', 'slug' => 'secret-gallery', 'status' => 'draft']);

    $this->get('/media')->assertOk()->assertInertia(function (Assert $page) {
        $page->component('media')
            ->has('images', 1)
            ->where('images.0.title', 'Live image')
            ->has('videos', 0)
            ->has('galleries', 1);
    });

    $this->get('/media/galleries/open-gallery')->assertOk();
    $this->get('/media/galleries/secret-gallery')->assertNotFound();
});

it('surfaces associated media on the event detail page', function () {
    Storage::fake('public');

    $event = Event::create([
        'title' => 'Dairy training', 'slug' => 'dairy-training', 'description' => 'Training week.',
        'starts_at' => now()->addWeek(), 'status' => 'published',
    ]);

    Gallery::create([
        'title' => 'Training gallery', 'slug' => 'training-gallery',
        'associated_type' => 'event', 'associated_id' => $event->id, 'status' => 'published',
    ]);

    MediaItem::create([
        'title' => 'Training recap', 'media_type' => 'video',
        'video_url' => 'https://www.youtube.com/watch?v=abcZdef456',
        'associated_type' => 'event', 'associated_id' => $event->id, 'status' => 'published',
    ]);

    $this->get('/events/dairy-training')->assertOk()->assertInertia(function (Assert $page) {
        $page->component('events/show')
            ->has('mediaGalleries', 1)
            ->where('mediaGalleries.0.title', 'Training gallery')
            ->has('mediaVideos', 1)
            ->where('mediaVideos.0.title', 'Training recap');
    });
});

it('lists media in the combined All Media admin index', function () {
    Storage::fake('public');

    MediaItem::create([
        'title' => 'Index image', 'media_type' => 'image',
        'image_path' => 'fallback/none.jpg', 'status' => 'published',
    ]);

    Gallery::create(['title' => 'Index gallery', 'slug' => 'index-gallery', 'status' => 'draft']);

    $this->get('/admin/media')->assertRedirect();
    $this->actingAs(mediaAdmin())->get('/admin/media')->assertOk()->assertInertia(function (Assert $page) {
        $page->component('admin/media/index')
            ->has('media', 1)
            ->where('media.0.type', 'image')
            ->has('galleries', 1);
    });
});

it('saves a value chain cluster without a description', function () {
    $this->actingAs(mediaAdmin())
        ->post(route('admin.clusters.store'), [
            'name' => 'Leather cluster',
            'status' => 'published',
        ])->assertRedirect();

    $cluster = ValueChainCluster::query()->where('name', 'Leather cluster')->firstOrFail();
    expect($cluster->description)->toBeNull();

    // Required fields still validate.
    $this->actingAs(mediaAdmin())
        ->from(route('admin.clusters.create'))
        ->post(route('admin.clusters.store'), ['name' => '', 'status' => 'published'])
        ->assertSessionHasErrors('name');
});
