<?php

namespace App\Migration\Media;

/**
 * What the importer would do with one resolved media file.
 *
 * `action` is one of:
 *  - `copy`     the target path is free and the source must be copied;
 *  - `reuse`    an identical file (same bytes) is already available, either
 *               from an earlier record in this run or from a previous import;
 *  - `conflict` the target path holds different bytes, or the source cannot be
 *               read — nothing may be overwritten.
 */
final readonly class MediaCopyPlan
{
    public const COPY = 'copy';

    public const REUSE = 'reuse';

    public const CONFLICT = 'conflict';

    public function __construct(
        public ResolvedMedia $media,
        public ?string $targetPath,
        public ?string $sourceMd5,
        public string $action,
        public ?string $reason = null,
    ) {}

    public function willCopy(): bool
    {
        return $this->action === self::COPY;
    }

    public function reused(): bool
    {
        return $this->action === self::REUSE;
    }

    public function conflicted(): bool
    {
        return $this->action === self::CONFLICT;
    }
}
