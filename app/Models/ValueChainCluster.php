<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A value chain cluster (Beef, Dairy, Poultry, ...) with a listing card and a
 * dedicated detail page holding a beneficiary table. The page chart is built
 * from those beneficiary records (see chartSeries()), never from a stored
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

    /**
     * Chart-ready distribution for this cluster: one bar per LGA + ward, in
     * the same order as the stored records. Rows that share an LGA and ward
     * are summed into a single bar so the chart never double-counts; the
     * table itself keeps every stored row unchanged. Expects `beneficiaries`
     * to be loaded, and is computed from those same rows as the table.
     *
     * @return array<int, array{label: string, lga: string, ward: string, value: int}>
     */
    public function chartSeries(): array
    {
        return $this->beneficiaries
            ->groupBy(fn (ClusterBeneficiary $row) => $row->lga."\u{1F}".$row->ward)
            ->map(function ($rows) {
                $first = $rows->first();

                return [
                    'label' => $first->lga.', '.$first->ward,
                    'lga' => $first->lga,
                    'ward' => $first->ward,
                    'value' => (int) $rows->sum('beneficiaries'),
                ];
            })
            ->values()
            ->all();
    }

    public function scopePublished(Builder $query): Builder
    {
        // Automatic ordering: alphabetical, matching the homepage shortcuts.
        return $query->where('status', 'published')->orderBy('name')->orderByDesc('id');
    }
}
