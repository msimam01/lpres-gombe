<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A visual report entry: a title, optional description and image/file shown
 * on the Visual Reports page.
 */
class VisualReport extends Model
{
    protected $fillable = ['title', 'description', 'image_path', 'file_path', 'status'];

    protected function casts(): array
    {
        return [];
    }

    public function scopePublished(Builder $query): Builder
    {
        // Automatic ordering: newest reports first.
        return $query->where('status', 'published')->orderByDesc('id');
    }
}
