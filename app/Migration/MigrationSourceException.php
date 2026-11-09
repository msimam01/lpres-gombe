<?php

namespace App\Migration;

use RuntimeException;

/**
 * Raised when a configured migration source is missing, unreadable or does not
 * look like the artefact it claims to be.
 */
class MigrationSourceException extends RuntimeException
{
    public static function notFound(string $path, string $hint): self
    {
        return new self("{$hint} not found: {$path}");
    }

    public static function unreadable(string $path, string $hint): self
    {
        return new self("{$hint} is not readable: {$path}");
    }

    public static function empty(string $path, string $hint): self
    {
        return new self("{$hint} is empty (0 bytes): {$path}");
    }

    /**
     * @param  list<string>  $missing
     */
    public static function missingColumns(string $path, array $missing): self
    {
        return new self(sprintf(
            'Media manifest is missing required column(s): %s (%s)',
            implode(', ', $missing),
            $path,
        ));
    }

    /**
     * Same failure, for a CSV source other than the media manifest.
     *
     * @param  list<string>  $missing
     */
    public static function missingColumnsFor(string $label, string $path, array $missing): self
    {
        return new self(sprintf(
            '%s is missing required column(s): %s (%s)',
            $label,
            implode(', ', $missing),
            $path,
        ));
    }

    public static function unreadableHeader(string $path): self
    {
        return new self("Media manifest has no readable header row: {$path}");
    }

    public static function unreadableHeaderFor(string $label, string $path): self
    {
        return new self("{$label} has no readable header row: {$path}");
    }
}
