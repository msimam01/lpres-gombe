<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ModuleConfig;
use App\Models\ClusterBeneficiary;
use App\Models\ValueChainCluster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ClusterAdminController extends AdminModuleController
{
    protected function config(): ModuleConfig
    {
        return new ModuleConfig(
            model: ValueChainCluster::class,
            routeBase: 'clusters',
            label: 'Value chain cluster',
            titleFrom: 'name',
            labelFrom: 'name',
            fields: [
                ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
                // Optional: a cluster can be published with or without a
                // description; existing descriptions are kept unless edited.
                ['key' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                ['key' => 'image_path', 'label' => 'Cluster chart / image', 'type' => 'image', 'removable' => true],
                [
                    'key' => 'beneficiaries',
                    'label' => 'Beneficiary records',
                    'type' => 'beneficiary-table',
                    // Never persisted on the cluster itself; synced into the
                    // cluster_beneficiaries table via syncBeneficiaries().
                ],
                [
                    'key' => 'status',
                    'label' => 'Status',
                    'type' => 'select',
                    'options' => ['draft' => 'Draft', 'published' => 'Published'],
                ],
            ],
        );
    }

    /** Edit form carries the existing beneficiary rows (S/No = row order). */
    public function edit(int $id): Response
    {
        $config = $this->config();
        $record = $config->model::query()->with('beneficiaries')->findOrFail($id);

        // `id` etc. from the base implementation, plus rows keyed for the form.
        $record->beneficiaries->each(fn (ClusterBeneficiary $row) => $row->setHidden(['value_chain_cluster_id', 'sort_index', 'created_at', 'updated_at', 'cluster']));

        $data = $record->only(array_merge($config->fillableKeys(), ['beneficiaries'])) + ['id' => $record->id];

        return Inertia::render('admin/resource/form', [
            'module' => $config->toFrontend(),
            'record' => $data,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $config = $this->config();
        $validated = $this->validBeneficiaries($request);

        $record = DB::transaction(function () use ($request, $config, $validated) {
            $data = $this->validatePayload($request, $config);
            $data = $this->applySlug($config, $data);

            $record = ValueChainCluster::create($data);
            $this->storeFiles($request, $config, $record);

            $this->syncBeneficiaries($record, $validated);

            return $record;
        });

        return redirect()
            ->route('admin.'.$config->routeBase.'.index')
            ->with('success', $config->label.' created.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $config = $this->config();
        $record = $config->model::query()->findOrFail($id);
        $validated = $this->validBeneficiaries($request);

        DB::transaction(function () use ($request, $config, $record, $validated) {
            // Slugs never change on update so public URLs stay bookmarkable.
            $data = $this->validatePayload($request, $config);
            $data = collect($data)->put('slug', $record->slug)->all();
            // beneficiary rows are synced below, never mass-assigned.
            unset($data['beneficiaries']);

            $record->update($data);
            $this->storeFiles($request, $config, $record);

            $this->syncBeneficiaries($record, $validated);
        });

        return redirect()
            ->route('admin.'.$config->routeBase.'.index')
            ->with('success', $config->label.' updated.');
    }

    /**
     * Validate the repeatable beneficiary rows. Fully empty rows (e.g. an
     * added-but-not-filled row) are skipped; existing rows submit their id so
     * the sync only touches rows actually in the submitted table.
     *
     * @return null|array<int, array{...}> null when no table was submitted
     *                                     (client that only updates the name/image), otherwise row arrays
     *                                     (id optional, lga, ward, beneficiaries)
     */
    private function validBeneficiaries(Request $request): ?array
    {
        if (! $request->exists('beneficiaries')) {
            return null;
        }

        $generic = $this->validationRules($config = $this->config());
        unset($generic['beneficiaries']);

        $validated = $request->validate(array_merge($generic, [
            'beneficiaries' => ['nullable', 'array'],
            'beneficiaries.*.id' => ['nullable', 'integer'],
            'beneficiaries.*.lga' => ['nullable', 'string', 'max:255'],
            'beneficiaries.*.ward' => ['nullable', 'string', 'max:255'],
            'beneficiaries.*.beneficiaries' => ['nullable', 'integer', 'min:0'],
        ]));

        $rows = $validated['beneficiaries'] ?? [];

        return collect($rows)
            ->filter(fn (array $row) => filled($row['lga'] ?? null) && filled($row['ward'] ?? null)
                && isset($row['beneficiaries']) && $row['beneficiaries'] !== '')
            ->map(fn (array $row) => [
                'id' => isset($row['id']) ? (int) $row['id'] : null,
                'lga' => trim((string) $row['lga']),
                'ward' => trim((string) $row['ward']),
                'beneficiaries' => (int) $row['beneficiaries'],
            ])
            ->values()
            ->all();
    }

    /**
     * Replace the cluster's rows with the submitted table, all inside the
     * caller's transaction. Because the form always submits the complete
     * current table (including untouched rows), updating only the name or
     * image never deletes existing records.
     */
    private function syncBeneficiaries(ValueChainCluster $record, ?array $rows): void
    {
        if ($rows === null) {
            return;
        }

        $keptIds = [];

        foreach (array_values($rows) as $sortIndex => $submitted) {
            $attributes = [
                'lga' => $submitted['lga'],
                'ward' => $submitted['ward'],
                'beneficiaries' => $submitted['beneficiaries'],
                'sort_index' => $sortIndex,
                'value_chain_cluster_id' => $record->id,
            ];

            // Only update rows that actually belong to this cluster.
            if ($submitted['id'] !== null) {
                $existing = ClusterBeneficiary::query()
                    ->whereKey($submitted['id'])
                    ->where('value_chain_cluster_id', $record->id)
                    ->first();

                if ($existing !== null) {
                    $existing->fill($attributes)->save();
                    $keptIds[] = $existing->id;

                    continue;
                }
            }

            $keptIds[] = ClusterBeneficiary::create($attributes)->id;
        }

        // Remove rows absent from the submitted table (deleted rows) — and
        // only rows of this cluster.
        if ($keptIds === []) {
            $record->beneficiaries()->delete();

            return;
        }

        $record->beneficiaries()->whereNotIn('id', $keptIds)->delete();
    }
}
