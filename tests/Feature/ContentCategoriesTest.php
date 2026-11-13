<?php

use App\Models\Event;
use App\Models\News;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Setting;
use App\Models\TeamCategory;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Focused coverage for the managed category modules (Project Categories and
 * Team Categories), their assignment workflows, the server-side automatic
 * news excerpt and the backend-driven homepage Projects/Events/Contact
 * sections.
 */
function createCategory(string $type, array $attributes = []): ProjectCategory|TeamCategory
{
    $model = $type === 'project' ? ProjectCategory::class : TeamCategory::class;

    // Categories are pure classification: name + slug + optional description.
    // No visibility switch and no manual display order exist by design.
    return $model::query()->create($attributes + [
        'name' => $type.' category',
        'slug' => $type.'-category',
    ]);
}

it('creates and edits a project category without any visibility controls', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.project-categories.store'), ['name' => 'Livestock'])
        ->assertRedirect();

    $category = ProjectCategory::query()->where('slug', 'livestock')->firstOrFail();

    $this->put(route('admin.project-categories.update', $category), ['name' => 'Livestock & Dairy'])
        ->assertRedirect();

    $category->refresh();
    expect($category->name)->toBe('Livestock & Dairy')
        ->and($category->slug)->toBe('livestock') // slugs stay stable on edit
        // Status/display-order columns are retired from category tables.
        ->and(Schema::hasColumn('project_categories', 'status'))->toBeFalse()
        ->and(Schema::hasColumn('project_categories', 'display_order'))->toBeFalse();

    // There is no publish/unpublish action for categories.
    expect(Route::has('admin.project-categories.toggle'))->toBeFalse();
});

it('creates team categories independently of team members, without visibility controls', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.team-categories.store'), ['name' => 'Technical Support Unit']);

    expect(TeamCategory::query()->where('slug', 'technical-support-unit')->count())->toBe(1)
        ->and(Schema::hasColumn('team_categories', 'status'))->toBeFalse()
        ->and(Schema::hasColumn('team_categories', 'display_order'))->toBeFalse();
});

it('blocks deleting a project category that is still assigned to projects', function () {
    $category = createCategory('project', ['name' => 'Dairy', 'slug' => 'dairy']);

    $project = Project::query()->create([
        'title' => 'Dairy upgrade', 'slug' => 'dairy-upgrade', 'description' => 'Desc.',
        'project_category_id' => $category->id, 'status' => 'published',
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.project-categories.destroy', $category->id))
        // Deletion requires reassignment first; the relationship is never
        // silently discarded.
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(ProjectCategory::find($category->id))->not->toBeNull()
        ->and($project->fresh()->project_category_id)->toBe($category->id);
});

it('blocks deleting a team category while members are assigned to it', function () {
    $category = createCategory('team', ['name' => 'Field Officers', 'slug' => 'field-officers']);

    $member = TeamMember::query()->create([
        'name' => 'Amina Bala', 'slug' => 'amina-bala',
        'team_category_id' => $category->id, 'status' => true,
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.team-categories.destroy', $category->id))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(TeamCategory::find($category->id))->not->toBeNull()
        ->and($member->fresh()->team_category_id)->toBe($category->id);
});

it('stores and reassigns a managed project category from the project form', function () {
    $dairy = createCategory('project', ['name' => 'Dairy', 'slug' => 'dairy']);
    $poultry = createCategory('project', ['name' => 'Poultry', 'slug' => 'poultry']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.projects.store'), [
            'title' => 'Feedlot programme',
            'description' => 'Feedlot support.',
            'project_category_id' => (string) $dairy->id,
            'status' => 'published',
        ]);

    $project = Project::query()->where('slug', 'feedlot-programme')->firstOrFail();
    expect($project->project_category_id)->toBe($dairy->id);

    $this->put(route('admin.projects.update', $project), [
        'title' => 'Feedlot programme',
        'description' => 'Feedlot support.',
        'project_category_id' => (string) $poultry->id,
        'status' => 'published',
    ]);

    expect($project->fresh()->project_category_id)->toBe($poultry->id);
});

it('rejects unknown category ids on the project form', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->from(route('admin.projects.create'))
        ->post(route('admin.projects.store'), [
            'title' => 'Bad category',
            'description' => 'X.',
            'project_category_id' => '999',
            'status' => 'published',
        ]);

    $this->assertDatabaseMissing('projects', ['slug' => 'bad-category']);
});

it('stores a team member inside a managed team category', function () {
    $category = createCategory('team', ['name' => 'Extension', 'slug' => 'extension']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.team.store'), [
            'name' => 'Peter Danjuma',
            'team_category_id' => (string) $category->id,
            'designation' => 'Extension officer',
            'status' => '1',
        ]);

    expect(TeamMember::query()->where('slug', 'peter-danjuma')->firstOrFail()->team_category_id)
        ->toBe($category->id);
});

it('generates the news excerpt automatically from the title on create and edit', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.news.store'), [
            'title' => 'L-PRES distributes livestock feed to 500 herders in Gombe',
            'body' => 'Full article body.',
            'status' => 'published',
            'published_at' => now()->toDateTimeString(),
            // A stray excerpt from a bypassing client must be ignored.
            'excerpt' => 'BYPASSED-VALUE',
        ]);

    $article = News::query()->where('slug', 'l-pres-distributes-livestock-feed-to-500-herders-in-gombe')->firstOrFail();
    expect($article->excerpt)->toBe('L-PRES distributes livestock feed to 500 herders in Gombe');

    $this->put(route('admin.news.update', $article), [
        'title' => 'Gombe herders receive livestock feed support',
        'body' => 'Full article body.',
        'status' => 'published',
        'published_at' => now()->toDateTimeString(),
    ]);

    // The excerpt is fully title-derived, so a title change re-derives it.
    expect($article->fresh()->excerpt)->toBe('Gombe herders receive livestock feed support');
});

