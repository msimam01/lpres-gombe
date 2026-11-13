<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Project extends Model
{
    protected $fillable = [
        'title', 'slug', 'featured_image_path', 'gallery_image_paths', 'description', 'project_category_id', 'status',
        'address', 'latitude', 'longitude', 'lga', 'ward', 'location_visibility',
    ];

    protected function casts(): array
    {
        return [
            'project_category_id' => 'integer',
            'gallery_image_paths' => 'array',
            // Fixed-point strings: decimal(10,7) must never drift through float.
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'location_visibility' => 'string',
        ];
    }

    /** Whether the project has a usable coordinate pair. */
    public function hasCoordinates(): bool
    {
        return is_numeric($this->latitude) && is_numeric($this->longitude);
    }

    /** Projects with a recorded position (regardless of visibility). */
    public function scopeMapped(Builder $query): Builder
    {
        return $query->whereNotNull('latitude')->whereNotNull('longitude');
    }

    public function scopeInLga(Builder $query, string $lga): Builder
    {
        return $query->where('lga', $lga);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProjectCategory::class, 'project_category_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        // Automatic ordering: newest records first.
        return $query->where('status', 'published')->orderByDesc('id');
    }

    /** Derive an excerpt from the title. */
    public static function excerptFromTitle(string $title): string
    {
        return trim(strip_tags($title));
    }
}
