<?php

namespace App\Http\Controllers;

use App\Models\ProjectComponent;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProjectComponentController extends Controller
{
    /**
     * A single public component page. Hidden/draft components and records
     * without a slug are indistinguishable from a missing page, and the URL
     * carries the slug rather than the database id.
     */
    public function show(ProjectComponent $component): Response
    {
        abort_unless($component->status === true && filled($component->slug), 404);

        $description = trim((string) $component->description);

        return Inertia::render('project-components/show', [
            'component' => [
                'title' => $component->title,
                'description' => $description !== '' ? $description : null,
                'image' => $component->image_path ? asset('storage/'.$component->image_path) : null,
                'updated' => $component->updated_at?->format('j F Y'),
                // A component without prose still gets a meaningful search
                // description rather than shipping an empty meta tag.
                'metaDescription' => $description !== ''
                    ? Str::limit(preg_replace('/\s+/', ' ', $description), 155, '…')
                    : Str::limit(
                        $component->title.' — a Gombe State L-PRES project component supporting livestock productivity, resilience and commercialisation.',
                        155,
                        '…',
                    ),
            ],
            'others' => ProjectComponent::published()
                ->whereKeyNot($component->getKey())
                ->get(['id', 'title', 'slug', 'image_path'])
                ->map(fn (ProjectComponent $other) => [
                    'title' => $other->title,
                    'href' => route('project-components.show', $other->slug),
                    'image' => $other->image_path ? asset('storage/'.$other->image_path) : null,
                ])
                ->values()
                ->all(),
        ]);
    }
}
