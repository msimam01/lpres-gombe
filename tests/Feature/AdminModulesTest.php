<?php

use App\Models\ContactEnquiry;
use App\Models\Event;
use App\Models\News;
use App\Models\NewsletterSubscriber;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Setting;
use App\Models\TeamCategory;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\ValueChainCluster;
use App\Models\VisualReport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function admin(): User
{
    return User::factory()->admin()->create();
}

it('requires authentication for the admin dashboard and modules', function () {
    $this->get('/admin/news')->assertRedirect();
    $this->actingAs(admin())->get('/admin/news')->assertOk();
});

it('creates a news record with a generated slug', function () {
    $this->actingAs(admin())
        ->post(route('admin.news.store'), [
            'title' => 'New milestone reached',
            'body' => 'Details of the milestone.',
            'status' => 'published',
            'published_at' => now()->subHour()->toDateTimeString(),
        ]);

    $article = News::query()->where('title', 'New milestone reached')->first();
    expect($article)->not->toBeNull()
        ->and($article->status)->toBe('published')
        ->and($article->slug)->toBe('new-milestone-reached');
});

it('validates required fields on news creation', function () {
    $this->actingAs(admin())
        ->from(route('admin.news.create'))
        ->post(route('admin.news.store'), ['title' => '']);

    $this->assertDatabaseCount('news', 0);
});

it('stores an uploaded featured image on the public disk', function () {
    Storage::fake('public');

    // GD is unavailable locally, so build the fake from a real JPEG asset.
    $realJpeg = file_get_contents(public_path('images/lpres/lpres-logo.jpeg'));

    $this->actingAs(admin())
        ->post(route('admin.news.store'), [
            'title' => 'Image backed article',
            'body' => 'Body.',
            'status' => 'draft',
            'featured_image_path' => UploadedFile::fake()->createWithContent('photo.jpg', $realJpeg),
        ]);

    $article = News::query()->where('title', 'Image backed article')->firstOrFail();
    Storage::disk('public')->assertExists($article->featured_image_path);
});

it('rejects disallowed file types for uploads', function () {
    Storage::fake('public');

    $this->actingAs(admin())
        ->from(route('admin.news.create'))
        ->post(route('admin.news.store'), [
            'title' => 'Executable upload',
            'body' => 'Body.',
            'status' => 'draft',
            'featured_image_path' => UploadedFile::fake()->create('malware.exe', 10),
        ]);

    Storage::disk('public')->assertDirectoryEmpty('uploads/news');
});

it('toggles news visibility between published and draft', function () {
    $article = News::query()->create([
        'title' => 'Toggleable piece', 'slug' => 'toggleable-piece', 'body' => 'Body.',
        'published_at' => now()->subHour(), 'status' => 'published',
    ]);

    $this->actingAs(admin())->post(route('admin.news.toggle', $article->id));
    expect($article->fresh()->status)->toBe('draft');

    $this->get(route('news.show', $article->slug))->assertNotFound();

    $this->actingAs(admin())->post(route('admin.news.toggle', $article->id));
    expect($article->fresh()->status)->toBe('published');
});

it('deletes a news record', function () {
    $article = News::query()->create([
        'title' => 'Delete me', 'slug' => 'delete-me', 'body' => 'Body.',
        'published_at' => now()->subHour(), 'status' => 'published',
    ]);

    $this->actingAs(admin())->delete(route('admin.news.destroy', $article->id));

    expect(News::find($article->id))->toBeNull();
    $this->get('/news/delete-me')->assertNotFound();
});

