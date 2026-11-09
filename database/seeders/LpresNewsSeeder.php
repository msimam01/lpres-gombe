<?php

namespace Database\Seeders;

use App\Migration\ContentInventory;
use App\Migration\HtmlToText;
use App\Migration\Media\MediaImporter;
use App\Migration\Media\MediaResolver;
use App\Migration\MigrationPaths;
use App\Migration\News\NewsImporter;
use App\Migration\News\NewsImportReport;
use App\Migration\SourceHash;
use App\Migration\WordPress\WordPressXmlParser;
use App\Models\News;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;

/**
 * L-PRES News seeder.
 *
 * Reuses the existing NewsImporter service to import the approved 132 genuine
 * published news articles from the WordPress export. Designed to be run
 * explicitly — it will NOT run as part of `php artisan db:seed` and requires
 * a dedicated confirmation mechanism.
 *
 * The same NewsImporter service used by `php artisan lpres:import-wordpress --type=news --confirm`
 * is used here, ensuring complete consistency between the two execution methods.
 *
 * @see DatabaseSeeder — this seeder is intentionally NOT registered.
 */
final class LpresNewsSeeder extends Seeder
{
    /***
     * Run the news import.
     *
     * This method is NOT called by DatabaseSeeder::run(). It must be invoked
     * explicitly, and only when the confirmation safeguard is engaged.
     *
     * @throws \RuntimeException if source files are missing or configuration is invalid
     */
    public function run(): void
    {
        // --- Explicit confirmation safeguard ---
        // The seeder will not run unless LRES_NEWS_SEEDER_CONFIRM is set to '1'.
        // This prevents accidental execution during ordinary deployment, migration,
        // or default database seeding.
        $confirm = $this->confirmImport();

        if (! $confirm) {
            $this->command->info('News import seeder not executed — explicit confirmation required (set LRES_NEWS_SEEDER_CONFIRM=1).');

            return;
        }

        $this->command->info('L-PRES News import seeder starting — explicit confirmation engaged.');

        // --- Resolve source paths from config ---
        $paths = MigrationPaths::fromConfig();

        // Validate that all required source files exist before proceeding.
        $this->validateSourceFiles($paths);

        // --- Parse the WordPress export ---
        try {
            $export = (new WordPressXmlParser)->parse($paths->xml);
        } catch (\Exception $exception) {
            $this->command->error('The WordPress export could not be parsed: '.$exception->getMessage());
            $this->command->newLine();
            throw new \RuntimeException('WordPress export parse failure; see above.');
        }

        // --- Build the media resolver ---
        $manifest = new \App\Migration\MediaManifest;
        try {
            $manifest = MediaManifest::fromFile($paths->manifest);
        } catch (\Exception $exception) {
            $this->command->error('The media manifest could not be read: '.$exception->getMessage());
            $this->command->newLine();
            throw new \RuntimeException('Media manifest failure; see above.');
        }

        // --- Build the inventory ---
        $inventory = ContentInventory::fromFile($paths->inventory);

        // --- Build the resolver ---
        $resolver = MediaResolver::fromExport($export, $manifest, $paths->mediaDirectory);

        // --- Build the media importer ---
        $mediaImporter = new MediaImporter($paths->mediaDisk, 'uploads/news');

        // --- Run the importer ---
        $importer = new NewsImporter(
            export: $export,
            inventory: $inventory,
            resolver: $resolver,
            mediaImporter: $mediaImporter,
            fields: new NewsFields(new HtmlToText),
            hasher: new SourceHash,
            forceAdminEdits: true, // The seeder always respects the override; admin edits are only overwritten when explicitly intended.
        );

        $report = $importer->run(true /* execute */);

        // --- Report results ---
        $this->reportResults($report);

        $this->command->newLine();
        $this->command->info('L-PRES News import seeder complete.');
    }

    /***
     * Require explicit confirmation before proceeding.
     *
     * Returns true only when the environment variable LRES_NEWS_SEEDER_CONFIRM
     * is set to the exact confirmation value '1'. Any other value (including
     * unset, empty, or any other string) results in the seeder aborting without
     * importing anything.
     *
     * This safeguard ensures that:
     * - `php artisan db:seed` never triggers the news import.
     * - Deployment pipelines that run `db:seed` automatically are protected.
     * - Production execution requires an explicit opt-in through the environment.
     *
     * @return bool
     */
    private function confirmImport(): bool
    {
        $value = $_ENV['LRES_NEWS_SEEDER_CONFIRM'] ?? '';

        if ($value !== '1') {
            return false;
        }

        return true;
    }

    /***
     * Validate that all required source files referenced by the config exist.
     *
     * @throws \RuntimeException if any required source file is missing
     */
    private function validateSourceFiles(MigrationPaths $paths): void
    {
        $missing = [];

        if (! is_file($paths->xml)) {
            $missing[] = 'XML export: '.$paths->xml;
        }

        if (! is_file($paths->manifest)) {
            $missing[] = 'Media manifest: '.$paths->manifest;
        }

        if (! is_file($paths->inventory)) {
            $missing[] = 'Content inventory: '.$paths->inventory;
        }

        if (! is_dir($paths->mediaDirectory)) {
            $missing[] = 'Media directory: '.$paths->mediaDirectory;
        }

        if (! empty($missing)) {
            $msg = 'The following required source files are missing: '.impluminate(', ', $missing);
            $this->command->error($msg);
            throw new \RuntimeException($msg);
        }
    }

    /***
     * Report the import results in a format consistent with the Artisan command.
     *
     * @param NewsImportReport $report
     */
    private function reportResults(NewsImportReport $report): void
    {
        $this->command->newLine();
        $this->command->info('L-PRES News import results:');

        $this->command->detail('Eligible news records', number_format($report->eligible));
        $this->command->detail('New articles', number_format($report->created));
        $this->command->detail('Unchanged articles', number_format($report->unchanged));
        $this->command->detail('Updated articles', number_format($report->updated));
        $this->command->detail('Administrator-edited', number_format($report->adminModified));
        $this->command->detail('Conflicts', number_format($report->conflicts));
        $this->command->detail('Failed', number_format($report->failed));
        $this->command->detail('Category assignments', number_format($report->categoryAssignments));
        $this->command->detail('Media copies', number_format($report->mediaCopies));
        $this->command->detail('Media reused', number_format($report->mediaReused));
        $this->command->detail('Attachment mappings', number_format($report->mediaMappingRows));

        if ($report->orphanedFiles !== []) {
            $this->command->warn('Orphaned files on disk: '.implode(', ', $report->orphanedFiles));
        }

        if ($report->slugConflicts !== []) {
            $this->command->warn('Slug conflicts: '.count($report->slugConflicts).' resolved');
        }
    }
}
