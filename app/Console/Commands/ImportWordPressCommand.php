<?php

namespace App\Console\Commands;

use App\Migration\Clusters\ClusterImportReport;
use App\Migration\Clusters\ClustersImporter;
use App\Migration\ContentInventory;
use App\Migration\HtmlToText;
use App\Migration\Media\MediaImporter;
use App\Migration\Media\MediaResolver;
use App\Migration\MediaManifest;
use App\Migration\MigrationPaths;
use App\Migration\MigrationSourceException;
use App\Migration\News\NewsFields;
use App\Migration\News\NewsImporter;
use App\Migration\News\NewsImportReport;
use App\Migration\Teams\TeamsImportReport;
use App\Migration\Projects\ProjectsFields;
use App\Migration\Projects\ProjectsImporter;
use App\Migration\SourceHash;
use App\Migration\WordPress\WordPressExport;
use App\Migration\WordPress\WordPressExportException;
use App\Migration\WordPress\WordPressXmlParser;
use App\Models\WpImportMap;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** WordPress migration command.
 *
 * Two modes:
 *
 *  1. Without `--type`: the read-only infrastructure overview shipped in
 *     phase 1 (sources, export, media manifest, import map). Nothing is ever
 *     written in this mode.
 *  2. With `--type=news`: the phase 2 news importer. `--dry-run` reports the
 *     complete plan without writing; `--confirm` executes it.
 *  3. With `--type=projects`: the phase 3.2 projects importer. Same features.
 *  4. With `--type=teams`: the phase 4 team member importer.
 *  5. With `--type=clusters`: the phase 5 value chain cluster importer.
 *
 * Every other content type is refused rather than silently imported.
 */
class ImportWordPressCommand extends Command
{
    protected $signature = 'lpres:import-wordpress
                            {--type= : Content type to import (supported: news, projects, teams, clusters)}
                            {--dry-run : Report what would be imported without writing anything}
                            {--confirm : Execute the import (requires --type)}
                            {--force-overwrite-admin-edits : Overwrite articles an administrator edited after import}';

    protected $description = 'Import the L-PRES WordPress export; dry run by default';

    /** Content types this command can import. */
    private const SUPPORTED_TYPES = ['news', 'projects', 'teams', 'clusters'];

    /** Number of problem entries listed before the rest are summarised. */
    private const PROBLEM_PREVIEW = 10;

