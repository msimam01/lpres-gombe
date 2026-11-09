<?php

namespace App\Migration;

/**
 * Resolved locations of the WordPress migration sources.
 *
 * Paths come from config/lpres_migration.php (which reads environment
 * variables). Relative values are resolved against the application base path,
 * never the process working directory, and nothing is ever substituted for a
 * missing path — the report names the bad path and how to fix it.
 *
 * The content inventory is the audit's eligibility register; it is required by
 * the news importer but not by the read-only overview, so `problems()` only
 * covers the three sources an overview needs.
 */
final readonly class MigrationPaths
{
    public function __construct(
        public string $xml,
        public string $manifest,
        public string $mediaDirectory,
        public string $inventory,
        public string $workingDirectory,
        public string $mediaDisk,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            xml: self::absolute((string) config('lpres_migration.xml', '')),
            manifest: self::absolute((string) config('lpres_migration.manifest', '')),
            mediaDirectory: self::absolute((string) config('lpres_migration.media_directory', '')),
            inventory: self::absolute((string) config('lpres_migration.inventory', '')),
            workingDirectory: self::absolute((string) config('lpres_migration.working_directory', '')),
            mediaDisk: (string) config('lpres_migration.media_disk', 'public'),
        );
    }

    /**
     * Everything wrong with the configured sources, in the order a user should
     * fix it. Empty when all three required sources are present.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        return [
            ...$this->fileProblem($this->xml, 'WordPress XML export', 'LPRES_MIGRATION_XML'),
            ...$this->fileProblem($this->manifest, 'Media manifest', 'LPRES_MIGRATION_MANIFEST'),
            ...$this->directoryProblem($this->mediaDirectory, 'Media directory', 'LPRES_MIGRATION_MEDIA_DIR'),
        ];
    }

    public function isValid(): bool
    {
        return $this->problems() === [];
    }

    /**
     * @return list<string>
     */
    private function fileProblem(string $path, string $label, string $variable): array
    {
        if ($path === '') {
            return ["{$label} is not configured. Set {$variable} in .env to the file's absolute path."];
        }

        if (! is_file($path)) {
            return ["{$label} not found: {$path} (configure {$variable})"];
        }

        if (! is_readable($path)) {
            return ["{$label} is not readable: {$path}"];
        }

        if ((int) filesize($path) === 0) {
            return ["{$label} is empty (0 bytes): {$path}"];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function directoryProblem(string $path, string $label, string $variable): array
    {
        if ($path === '') {
            return ["{$label} is not configured. Set {$variable} in .env to the directory's absolute path."];
        }

        if (! is_dir($path)) {
            return ["{$label} not found: {$path} (configure {$variable})"];
        }

        if (! is_readable($path)) {
            return ["{$label} is not readable: {$path}"];
        }

        return [];
    }

    /**
     * Resolve a configured value to an absolute path without touching disk.
     */
    private static function absolute(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '';
        }

        // Already absolute: POSIX root, UNC share, or a Windows drive path.
        if (preg_match('#^(?:[A-Za-z]:[\\\\/]|[\\\\/]{1,2})#', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }
}
