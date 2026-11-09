<?php

namespace App\Migration\Clusters;

use App\Migration\SourceHash;
use App\Migration\WordPress\WordPressExport;
use App\Migration\WordPress\WordPressItem;
use App\Models\ClusterBeneficiary;
use App\Models\ValueChainCluster;
use App\Models\WpImportMap;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Imports the nine audited value chain cluster pages.
 *
 * Every write goes through `wp_import_map`, which makes re-runs safe:
 *
 *   source unchanged, target untouched  -> unchanged (no write at all)
 *   source changed,   target untouched  -> safe update from the source
 *   target changed (or no target hash)  -> administrator edit, skipped unless
 *                                          --force-overwrite-admin-edits
 *
 * A dry run runs the same code path with `$execute = false`: it parses,
 * validates, plans rows, checks charts and reports every planned write, but
 * performs no database write and copies no file.
 *
 * Row policy (approved):
 *   - the source table is imported in page order, `sort_index` 0..n;
 *   - `TOTAL` summary rows are skipped and counted, never imported;
 *   - the Eggs row that duplicates row 7 is excluded explicitly, with its
 *     reason recorded on the mapping and in the report;
 *   - whitespace is trimmed/normalised (`&nbsp;`, doubled spaces); wording and
 *     beneficiary counts are never changed;
 *   - the ratio of rows/totals is verified against the audit before each
 *     cluster is written, so a changed source fails instead of importing
 *     silently wrong figures.
 *
 * Chart policy: the nine audited PNGs live in public/images/lpres/clusters and
 * are copied to `uploads/clusters/{slug}.png` on the public disk (the path the
 * public pages read). An existing destination file is never overwritten or
 * deleted, so a chart uploaded through the admin survives a re-run.
 */
final class ClustersImporter
{
    /** `wp_import_map.source_type` for a cluster page. */
    public const SOURCE_TYPE = 'value_chain_cluster';

    /** Directory inside the public disk that holds imported cluster charts. */
    public const CHART_DIRECTORY = 'uploads/clusters';

    private ClusterFields $fields;

    /**
     * @param  array<int, array{rows: int, beneficiaries: int}>  $expectedCounts
     */
    public function __construct(
        private readonly WordPressExport $export,
        private readonly SourceHash $hasher,
        private readonly bool $forceAdminEdits = false,
        private readonly ?string $chartSourceDirectory = null,
        private readonly array $expectedCounts = ClusterFields::EXPECTED,
        private readonly string $disk = 'public',
        ?ClusterFields $fields = null,
    ) {
        $this->fields = $fields ?? new ClusterFields;
    }

    public function run(bool $execute): ClusterImportReport
    {
        $report = new ClusterImportReport(! $execute);
        $report->eligible = count(ClusterFields::CLUSTERS);

        $report->note('Audited cluster pages: '.implode(', ', array_keys(ClusterFields::CLUSTERS)));

        foreach (ClusterFields::CLUSTERS as $wpId => $definition) {
            $item = $this->export->find($wpId);

            if ($item === null) {
                $report->fail('#'.$wpId.' '.$definition['name'].': listed as an audited cluster page but missing from the XML export');

                continue;
            }

            $this->importOne($wpId, $item, $report, $execute);
        }

        return $report;
    }

