<?php

namespace App\Migration;

/**
 * The CSV manifest produced by the WordPress media download.
 *
 * Columns: url, relative_path, referenced_by, status, http_status, bytes.
 * The manifest is the only authoritative mapping between a WordPress upload
 * URL and the file on disk — paths are never reconstructed from URL guesswork.
 *
 * Reading is side-effect free; `missingFiles()` performs read-only existence
 * checks against the configured media directory.
 */
final class MediaManifest
{
    public const REQUIRED_COLUMNS = [
        'url',
        'relative_path',
        'referenced_by',
        'status',
        'http_status',
        'bytes',
    ];

    private const BOM = "\xEF\xBB\xBF";

    /**
     * @param  list<string>  $columns
     * @param  list<array<string, string>>  $rows
     */
    private function __construct(
        public readonly string $path,
        public readonly array $columns,
        public readonly array $rows,
    ) {}

    /**
     * @throws MigrationSourceException
     */
    public static function fromFile(string $path): self
    {
        if (! is_file($path)) {
            throw MigrationSourceException::notFound($path, 'Media manifest');
        }

        if (! is_readable($path)) {
            throw MigrationSourceException::unreadable($path, 'Media manifest');
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw MigrationSourceException::unreadable($path, 'Media manifest');
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            throw MigrationSourceException::unreadableHeader($path);
        }

        $columns = array_map(fn (mixed $column) => strtolower(trim((string) $column)), $header);

        // Excel and some exporters prepend a UTF-8 BOM to the first header cell.
        if (isset($columns[0]) && str_starts_with($columns[0], self::BOM)) {
            $columns[0] = substr($columns[0], strlen(self::BOM));
        }

        $missing = array_values(array_diff(self::REQUIRED_COLUMNS, $columns));

        if ($missing !== []) {
            fclose($handle);

            throw MigrationSourceException::missingColumns($path, $missing);
        }

        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || (count($row) === 1 && trim((string) $row[0]) === '')) {
                continue;
            }

            $mapped = [];

            foreach ($columns as $index => $column) {
                $mapped[$column] = trim((string) ($row[$index] ?? ''));
            }

            $rows[] = $mapped;
        }

        fclose($handle);

        return new self($path, $columns, $rows);
    }

    public function count(): int
    {
        return count($this->rows);
    }

    /**
     * Distinct `url` values in the manifest.
     */
    public function uniqueUrlCount(): int
    {
        $urls = array_filter(array_column($this->rows, 'url'), fn (string $url) => $url !== '');

        return count(array_unique($urls));
    }

    /**
     * Rows whose `relative_path` has no file under `$mediaDirectory`.
     *
     * @return list<string>
     */
    public function missingFiles(string $mediaDirectory): array
    {
        $missing = [];

        foreach ($this->rows as $row) {
            $relative = $row['relative_path'] ?? '';

            if ($relative === '') {
                $missing[] = '(row without relative_path: '.$row['url'].')';

                continue;
            }

            $absolute = rtrim($mediaDirectory, '/\\').DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);

            if (! is_file($absolute)) {
                $missing[] = $relative;
            }
        }

        return $missing;
    }

    /**
     * Rows whose recorded `status` is not a successful download.
     *
     * @return list<string>
     */
    public function unsuccessfulRows(): array
    {
        return array_values(array_filter(
            array_map(
                fn (array $row) => $row['relative_path'].' ('.$row['status'].')',
                $this->rows,
            ),
            fn (string $label) => ! str_contains(strtolower($label), 'downloaded'),
        ));
    }
}
