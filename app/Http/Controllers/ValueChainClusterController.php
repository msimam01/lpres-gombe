<?php

namespace App\Http\Controllers;

use App\Models\ValueChainCluster;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ValueChainClusterController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('value-chains/index', [
            'clusters' => ValueChainCluster::published()
                ->withCount('beneficiaries')
                ->withSum('beneficiaries', 'beneficiaries')
                // One eager load for every card's chart preview (no N+1).
                ->with('beneficiaries:id,value_chain_cluster_id,lga,ward,beneficiaries,sort_index')
                ->get(['id', 'name', 'slug', 'image_path', 'description'])
                ->map(fn (ValueChainCluster $cluster) => [
                    'id' => $cluster->id,
                    'name' => $cluster->name,
                    'href' => Route::has('value-chains.show')
                        ? route('value-chains.show', $cluster->slug) : '#',
                    'excerpt' => $cluster->description ? Str::words(strip_tags($cluster->description), 28, '…') : null,
                    'image' => $cluster->image_path ? asset('storage/'.$cluster->image_path) : null,
                    'recordCount' => $cluster->beneficiaries_count,
                    'totalBeneficiaries' => (int) ($cluster->beneficiaries_sum_beneficiaries ?? 0),
                    // Chart preview shown on the card when there is no image.
                    'chart' => array_map(fn (array $point) => [
                        'label' => $point['label'],
                        'value' => $point['value'],
                    ], $cluster->chartSeries()),
                ])
                ->all(),
        ]);
    }

    public function show(ValueChainCluster $cluster): Response
    {
        abort_unless($cluster->status === 'published', 404);

        $cluster->load('beneficiaries');

        // The chart is derived from exactly the same rows as the table below,
        // so the bars, the table and the total can never disagree. Any
        // historical chart image on the record is left untouched but is no
        // longer sent to (or rendered by) the public page.
        return Inertia::render('value-chains/show', [
            'cluster' => [
                'name' => $cluster->name,
                'description' => $cluster->description,
                'records' => $cluster->beneficiaries->values()->map(fn ($row, $index) => [
                    'sNo' => $index + 1, // Derived S/No from displayed order.
                    'lga' => $row->lga,
                    'ward' => $row->ward,
                    'beneficiaries' => $row->beneficiaries,
                ])->all(),
                'totalBeneficiaries' => (int) $cluster->beneficiaries->sum('beneficiaries'),
                'chart' => [
                    'title' => sprintf('Beneficiary distribution — %s', $cluster->name),
                    'series' => $cluster->chartSeries(),
                ],
            ],
            'others' => ValueChainCluster::published()
                ->where('id', '!=', $cluster->id)
                ->get(['id', 'name', 'slug'])
                ->map(fn (ValueChainCluster $item) => [
                    'name' => $item->name,
                    'href' => route('value-chains.show', $item->slug),
                ])
                ->all(),
        ]);
    }
}
