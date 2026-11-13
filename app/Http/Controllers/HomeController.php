<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\News;
use App\Models\Project;
use App\Models\ProjectComponent;
use App\Models\ValueChainCluster;
use App\Settings\ContactSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * Homepage. Existing Phase 1–4 sections are preserved; the Latest News,
     * Project Components, Projects, Events and Contact sections all read
     * from the database / managed settings.
     */
    public function index(Request $request): Response
    {
        $today = today();

        return Inertia::render('home', [
            'dashboardComponents' => ProjectComponent::published()
                ->get(['id', 'title', 'slug', 'description', 'image_path'])
                ->map(fn (ProjectComponent $component) => [
                    'id' => $component->id,
                    'title' => $component->title,
                    'href' => route('project-components.show', $component->slug),
                    'description' => $component->description,
                    'excerpt' => $component->description
                        ? Str::words(strip_tags($component->description), 26, '…')
                        : null,
                    'image' => $component->image_path ? asset('storage/'.$component->image_path) : null,
                ])
                ->all(),

            'latestNews' => News::published()
                ->take((int) config('cms.homepage_news_count', 9))
                ->get(['id', 'title', 'slug', 'excerpt', 'featured_image_path', 'published_at'])
                ->map(fn (News $article) => [
                    'id' => $article->id,
                    'title' => $article->title,
                    'href' => route('news.show', $article->slug),
                    'excerpt' => $article->excerpt ?: Str::words(strip_tags($article->body), 24, '…'),
                    'image' => $article->featured_image_path ? asset('storage/'.$article->featured_image_path) : null,
                    'published' => $article->published_at?->format('Y-m-d'),
                    'dateDay' => $article->published_at?->format('d'),
                    'dateMonth' => ' '.strtoupper($article->published_at?->format('M')),
                ])
                ->all(),

            'homeProjects' => Project::published()
                ->with('category:id,name,slug')
                ->take((int) config('cms.homepage_projects_count', 3))
                ->get(['id', 'title', 'slug', 'featured_image_path', 'description', 'project_category_id'])
                ->map(fn (Project $project) => [
                    'id' => $project->id,
                    'title' => $project->title,
                    'href' => route('projects.show', $project->slug),
                    'excerpt' => $project->description ? Str::words(strip_tags($project->description), 22, '…') : null,
                    'image' => $project->featured_image_path ? asset('storage/'.$project->featured_image_path) : null,
                    'category' => $project->category?->name,
                ])
                ->all(),

            'upcomingEvents' => Event::query()
                ->where('status', 'published')
                ->where(fn ($query) => $query->whereDate('starts_at', '>=', $today)->orWhereNull('starts_at'))
                ->orderByRaw('starts_at IS NULL, starts_at asc')
                ->take((int) config('cms.homepage_events_count', 3))
                ->get(['id', 'title', 'slug', 'image_path', 'description', 'starts_at', 'ends_at', 'location'])
                ->map(fn (Event $event) => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'href' => route('events.show', $event->slug),
                    'excerpt' => $event->description ? Str::words(strip_tags($event->description), 22, '…') : null,
                    'image' => $event->image_path ? asset('storage/'.$event->image_path) : null,
                    'startsAt' => $event->starts_at?->format('j F Y'),
                    'endsAt' => $event->ends_at?->format('j F Y'),
                    'location' => $event->location,
                ])
                ->all(),

            'contact' => ContactSettings::resolve()->shared(),

            // Homepage value-chain shortcuts link only to clusters that
            // actually exist and are published; the fallback href is the
            // value-chains listing page so no shortcut can 404.
            'valueChainHrefs' => ValueChainCluster::published()
                ->get(['slug'])
                ->pluck('slug')
                ->mapWithKeys(fn (string $slug): array => [
                    $slug => route('value-chains.show', $slug),
                ])
                ->all(),
        ]);
    }
}