it('creates an ordinary team member and keeps the SPC out of the CRUD', function () {
    $category = TeamCategory::query()->create(['name' => 'Project Management Team', 'slug' => 'project-management-team']);
    Storage::fake('public');

    $realJpeg = file_get_contents(public_path('images/lpres/lpres-logo.jpeg'));

    $this->actingAs(admin())
        ->post(route('admin.team.store'), [
            'name' => 'Aliyu Bello',
            'designation' => 'Value chain officer',
            'team_category_id' => (string) $category->id,
            'photograph_path' => UploadedFile::fake()->createWithContent('photo.jpg', $realJpeg),
            // Fields that no longer exist on the form/schema must be ignored.
            'is_spc' => '1',
            'biography' => 'Should be ignored.',
            'email' => 'ignored@example.com',
        ]);

    $member = TeamMember::query()->where('slug', 'aliyu-bello')->firstOrFail();
    expect($member->team_category_id)->toBe($category->id)
        // The SPC checkbox and biography/contact fields are gone from the schema.
        ->and(Schema::hasColumn('team_members', 'is_spc'))->toBeFalse()
        ->and(Schema::hasColumn('team_members', 'biography'))->toBeFalse()
        ->and(Schema::hasColumn('team_members', 'email'))->toBeFalse()
        ->and(Schema::hasColumn('team_members', 'phone'))->toBeFalse();

    // No biography/profile page for ordinary members; the SPC profile route
    // serves the hardcoded editorial page instead.
    $this->get('/spc-profile')->assertOk();
});

it('creates value chain clusters through the admin panel', function () {
    $this->actingAs(admin())
        ->post(route('admin.clusters.store'), [
            'name' => 'Dairy', 'description' => 'The dairy value chain.',
            'status' => 'published',
        ]);

    expect(ValueChainCluster::where('slug', 'dairy')->first())->not->toBeNull();
    $this->get('/value-chains/dairy')->assertOk();
});

it('serves the contact page with details only — no public enquiry form anywhere', function () {
    $this->get('/contact')->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('contact')->has('contact.email')
    );
    expect(ContactEnquiry::count())->toBe(0);

    // The submission endpoint is retired: posting anywhere must not create
    // enquiries (the site intentionally has no public contact form).
    $this->post('/contact', ['name' => 'V', 'email' => 'v@example.com', 'message' => 'Hi'])
        ->assertStatus(405);
    expect(ContactEnquiry::count())->toBe(0);
});

it('stores newsletter subscriptions once per email', function () {
    $this->post(route('newsletter.subscribe'), ['email' => 'reader@example.com'])->assertRedirect();
    $this->post(route('newsletter.subscribe'), ['email' => 'reader@example.com'])->assertRedirect();

    expect(NewsletterSubscriber::where('email', 'reader@example.com')->count())->toBe(1);
});

it('uploads multiple project gallery images and retains the featured image on edit', function () {
    Storage::fake('public');
    $realJpeg = file_get_contents(public_path('images/lpres/lpres-logo.jpeg'));
    $category = ProjectCategory::create(['name' => 'Value chains', 'slug' => 'value-chains']);

    $this->actingAs(admin())
        ->post(route('admin.projects.store'), [
            'title' => 'Fodder Expansion',
            'description' => 'Expanding fodder production across Gombe State.',
            'project_category_id' => (string) $category->id,
            'featured_image_path' => UploadedFile::fake()->createWithContent('featured.jpg', $realJpeg),
            'location_visibility' => 'hidden',
            'gallery_gallery_image_paths' => ['', ''],
            'files_gallery_image_paths' => [
                UploadedFile::fake()->createWithContent('g1.jpg', $realJpeg),
                UploadedFile::fake()->createWithContent('g2.jpg', $realJpeg),
            ],
            'status' => 'published',
        ])->assertRedirect();

    $project = Project::where('slug', 'fodder-expansion')->firstOrFail();
    expect($project->gallery_image_paths)->toHaveCount(2);
    Storage::disk('public')->assertExists($project->featured_image_path);
    foreach ($project->gallery_image_paths as $path) {
        Storage::disk('public')->assertExists($path);
    }

    // Editing: keep the first gallery image, drop the second, keep the
    // featured image by simply not re-uploading it.
    $kept = [$project->gallery_image_paths[0]];
    $dropped = $project->gallery_image_paths[1];

    $this->put(route('admin.projects.update', $project->id), [
        'title' => 'Fodder Expansion',
        'description' => 'Updated description.',
        'project_category_id' => (string) $category->id,
        'location_visibility' => 'hidden',
        'gallery_gallery_image_paths' => $kept,
        'status' => 'published',
    ])->assertRedirect();

    $project->refresh();
    expect($project->gallery_image_paths)->toBe($kept)
        ->and(Storage::disk('public')->exists($dropped))->toBeFalse()
        ->and(Storage::disk('public')->exists($project->featured_image_path))->toBeTrue()
        ->and($project->featured_image_path)->toBeString();
});

