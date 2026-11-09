<?php

namespace App\Migration\WordPress;

use RuntimeException;

/**
 * Raised when a WordPress export cannot be read or is not a WXR document.
 *
 * Every message names the offending file and, where relevant, the libxml
 * problem, so the command can print an actionable error instead of a stack
 * trace.
 */
class WordPressExportException extends RuntimeException
{
    public static function missing(string $path): self
    {
        return new self("WordPress XML export not found: {$path}");
    }

    public static function unreadable(string $path): self
    {
        return new self("WordPress XML export is not readable: {$path}");
    }

    public static function empty(string $path): self
    {
        return new self("WordPress XML export is empty (0 bytes): {$path}");
    }

    public static function notWxr(string $path): self
    {
        return new self(
            "Not a WordPress eXtended RSS export (no <channel> element): {$path}. "
            .'Export again from Tools -> Export in the WordPress admin.'
        );
    }

    public static function malformed(string $path, string $detail): self
    {
        return new self("WordPress XML export could not be parsed: {$path} — {$detail}");
    }

    public static function invalidItem(string $path, int $index, string $detail): self
    {
        return new self("WordPress XML export contains an invalid item at position {$index}: {$detail} ({$path})");
    }
}
