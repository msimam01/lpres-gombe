<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An ordinary project team member ("Project Specialist"): photograph, full
 * name, designation and team category. The State Project Coordinator is NOT
 * a member record — the SPC is a dedicated editorial profile (see
 * resources/js/pages/spc-profile.tsx and the /spc-profile route), so ordinary
 * members never get a Read More link or an individual biography page.
 */
class TeamMember extends Model
{
    protected $fillable = [
        'name', 'slug', 'photograph_path', 'designation', 'team_category_id', 'status',
    ];

    protected function casts(): array
    {
        return [
            'team_category_id' => 'integer',
            'status' => 'boolean',
        ];
    }

    public function teamCategory(): BelongsTo
    {
        return $this->belongsTo(TeamCategory::class, 'team_category_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        // Automatic ordering: alphabetical within each managed category.
        return $query->where('status', true)->orderBy('name')->orderByDesc('id');
    }
}
