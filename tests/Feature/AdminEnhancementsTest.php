<?php

use App\Models\Event;
use App\Models\MediaItem;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function enhancementsAdmin(): User
{
    return User::factory()->admin()->create();
}

function enhancementsJpeg(): string
{
    return file_get_contents(public_path('images/lpres/lpres-logo.jpeg'));
}

it('renders the project locations map section data on the homepage', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('home')
            ->where('map.dataUrl', route('our-projects.map-data'))
            ->has('map.center.lat')
            ->has('map.center.lng')
            ->has('map.attribution')
            ->has('map.zoom'));

    // The section reads the same public marker feed as the projects page.
    $this->get(route('our-projects.map-data'))
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('adds several images in one bulk upload, each as its own item', function () {
    Storage::fake('public');

    $event = Event::create([
        'title' => 'Bulk upload event', 'slug' => 'bulk-upload-event',
        'description' => 'Event used by the bulk upload test.', 'starts_at' => now()->addWeek(), 'status' => 'published',
    ]);

    $this->actingAs(enhancementsAdmin())
        ->post(route('admin.media.images.bulk-store'), [
            'images' => [
                UploadedFile::fake()->createWithContent('field-visit.jpg', enhancementsJpeg()),
                UploadedFile::fake()->createWithContent('vaccination_drive.jpg', enhancementsJpeg()),
                UploadedFile::fake()->createWithContent('farmers-day.jpg', enhancementsJpeg()),
            ],
            'caption' => 'Shared batch caption.',
            'associated' => 'event:'.$event->id,
            'status' => 'published',
        ])->assertRedirect(route('admin.media.index'));

    expect(MediaItem::query()->count())->toBe(3);

    $items = MediaItem::query()->orderBy('id')->get();

    expect($items->pluck('title')->all())->toBe(['Field Visit', 'Vaccination Drive', 'Farmers Day'])
        ->and($items->every(fn (MediaItem $item) => $item->media_type === MediaItem::TYPE_IMAGE))->toBeTrue()
        ->and($items->every(fn (MediaItem $item) => $item->caption === 'Shared batch caption.'))->toBeTrue()
        ->and($items->every(fn (MediaItem $item) => $item->status === 'published'))->toBeTrue()
        ->and($items->every(fn (MediaItem $item) => $item->associated_type === 'event' && $item->associated_id === $event->id))->toBeTrue();

    foreach ($items as $item) {
        Storage::disk('public')->assertExists($item->image_path);
    }
});

it('validates bulk image uploads and requires at least one image', function () {
    Storage::fake('public');

    $admin = enhancementsAdmin();

    $this->actingAs($admin)
        ->from(route('admin.media.images.bulk'))
        ->post(route('admin.media.images.bulk-store'), [
            'images' => [UploadedFile::fake()->create('notes.pdf', 20)],
        ])->assertSessionHasErrors('images.0');

    $this->actingAs($admin)
        ->from(route('admin.media.images.bulk'))
        ->post(route('admin.media.images.bulk-store'), [])
        ->assertSessionHasErrors('images');

    expect(MediaItem::query()->count())->toBe(0);
});

it('saves manually entered office coordinates', function () {
    $this->actingAs(enhancementsAdmin())
        ->put(route('admin.settings.update'), [
            'contact_address' => 'GSADP Quarters, Gombe',
            'office_latitude' => '10.289690',
            'office_longitude' => '11.167290',
        ])->assertRedirect(route('admin.settings.edit'));

    expect(Setting::get('office_latitude'))->toBe('10.289690')
        ->and(Setting::get('office_longitude'))->toBe('11.167290');
});

it('rejects out-of-range office coordinates', function () {
    $this->actingAs(enhancementsAdmin())
        ->from(route('admin.settings.edit'))
        ->put(route('admin.settings.update'), [
            'office_latitude' => '91',
            'office_longitude' => '200',
        ])->assertSessionHasErrors(['office_latitude', 'office_longitude']);

    expect(Setting::get('office_latitude'))->toBeNull();
});

it('renders the bulk upload page with a multiple file input', function () {
    $response = $this->actingAs(enhancementsAdmin())->get(route('admin.media.images.bulk'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('admin/media/bulk-images')
        ->has('associations'));
});
