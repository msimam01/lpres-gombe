<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Project extends Model
{
    protected $fillable = [
        'title', 'slug', 'featured_image_path', 'gallery_image_paths', 'description', 'project_category_id', 'status',
    ];

    protected function casts(): array
    {
        return [
            'project_category_id' => 'integer',
            'gallery_image_paths' => 'array',
        ];
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
