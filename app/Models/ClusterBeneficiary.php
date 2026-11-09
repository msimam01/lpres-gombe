<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row of a value chain cluster's beneficiary table: LGA, Ward and the
 * number of beneficiaries in that ward. The public "S/No" column is derived
 * from the record position (sort_index), so it is never stored or typed.
 */
class ClusterBeneficiary extends Model
{
    protected $fillable = [
        'value_chain_cluster_id', 'lga', 'ward', 'beneficiaries', 'sort_index',
    ];

    protected function casts(): array
    {
        return [
            'value_chain_cluster_id' => 'integer',
            'beneficiaries' => 'integer',
            'sort_index' => 'integer',
        ];
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(ValueChainCluster::class, 'value_chain_cluster_id', 'id');
    }
}
