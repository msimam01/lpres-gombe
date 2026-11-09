<?php

namespace App\Migration\Teams;

/**
 * Structured outcome of one team member import run (dry run or execution).
 *
 * Mirrors {@see \App\Migration\News\NewsImportReport}: the command only renders
 * this object, so a dry run and an actual import describe themselves the same
 * way. Array members hold preview lines; counters hold totals.
 */
final class TeamsImportReport
{
    public function __construct(public readonly bool $dryRun) {}

    /** Records the audited inventory classifies as genuine published team members. */
    public int $eligible = 0;

    /** @var array<string, int> Reason => count, for records deliberately not imported. */
    public array $excluded = [];

    public int $existingMappings = 0;

    /** Team members created (or, in a dry run, that would be created). */
    public int $created = 0;

    /** Team members updated from a changed WordPress source (planned when dry). */
    public int $updated = 0;

    /** Already imported and untouched on both sides. */
    public int $unchanged = 0;

    /** Source changed and the target is untouched: a safe update. */
    public int $sourceChanged = 0;

    /** Target edited since import; skipped unless forced. */
    public int $adminModified = 0;

    /** Mapping/target state that needs a human decision. */
    public int $conflicts = 0;

    public int $failed = 0;

    /** @var list<string> */
    public array $conflictDetails = [];

    /** @var list<string> */
    public array $adminModifiedDetails = [];

    /** @var list<string> */
    public array $failedDetails = [];

    /** @var list<string> */
    public array $slugConflicts = [];

    /** @var list<string> */
    public array $missingFeatured = [];

    /** @var list<string> */
    public array $missingEmbedded = [];

    /** Inline images deliberately ignored (theme/plugin placeholder assets). */
    public int $ignoredEmbedded = 0;

    /** @var list<string> */
    public array $mediaConflictDetails = [];

    public int $mediaConflictCount = 0;

    /** Distinct media files that would be / were copied. */
    public int $mediaCopies = 0;

    /** Media files already present with identical bytes (not copied again). */
    public int $mediaReused = 0;

    /** Team members assigned to a Laravel team category. */
    public int $categoryAssignments = 0;

    public bool $categoryExists = false;

    public bool $categoryWillBeCreated = false;

    /** Team member + attachment mapping rows that would be / were written. */
    public int $mappingRows = 0;

    public int $mediaMappingRows = 0;

    public int $newMediaMappingRows = 0;

    /** @var list<string> Files left on disk after a rolled-back record. */
    public array $orphanedFiles = [];

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

    /**
     * Records written (created + updated) by this run.
     */
    public function imported(): int
    {
        return $this->created + $this->updated;
    }

    /**
     * Records intentionally not written: unchanged, administrator-edited,
     * conflicting or failed.
     */
    public function skipped(): int
    {
        return $this->unchanged + $this->adminModified + $this->conflicts + $this->failed;
    }

    public function potentialDatabaseWrites(): int
    {
        return $this->imported()
            + $this->mappingRows
            + $this->mediaMappingRows
            + $this->categoryAssignments
            + ($this->categoryWillBeCreated ? 1 : 0);
    }

    /**
     * A run that requires attention (non-zero exit beyond success).
     */
    public function needsAttention(): bool
    {
        return $this->conflicts > 0
            || $this->failed > 0
            || $this->adminModified > 0
            || $this->mediaConflictCount > 0
            || $this->orphanedFiles !== [];
    }
}