it('uploads an event gallery that renders on the public detail page', function () {
    Storage::fake('public');
    $realJpeg = file_get_contents(public_path('images/lpres/lpres-logo.jpeg'));

    $this->actingAs(admin())
        ->post(route('admin.events.store'), [
            'title' => 'Livestock Fair 2026',
            'description' => 'Annual livestock fair.',
            'starts_at' => now()->addWeek()->toDateString(),
            'location' => 'Gombe',
            'gallery_gallery_image_paths' => [''],
            'files_gallery_image_paths' => [UploadedFile::fake()->createWithContent('fair.jpg', $realJpeg)],
            'status' => 'published',
        ])->assertRedirect();

    $event = Event::where('slug', 'livestock-fair-2026')->firstOrFail();
    expect($event->gallery_image_paths)->toHaveCount(1);

    $this->get('/events/livestock-fair-2026')->assertOk()->assertInertia(
        fn (Assert $page) => $page
            ->component('events/show')
            ->where('event.title', 'Livestock Fair 2026')
            ->has('event.gallery', 1)
    );
});

it('manages visual reports and shows only published ones publicly', function () {
    Storage::fake('public');
    $realJpeg = file_get_contents(public_path('images/lpres/lpres-logo.jpeg'));

    // A report without an image is rejected.
    $this->actingAs(admin())
        ->post(route('admin.visual-reports.store'), [
            'title' => 'Incomplete report', 'status' => 'published',
        ])->assertSessionHasErrors('image_path');

    $this->post(route('admin.visual-reports.store'), [
        'title' => 'Q2 PDO Dashboard',
        'description' => 'Second quarter key performance dashboard.',
        'image_path' => UploadedFile::fake()->createWithContent('dashboard.jpg', $realJpeg),
        'file_path' => UploadedFile::fake()->create('summary.pdf', 12, 'application/pdf'),
        'status' => 'published',
    ])->assertRedirect();

    $report = VisualReport::where('title', 'Q2 PDO Dashboard')->firstOrFail();
    Storage::disk('public')->assertExists($report->image_path);

    // Drafts stay private.
    VisualReport::create(['title' => 'Hidden report', 'status' => 'draft']);

    $this->get('/visual-reports')->assertOk()->assertInertia(
        fn (Assert $page) => $page
            ->component('visual-reports')
            ->has('reports', 1)
            ->where('reports.0.title', 'Q2 PDO Dashboard')
            ->has('reports.0.file')
    );
});

it('saves site settings including a fresh logo upload', function () {
    Storage::fake('public');

    $realJpeg = file_get_contents(public_path('images/lpres/lpres-logo.jpeg'));

    $this->actingAs(admin())
        ->put(route('admin.settings.update'), [
            'contact_address' => 'GSADP Quarters, Gombe',
            'contact_email' => 'gombe@lpresnigeria.gov.ng',
            'contact_phone' => '+234 8035336243',
            'logo_path' => UploadedFile::fake()->createWithContent('logo.jpg', $realJpeg),
        ])->assertRedirect();

    expect(Setting::get('contact_email'))->toBe('gombe@lpresnigeria.gov.ng');

    $logoPath = Setting::get('logo_path');
    expect($logoPath)->not->toBeNull();
    Storage::disk('public')->assertExists($logoPath);
});
