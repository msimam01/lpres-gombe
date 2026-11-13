<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesAssociatedMedia;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Support\ProjectFilters;
use App\Support\ProjectLocation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    use ResolvesAssociatedMedia;

    public function index(Request $request, ProjectLocation $location): Response
    {
        $filters = ProjectFilters::fromRequest($request);

        $projects = ProjectFilters::apply(
            Project::published()->with('category:id,name,slug'),
            $filters,
        )
            ->get(['id', 'title', 'slug', 'featured_image_path', 'description', 'project_category_id', 'lga', 'latitude', 'longitude', 'location_visibility'])
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'title' => $project->title,
                'href' => route('projects.show', $project->slug),
                'excerpt' => $project->description ? Str::words(strip_tags($project->description), 24, '…') : null,
                'image' => $project->featured_image_path ? asset('storage/'.$project->featured_image_path) : null,
                'category' => $project->category?->name,
                'lga' => $project->lga,
                // Whether a marker will exist for this project, not the point itself.
                'isMapped' => $location->publicCoordinates($project) !== null,
            ])
            ->all();

        // Only real categories that currently classify published projects:
        // no invented options, and no dead filter values.
        $categories = ProjectCategory::query()
            ->whereHas('projects', fn (Builder $query) => $query->where('status', 'published'))
            ->orderBy('name')
            ->get(['name', 'slug'])
            ->map(fn (ProjectCategory $category) => [
                'name' => $category->name,
                'slug' => $category->slug,
            ])
            ->all();

        $publicMap = config('lpres.public_map');

        return Inertia::render('projects/index', [
            'projects' => $projects,
            'filters' => $filters,
            'filterOptions' => [
                'lgas' => config('lpres.lgas'),
                'categories' => $categories,
            ],
            'map' => [
                'tileUrl' => $publicMap['tileUrl'] ?? null,
                'attribution' => $publicMap['attribution'] ?? '',
                'center' => $publicMap['center'],
                'zoom' => $publicMap['zoom'] ?? 8,
                'minZoom' => $publicMap['minZoom'] ?? 3,
                'maxZoom' => $publicMap['maxZoom'] ?? 19,
                'dataUrl' => route('our-projects.map-data'),
            ],
        ]);
    }

    public function show(Project $project, ProjectLocation $location): Response
    {
        abort_unless($project->status === 'published', 404);

        $publicMap = config('lpres.public_map');

        return Inertia::render('projects/show', [
            'project' => [
                'title' => $project->title,
                'description' => $project->description,
                'image' => $project->featured_image_path ? asset('storage/'.$project->featured_image_path) : null,
                'gallery' => collect($project->gallery_image_paths ?? [])
                    ->map(fn (string $path) => asset('storage/'.$path))
                    ->values()
                    ->all(),
                'category' => $project->category?->name,
                // Public projection only: no stored latitude/longitude leaves here.
                'location' => $location->forDisplay($project),
            ],
            'map' => [
                'tileUrl' => $publicMap['tileUrl'] ?? null,
                'attribution' => $publicMap['attribution'] ?? '',
                'center' => $publicMap['center'],
                'zoom' => $publicMap['zoom'] ?? 8,
                'minZoom' => $publicMap['minZoom'] ?? 3,
                'maxZoom' => $publicMap['maxZoom'] ?? 19,
            ],
            'others' => Project::published()
                ->where('id', '!=', $project->id)
                ->take(3)
                ->get(['id', 'title', 'slug'])
                ->map(fn (Project $item) => [
                    'title' => $item->title,
                    'href' => route('projects.show', $item->slug),
                ])
                ->all(),

            // Published galleries, videos and image items associated with
            // this project.
            ...$this->associatedMedia('project', $project->id),
        ]);
    }
}
