<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A value chain cluster (Beef, Dairy, Poultry, ...) with a listing card and a
 * dedicated detail page holding a beneficiary table plus an uploaded chart
 * image.
 */
class ValueChainCluster extends Model
{
    protected $fillable = ['name', 'slug', 'image_path', 'description', 'status'];

    protected function casts(): array
    {
        return [];
    }

    /**
     * Cluster name is also the form's slug source; exposed so the generic
     * module controller finds the label/slug source (labelFrom = 'name').
     */
    public function beneficiaries(): HasMany
    {
        return $this->hasMany(ClusterBeneficiary::class, 'value_chain_cluster_id')
            ->orderBy('sort_index')
            ->orderBy('id');
    }

    public function scopePublished(Builder $query): Builder
    {
        // Automatic ordering: alphabetical, matching the homepage shortcuts.
        return $query->where('status', 'published')->orderBy('name')->orderByDesc('id');
    }
}
