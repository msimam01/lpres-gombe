<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A public media item: a standalone image or a video (YouTube — the video
 * file itself never uploads to this server). An item may optionally be
 * associated with one event, news article or project record, or exist
 * independently.
 */
class MediaItem extends Model
{
    public const TYPE_IMAGE = 'image';

    public const TYPE_VIDEO = 'video';

    /** Associated content types and the model each maps to. */
    public const ASSOCIATIONS = [
        'event' => Event::class,
        'news' => News::class,
        'project' => Project::class,
    ];

    protected $fillable = [
        'title', 'media_type', 'image_path', 'video_url', 'thumbnail_path', 'caption',
        'album', 'status', 'associated_type', 'associated_id',
    ];

    public function scopePublished(Builder $query): Builder
    {
        // Automatic ordering: newest items first.
        return $query->where('status', 'published')->orderByDesc('id');
    }

    public function scopeVideos(Builder $query): Builder
    {
        return $query->where('media_type', self::TYPE_VIDEO);
    }

    public function scopeImages(Builder $query): Builder
    {
        return $query->where('media_type', self::TYPE_IMAGE);
    }

    /**
     * The event/news/project record this media belongs to, if the referenced
     * content still exists. Resolved eagerly by callers so pages never issue
     * per-item queries.
     */
    public function associationTitle(): ?string
    {
        // Every associated model (event, news, project) names itself `title`.
        return $this->resolveAssociated()?->title;
    }

    public function resolveAssociated(): ?Model
    {
        $class = self::ASSOCIATIONS[$this->associated_type ?? ''] ?? null;

        if ($class === null || $this->associated_id === null) {
            return null;
        }

        return $class::query()->find($this->associated_id);
    }

    /**
     * Href of the associated public record, or null for standalone items.
     */
    public function associationHref(): ?string
    {
        $associated = $this->resolveAssociated();

        if ($associated === null) {
            return null;
        }

        return match ($this->associated_type) {
            'event' => route('events.show', $associated->slug),
            'news' => route('news.show', $associated->slug),
            'project' => route('projects.show', $associated->slug),
            default => null,
        };
    }

    /**
     * A stable YouTube thumbnail for the given video URL, when extractable.
     */
    public static function youtubeId(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        return match (true) {
            (bool) preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/)([\w-]{6,})#', $url, $m) => $m[1],
            (bool) preg_match('#youtube\.com/embed/([\w-]{6,})#', $url, $m) => $m[1],
            default => null,
        };
    }
}