it('shows only published projects on the homepage Projects section', function () {
    $category = createCategory('project', ['name' => 'Pastoralist', 'slug' => 'pastoralist']);

    Project::query()->create([
        'title' => 'Published ranch', 'slug' => 'published-ranch', 'description' => str_repeat('Ranch.', 20),
        'project_category_id' => $category->id, 'status' => 'published',
    ]);
    Project::query()->create([
        'title' => 'Draft ranch', 'slug' => 'draft-ranch', 'description' => str_repeat('Draft.', 20),
        'status' => 'draft',
    ]);

    $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('home')
        ->has('homeProjects', 1)
        ->where('homeProjects.0.title', 'Published ranch')
        ->where('homeProjects.0.category', 'Pastoralist'));
});

it('shows upcoming published events on the homepage and keeps drafts away', function () {
    Event::query()->create([
        'title' => 'Future summit', 'slug' => 'future-summit', 'description' => str_repeat('Summit.', 10),
        'starts_at' => now()->addWeek(), 'status' => 'published',
    ]);
    Event::query()->create([
        'title' => 'Past retreat', 'slug' => 'past-retreat', 'description' => 'Old.',
        'starts_at' => now()->subWeek(), 'status' => 'published',
    ]);
    Event::query()->create([
        'title' => 'Hidden seminar', 'slug' => 'hidden-seminar', 'description' => 'Nope.',
        'starts_at' => now()->addWeek(), 'status' => 'draft',
    ]);

    $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('upcomingEvents', 1)
        ->where('upcomingEvents.0.title', 'Future summit'));
});

it('shares the managed contact details with the homepage Contact section', function () {
    Setting::put('contact_email', 'gombe@lpresnigeria.gov.ng');
    Setting::put('contact_address', 'GSADP Quarters, Gombe');
    Setting::put('contact_phone', '+234 8035336243');
    cache()->forget('site.contact-settings');

    $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('contact.email', 'gombe@lpresnigeria.gov.ng')
        ->where('contact.address', 'GSADP Quarters, Gombe'));
});

it('exposes managed category names on the Project Team page with no SPC flag', function () {
    $category = createCategory('team', ['name' => 'Project Management', 'slug' => 'project-management']);

    TeamMember::query()->create([
        'name' => 'Regular Officer', 'slug' => 'regular-officer', 'designation' => 'Officer',
        'team_category_id' => $category->id, 'status' => true,
    ]);

    $this->get('/project-team')->assertOk()->assertInertia(function (Assert $page) {
        $page->component('project-team')
            ->has('members', 1)
            ->where('members.0.category', 'Project Management')
            ->missing('members.0.isSpc')
            ->missing('members.0.href'); // only the hardcoded SPC intro has Read More
    });

    // The dedicated profile is a fixed editorial route, always available.
    $this->get('/spc-profile')->assertOk();
});

it('settles projects without any category on the public listing', function () {
    Project::query()->create([
        'title' => 'Uncategorised build', 'slug' => 'uncategorised-build',
        'description' => 'Desc.', 'status' => 'published',
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.projects.store'), [
            'title' => 'Second build', 'description' => 'Desc.', 'status' => 'published',
        ]);

    $this->get('/our-projects')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('projects', 2)
        ->where('projects.0.category', null));
});
