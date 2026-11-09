<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Gallery;
use App\Models\MediaItem;

/**
 * Shared media resolution for public detail pages: published galleries,
 * videos and image items associated with one event, news article or
 * project record. Used by the detail controllers so every page surfaces
 * the same media coverage shape.
 */
trait ResolvesAssociatedMedia
{
    /**
     * @param  string  $type  MediaItem::ASSOCIATIONS key (event / news / project)
     * @param  int  $id  The record id the media is associated with
     * @return array{mediaGalleries: array, mediaVideos: array, mediaImages: array}
     */
    protected function associatedMedia(string $type, int $id): array
    {
        $galleries = Gallery::query()
            ->published()
            ->where('associated_type', $type)
            ->where('associated_id', $id)
            ->with('images')
            ->get(['id', 'title', 'slug', 'description'])
            ->map(fn (Gallery $gallery): array => [
                'title' => $gallery->title,
                'href' => route('media.galleries.show', $gallery->slug),
                'imageCount' => $gallery->images->count(),
                'cover' => $gallery->images->first()?->path
                    ? asset('storage/'.$gallery->images->first()->path)
                    : null,
            ])
            ->values()
            ->all();

        $videos = MediaItem::query()
            ->published()
            ->videos()
            ->where('associated_type', $type)
            ->where('associated_id', $id)
            ->orderByDesc('id')
            ->get(['id', 'title', 'caption', 'video_url', 'thumbnail_path'])
            ->map(fn (MediaItem $video): array => [
                'title' => $video->title,
                'caption' => $video->caption,
                'videoUrl' => $video->video_url,
                'thumbnail' => $video->thumbnail_path
                    ? asset('storage/'.$video->thumbnail_path)
                    : (MediaItem::youtubeId($video->video_url)
                        ? 'https://i.ytimg.com/vi/'.MediaItem::youtubeId($video->video_url).'/hqdefault.jpg'
                        : null),
            ])
            ->values()
            ->all();

        $images = MediaItem::query()
            ->published()
            ->images()
            ->where('associated_type', $type)
            ->where('associated_id', $id)
            ->orderByDesc('id')
            ->get(['id', 'title', 'caption', 'image_path'])
            ->map(fn (MediaItem $image): array => [
                'title' => $image->title,
                'caption' => $image->caption,
                'src' => $image->image_path ? asset('storage/'.$image->image_path) : null,
            ])
            ->values()
            ->all();

        return ['mediaGalleries' => $galleries, 'mediaVideos' => $videos, 'mediaImages' => $images];
    }
}
