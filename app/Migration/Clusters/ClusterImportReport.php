<?php

namespace App\Migration\Clusters;

/**
 * Structured outcome of one value chain cluster import run.
 *
 * Mirrors the news/projects/teams reports: the command renders only this
 * object, so a dry run and an executed import describe themselves identically.
 * Array members hold preview lines; counters hold totals.
 */
final class ClusterImportReport
{
    public function __construct(public readonly bool $dryRun) {}

    /** The nine audited pages this phase imports. */
    public int $eligible = 0;

    /** Mappings that already exist for a cluster page. */
    public int $existingMappings = 0;

    /** Clusters created (or, in a dry run, that would be created). */
    public int $created = 0;

    /** Clusters updated from a changed source (planned when dry). */
    public int $updated = 0;

    /** Already imported and untouched on both sides. */
    public int $unchanged = 0;

    /** Source changed while the target is untouched: a safe update. */
    public int $sourceChanged = 0;

    /** Target edited since import; skipped unless forced. */
    public int $adminModified = 0;

    /** State that needs a human decision (e.g. an unimported cluster owns the slug). */
    public int $conflicts = 0;

    public int $failed = 0;

    /** Data rows read from the nine page tables (94 in the audited export). */
    public int $sourceRows = 0;

    /** Rows deliberately excluded by the audit (the Eggs duplicate). */
    public int $excludedRows = 0;

    /** `TOTAL` summary rows in the source that are not beneficiary records. */
    public int $summaryRows = 0;

    /** Distinct rows the import writes (93). */
    public int $rowsToImport = 0;

    /** Beneficiary total of the distinct rows (15,985). */
    public int $beneficiaries = 0;

    public int $createdRows = 0;

    public int $updatedRows = 0;

    public int $deletedRows = 0;

    /** Charts copied to the public disk (planned when dry). */
    public int $chartCopies = 0;

    /** Charts found already present while processing a cluster (never overwritten).
     *  Unchanged clusters are skipped before media planning, so a no-op re-run
     *  reports zero here. */
    public int $chartReused = 0;

    /** Total rows written by this run (clusters + beneficiary rows). */
    public int $rowWrites = 0;

    /** Cluster provenance mappings written. */
    public int $mappingRows = 0;

    /** @var list<string> */
    public array $exclusionDetails = [];

    /** @var list<string> */
    public array $conflictDetails = [];

    /** @var list<string> */
    public array $adminModifiedDetails = [];

    /** @var list<string> */
    public array $failedDetails = [];

    /** @var list<string> */
    public array $missingCharts = [];

    /**
     * Per-cluster summary for the command table.
     *
     * @var list<array{wp_id: int, name: string, slug: string, rows: int, beneficiaries: int, action: string, chart: string}>
     */
    public array $clusters = [];

    /** @var list<string> */
    public array $notes = [];

    public function conflict(string $detail): void
    {
        $this->conflicts++;
        $this->conflictDetails[] = $detail;
    }

    public function fail(string $detail): void
    {
        $this->failed++;
        $this->failedDetails[] = $detail;
    }

    public function note(string $note): void
    {
        $this->notes[] = $note;
    }

    /** Records written (created + updated clusters). */
    public function imported(): int
    {
        return $this->created + $this->updated;
    }

    public function potentialDatabaseWrites(): int
    {
        return $this->imported()
            + $this->createdRows
            + $this->updatedRows
            + $this->deletedRows
            + $this->mappingRows;
    }

    public function needsAttention(): bool
    {
        return $this->conflicts > 0
            || $this->failed > 0
            || $this->adminModified > 0
            || $this->missingCharts !== [];
    }
}
