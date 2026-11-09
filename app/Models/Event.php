<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A project event. Only `published` records appear publicly; the listing is
 * ordered by start date (soonest first).
 */
class Event extends Model
{
    protected $fillable = [
        'title', 'slug', 'image_path', 'gallery_image_paths', 'description', 'starts_at', 'ends_at', 'location', 'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'gallery_image_paths' => 'array',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->orderBy('starts_at')->orderByDesc('id');
    }
}
