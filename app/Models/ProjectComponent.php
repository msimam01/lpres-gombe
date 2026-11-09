<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single homepage Project Component card managed from the admin panel.
 */
class ProjectComponent extends Model
{
    protected $fillable = ['title', 'description', 'image_path', 'display_order', 'status'];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function scopePublished($query)
    {
        return $query->where('status', true)->orderBy('display_order')->orderBy('id');
    }
}
