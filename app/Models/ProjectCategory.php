<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A managed project category used to classify projects in the Project
 * form. Categories are internal classification, not standalone public
 * content: there is no visibility switch and no manual ordering — listings
 * are alphabetical by name.
 */
class ProjectCategory extends Model
{
    protected $fillable = ['name', 'slug', 'description'];

    /** The unique slug for the Projects category. */
    public const PROJECT_SLUG = 'projects';

    /** The WordPress term ID used for Projects category sourcing. */
    public const PROJECT_SOURCE_TERM_ID = 16;

    protected function casts(): array
    {
        return [];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->orderBy('name');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'project_category_id');
    }
}
