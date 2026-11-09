<?php

use App\Models\News;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the lpres homepage component', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('home'));
});

it('renders the static organisation pages from their real routes', function (string $url, string $component) {
    $this->get($url)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    ['/about', 'about'],
    ['/vision', 'vision'],
    ['/mission', 'mission'],
    ['/core-values', 'core-values'],
    ['/contact', 'contact'],
]);

it('shows empty states for content pages when nothing is published yet', function (string $url, string $component) {
    $this->get($url)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    ['/news', 'news/index'],
    ['/events', 'events/index'],
    ['/our-projects', 'projects/index'],
    ['/value-chains', 'value-chains/index'],
    ['/media', 'media'],
    ['/visual-reports', 'visual-reports'],
    ['/project-team', 'project-team'],
]);

it('returns a 404 for unknown detail routes', function (string $url) {
    $this->get($url)->assertNotFound();
})->with([
    '/value-chains/unknown',
    '/news/does-not-exist',
    '/events/does-not-exist',
    '/projects/does-not-exist',
    '/team/aliyu-bello', // ordinary members have no profile page
]);

it('serves the hardcoded SPC profile route without any team records', function () {
    $this->get('/spc-profile')->assertOk();
});

it('keeps the news archive behind the homepage news section', function () {
    // A page of records beyond the homepage limit must land on the archive,
    // not on the homepage.
    News::query()->create([
        'title' => 'Archived brief',
        'slug' => 'archived-brief',
        'body' => 'Body.',
        'published_at' => now()->subDays(400),
        'status' => 'published',
    ]);

    $response = $this->get('/news');

    $response->assertOk();
    $response->assertInertia(function (Assert $page) {
        $page->component('news/index')
            ->has('articles.data', 1)
            ->where('articles.data.0.title', 'Archived brief');
    });
});
