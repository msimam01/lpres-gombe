<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesAssociatedMedia;
use App\Models\Project;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    use ResolvesAssociatedMedia;

    public function index(): Response
    {
        return Inertia::render('projects/index', [
            // Grouped client-side by managed category, preserving display order.
            'projects' => Project::published()
                ->with('category:id,name,slug')
                ->get(['id', 'title', 'slug', 'featured_image_path', 'description', 'project_category_id'])
                ->map(fn (Project $project) => [
                    'id' => $project->id,
                    'title' => $project->title,
                    'href' => route('projects.show', $project->slug),
                    'excerpt' => $project->description ? Str::words(strip_tags($project->description), 24, '…') : null,
                    'image' => $project->featured_image_path ? asset('storage/'.$project->featured_image_path) : null,
                    'category' => $project->category?->name,
                ])
                ->all(),
        ]);
    }

    public function show(Project $project): Response
    {
        abort_unless($project->status === 'published', 404);

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
