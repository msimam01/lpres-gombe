<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicProjectMarkerResource;
use App\Models\Project;
use App\Support\ProjectFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lightweight marker feed for the public Projects map.
 *
 * Published projects only, thin column selection, no galleries or
 * descriptions, and every coordinate passes through the public privacy layer.
 */
class ProjectMapDataController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $filters = ProjectFilters::fromRequest($request);

        $projects = ProjectFilters::apply(
            Project::published()->with('category:id,name,slug'),
            $filters,
        )->get([
            'id',
            'title',
            'slug',
            'project_category_id',
            'lga',
            'latitude',
            'longitude',
            'location_visibility',
        ]);

        $markers = $projects
            ->map(fn (Project $project) => (new PublicProjectMarkerResource($project))->toArray($request))
            ->filter()
            ->values();

        return response()
            ->json(['data' => $markers])
            ->header('Cache-Control', 'public, max-age=60, s-maxage=300');
    }
}
