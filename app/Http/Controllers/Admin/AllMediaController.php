<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\MediaItem;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "All Media": a combined index of the media architecture — video items,
 * image items and galleries — with direct links into each dedicated module.
 */
class AllMediaController extends Controller
{
    public function index(): Response
    {
        $media = MediaItem::query()->orderByDesc('id')->paginate(20, pageName: 'media_page');

        $mediaRows = $media->through(fn (MediaItem $item): array => [
            'id' => $item->id,
            'label' => (string) $item->title,
            'status' => $item->status,
            'type' => $item->media_type,
            'editHref' => route(
                'admin.media.'.($item->media_type === MediaItem::TYPE_VIDEO ? 'videos' : 'images').'.edit',
                $item->id,
            ),
        ]);

        $galleries = Gallery::query()
            ->withCount('images')
            ->orderByDesc('id')
            ->paginate(20, pageName: 'galleries_page');

        $galleryRows = $galleries->through(fn (Gallery $gallery): array => [
            'id' => $gallery->id,
            'label' => (string) $gallery->title,
            'status' => $gallery->status,
            'images' => $gallery->images_count,
            'editHref' => route('admin.media.galleries.edit', $gallery->id),
        ]);

        return Inertia::render('admin/media/index', [
            'media' => $mediaRows->all(),
            'mediaLinks' => $media->linkCollection()->all(),
            'mediaLastPage' => $media->lastPage(),
            'galleries' => $galleryRows->all(),
            'galleryLinks' => $galleries->linkCollection()->all(),
            'galleryLastPage' => $galleries->lastPage(),
        ]);
    }
}
