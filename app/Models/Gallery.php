<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named collection of images ("gallery"). It may stand alone or be
 * associated with one event, news article or project record. Images live in
 * the gallery_images table in display order.
 */
class Gallery extends Model
{
    public const ASSOCIATIONS = MediaItem::ASSOCIATIONS;

    protected $fillable = [
        'title', 'slug', 'description', 'status', 'associated_type', 'associated_id',
    ];

    public function images(): HasMany
    {
        return $this->hasMany(GalleryImage::class)->orderBy('sort_index')->orderBy('id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->orderByDesc('id');
    }

    public function resolveAssociated(): ?Model
    {
        $class = self::ASSOCIATIONS[$this->associated_type ?? ''] ?? null;

        if ($class === null || $this->associated_id === null) {
            return null;
        }

        return $class::query()->find($this->associated_id);
    }

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
}
