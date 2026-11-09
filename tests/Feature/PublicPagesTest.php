<?php

use App\Models\Event;
use App\Models\News;
use App\Models\Project;
use App\Models\TeamCategory;
use App\Models\TeamMember;
use App\Models\ValueChainCluster;
use Inertia\Testing\AssertableInertia as Assert;

it('hides draft news and shows published news on the archive', function () {
    News::query()->create([
        'title' => 'Published piece',
        'slug' => 'published-piece',
        'body' => 'Full text of the published piece.',
        'published_at' => now()->subDays(5),
        'status' => 'published',
    ]);

    News::query()->create([
        'title' => 'Draft piece',
        'slug' => 'draft-piece',
        'body' => 'Not ready yet.',
        'published_at' => null,
        'status' => 'draft',
    ]);

    News::query()->create([
        'title' => 'Future piece',
        'slug' => 'future-piece',
        'body' => 'Scheduled.',
        'published_at' => now()->addDays(10),
        'status' => 'published',
    ]);

    $this->get('/news')->assertOk()->assertInertia(function (Assert $page) {
        $page->component('news/index')
            ->has('articles.data', 1)
            ->where('articles.data.0.title', 'Published piece');
    });
});

it('opens a published article and still hides its draft shadow', function () {
    News::query()->create([
        'title' => 'Openable piece',
        'slug' => 'openable-piece',
        'body' => "First paragraph.\n\nSecond paragraph.",
        'published_at' => now()->subDay(),
        'status' => 'published',
    ]);

    $this->get('/news/openable-piece')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('news/show')
            ->where('article.title', 'Openable piece'));

    $this->get('/news/missing-article')->assertNotFound();
});

it('paginates the public news archive at twelve per page', function () {
    foreach (range(1, 15) as $index) {
        News::query()->create([
            'title' => "Article $index",
            'slug' => "article-$index",
            'body' => "Body $index.",
            'published_at' => now()->subDays($index),
            'status' => 'published',
        ]);
    }

    $this->get('/news')->assertInertia(fn (Assert $page) => $page->has('articles.data', 12));
    $this->get('/news?page=2')->assertInertia(fn (Assert $page) => $page->has('articles.data', 3));
});

it('serves the dedicated SPC profile as a hardcoded editorial page', function () {
    // The SPC profile is not managed through the Team Member CRUD: the page
    // is always available regardless of any team records.
    $this->get('/spc-profile')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('team/spc-profile'));
});

it('groups ordinary team members under managed categories with no profile links', function () {
    $category = TeamCategory::query()->create(['name' => 'Project Specialists', 'slug' => 'project-specialists']);

    TeamMember::query()->create([
        'name' => 'Aliyu Bello',
        'slug' => 'aliyu-bello',
        'designation' => 'Value chain officer',
        'team_category_id' => $category->id,
        'status' => true,
    ]);

    $this->get('/project-team')->assertOk()->assertInertia(function (Assert $page) {
        $page->component('project-team')
            ->has('members', 1)
            ->where('members.0.name', 'Aliyu Bello')
            ->where('members.0.category', 'Project Specialists')
            ->missing('members.0.href') // ordinary members get no detail page/Read More
            ->missing('members.0.isSpc'); // the SPC is not a member flag anymore
    });
});

it('shows only published value chain clusters and serves their detail pages', function () {
    ValueChainCluster::query()->create([
        'name' => 'Beef', 'slug' => 'beef', 'description' => 'Beef cluster description.', 'status' => 'published',
    ]);
    ValueChainCluster::query()->create([
        'name' => 'Hidden', 'slug' => 'hidden', 'description' => 'Draft cluster.', 'status' => 'draft',
    ]);

    $this->get('/value-chains')->assertInertia(fn (Assert $page) => $page
        ->component('value-chains/index')
        ->has('clusters', 1)
        ->where('clusters.0.name', 'Beef'));

    $this->get('/value-chains/beef')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('value-chains/show')
            ->where('cluster.name', 'Beef'));

    $this->get('/value-chains/hidden')->assertNotFound();
});

it('shows only published events and projects', function () {
    Event::query()->create([
        'title' => 'Farmers field day', 'slug' => 'farmers-field-day', 'description' => 'Gathering.',
        'starts_at' => now()->addWeek(), 'location' => 'Gombe', 'status' => 'published',
    ]);
    Event::query()->create([
        'title' => 'Internal planning', 'slug' => 'internal-planning', 'status' => 'draft',
    ]);
    Project::query()->create([
        'title' => 'Feedlot development', 'slug' => 'feedlot-development', 'description' => 'later.',
        'status' => 'published',
    ]);

    $this->get('/events')->assertInertia(fn (Assert $page) => $page
        ->component('events/index')->has('events.data', 1)->where('events.data.0.title', 'Farmers field day'));

    $this->get('/our-projects')->assertInertia(fn (Assert $page) => $page
        ->component('projects/index')->has('projects', 1)->where('projects.0.title', 'Feedlot development'));
});
