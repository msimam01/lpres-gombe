<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A managed team category used to group members on the Project Team page.
 * Categories are internal classification, not standalone public content:
 * no visibility switch and no manual ordering — listings are alphabetical
 * by name.
 */
class TeamCategory extends Model
{
    protected $fillable = ['name', 'slug', 'description'];

    public const PIU_SLUG = 'piu';
    public const ASS_SLUG = 'ass';

    protected function casts(): array
    {
        return [];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->orderBy('name');
    }

    public function members(): HasMany
    {
        return $this->hasMany(TeamMember::class, 'team_category_id');
    }
}