    private function importOne(int $wpId, WordPressItem $item, ClusterImportReport $report, bool $execute): void
    {
        $definition = ClusterFields::CLUSTERS[$wpId];
        $name = $definition['name'];
        $slug = $definition['slug'];
        $label = '#'.$wpId.' '.$name;

        if (! $item->isPublished()) {
            $report->fail($label.': the WordPress page is '.$item->status.', not publish');

            return;
        }

        try {
            $table = $this->fields->table($item);
        } catch (RuntimeException $exception) {
            $report->fail($label.': '.$exception->getMessage());

            return;
        }

        $rows = $table['rows'];
        $report->sourceRows += count($rows);
        $report->summaryRows += $table['summaryRows'];

        $exclusion = $this->applyExclusion($wpId, $rows, $report);

        if ($exclusion === null) {
            return;
        }

        $total = array_sum(array_column($rows, 'beneficiaries'));

        if (! $this->matchesAudit($wpId, count($rows), $total, $label, $report)) {
            return;
        }

        $md5 = $this->chartMd5($slug, $label, $report);

        if ($md5 === null) {
            return;
        }

        $sourceHash = $this->sourceHash($wpId, $slug, $name, $item, $rows, $md5);
        $mapping = WpImportMap::lookup(self::SOURCE_TYPE, $wpId);
        $target = null;
        $isCreate = true;

        if ($mapping !== null) {
            $report->existingMappings++;

            $mapped = $mapping->target;

            if (! $mapping->isImported() || ! $mapped instanceof ValueChainCluster) {
                $report->conflict($label.': an import mapping exists but its cluster is missing, so the target cannot be verified');

                return;
            }

            $target = $mapped;
            $sourceChanged = $mapping->hasSourceChanged($sourceHash);
            $targetChanged = $mapping->hasTargetChanged($this->targetHash($target));

            if ($sourceChanged) {
                $report->sourceChanged++;
            }

            if ($targetChanged && ! $this->forceAdminEdits) {
                $report->adminModified++;
                $report->adminModifiedDetails[] = sprintf(
                    '%s: %s since import; skipped (re-run with --force-overwrite-admin-edits to overwrite)%s',
                    $label,
                    $mapping->target_hash === null ? 'no target hash recorded' : 'the cluster was edited',
                    $sourceChanged ? ' — the WordPress source also changed' : '',
                );
                $report->clusters[] = $this->clusterRow($wpId, $target, 'admin-edited', 'kept');

                return;
            }

            if (! $sourceChanged && ! $targetChanged) {
                $report->unchanged++;
                $report->clusters[] = $this->clusterRow($wpId, $target, 'unchanged', 'kept');

                return;
            }

            $isCreate = false;

            if ($targetChanged) {
                $report->note($label.': administrator edits overwritten because --force-overwrite-admin-edits was given');
            }
        } else {
            // A cluster that owns the slug but was not imported is an
            // administrator’s record: adopting it would overwrite their work.
            $existing = ValueChainCluster::query()->where('slug', $slug)->first();

            if ($existing !== null) {
                $report->conflict($label.': a cluster with the slug "'.$slug.'" already exists but was not created by this importer');

                return;
            }
        }

        $targetPath = self::CHART_DIRECTORY.'/'.$slug.'.png';
        $chartExists = $this->chartExists($targetPath);

        if ($chartExists) {
            $report->chartReused++;
            $this->noteChartMismatch($targetPath, $md5, $label, $report);
        } else {
            $report->chartCopies++;
        }

        $rowsToWrite = count($rows);
        $report->rowsToImport += $rowsToWrite;
        $report->beneficiaries += $total;

        $sync = $this->planRows($target, $rows);
        $report->createdRows += $sync['create'];
        $report->updatedRows += $sync['update'];
        $report->deletedRows += $sync['delete'];

        $copied = false;

        try {
            if ($execute) {
                DB::beginTransaction();
            }

            if ($execute && ! $chartExists) {
                $this->copyChart($slug, $targetPath);
                $copied = true;
            }

            if ($execute) {
                $columns = [
                    'name' => $name,
                    'slug' => $slug,
                    'image_path' => $targetPath,
                    'description' => $this->fields->description($wpId),
                    'status' => 'published',
                ];

                if ($isCreate) {
                    $target = ValueChainCluster::create($columns);
                } else {
                    $target->update($columns);
                }

                $this->applyRows($target, $rows);
                $this->saveMapping($wpId, $item, $target, $mapping, $sourceHash, $md5, $rows, $table, $targetPath, $report);

                DB::commit();
            }

            $report->rowWrites += $rowsToWrite + 1;
            $report->mappingRows++;

            if ($isCreate) {
                $report->created++;
            } else {
                $report->updated++;
            }

            $report->clusters[] = [
                'wp_id' => $wpId,
                'name' => $name,
                'slug' => $slug,
                'rows' => $rowsToWrite,
                'beneficiaries' => $total,
                'action' => $isCreate ? ($report->dryRun ? 'would create' : 'created') : ($report->dryRun ? 'would update' : 'updated'),
                'chart' => $chartExists ? 'reused' : ($report->dryRun ? 'would copy' : 'copied'),
            ];
        } catch (Throwable $exception) {
            if ($execute && DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            if ($copied) {
                $this->removeCopiedChart($targetPath, $report);
            }

            $report->fail($label.': '.$exception->getMessage());
        }
    }

    /**
     * Remove the audited duplicate from the parsed rows.
     *
     * @param  list<array{lga: string, ward: string, beneficiaries: int}>  $rows
     * @return bool|null True when an exclusion was applied, false when there was
     *                   nothing to exclude, null when the run must stop.
     */
    private function applyExclusion(int $wpId, array &$rows, ClusterImportReport $report): ?bool
    {
        $exclusions = ClusterFields::EXCLUSIONS[$wpId] ?? [];

        if ($exclusions === []) {
            return false;
        }

        $applied = false;

        foreach ($exclusions as $position => $exclusion) {
            $index = $position - 1;
            $row = $rows[$index] ?? null;

            if ($row === null) {
                $report->fail('#'.$wpId.': the approved duplicate at data row '.$position.' no longer exists in the source');

                return null;
            }

            $same = $row['lga'] === $exclusion['lga']
                && $row['ward'] === $exclusion['ward']
                && $row['beneficiaries'] === $exclusion['beneficiaries'];

            if (! $same) {
                $report->fail(sprintf(
                    '#%d: data row %d is no longer the approved duplicate (%s / %s / %d); resolve before importing',
                    $wpId,
                    $position,
                    $exclusion['lga'],
                    $exclusion['ward'],
                    $exclusion['beneficiaries'],
                ));

                return null;
            }

            array_splice($rows, $index, 1);
            $applied = true;
            $report->excludedRows++;
            $report->exclusionDetails[] = sprintf(
                '#%d data row %d (%s / %s / %d) excluded: %s',
                $wpId,
                $position,
                $exclusion['lga'],
                $exclusion['ward'],
                $exclusion['beneficiaries'],
                $exclusion['reason'],
            );
        }

        return $applied;
    }

    /**
     * @param  list<array{lga: string, ward: string, beneficiaries: int}>  $rows
     */
    private function matchesAudit(int $wpId, int $rowCount, int $total, string $label, ClusterImportReport $report): bool
    {
        $expected = $this->expectedCounts[$wpId] ?? null;

        if ($expected === null) {
            return true;
        }

        if ($rowCount !== $expected['rows']) {
            $report->fail(sprintf(
                '%s: the source has %d distinct rows after exclusions; the audit approved %d',
                $label,
                $rowCount,
                $expected['rows'],
            ));

            return false;
        }

        if ($total !== $expected['beneficiaries']) {
            $report->fail(sprintf(
                '%s: the source totals %s beneficiaries; the audit approved %s',
                $label,
                number_format($total),
                number_format($expected['beneficiaries']),
            ));

            return false;
        }

        return true;
    }

    private function chartMd5(string $slug, string $label, ClusterImportReport $report): ?string
    {
        $source = $this->chartSourceDirectory().'/'.$slug.'.png';

        if (! is_file($source)) {
            $report->missingCharts[] = $label.': '.$source;
            $report->fail($label.': the audited chart '.$slug.'.png is missing from '.$this->chartSourceDirectory());

            return null;
        }

        $md5 = @md5_file($source);

        if ($md5 === false) {
            $report->fail($label.': the audited chart '.$slug.'.png could not be read');

            return null;
        }

        return $md5;
    }

    private function chartSourceDirectory(): string
    {
        return $this->chartSourceDirectory ?? public_path('images/lpres/clusters');
    }

    /**
     * @param  list<array{lga: string, ward: string, beneficiaries: int}>  $rows
     */
    private function sourceHash(int $wpId, string $slug, string $name, WordPressItem $item, array $rows, string $md5): string
    {
        return $this->hasher->forPayload([
            'source_type' => self::SOURCE_TYPE,
            'source_id' => $wpId,
            'name' => $name,
            'slug' => $slug,
            'status' => $item->status,
            'chart_attachment_id' => ClusterFields::CLUSTERS[$wpId]['attachment_id'],
            'chart_md5' => $md5,
            'rows' => array_map(
                fn (array $row) => [$row['lga'], $row['ward'], $row['beneficiaries']],
                $rows,
            ),
        ]);
    }

    private function targetHash(ValueChainCluster $cluster): string
    {
        return $this->hasher->forPayload([
            'name' => $cluster->name,
            'slug' => $cluster->slug,
            'image_path' => $cluster->image_path,
            'description' => $cluster->description,
            'status' => $cluster->status,
            'rows' => $cluster->beneficiaries()
                ->get()
                ->map(fn (ClusterBeneficiary $row) => [
                    $row->lga,
                    $row->ward,
                    (int) $row->beneficiaries,
                    (int) $row->sort_index,
                ])
                ->all(),
        ]);
    }

    /**
     * @param  list<array{lga: string, ward: string, beneficiaries: int}>  $rows
     * @return array{create: int, update: int, delete: int}
     */
    private function planRows(?ValueChainCluster $target, array $rows): array
    {
        if ($target === null) {
            return ['create' => count($rows), 'update' => 0, 'delete' => 0];
        }

        [$byIndex, $extra] = $this->existingRows($target);
        $plan = ['create' => 0, 'update' => 0, 'delete' => count($extra)];

        foreach ($rows as $index => $row) {
            $existing = $byIndex[$index] ?? null;

            if ($existing === null) {
                $plan['create']++;

                continue;
            }

            if ($existing->lga !== $row['lga']
                || $existing->ward !== $row['ward']
                || (int) $existing->beneficiaries !== $row['beneficiaries']) {
                $plan['update']++;
            }
        }

        foreach (array_keys($byIndex) as $index) {
            if ($index >= count($rows)) {
                $plan['delete']++;
            }
        }

        return $plan;
    }

    /**
     * @return array{0: array<int, ClusterBeneficiary>, 1: list<ClusterBeneficiary>}
     */
    private function existingRows(ValueChainCluster $target): array
    {
        /** @var Collection<int, ClusterBeneficiary> $rows */
        $rows = $target->beneficiaries()->get();
        $byIndex = [];
        $extra = [];

        foreach ($rows as $row) {
            if (isset($byIndex[(int) $row->sort_index])) {
                $extra[] = $row;

                continue;
            }

            $byIndex[(int) $row->sort_index] = $row;
        }

        return [$byIndex, $extra];
    }

    /**
     * @param  list<array{lga: string, ward: string, beneficiaries: int}>  $rows
     */
    private function applyRows(ValueChainCluster $target, array $rows): void
    {
        [$byIndex, $extra] = $this->existingRows($target);

        foreach ($rows as $index => $row) {
            $attributes = [
                'lga' => $row['lga'],
                'ward' => $row['ward'],
                'beneficiaries' => $row['beneficiaries'],
                'sort_index' => $index,
            ];

            $existing = $byIndex[$index] ?? null;

            if ($existing === null) {
                ClusterBeneficiary::create($attributes + ['value_chain_cluster_id' => $target->id]);
            } elseif ($existing->lga !== $row['lga']
                || $existing->ward !== $row['ward']
                || (int) $existing->beneficiaries !== $row['beneficiaries']) {
                $existing->update($attributes);
            }
        }

        $removed = $extra;

        foreach ($byIndex as $index => $row) {
            if ($index >= count($rows)) {
                $removed[] = $row;
            }
        }

        foreach ($removed as $row) {
            $row->delete();
        }
    }

    /**
     * @param  list<array{lga: string, ward: string, beneficiaries: int}>  $rows
     * @param  array{rows: list<array{lga: string, ward: string, beneficiaries: int}>, summaryRows: int}  $table
     */
    private function saveMapping(
        int $wpId,
        WordPressItem $item,
        ValueChainCluster $target,
        ?WpImportMap $mapping,
        string $sourceHash,
        string $md5,
        array $rows,
        array $table,
        string $targetPath,
        ClusterImportReport $report,
    ): void {
        $mapping ??= new WpImportMap([
            'source_type' => self::SOURCE_TYPE,
            'source_id' => $wpId,
        ]);

        $mapping->fill([
            'source_type' => self::SOURCE_TYPE,
            'source_id' => $wpId,
            'target_type' => ValueChainCluster::class,
            'target_id' => $target->id,
            'source_hash' => $sourceHash,
            'source_metadata' => $this->metadata($wpId, $item, $rows, $table, $targetPath, $md5, $report),
            'imported_at' => now(),
        ]);

        $mapping->save();

        $target->refresh();

        $mapping->forceFill([
            'target_hash' => $this->targetHash($target),
        ])->save();
    }

    /**
     * @param  list<array{lga: string, ward: string, beneficiaries: int}>  $rows
     * @param  array{rows: list<array{lga: string, ward: string, beneficiaries: int}>, summaryRows: int}  $table
     * @return array<string, mixed>
     */
    private function metadata(
        int $wpId,
        WordPressItem $item,
        array $rows,
        array $table,
        string $targetPath,
        string $md5,
        ClusterImportReport $report,
    ): array {
        $excluded = ClusterFields::EXCLUSIONS[$wpId] ?? [];

        return [
            'wp_post_id' => $wpId,
            'wp_post_type' => $item->type,
            'wp_status' => $item->status,
            'wp_title' => $item->title,
            'wp_slug' => $item->slug,
            'wp_link' => $item->link,
            'wp_published_at' => $item->publishedAt,
            'wp_modified_at' => $item->modifiedAt,
            'chart_attachment_id' => ClusterFields::CLUSTERS[$wpId]['attachment_id'],
            'chart_source_path' => 'images/lpres/clusters/'.ClusterFields::CLUSTERS[$wpId]['slug'].'.png',
            'chart_stored_path' => $targetPath,
            'chart_md5' => $md5,
            'source_row_count' => count($table['rows']),
            'summary_rows' => $table['summaryRows'],
            'imported_row_count' => count($rows),
            'imported_beneficiaries' => array_sum(array_column($rows, 'beneficiaries')),
            'excluded_rows' => array_map(
                fn (int $position, array $exclusion) => [
                    'position' => $position,
                    'lga' => $exclusion['lga'],
                    'ward' => $exclusion['ward'],
                    'beneficiaries' => $exclusion['beneficiaries'],
                    'reason' => $exclusion['reason'],
                ],
                array_keys($excluded),
                array_values($excluded),
            ),
            'imported_from' => 'lpres:import-wordpress --type=clusters',
        ];
    }

    private function copyChart(string $slug, string $targetPath): void
    {
        $source = $this->chartSourceDirectory().'/'.$slug.'.png';
        $stream = @fopen($source, 'rb');

        if ($stream === false) {
            throw new RuntimeException('the chart '.$slug.'.png could not be opened');
        }

        $stored = Storage::disk($this->disk)->put($targetPath, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        if (! $stored) {
            throw new RuntimeException('the chart could not be copied to '.$targetPath);
        }
    }

    private function removeCopiedChart(string $targetPath, ClusterImportReport $report): void
    {
        try {
            Storage::disk($this->disk)->delete($targetPath);
        } catch (Throwable) {
            $report->notes[] = 'a chart copied for a failed cluster could not be removed: '.$targetPath;
        }
    }

    private function chartExists(string $targetPath): bool
    {
        try {
            return Storage::disk($this->disk)->exists($targetPath);
        } catch (Throwable) {
            return false;
        }
    }

    private function noteChartMismatch(string $targetPath, string $md5, string $label, ClusterImportReport $report): void
    {
        try {
            $existing = Storage::disk($this->disk)->get($targetPath);
        } catch (Throwable) {
            return;
        }

        if ($existing !== null && md5($existing) !== $md5) {
            $report->note($label.': a different file already occupies '.$targetPath.'; it was kept (files are never overwritten)');
        }
    }

    /**
     * @return array{wp_id: int, name: string, slug: string, rows: int, beneficiaries: int, action: string, chart: string}
     */
    private function clusterRow(int $wpId, ValueChainCluster $cluster, string $action, string $chart): array
    {
        return [
            'wp_id' => $wpId,
            'name' => (string) $cluster->name,
            'slug' => (string) $cluster->slug,
            'rows' => $cluster->beneficiaries()->count(),
            'beneficiaries' => (int) $cluster->beneficiaries()->sum('beneficiaries'),
            'action' => $action,
            'chart' => $chart,
        ];
    }
}
