<?php

namespace App\Migration\Media;

/**
 * Outcome of resolving a featured image, or an image embedded in an article
 * body. Either the media was found, or `reason` explains precisely why it
 * cannot be imported (missing attachment record, missing manifest row, missing
 * file) so the import report can name it instead of silently dropping it.
 *
 * `code` distinguishes the two kinds of failure:
 *  - `ABSENT`     the record genuinely has no media in this place;
 *  - `UNRESOLVED` media is declared but cannot be imported, so an existing
 *                 Laravel value must be kept rather than cleared.
 */
final readonly class MediaLookup
{
    public const ABSENT = 'absent';

    public const UNRESOLVED = 'unresolved';

    private function __construct(
        public ?ResolvedMedia $media,
        public ?string $reason,
        public string $code,
    ) {}

    public static function found(ResolvedMedia $media): self
    {
        return new self($media, null, 'found');
    }

    public static function missing(string $reason, string $code = self::UNRESOLVED): self
    {
        return new self(null, $reason, $code);
    }

    public static function absent(string $reason): self
    {
        return new self(null, $reason, self::ABSENT);
    }

    public function isFound(): bool
    {
        return $this->media !== null;
    }

    public function isAbsent(): bool
    {
        return $this->code === self::ABSENT;
    }
}
