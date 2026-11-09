<?php

namespace App\Http\Controllers;

use App\Models\Gallery;
use App\Models\MediaItem;
use Illuminate\Database\Eloquent\Model;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public media pages: published standalone images, YouTube videos and image
 * galleries, each clearly distinguished, with links to the project, event or
 * news record an item is associated with.
 */
class MediaController extends Controller
{
    public function index(): Response
    {
        $galleries = Gallery::published()
            ->withCount('images')
            ->get()
            ->map(fn (Gallery $gallery): array => [
                'id' => $gallery->id,
                'title' => $gallery->title,
                'slug' => $gallery->slug,
                'description' => $gallery->description,
                'cover' => $gallery->images->first()?->path ? asset('storage/'.$gallery->images->first()->path) : null,
                'imageCount' => $gallery->images_count,
                'seenOnLabel' => $this->associationLabel($gallery->resolveAssociated(), $gallery->associated_type),
                'seenOnHref' => $gallery->associationHref(),
                'href' => route('media.galleries.show', $gallery->slug),
            ])
            ->values();

        $items = MediaItem::published()
            ->get()
            ->map(function (MediaItem $item): array {
                $isVideo = $item->media_type === MediaItem::TYPE_VIDEO;
                $youtubeId = $isVideo ? MediaItem::youtubeId($item->video_url) : null;

                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'type' => $item->media_type,
                    'image' => $item->image_path ? asset('storage/'.$item->image_path) : null,
                    // Trust YouTube's public thumbnail for videos without a
                    // custom upload; the file itself never lands on this server.
                    'thumbnail' => $item->thumbnail_path
                        ? asset('storage/'.$item->thumbnail_path)
                        : ($youtubeId ? 'https://i.ytimg.com/vi/'.$youtubeId.'/hqdefault.jpg' : null),
                    'videoUrl' => $item->video_url,
                    'caption' => $item->caption,
                    'seenOnLabel' => $this->associationLabel($item->resolveAssociated(), $item->associated_type),
                    'seenOnHref' => $item->associationHref(),
                ];
            })
            ->values();

        return Inertia::render('media', [
            'galleries' => $galleries->all(),
            'images' => $items->where('type', MediaItem::TYPE_IMAGE)->values()->all(),
            'videos' => $items->where('type', MediaItem::TYPE_VIDEO)->values()->all(),
        ]);
    }

    public function gallery(Gallery $gallery): Response
    {
        abort_unless($gallery->status === 'published', 404);

        $gallery->loadCount('images');

        $images = $gallery->images
            ->map(fn ($image, int $index): array => [
                'src' => asset('storage/'.$image->path),
                'alt' => $gallery->title.' — image '.($index + 1),
            ])
            ->values()
            ->all();

        $associated = $gallery->resolveAssociated();

        return Inertia::render('media/gallery', [
            'gallery' => [
                'title' => $gallery->title,
                'description' => $gallery->description,
                'images' => $images,
                'seenOnLabel' => $this->associationLabel($associated, $gallery->associated_type),
                'seenOnHref' => $gallery->associationHref(),
            ],
            'otherGalleries' => Gallery::published()
                ->where('id', '!=', $gallery->id)
                ->take(5)
                ->get(['id', 'title', 'slug'])
                ->map(fn (Gallery $item): array => [
                    'title' => $item->title,
                    'href' => route('media.galleries.show', $item->slug),
                ])
                ->all(),
        ]);
    }

    /** Label ("Event: Farmers field day") + href for the associated record. */
    private function associationLabel(?Model $record, ?string $type): ?string
    {
        if ($record === null || $type === null) {
            return null;
        }

        $label = match ($type) {
            'event' => 'Event',
            'news' => 'News',
            'project' => 'Project',
            default => null,
        };

        return $label === null ? null : $label.': '.$record->title;
    }
}
