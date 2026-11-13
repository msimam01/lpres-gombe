<?php

namespace App\Http\Resources;

use App\Models\Project;
use App\Support\ProjectLocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public map marker. The only location fields this resource may emit are the
 * ones ProjectLocation already cleared for publication — never the model's
 * stored latitude/longitude.
 *
 * @mixin Project
 */
class PublicProjectMarkerResource extends JsonResource
{
    /** @return array<string, mixed>|null Null when the project must not be mapped. */
    public function toArray(Request $request): ?array
    {
        /** @var Project $project */
        $project = $this->resource;

        $location = app(ProjectLocation::class);
        $coordinates = $location->publicCoordinates($project);

        if ($coordinates === null) {
            return null;
        }

        return [
            'id' => $project->id,
            'title' => $project->title,
            'slug' => $project->slug,
            'href' => route('projects.show', $project->slug),
            'category' => $project->category?->name,
            'lga' => $project->lga,
            'latitude' => $coordinates['latitude'],
            'longitude' => $coordinates['longitude'],
            'precision' => $coordinates['precision'],
            'directionsUrl' => $location->directionsUrl($coordinates),
        ];
    }
}
