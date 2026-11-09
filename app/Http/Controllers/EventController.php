<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesAssociatedMedia;
use App\Models\Event;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    use ResolvesAssociatedMedia;

    public function index(): Response
    {
        return Inertia::render('events/index', [
            'events' => Event::published()
                ->paginate(12)
                ->through(fn (Event $event) => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'href' => route('events.show', $event->slug),
                    'excerpt' => $event->description ? Str::words(strip_tags($event->description), 28, '…') : null,
                    'image' => $event->image_path ? asset('storage/'.$event->image_path) : null,
                    'startsAt' => $event->starts_at?->format('j F Y'),
                    'endsAt' => $event->ends_at?->format('j F Y'),
                    'location' => $event->location,
                ]),
        ]);
    }

    public function show(Event $event): Response
    {
        abort_unless($event->status === 'published', 404);

        return Inertia::render('events/show', [
            'event' => [
                'title' => $event->title,
                'description' => $event->description,
                'image' => $event->image_path ? asset('storage/'.$event->image_path) : null,
                'gallery' => collect($event->gallery_image_paths ?? [])
                    ->map(fn (string $path) => asset('storage/'.$path))
                    ->values()
                    ->all(),
                'startsAt' => $event->starts_at?->format('j F Y'),
                'endsAt' => $event->ends_at?->format('j F Y'),
                'location' => $event->location,
            ],

            // Published media associated with this event: its galleries,
            // videos and image items, so detail pages surface the full
            // media coverage.
            ...$this->associatedMedia('event', $event->id),
        ]);
    }
}