    public function handle(WordPressXmlParser $parser, SourceHash $hasher): int
    {
        $this->components->info('L-PRES WordPress migration');

        $type = trim((string) $this->option('type'));
        $dryRun = (bool) $this->option('dry-run');
        $confirm = (bool) $this->option('confirm');

        if ($dryRun && $confirm) {
            $this->components->error('--dry-run and --confirm cannot be combined.');

            return self::INVALID;
        }

        if ($type !== '') {
            if (! in_array($type, self::SUPPORTED_TYPES, true)) {
                $this->components->error("Content type \"{$type}\" is not supported.");
                $this->line('  This phase imports: '.implode(', ', self::SUPPORTED_TYPES).'.');
                $this->line('  Other content types (projects, team, events, pages) are out of scope.');

                return self::INVALID;
            }

            if ($type === 'news') {
                return $this->importNews($parser, $hasher, $confirm);
            }

            if ($type === 'projects') {
                return $this->importProjects($parser, $hasher, $confirm);
            }

            if ($type === 'teams') {
                return $this->importTeams($parser, $hasher, $confirm);
            }

            if ($type === 'clusters') {
                return $this->importClusters($parser, $hasher, $confirm);
            }

            return self::INVALID;
        }

        if ($confirm) {
            $this->components->error('--confirm needs a content type.');
            $this->line('  Run: php artisan lpres:import-wordpress --type=news --confirm');
            $this->line('  Or: php artisan lpres:import-wordpress --type=projects --confirm
  Or: php artisan lpres:import-wordpress --type=teams --confirm
  Or: php artisan lpres:import-wordpress --type=clusters --confirm');

            return self::INVALID;
        }

        if (! $dryRun) {
            return $this->refuseImport();
        }

        return $this->overview($parser, $hasher);
    }

    /** News import (phase 2). */
    private function importNews(WordPressXmlParser $parser, SourceHash $hasher, bool $confirm): int
    {
        $paths = MigrationPaths::fromConfig();

        if (! $this->reportSources($paths)) {
            return self::INVALID;
        }

        try {
            $export = $parser->parse($paths->xml);
            $manifest = MediaManifest::fromFile($paths->manifest);
            $inventory = ContentInventory::fromFile($paths->inventory);
        } catch (WordPressExportException $exception) {
            $this->newLine();
            $this->components->error('The WordPress export could not be parsed.');
            $this->line('  '.$exception->getMessage());

            return self::INVALID;
        } catch (MigrationSourceException $exception) {
            $this->newLine();
            $this->components->error('A migration source is not usable.');
            $this->line('  '.$exception->getMessage());

            return self::INVALID;
        }

        $this->detail('Audited inventory', $inventory->path.' <fg=gray>('.count($inventory->eligibleNewsIds()).' eligible)</>');

        if (! $this->reportMedia($paths, $manifest)) {
            return self::INVALID;
        }

        $importer = new NewsImporter(
            export: $export,
            inventory: $inventory,
            resolver: MediaResolver::fromExport($export, $manifest, $paths->mediaDirectory),
            mediaImporter: new MediaImporter($paths->mediaDisk, 'uploads/news'),
            fields: new NewsFields(new HtmlToText),
            hasher: $hasher,
            forceAdminEdits: (bool) $this->option('force-overwrite-admin-edits'),
        );

        $report = $importer->run($confirm);

        $this->reportNewsPlan($report);

        if (! $confirm) {
            $this->newLine();
            $this->components->info('Dry run complete — nothing was written.');
            $this->line('  Execute with: php artisan lpres:import-wordpress --type=news --confirm');

            return $report->needsAttention() ? self::FAILURE : self::SUCCESS;
        }

        $this->newLine();

        if ($report->failed > 0 || $report->orphanedFiles !== []) {
            $this->components->error(sprintf(
                'Import finished with %d failed record(s); %d file(s) left on disk.',
                $report->failed,
                count($report->orphanedFiles),
            ));
        } elseif ($report->conflicts > 0 || $report->adminModified > 0 || $report->mediaConflictCount > 0) {
            $this->components->warn('Import finished; some records need review (see above).');
        } else {
            $this->components->info('Import finished.');
        }

        return $report->needsAttention() ? self::FAILURE : self::SUCCESS;
    }

    /** Projects import (phase 3.2). */
    private function importProjects(WordPressXmlParser $parser, SourceHash $hasher, bool $confirm): int
    {
        $paths = MigrationPaths::fromConfig();

        if (! $this->reportSources($paths)) {
            return self::INVALID;
        }

        try {
            $export = $parser->parse($paths->xml);
            $manifest = MediaManifest::fromFile($paths->manifest);
            $inventory = ContentInventory::fromFile($paths->inventory);
        } catch (WordPressExportException $exception) {
            $this->newLine();
            $this->components->error('The WordPress export could not be parsed.');
            $this->line('  '.$exception->getMessage());

            return self::INVALID;
        } catch (MigrationSourceException $exception) {
            $this->newLine();
            $this->components->error('A migration source is not usable.');
            $this->line('  '.$exception->getMessage());

            return self::INVALID;
        }

        $this->detail('Audited inventory', $inventory->path.' <fg=gray>('.count($inventory->eligibleProjectIds()).' eligible)</>');

        if (! $this->reportMedia($paths, $manifest)) {
            return self::INVALID;
        }

        $importer = new ProjectsImporter(
            export: $export,
            inventory: $inventory,
            resolver: MediaResolver::fromExport($export, $manifest, $paths->mediaDirectory),
            mediaImporter: new MediaImporter($paths->mediaDisk, 'uploads/projects'),
            fields: new ProjectsFields(new HtmlToText),
            hasher: $hasher,
            forceAdminEdits: (bool) $this->option('force-overwrite-admin-edits'),
        );

        $report = $importer->run($confirm);

        $this->reportProjectsPlan($report);

        if (! $confirm) {
            $this->newLine();
            $this->components->info('Dry run complete — nothing was written.');
            $this->line('  Execute with: php artisan lpres:import-wordpress --type=projects --confirm');

            return $report->needsAttention() ? self::FAILURE : self::SUCCESS;
        }

        $this->newLine();

        if ($report->failed > 0 || $report->orphanedFiles !== []) {
            $this->components->error(sprintf(
                'Import finished with %d failed record(s); %d file(s) left on disk.',
                $report->failed,
                count($report->orphanedFiles),
            ));
        } elseif ($report->conflicts > 0 || $report->adminModified > 0 || $report->mediaConflictCount > 0) {
            $this->components->warn('Import finished; some records need review (see above).');
        } else {
            $this->components->info('Import finished.');
        }

        return $report->needsAttention() ? self::FAILURE : self::SUCCESS;
    }

    /** Teams import (phase 4). */
    private function importTeams(WordPressXmlParser $parser, SourceHash $hasher, bool $confirm): int
    {
        $paths = MigrationPaths::fromConfig();

        if (! $this->reportSources($paths)) {
            return self::INVALID;
        }

        try {
            $export = $parser->parse($paths->xml);
            $manifest = MediaManifest::fromFile($paths->manifest);
            $inventory = ContentInventory::fromFile($paths->inventory);
        } catch (WordPressExportException $exception) {
            $this->newLine();
            $this->components->error('The WordPress export could not be parsed.');
            $this->line('  '.$exception->getMessage());
            return self::INVALID;
        } catch (MigrationSourceException $exception) {
            $this->newLine();
            $this->components->error('A migration source is not usable.');
            $this->line('  '.$exception->getMessage());
            return self::INVALID;
        }

        $this->detail('Audited inventory', $inventory->path.' <fg=gray>('.count($inventory->eligibleTeamIds()).' eligible)</>');

        if (! $this->reportMedia($paths, $manifest)) {
            return self::INVALID;
        }

        $importer = new \App\Migration\Teams\TeamsImporter(
            export: $export,
            inventory: $inventory,
            resolver: MediaResolver::fromExport($export, $manifest, $paths->mediaDirectory),
            mediaImporter: new MediaImporter($paths->mediaDisk, 'uploads/team-members'),
            fields: new \App\Migration\Teams\TeamFields(new HtmlToText),
            hasher: $hasher,
            forceAdminEdits: (bool) $this->option('force-overwrite-admin-edits'),
        );

        $report = $importer->run($confirm);

        $this->reportTeamsPlan($report);

        if (! $confirm) {
            $this->newLine();
            $this->components->info('Dry run complete — nothing was written.');
            $this->line('  Execute with: php artisan lpres:import-wordpress --type=teams --confirm');
            return $report->needsAttention() ? self::FAILURE : self::SUCCESS;
        }

        $this->newLine();

        if ($report->failed > 0 || $report->orphanedFiles !== []) {
            $this->components->error(sprintf(
                'Import finished with %d failed record(s); %d file(s) left on disk.',
                $report->failed,
                count($report->orphanedFiles),
            ));
        } elseif ($report->conflicts > 0 || $report->adminModified > 0 || $report->mediaConflictCount > 0) {
            $this->components->warn('Import finished; some records need review (see above).');
        } else {
            $this->components->info('Import finished.');
        }

        return $report->needsAttention() ? self::FAILURE : self::SUCCESS;
    }

    /** Value chain clusters import (phase 5). */
    private function importClusters(WordPressXmlParser $parser, SourceHash $hasher, bool $confirm): int
    {
        $paths = MigrationPaths::fromConfig();

        if (! $this->reportSources($paths)) {
            return self::INVALID;
        }

        try {
            $export = $parser->parse($paths->xml);
        } catch (WordPressExportException $exception) {
            $this->newLine();
            $this->components->error('The WordPress export could not be parsed.');
            $this->line('  '.$exception->getMessage());

            return self::INVALID;
        }

        $importer = new ClustersImporter(
            export: $export,
            hasher: $hasher,
            forceAdminEdits: (bool) $this->option('force-overwrite-admin-edits'),
            disk: (string) config('lpres_migration.media_disk', 'public'),
        );

        $report = $importer->run($confirm);

        $this->reportClustersPlan($report);

        if (! $confirm) {
            $this->newLine();
            $this->components->info('Dry run complete — nothing was written.');
            $this->line('  Execute with: php artisan lpres:import-wordpress --type=clusters --confirm');

            return $report->needsAttention() ? self::FAILURE : self::SUCCESS;
        }

        $this->newLine();

        if ($report->failed > 0) {
            $this->components->error(sprintf('Import finished with %d failed cluster page(s).', $report->failed));
        } elseif ($report->conflicts > 0 || $report->adminModified > 0) {
            $this->components->warn('Import finished; some clusters need review (see above).');
        } else {
            $this->components->info('Import finished.');
        }

        return $report->needsAttention() ? self::FAILURE : self::SUCCESS;
    }

    private function reportClustersPlan(ClusterImportReport $report): void
    {
        $this->newLine();
        $this->components->info($report->dryRun ? 'Clusters import plan (dry run)' : 'Clusters import');

        $this->detail('Eligible cluster pages', number_format($report->eligible));
        $this->detail('Existing mappings', number_format($report->existingMappings));
        $this->detail('New clusters', number_format($report->created));
        $this->detail('Unchanged clusters', number_format($report->unchanged));
        $this->detail('Changed WordPress sources', number_format($report->sourceChanged));
        $this->detail('Updated clusters', number_format($report->updated));
        $this->detail('Administrator-edited', number_format($report->adminModified));
        $this->detail('Conflicts', number_format($report->conflicts));
        $this->detail('Failed', number_format($report->failed));

        $this->newLine();
        $this->components->info('Beneficiary rows');
        $this->detail('Source data rows', number_format($report->sourceRows));
        $this->detail('Excluded duplicate rows', number_format($report->excludedRows));
        $this->detail('Summary TOTAL rows skipped', number_format($report->summaryRows));
        $this->detail('Rows to import', number_format($report->rowsToImport));
        $this->detail('Beneficiaries', number_format($report->beneficiaries));
        $this->detail('Row inserts', number_format($report->createdRows));
        $this->detail('Row updates', number_format($report->updatedRows));
        $this->detail('Row deletions', number_format($report->deletedRows));

        $this->newLine();
        $this->components->info('Charts');
        $this->detail(
            $report->dryRun ? 'Charts to copy' : 'Charts copied',
            number_format($report->chartCopies),
        );
        $this->detail('Charts reused (never overwritten)', number_format($report->chartReused));
        $this->detail('Charts missing', $this->countLine(count($report->missingCharts)));

        if ($report->clusters !== []) {
            $this->newLine();
            $this->components->info('Per-cluster plan');

            $this->table(
                ['WP ID', 'Cluster', 'Rows', 'Beneficiaries', 'Action', 'Chart'],
                array_map(fn (array $row) => [
                    $row['wp_id'],
                    $row['name'],
                    number_format($row['rows']),
                    number_format($row['beneficiaries']),
                    $row['action'],
                    $row['chart'],
                ], $report->clusters),
            );
        }

        if ($report->exclusionDetails !== []) {
            $this->newLine();
            $this->components->info('Excluded rows');

            foreach ($report->exclusionDetails as $detail) {
                $this->line('  '.$detail);
            }
        }

        $this->newLine();
        $this->components->info($report->dryRun ? 'Potential database writes' : 'Database writes');
        $this->detail('Cluster rows', number_format($report->imported()));
        $this->detail('Beneficiary rows', number_format($report->createdRows + $report->updatedRows + $report->deletedRows));
        $this->detail('Import-map rows', number_format($report->mappingRows));
        $this->detail('Total', number_format($report->potentialDatabaseWrites()));

        $this->renderProblems($report);
    }

    private function reportTeamsPlan(TeamsImportReport $report): void
    {
        $this->newLine();
        $this->components->info($report->dryRun ? 'Teams import plan (dry run)' : 'Teams import');

        $this->detail('Eligible team records', number_format($report->eligible));
        $this->detail('Existing mappings', number_format($report->existingMappings));
        $this->detail('New team members', number_format($report->created));
        $this->detail('Unchanged team members', number_format($report->unchanged));
        $this->detail('Changed WordPress sources', number_format($report->sourceChanged));
        $this->detail('Updated team members', number_format($report->updated));
        $this->detail('Administrator-edited', number_format($report->adminModified));
        $this->detail('Conflicts', number_format($report->conflicts));
        $this->detail('Failed', number_format($report->failed));

        $this->reportTeamsExclusions($report);

        $this->newLine();
        $this->components->info('Categories');
        $categoryState = match (true) {
            $report->categoryExists => 'exists',
            $report->categoryWillBeCreated => $report->dryRun ? 'would be created' : 'created',
            default => 'unresolved',
        };
        $this->detail('Team category PIUs', $categoryState.' <fg=gray>(slug: piu)</>');
        $this->detail('Team category Assistant', $categoryState.' <fg=gray>(slug: ass)</>');
        $this->detail('Assignments', number_format($report->categoryAssignments));

        $this->newLine();
        $this->components->info('Media');
        $this->detail('Featured images missing', $this->countLine(count($report->missingFeatured)));
        $this->detail('Embedded images missing', $this->countLine(count($report->missingEmbedded)));
        $this->detail('Embedded images ignored', $report->ignoredEmbedded);
        $this->detail('Media conflicts', $this->countLine($report->mediaConflictCount));
        $this->detail(
            $report->dryRun ? 'Files to copy' : 'Files copied',
            number_format($report->mediaCopies),
        );
        $this->detail('Files reused (identical)', number_format($report->mediaReused));

        $this->newLine();
        $this->components->info('Potential database writes');
        $this->detail('Team member rows', number_format($report->imported()));
        $this->detail('Import-map rows', number_format($report->mappingRows));
        $this->detail('Attachment mappings', sprintf(
            '%s (%s new)',
            number_format($report->mediaMappingRows),
            number_format($report->newMediaMappingRows),
        ));
        $this->detail('Category pivot rows', number_format($report->categoryAssignments));
        $this->detail('Total', number_format($report->potentialDatabaseWrites()));

        $this->renderProblems($report);
    }
    private function reportNewsPlan(NewsImportReport $report): void
    {
        $this->newLine();
        $this->components->info($report->dryRun ? 'News import plan (dry run)' : 'News import');

        $this->detail('Eligible news records', number_format($report->eligible));
        $this->detail('Existing mappings', number_format($report->existingMappings));
        $this->detail('New articles', number_format($report->created));
        $this->detail('Unchanged articles', number_format($report->unchanged));
        $this->detail('Changed WordPress sources', number_format($report->sourceChanged));
        $this->detail('Updated articles', number_format($report->updated));
        $this->detail('Administrator-edited', number_format($report->adminModified));
        $this->detail('Conflicts', number_format($report->conflicts));
        $this->detail('Failed', number_format($report->failed));

        $this->renderExclusions($report);

        $this->newLine();
        $this->components->info('Categories');
        $categoryState = match (true) {
            $report->categoryExists => 'exists',
            $report->categoryWillBeCreated => $report->dryRun ? 'would be created' : 'created',
            default => 'unresolved',
        };
        $this->detail('News category', $categoryState.' <fg=gray>(slug: news, wp term 67)</>');
        $this->detail('Assignments', number_format($report->categoryAssignments));

        $this->newLine();
        $this->components->info('Media');
        $this->detail('Featured images missing', $this->countLine(count($report->missingFeatured)));
        $this->detail('Embedded images missing', $this->countLine(count($report->missingEmbedded)));
        $this->detail('Embedded images ignored', number_format($report->ignoredEmbedded).' <fg=gray>(theme/plugin assets)</>');
        $this->detail('Media conflicts', $this->countLine($report->mediaConflictCount));
        $this->detail(
            $report->dryRun ? 'Files to copy' : 'Files copied',
            number_format($report->mediaCopies),
        );
        $this->detail('Files reused (identical)', number_format($report->mediaReused));

        $this->newLine();
        $this->components->info($report->dryRun ? 'Potential database writes' : 'Database writes');
        $this->detail('Article rows', number_format($report->imported()));
        $this->detail('Import-map rows', number_format($report->mappingRows));
        $this->detail('Attachment mappings', sprintf(
            '%s (%s new)',
            number_format($report->mediaMappingRows),
            number_format($report->newMediaMappingRows),
        ));
        $this->detail('Category pivot rows', number_format($report->categoryAssignments));
        $this->detail('Total', number_format($report->potentialDatabaseWrites()));

        $this->renderProblems($report);
    }

    private function reportProjectsPlan(NewsImportReport $report): void
    {
        $this->newLine();
        $this->components->info($report->dryRun ? 'Projects import plan (dry run)' : 'Projects import');

        $this->detail('Eligible project records', number_format($report->eligible));
        $this->detail('Existing mappings', number_format($report->existingMappings));
        $this->detail('New projects', number_format($report->created));
        $this->detail('Unchanged projects', number_format($report->unchanged));
        $this->detail('Changed WordPress sources', number_format($report->sourceChanged));
        $this->detail('Updated projects', number_format($report->updated));
        $this->detail('Administrator-edited', number_format($report->adminModified));
        $this->detail('Conflicts', number_format($report->conflicts));
        $this->detail('Failed', number_format($report->failed));

        $this->renderExclusions($report);

        $this->newLine();
        $this->components->info('Categories');
        $categoryState = match (true) {
            $report->categoryExists => 'exists',
            $report->categoryWillBeCreated => $report->dryRun ? 'would be created' : 'created',
            default => 'unresolved',
        };
        $this->detail('Project category', $categoryState.' <fg=gray>(slug: projects, wp term 68)</>');
        $this->detail('Assignments', number_format($report->categoryAssignments));

        $this->newLine();
        $this->components->info('Media');
        $this->detail('Featured images missing', $this->countLine(count($report->missingFeatured)));
        $this->detail('Embedded images missing', $this->countLine(count($report->missingEmbedded)));
        $this->detail('Embedded images ignored', number_format($report->ignoredEmbedded).' <fg=gray>(theme/plugin assets)</>');
        $this->detail('Media conflicts', $this->countLine($report->mediaConflictCount));
        $this->detail(
            $report->dryRun ? 'Files to copy' : 'Files copied',
            number_format($report->mediaCopies),
        );
        $this->detail('Files reused (identical)', number_format($report->mediaReused));

        $this->newLine();
        $this->components->info('Potential database writes');
        $this->detail('Project rows', number_format($report->imported()));
        $this->detail('Import-map rows', number_format($report->mappingRows));
        $this->detail('Attachment mappings', sprintf(
            '%s (%s new)',
            number_format($report->mediaMappingRows),
            number_format($report->newMediaMappingRows),
        ));
        $this->detail('Category pivot rows', number_format($report->categoryAssignments));
        $this->detail('Total', number_format($report->potentialDatabaseWrites()));

        $this->renderProblems($report);
    }

    private function renderExclusions(NewsImportReport $report): void
    {
        $excluded = array_sum($report->excluded);

        if ($excluded === 0) {
            return;
        }

        $this->newLine();
        $this->components->info('Excluded (by the audited content inventory)');

        arsort($report->excluded);

        foreach ($report->excluded as $reason => $count) {
            $this->detail($reason, number_format($count));
        }
    }

    private function reportTeamsExclusions(TeamsImportReport $report): void
    {
        $excluded = array_sum($report->excluded);

        if ($excluded === 0) {
            return;
        }

        $this->newLine();
        $this->components->info('Excluded (by the audited content inventory)');

        arsort($report->excluded);

        foreach ($report->excluded as $reason => $count) {
            $this->detail($reason, number_format($count));
        }
    }

    private function renderProblems(NewsImportReport|TeamsImportReport|ClusterImportReport $report): void
    {
        $sections = [
            'Administrator edits not overwritten' => $report->adminModifiedDetails,
            'Slug collisions resolved' => property_exists($report, 'slugConflicts') ? $report->slugConflicts : [],
            'Missing featured images' => property_exists($report, 'missingFeatured') ? $report->missingFeatured : [],
            'Missing embedded images' => property_exists($report, 'missingEmbedded') ? $report->missingEmbedded : [],
            'Media conflicts' => property_exists($report, 'mediaConflictDetails') ? $report->mediaConflictDetails : [],
            'Missing charts' => property_exists($report, 'missingCharts') ? $report->missingCharts : [],
            'Conflicts' => $report->conflictDetails,
            'Failed records' => $report->failedDetails,
            'Files left on disk' => property_exists($report, 'orphanedFiles') ? $report->orphanedFiles : [],
        ];

        foreach ($sections as $heading => $lines) {
            if ($lines === []) {
                continue;
            }

            $this->newLine();
            $this->components->warn($heading.' ('.count($lines).')');

            foreach (array_slice($lines, 0, self::PROBLEM_PREVIEW) as $line) {
                $this->line('  '.$line);
            }

            if (count($lines) > self::PROBLEM_PREVIEW) {
                $this->line('  … and '.(count($lines) - self::PROBLEM_PREVIEW).' more');
            }
        }

        if ($report->notes !== []) {
            $this->newLine();
            $this->components->info('Notes');

            foreach ($report->notes as $note) {
                $this->line('  '.$note);
            }
        }
    }

    private function countLine(int $count): string
    {
        return $count === 0 ? '<fg=green>0</>' : '<fg=yellow>'.number_format($count).'</>';
    }

    // ---------------------------------------------------------------------
    // Read-only overview (phase 1)
    // ---------------------------------------------------------------------

    private function refuseImport(): int
    {
        $this->components->error('Choose a content type to import.');
        $this->line('  Supported today: news, projects, teams, clusters.');
        $this->newLine();
        $this->line('  Inspect a news import without writing:');
        $this->line('    php artisan lpres:import-wordpress --type=news --dry-run');
        $this->newLine();
        $this->line('  Inspect a projects import without writing:');
        $this->line('    php artisan lpres:import-wordpress --type=projects --dry-run');
        $this->newLine();
        $this->line('  Inspect a value chain clusters import without writing:');
        $this->line('    php artisan lpres:import-wordpress --type=clusters --dry-run');
        $this->newLine();
        $this->line('  Without --type this command only prints the infrastructure overview:');
        $this->line('    php artisan lpres:import-wordpress --dry-run');

        return self::FAILURE;
    }

    private function overview(WordPressXmlParser $parser, SourceHash $hasher): int
    {
        $paths = MigrationPaths::fromConfig();

        if (! $this->reportSources($paths)) {
            return self::INVALID;
        }

        try {
            $export = $parser->parse($paths->xml);
        } catch (WordPressExportException $exception) {
            $this->newLine();
            $this->components->error('The WordPress export could not be parsed.');
            $this->line('  '.$exception->getMessage());

            return self::INVALID;
        }

        $this->reportExport($export);

        if (! $this->reportMedia($paths)) {
            return self::INVALID;
        }

        $this->reportImportMap($export, $hasher);

        $this->newLine();
        $this->components->info('Dry run complete — nothing was written.');

        return self::SUCCESS;
    }

    private function reportSources(MigrationPaths $paths): bool
    {
        $this->newLine();
        $this->components->info('Sources');
        // Printed as plain lines: twoColumnDetail's dot leaders are unreadable
        // next to a 130-character Windows path.
        $this->line('  XML export       '.$this->describe($paths->xml));
        $this->line('  Media manifest   '.$this->describe($paths->manifest));
        $this->line('  Media directory  '.$this->describe($paths->mediaDirectory));

        $problems = $paths->problems();

        if ($problems === []) {
            return true;
        }

        $this->newLine();
        $this->components->error('Migration sources are not usable.');

        foreach ($problems as $problem) {
            $this->line('  • '.$problem);
        }

        $this->newLine();
        $this->line('  Configure the paths in .env (see .env.example) or config/lpres_migration.php.');

        return false;
    }

    private function describe(string $path): string
    {
        if ($path === '') {
            return '<fg=red>not configured</>';
        }

        if (! file_exists($path)) {
            return '<fg=red>missing</> '.$path;
        }

        if (is_dir($path)) {
            return $path.' <fg=green>ok</>';
        }

        return $path.' <fg=green>ok</> <fg=gray>('.self::bytes((int) filesize($path)).')</>';
    }

    private function reportExport(WordPressExport $export): void
    {
        $this->newLine();
        $this->components->info('WordPress export');
        $this->detail('Site', $export->siteTitle.' <fg=gray>'.$export->siteUrl.'</>');
        $this->detail('WXR version', $export->wxrVersion !== '' ? $export->wxrVersion : 'unknown');
        $this->detail('Records', number_format($export->count()));
        $this->detail('Taxonomies', sprintf(
            '%d categories, %d tags, %d custom terms',
            count($export->categories),
            count($export->tags),
            count($export->terms),
        ));

        $rows = [];

        foreach ($export->countsByType() as $type => $count) {
            $statuses = $export->countsByStatusForType($type);
            $published = $statuses['publish'] ?? 0;
            $draft = $statuses['draft'] ?? 0;

            $rows[] = [
                Str::limit($type !== '' ? $type : '(none)', 30),
                number_format($count),
                number_format($published),
                number_format($draft),
                number_format($count - $published - $draft),
            ];
        }

        $this->table(['Post type', 'Records', 'Publish', 'Draft', 'Other'], $rows);
    }

    private function reportMedia(MigrationPaths $paths, ?MediaManifest $manifest = null): bool
    {
        $this->newLine();
        $this->components->info('Media');

        try {
            $manifest ??= MediaManifest::fromFile($paths->manifest);
        } catch (MigrationSourceException $exception) {
            $this->components->error('The media manifest is not usable.');
            $this->line('  '.$exception->getMessage());

            return false;
        }

        $missing = $manifest->missingFiles($paths->mediaDirectory);

        // The manifest itself normally sits inside the media directory; it is
        // not a media file, so it must not inflate the on-disk count.
        $manifestRealPath = realpath($paths->manifest);
        $onDisk = collect(File::allFiles($paths->mediaDirectory))
            ->reject(fn (\SplFileInfo $file) => $manifestRealPath !== false
                && $file->getRealPath() === $manifestRealPath)
            ->count();

        $this->detail('Manifest rows', number_format($manifest->count()));
        $this->detail('Unique URLs', number_format($manifest->uniqueUrlCount()));
        $this->detail('Files on disk', number_format($onDisk));
        $this->detail(
            'Missing files',
            $missing === [] ? '<fg=green>0</>' : '<fg=red>'.number_format(count($missing)).'</>',
        );

        if ($missing !== []) {
            $this->newLine();
            $this->components->error('The media manifest references files that are not on disk.');

            foreach (array_slice($missing, 0, self::PROBLEM_PREVIEW) as $file) {
                $this->line('  • '.$file);
            }

            if (count($missing) > self::PROBLEM_PREVIEW) {
                $this->line('  … and '.(count($missing) - self::PROBLEM_PREVIEW).' more');
            }

            $this->newLine();
            $this->line('  Re-run the media download before importing; files are never substituted.');

            return false;
        }

        return true;
    }

    private function reportImportMap(WordPressExport $export, SourceHash $hasher): void
    {
        $this->newLine();
        $this->components->info('Import map');

        if (! Schema::hasTable('wp_import_map')) {
            $this->components->warn('The wp_import_map table does not exist yet — run `php artisan migrate`.');
            $this->line('  Until then every source record is reported as new.');

            return;
        }

        $mappings = WpImportMap::query()->get()->keyBy(
            fn (WpImportMap $mapping) => $mapping->source_type.':'.$mapping->source_id,
        );

        $this->reportImportMapTable($export, $mappings, $hasher);

        $lastImport = $mappings
            ->filter(fn (WpImportMap $mapping) => $mapping->imported_at !== null)
            ->max(fn (WpImportMap $mapping) => $mapping->imported_at);

        if ($lastImport !== null) {
            $this->detail('Last import', $lastImport->toDateTimeString());
        }
    }

    /** @param Collection<string, WpImportMap> $mappings */
    private function reportImportMapTable(WordPressExport $export, Collection $mappings, SourceHash $hasher): void
    {
        $rows = [];
        $totals = ['records' => 0, 'imported' => 0, 'pending' => 0, 'new' => 0, 'changed' => 0];
        $itemsByType = [];

        foreach ($export->items() as $item) {
            $itemsByType[$item->type][] = $item;
        }

        foreach ($export->countsByType() as $type => $count) {
            $imported = 0;
            $pending = 0;
            $new = 0;
            $changed = 0;

            foreach ($itemsByType[$type] ?? [] as $item) {
                $mapping = $mappings->get($type.':'.$item->sourceId);

                if (! $mapping instanceof WpImportMap) {
                    $new++;

                    continue;
                }

                if (! $mapping->isImported()) {
                    $pending++;

                    continue;
                }

                $imported++;

                if ($mapping->hasSourceChanged($hasher->forItem($item))) {
                    $changed++;
                }
            }

            $rows[] = [
                Str::limit($type !== '' ? $type : '(none)', 30),
                number_format($count),
                number_format($imported),
                number_format($pending),
                number_format($new),
                $changed > 0 ? '<fg=yellow>'.number_format($changed).'</>' : '0',
            ];

            $totals['records'] += $count;
            $totals['imported'] += $imported;
            $totals['pending'] += $pending;
            $totals['new'] += $new;
            $totals['changed'] += $changed;
        }

        $this->table(
            ['Source type', 'Records', 'Imported', 'Reserved', 'New', 'Changed'],
            $rows,
        );

        $this->detail(
            'Summary',
            sprintf(
                '%s records: %s imported, %s reserved, %s new, %s changed since import',
                number_format($totals['records']),
                number_format($totals['imported']),
                number_format($totals['pending']),
                number_format($totals['new']),
                number_format($totals['changed']),
            ),
        );
    }

    /** Fixed-column label/value line. twoColumnDetail pads with dot leaders that
     * become unreadable beside a full Windows path.
     */
    private function detail(string $label, string $value): void
    {
        $this->line(sprintf('  %-28s %s', $label, $value));
    }

    private static function bytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 1).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }
}
